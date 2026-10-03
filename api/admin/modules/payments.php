<?php
/**
 * Payments: online (transactions, PhonePe) + cash-on-service bookings.
 * Served by api/admin/payments.php when an `action` parameter is present
 * (without it that file keeps its original Admin-Token listing).
 */

function payments_list(array $in, ?array $admin): array
{
    [$page, $limit, $offset] = page_params($in);
    [$items, $total] = payment_query($in, $limit, $offset);
    $extra = [];
    if (!empty($in['with_summary'])) {
        $extra['summary'] = payments_summary_data($in);
    }
    return ok(paginated($items, $total, $page, $limit, $extra));
}

function payments_summary_data(array $in): array
{
    $from = !empty($in['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $in['date_from']) ? $in['date_from'] : null;
    $to   = !empty($in['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $in['date_to']) ? $in['date_to'] : null;
    $totals = payment_totals($from, $to);
    $counts = q_one('SELECT SUM(p.method = \'Online\') AS online, SUM(p.method = \'Cash\') AS cash FROM (' . payment_union_sql() . ') p');
    return $totals + ['online_count' => (int) $counts['online'], 'cash_count' => (int) $counts['cash']];
}

function payments_summary(array $in, ?array $admin): array
{
    return ok(payments_summary_data($in));
}

/** Invoice data. id = transaction id ("ZC-66-36", "CASH-12") or booking_id. */
function payments_get(array $in, ?array $admin): array
{
    $txn = trim((string) ($in['id'] ?? ''));
    $bookingId = (int) ($in['booking_id'] ?? 0);
    if ($txn === '' && !$bookingId) {
        throw new ApiException('Transaction id or booking id is required.', 422, ['id' => 'Required.']);
    }
    $filter = $bookingId ? ['booking_id' => $bookingId] : ['search' => null];
    if ($txn !== '') {
        if (preg_match('/^CASH-(\d+)$/', $txn, $m)) {
            $filter = ['booking_id' => (int) $m[1], 'method' => 'Cash'];
        } else {
            $row = q_one('SELECT p.*, u.first_name AS u_first, u.last_name AS u_last, u.phone AS u_phone FROM (' . payment_union_sql() . ') p LEFT JOIN users u ON u.ID = p.user_ref WHERE p.txn = ?', [$txn]);
            if (!$row) {
                throw not_found('Payment');
            }
            return ok(payments_invoice(payment_row($row)));
        }
    }
    [$items] = payment_query($filter, 1, 0);
    if (!$items) {
        throw not_found('Payment');
    }
    return ok(payments_invoice($items[0]));
}

function payments_invoice(array $payment): array
{
    $booking = $payment['booking_id'] ? booking_find($payment['booking_id']) : null;
    $refund = null;
    if ($payment['kind'] === 'online') {
        $t = q_one('SELECT refund_status, refund_amount, refund_note, refunded_at, phonepe_data FROM transactions WHERE transaction_id = ?', [$payment['txn']]);
        $refund = $t ? [
            'status' => $t['refund_status'],
            'amount' => $t['refund_amount'] !== null ? (float) $t['refund_amount'] : null,
            'note'   => $t['refund_note'],
            'at'     => $t['refunded_at'],
        ] : null;
    }
    return [
        'payment' => $payment,
        'refund'  => $refund,
        'invoice_no' => 'INV-' . ($payment['booking'] ?: $payment['txn']),
        'booking' => $booking ? [
            'id' => $booking['id'], 'code' => $booking['code'], 'service' => $booking['service'], 'items' => $booking['items'],
            'date' => $booking['date'], 'slot' => $booking['slot'], 'address' => $booking['address'], 'mobile' => $booking['mobile'],
            'customer' => $booking['customer'], 'status' => $booking['status'],
        ] : null,
    ];
}

/**
 * Manual refund tracking (Super Admin). The PhonePe refund API is not
 * called from here yet (TODO); admins refund in the PhonePe dashboard and
 * record the outcome so reports and the customer history stay correct.
 */
function payments_refund_update(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $txn    = $v->str('id', 'Transaction', ['required' => true, 'max' => 50]);
    $status = $v->enum('refund_status', 'Refund status', ['Refund Pending', 'Processed', 'None'], ['required' => true]);
    $amount = $v->num('amount', 'Refund amount', ['min' => 1]);
    $note   = $v->str('note', 'Note', ['max' => 255, 'default' => '']);
    $v->check();

    $t = q_one('SELECT * FROM transactions WHERE transaction_id = ?', [$txn]);
    if (!$t) {
        throw new ApiException('Refunds can only be recorded for online transactions.', 404);
    }
    if ($t['status'] !== 'success' && $status !== 'None') {
        throw new ApiException('Only successful payments can be refunded.', 409);
    }
    if ($amount !== null && $amount > (float) $t['amount']) {
        throw new ApiException('Refund amount cannot exceed the paid amount.', 422, ['amount' => 'Max ' . $t['amount']]);
    }
    if ($status === 'None') {
        q('UPDATE transactions SET refund_status = NULL, refund_amount = NULL, refund_note = NULL, refunded_at = NULL WHERE id = ?', [$t['id']]);
    } else {
        q(
            'UPDATE transactions SET refund_status = ?, refund_amount = ?, refund_note = ?, refunded_at = ? WHERE id = ?',
            [$status, $amount ?? ($t['refund_amount'] ?? $t['amount']), $note !== '' ? $note : $t['refund_note'], $status === 'Processed' ? now() : null, $t['id']]
        );
    }
    $booking = q_one("SELECT ID FROM Service_booking WHERE CONCAT('ZC-', unique_booking_id) = ?", [$txn]);
    if ($booking) {
        q(
            'INSERT INTO booking_status_history (booking_id, event, note, changed_by, changed_by_name, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$booking['ID'], 'refund', 'Refund: ' . $status . ($note !== '' ? ' - ' . $note : ''), $admin['id'], $admin['name'], now()]
        );
    }
    return ok(['id' => $txn, 'refund_status' => $status === 'None' ? null : $status], $status === 'None' ? 'Refund cleared.' : "Refund marked as $status.");
}

return [
    'list'          => ['GET',  'payments_list'],
    'summary'       => ['GET',  'payments_summary'],
    'get'           => ['GET',  'payments_get'],
    'refund_update' => ['POST', 'payments_refund_update', 'super'],
];
