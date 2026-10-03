<?php
/**
 * Sends scheduled customer notifications and promotional SMS campaigns that
 * are due (Admin > Notifications / SMS with a schedule time).
 *
 * Each row is claimed (Scheduled -> Processing) before sending so it is only
 * sent once, then updated with its real outcome (Sent / Partially sent /
 * Not sent / Failed + reason). Rows left in Processing for more than 30
 * minutes (run killed mid-way) are marked Failed and are not retried, to
 * avoid sending duplicates. A summary of every run goes to cron/last_run.json
 * (shown on the admin Notifications and SMS pages).
 */

// Cron (every 5 minutes):
//   */5 * * * * /usr/bin/php /path/to/site/cron/send_scheduled.php >> /path/to/site/cron/send_scheduled.log 2>&1
// Windows / XAMPP test run:
//   E:\xampp\php\php.exe cron\send_scheduled.php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require dirname(__DIR__) . '/api/admin/core/bootstrap.php';

const CRON_BATCH = 50;          // rows per table per run
const CRON_STUCK_MINUTES = 30;

$lock = fopen(__DIR__ . '/send_scheduled.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    cron_log('Another run is still in progress; skipped.');
    exit(0);
}

function cron_log(string $line): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL;
}

/** Marks a claimed row as done with its outcome. */
function cron_finish(string $table, int $id, string $status, string $detail): void
{
    if ($table === 'customer_notifications') {
        q(
            'UPDATE customer_notifications SET status = ?, status_detail = ?, sent_at = ? WHERE id = ?',
            [$status, mb_substr($detail, 0, 255), in_array($status, ['Sent', 'Partially sent'], true) ? now() : null, $id]
        );
    } else {
        q('UPDATE sms_log SET status = ?, provider_response = ? WHERE id = ?', [$status, $detail, $id]);
    }
}

/** Scheduled -> Processing; false when another run already took the row. */
function cron_claim(string $table, int $id): bool
{
    return q("UPDATE $table SET status = 'Processing', processed_at = ? WHERE id = ? AND status = 'Scheduled'", [now(), $id])->rowCount() === 1;
}

function cron_params(?string $json): array
{
    $params = json_decode((string) $json, true);
    return is_array($params) ? $params : [];
}

function cron_send_notification(array $row): array
{
    [$recipients] = customer_audience($row['audience'], cron_params($row['audience_params']));
    if (!$recipients) {
        return ['Not sent', 'No customers match this audience any more.', 0];
    }
    $channels = array_values(array_intersect(explode(',', (string) $row['channels']), ['push', 'sms', 'email']));
    $template = q_one('SELECT dlt_template_id FROM sms_templates WHERE template_key = ? AND status = 1', [$row['type']]);
    $creator = $row['created_by'] !== null ? ['id' => (int) $row['created_by']] : null;
    $result = bulk_send($recipients, $channels, $row['title'], $row['message'], $row['type'], $template['dlt_template_id'] ?? null, $creator);
    return [$result['status'], $result['detail'], count($recipients)];
}

function cron_send_sms_campaign(array $row): array
{
    [$recipients] = customer_audience((string) ($row['audience'] ?: 'all'), cron_params($row['audience_params']));
    if (!$recipients) {
        return ['Not sent', 'No customers match this audience any more.', 0];
    }
    // Checked here (not left to bulk_send) so no extra summary row is logged.
    $template = q_one("SELECT dlt_template_id, status FROM sms_templates WHERE template_key = 'promotional'");
    if (!$template || (int) $template['status'] !== 1) {
        return ['Not sent', 'The "Promotional" SMS template is inactive.', count($recipients)];
    }
    if ($reason = sms_unavailable_reason($template['dlt_template_id'])) {
        return ['Not sent', $reason, count($recipients)];
    }
    $creator = $row['created_by'] !== null ? ['id' => (int) $row['created_by']] : null;
    $result = bulk_send($recipients, ['sms'], 'Promotional SMS', $row['message'], 'promotional', $template['dlt_template_id'], $creator);
    return [$result['status'], $result['detail'], count($recipients)];
}

$summary = [
    'started_at'    => now(),
    'finished_at'   => null,
    'notifications' => ['due' => 0, 'results' => []],
    'sms'           => ['due' => 0, 'results' => []],
    'stuck_failed'  => 0,
    'errors'        => [],
];

try {
    $stuckBefore = date('Y-m-d H:i:s', time() - CRON_STUCK_MINUTES * 60);
    $stuckDetail = 'Not sent: a scheduler run stopped while processing this item (see cron log). Not retried automatically to avoid duplicates.';
    $summary['stuck_failed'] += q("UPDATE customer_notifications SET status = 'Failed', status_detail = ? WHERE status = 'Processing' AND processed_at < ?", [$stuckDetail, $stuckBefore])->rowCount();
    $summary['stuck_failed'] += q("UPDATE sms_log SET status = 'Failed', provider_response = ? WHERE status = 'Processing' AND processed_at < ?", [$stuckDetail, $stuckBefore])->rowCount();

    $jobs = [
        'notifications' => ['customer_notifications', 'cron_send_notification'],
        'sms'           => ['sms_log', 'cron_send_sms_campaign'],
    ];
    foreach ($jobs as $key => [$table, $sender]) {
        $due = q_all("SELECT * FROM $table WHERE status = 'Scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= ? ORDER BY scheduled_at, id LIMIT " . CRON_BATCH, [now()]);
        $summary[$key]['due'] = count($due);
        foreach ($due as $row) {
            $id = (int) $row['id'];
            if (!cron_claim($table, $id)) {
                continue;
            }
            try {
                [$status, $detail, $count] = $sender($row);
            } catch (ApiException $e) {
                [$status, $detail, $count] = ['Not sent', $e->getMessage(), 0];
            } catch (Throwable $e) {
                error_log("[ZenCare cron] $table #$id: " . $e->getMessage());
                [$status, $detail, $count] = ['Failed', 'Error while sending: ' . $e->getMessage(), 0];
            }
            cron_finish($table, $id, $status, $detail);
            $summary[$key]['results'][] = ['id' => $id, 'status' => $status, 'recipients' => $count, 'detail' => $detail];
            cron_log("$table #$id ($count recipient(s)): $status - $detail");
        }
    }
} catch (Throwable $e) {
    $summary['errors'][] = $e->getMessage();
    error_log('[ZenCare cron] ' . $e->getMessage());
    cron_log('ERROR: ' . $e->getMessage());
}

$summary['finished_at'] = now();
file_put_contents(__DIR__ . '/last_run.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

cron_log(sprintf(
    'Done. Notifications due: %d, SMS campaigns due: %d, stuck marked failed: %d%s',
    $summary['notifications']['due'],
    $summary['sms']['due'],
    $summary['stuck_failed'],
    $summary['errors'] ? ', errors: ' . count($summary['errors']) : ''
));

flock($lock, LOCK_UN);
fclose($lock);
exit($summary['errors'] ? 1 : 0);
