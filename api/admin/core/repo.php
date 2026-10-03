<?php
/**
 * Shared read models used by several modules (bookings, dashboard,
 * customers, professionals, payments, reports).
 *
 * Booking <-> online payment link: the website starts PhonePe payments with
 * order_id "ZC-<unique_booking_id>" (zen-pages.js), stored as
 * transactions.transaction_id.
 */

const BOOKING_TXN_JOIN = "LEFT JOIN transactions t ON t.transaction_id = CONCAT('ZC-', b.unique_booking_id)";

/** Canonical payment status (Paid / Pending / Failed / Refunded) as SQL. */
function booking_payment_status_sql(): string
{
    return "CASE
        WHEN t.refund_status = 'Processed' THEN 'Refunded'
        WHEN t.transaction_id IS NOT NULL THEN (CASE t.status WHEN 'success' THEN 'Paid' WHEN 'failed' THEN 'Failed' ELSE 'Pending' END)
        WHEN LOWER(b.payment_status) = 'paid' THEN 'Paid'
        WHEN LOWER(b.payment_status) = 'refunded' THEN 'Refunded'
        ELSE 'Pending' END";
}

function booking_select_sql(): string
{
    return "SELECT b.*,
            u.first_name AS u_first, u.last_name AS u_last, u.phone AS u_phone, u.email AS u_email,
            p.full_name AS pro_name, p.mobile AS pro_mobile, p.primary_category AS pro_category,
            t.transaction_id AS txn_id, t.status AS txn_status, t.amount AS txn_amount,
            t.payment_mode AS txn_mode, t.refund_status AS txn_refund, t.created_at AS txn_date,
            " . booking_status_sql('b.status') . " AS canonical_status,
            " . booking_payment_status_sql() . " AS pay_status
        FROM service_booking b
        LEFT JOIN users u ON u.ID = b.user_id
        LEFT JOIN service_partners p ON p.id = b.professional_id
        " . BOOKING_TXN_JOIN;
}

/**
 * Items booked, from the free-text subcategories column:
 *   website:  "Foam Jet AC Service (₹599), Bathroom Cleaning (₹499)"
 *   app:      '[["Gas Filling"]]'  or plain "Window Cleaning"
 * @return array<int,array{name:string,price:?float}>
 */
function booking_items(?string $raw): array
{
    $raw = trim((string) $raw);
    if ($raw === '' || $raw === '""') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $flat = [];
        array_walk_recursive($decoded, function ($v) use (&$flat) {
            if (is_scalar($v) && trim((string) $v) !== '') {
                $flat[] = trim((string) $v);
            }
        });
        $parts = $flat;
    } else {
        $parts = preg_split('/,\s*(?![^()]*\))/u', $raw);
    }
    $items = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        if (preg_match('/^(.*?)\s*\(\s*(?:₹|Rs\.?|INR)\s*([\d,]+(?:\.\d+)?)\s*\)$/u', $part, $m)) {
            $items[] = ['name' => trim($m[1]), 'price' => (float) str_replace(',', '', $m[2])];
        } else {
            $items[] = ['name' => $part, 'price' => null];
        }
    }
    return $items;
}

/** Booking amount: stored price, else online amount, else sum of item prices. */
function booking_amount(?string $price, ?string $txnAmount, array $items): float
{
    if ($price !== null && $price !== '' && (float) $price > 0) {
        return (float) $price;
    }
    if ($txnAmount !== null && (float) $txnAmount > 0) {
        return (float) $txnAmount;
    }
    return (float) array_sum(array_map(fn($i) => (float) ($i['price'] ?? 0), $items));
}

/** "Name 9876543210" stored as "Contact: ..." inside landmark by checkout. */
function booking_contact(?string $landmark): array
{
    if ($landmark && preg_match('/Contact:\s*(.*?)\s*(\+?\d[\d\s]{8,14}\d)?\s*(\||$)/u', $landmark, $m)) {
        return ['name' => trim($m[1]), 'mobile' => isset($m[2]) ? preg_replace('/\D/', '', $m[2]) : ''];
    }
    return ['name' => '', 'mobile' => ''];
}

/** Normalised booking for the admin UI and API consumers. */
function booking_row(array $r): array
{
    $items   = booking_items($r['subcategories'] ?? '');
    $contact = booking_contact($r['landmark'] ?? '');
    $code    = trim((string) ($r['unique_booking_id'] ?? ''), " \"");
    $address = trim((string) ($r['location'] ?? ''));
    $parts   = array_map('trim', explode(',', $address));
    $pincode = preg_match('/\b([1-9]\d{5})\b/', $address, $pm) ? $pm[1] : '';
    $mobile  = ($r['u_phone'] ?? '') ?: $contact['mobile'];
    $txn     = $r['txn_id'] ?? null;

    $landmark = trim(preg_replace('/\s*\|?\s*Contact:.*$/u', '', (string) ($r['landmark'] ?? '')));
    $notes = '';
    if (preg_match('/Note:\s*([^|]*)/u', $landmark, $nm)) {
        $notes = trim($nm[1]);
        $landmark = trim(preg_replace('/\s*\|?\s*Note:[^|]*/u', '', $landmark), " |");
    }

    return [
        'id'              => (int) $r['ID'],
        'code'            => $code !== '' ? $code : '#' . $r['ID'],
        'customer_id'     => $r['user_id'] !== null ? (int) $r['user_id'] : null,
        'customer'        => full_name($r['u_first'] ?? '', $r['u_last'] ?? '') ?: ($contact['name'] ?: 'Guest'),
        'contact_name'    => $contact['name'],
        'mobile'          => format_mobile($mobile),
        'raw_mobile'      => $mobile,
        'email'           => $r['u_email'] ?? '',
        'service'         => $items ? implode(', ', array_column($items, 'name')) : ($r['category'] ?? ''),
        'items'           => $items,
        'category'        => (string) ($r['category'] ?? ''),
        'date'            => $r['date'] ?? null,
        'slot'            => (string) ($r['Service_Slot'] ?? $r['service_slot'] ?? ''),
        'amount'          => booking_amount($r['price'] ?? null, $r['txn_amount'] ?? null, $items),
        'gross_amount'    => isset($r['gross_amount']) ? (float) $r['gross_amount'] : null,
        'coupon_id'       => !empty($r['coupon_id']) ? (int) $r['coupon_id'] : null,
        'coupon_code'     => (string) ($r['coupon_code'] ?? ''),
        'discount'        => isset($r['discount_amount']) ? (float) $r['discount_amount'] : 0.0,
        'payment_method'  => $txn ? 'Online' : (($r['payment_method'] ?? '') ?: 'Cash'),
        'payment_status'  => $r['pay_status'] ?? 'Pending',
        'refund_status'   => $r['txn_refund'] ?? null,
        'transaction_id'  => $txn,
        'transaction_status' => $r['txn_status'] ?? null,
        'status'          => $r['canonical_status'] ?? booking_status_canonical($r['status'] ?? ''),
        'raw_status'      => (string) ($r['status'] ?? ''),
        'professional_id' => $r['professional_id'] !== null ? (int) $r['professional_id'] : null,
        'professional'    => (string) (($r['pro_name'] ?? '') ?: ($r['technician_name'] ?? '')),
        'professional_mobile' => format_mobile(($r['pro_mobile'] ?? '') ?: ($r['technician_phone'] ?? '')),
        'professional_category' => implode(', ', text_list($r['pro_category'] ?? '')),
        'address'         => $address,
        'area'            => count($parts) >= 3 ? $parts[count($parts) - 2] : ($parts[0] ?? ''),
        'pincode'         => $pincode,
        'landmark'        => $landmark,
        'notes'           => $notes,
        'created'         => $r['created_at'] ?? null,
        'assigned_at'     => $r['assigned_at'] ?? null,
        'completed_at'    => $r['completed_at'] ?? null,
        'cancel_reason'   => $r['cancel_reason'] ?? null,
        'cancelled_at'    => $r['cancelled_at'] ?? null,
    ];
}

function booking_needs_assignment(array $b): bool
{
    return $b['professional'] === '' && in_array($b['status'], ['New', 'Pending'], true);
}

function booking_find(int $id): ?array
{
    $row = q_one(booking_select_sql() . ' WHERE b.ID = ?', [$id]);
    return $row ? booking_row($row) : null;
}

/**
 * Filtered booking list (shared by bookings, customers, professionals, reports).
 * Filters: search, status (canonical | unassigned), category, payment_status,
 *          professional_id, customer_id, date_from/date_to (service date),
 *          created_from/created_to.
 */
function booking_query(array $in, int $limit, int $offset, string $order = 'b.created_at DESC, b.ID DESC'): array
{
    $where = ['1 = 1'];
    $params = [];

    if (!empty($in['search'])) {
        $where[] = like_clause(
            ['b.unique_booking_id', 'u.first_name', 'u.last_name', 'u.phone', 'b.category', 'b.subcategories', 'b.location', 'b.landmark', 'b.technician_name', 'p.full_name'],
            trim((string) $in['search']),
            $params
        );
    }
    $status = (string) ($in['status'] ?? '');
    if (strtolower($status) === 'unassigned') {
        $where[] = booking_needs_assignment_sql('b');
    } elseif ($status !== '') {
        $canonical = ucfirst(strtolower($status));
        if (!in_array($canonical, BOOKING_STATUSES, true)) {
            throw new ApiException('Unknown booking status.', 422, ['status' => 'Unknown status.']);
        }
        $where[] = booking_status_where($canonical, $params);
    }
    if (!empty($in['category'])) {
        $where[] = 'b.category LIKE ?';
        $params[] = '%' . $in['category'] . '%';
    }
    if (!empty($in['payment_status'])) {
        $where[] = '(' . booking_payment_status_sql() . ') = ?';
        $params[] = ucfirst(strtolower((string) $in['payment_status']));
    }
    if (isset($in['professional_id']) && $in['professional_id'] !== '') {
        if ($in['professional_id'] === 'none') {
            $where[] = booking_unassigned_sql('b');
        } else {
            $where[] = 'b.professional_id = ?';
            $params[] = (int) $in['professional_id'];
        }
    }
    if (!empty($in['customer_id'])) {
        $where[] = 'b.user_id = ?';
        $params[] = (int) $in['customer_id'];
    }
    foreach (['date_from' => 'b.date >= ?', 'date_to' => 'b.date <= ?', 'created_from' => 'b.created_at >= ?', 'created_to' => 'b.created_at <= ?'] as $key => $clause) {
        if (!empty($in[$key]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $in[$key])) {
            $where[] = $clause;
            $params[] = $in[$key] . ($key === 'created_to' ? ' 23:59:59' : ($key === 'created_from' ? ' 00:00:00' : ''));
        }
    }

    $sqlWhere = implode(' AND ', $where);
    $total = (int) q_value(
        "SELECT COUNT(*) FROM service_booking b
         LEFT JOIN users u ON u.ID = b.user_id
         LEFT JOIN service_partners p ON p.id = b.professional_id
         " . BOOKING_TXN_JOIN . " WHERE $sqlWhere",
        $params
    );
    $rows = q_all(booking_select_sql() . " WHERE $sqlWhere ORDER BY $order LIMIT $limit OFFSET $offset", $params);

    return [array_map('booking_row', $rows), $total];
}

/** Counts per canonical status + unassigned queue (+ optional extra WHERE). */
function booking_status_counts(string $extraWhere = '1 = 1', array $params = []): array
{
    $counts = array_fill_keys(BOOKING_STATUSES, 0);
    $rows = q_all('SELECT ' . booking_status_sql('b.status') . " AS s, COUNT(*) AS c FROM service_booking b WHERE $extraWhere GROUP BY s", $params);
    foreach ($rows as $row) {
        $counts[$row['s']] = (int) $row['c'];
    }
    $counts['unassigned'] = (int) q_value('SELECT COUNT(*) FROM service_booking b WHERE ' . booking_needs_assignment_sql('b') . " AND $extraWhere", $params);
    $counts['total'] = array_sum(array_intersect_key($counts, array_flip(BOOKING_STATUSES)));
    return $counts;
}

/**
 * Money totals for a date range (null = all time).
 * Online = successful PhonePe transactions; cash = bookings without a
 * transaction marked Paid (amount derived like booking_amount()).
 */
function payment_totals(?string $from = null, ?string $to = null): array
{
    $tw = "t.status = 'success'";
    $tp = [];
    if ($from) {
        $tw .= ' AND t.created_at >= ?';
        $tp[] = $from . ' 00:00:00';
    }
    if ($to) {
        $tw .= ' AND t.created_at <= ?';
        $tp[] = $to . ' 23:59:59';
    }
    $online = (float) q_value("SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE $tw", $tp);

    $cash = 0.0;
    $pending = 0.0;
    $rows = q_all(
        'SELECT b.price, b.subcategories, LOWER(COALESCE(b.payment_status, \'\')) AS ps, ' . booking_status_sql('b.status') . ' AS s,
                COALESCE(DATE(b.completed_at), b.date, DATE(b.created_at)) AS d
         FROM service_booking b ' . BOOKING_TXN_JOIN . ' WHERE t.id IS NULL'
    );
    foreach ($rows as $r) {
        if (($from && $r['d'] < $from) || ($to && $r['d'] > $to)) {
            continue;
        }
        $amount = booking_amount($r['price'], null, booking_items($r['subcategories']));
        if ($r['ps'] === 'paid') {
            $cash += $amount;
        } elseif ($r['s'] !== 'Cancelled') {
            $pending += $amount;
        }
    }

    $rw = "refund_status = 'Processed'";
    $rp = [];
    if ($from) {
        $rw .= ' AND refunded_at >= ?';
        $rp[] = $from . ' 00:00:00';
    }
    if ($to) {
        $rw .= ' AND refunded_at <= ?';
        $rp[] = $to . ' 23:59:59';
    }
    $refunds = (float) q_value("SELECT COALESCE(SUM(COALESCE(refund_amount, amount)), 0) FROM transactions WHERE $rw", $rp);
    $refundPending = (float) q_value("SELECT COALESCE(SUM(COALESCE(refund_amount, amount)), 0) FROM transactions WHERE refund_status = 'Refund Pending'");

    $received = $online + $cash;
    return [
        'received'        => $received,
        'online'          => $online,
        'cash'            => $cash,
        'pending'         => $pending,
        'refunds'         => $refunds,
        'refunds_pending' => $refundPending,
        'online_share'    => $received > 0 ? (int) round($online / $received * 100) : 0,
    ];
}

/**
 * Payments = every online transaction + cash bookings without a transaction
 * (paid, or still collectable i.e. not cancelled).
 */
function payment_union_sql(): string
{
    return "SELECT 'online' AS kind, t.id AS pid, t.transaction_id AS txn, b.ID AS booking_id, b.unique_booking_id AS booking_code,
                COALESCE(b.user_id, t.user_id) AS user_ref, t.amount AS amount, b.price AS price, b.subcategories AS subcategories,
                'Online' AS method, COALESCE(NULLIF(t.payment_mode, ''), 'PHONEPE') AS gateway,
                CASE WHEN t.refund_status = 'Processed' THEN 'Refunded' WHEN t.status = 'success' THEN 'Paid' WHEN t.status = 'failed' THEN 'Failed' ELSE 'Pending' END AS pstatus,
                t.refund_status AS refund_status, t.created_at AS pdate
            FROM transactions t
            LEFT JOIN service_booking b ON CONCAT('ZC-', b.unique_booking_id) = t.transaction_id
        UNION ALL
        SELECT 'cash', b.ID, CONCAT('CASH-', b.ID), b.ID, b.unique_booking_id,
                b.user_id, NULL, b.price, b.subcategories,
                'Cash', 'Cash on service',
                CASE WHEN LOWER(b.payment_status) = 'paid' THEN 'Paid' ELSE 'Pending' END,
                NULL, COALESCE(b.completed_at, b.created_at)
            FROM service_booking b
            LEFT JOIN transactions t ON t.transaction_id = CONCAT('ZC-', b.unique_booking_id)
            WHERE t.id IS NULL AND (LOWER(b.payment_status) = 'paid' OR " . booking_status_sql('b.status') . " <> 'Cancelled')";
}

function payment_row(array $r): array
{
    $amount = $r['kind'] === 'online'
        ? (float) $r['amount']
        : booking_amount($r['price'], null, booking_items($r['subcategories']));
    $code = trim((string) ($r['booking_code'] ?? ''), ' "');
    return [
        'kind'        => $r['kind'],
        'id'          => (int) $r['pid'],
        'txn'         => $r['txn'],
        'booking_id'  => $r['booking_id'] !== null ? (int) $r['booking_id'] : null,
        'booking'     => $r['booking_id'] !== null ? ($code !== '' ? $code : '#' . $r['booking_id']) : '',
        'customer_id' => $r['user_ref'] !== null && ctype_digit((string) $r['user_ref']) ? (int) $r['user_ref'] : null,
        'customer'    => full_name($r['u_first'] ?? '', $r['u_last'] ?? '') ?: 'Customer #' . ($r['user_ref'] ?? '-'),
        'mobile'      => format_mobile($r['u_phone'] ?? ''),
        'amount'      => $amount,
        'method'      => $r['method'],
        'gateway'     => $r['gateway'],
        'status'      => $r['pstatus'],
        'refund'      => $r['refund_status'] ?: '-',
        'date'        => $r['pdate'],
    ];
}

/**
 * Filters: search, method (Online|Cash), status (Paid|Pending|Failed|Refunded),
 *          refund (Refund Pending|Processed), customer_id, booking_id,
 *          date_from/date_to.
 */
function payment_query(array $in, int $limit, int $offset): array
{
    $where = ['1 = 1'];
    $params = [];
    if (!empty($in['search'])) {
        $where[] = like_clause(['p.txn', 'p.booking_code', 'u.first_name', 'u.last_name', 'u.phone'], trim((string) $in['search']), $params);
    }
    foreach (['method' => 'p.method', 'status' => 'p.pstatus', 'refund' => 'p.refund_status'] as $key => $col) {
        if (!empty($in[$key])) {
            $where[] = "$col = ?";
            $params[] = (string) $in[$key];
        }
    }
    if (!empty($in['customer_id'])) {
        $where[] = 'p.user_ref = ?';
        $params[] = (string) (int) $in['customer_id'];
    }
    if (!empty($in['booking_id'])) {
        $where[] = 'p.booking_id = ?';
        $params[] = (int) $in['booking_id'];
    }
    if (!empty($in['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $in['date_from'])) {
        $where[] = 'p.pdate >= ?';
        $params[] = $in['date_from'] . ' 00:00:00';
    }
    if (!empty($in['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $in['date_to'])) {
        $where[] = 'p.pdate <= ?';
        $params[] = $in['date_to'] . ' 23:59:59';
    }
    $from = '(' . payment_union_sql() . ') p LEFT JOIN users u ON u.ID = p.user_ref';
    $sqlWhere = implode(' AND ', $where);

    $total = (int) q_value("SELECT COUNT(*) FROM $from WHERE $sqlWhere", $params);
    $rows = q_all(
        "SELECT p.*, u.first_name AS u_first, u.last_name AS u_last, u.phone AS u_phone FROM $from WHERE $sqlWhere ORDER BY p.pdate DESC, p.pid DESC LIMIT $limit OFFSET $offset",
        $params
    );
    return [array_map('payment_row', $rows), $total];
}

/** Accepts a category id (or its exact name). */
function resolve_category_id($value): ?int
{
    if ($value === null || $value === '' || !is_scalar($value)) {
        return null;
    }
    $id = ctype_digit((string) $value)
        ? q_value('SELECT CATEGORY_ID FROM service_category WHERE CATEGORY_ID = ?', [(int) $value])
        : q_value('SELECT CATEGORY_ID FROM service_category WHERE NAME = ?', [(string) $value]);
    return $id !== null ? (int) $id : null;
}

/* --------------------------------------------------------------------------
 * Professionals (service_partners)
 * ------------------------------------------------------------------------ */

function professional_status_label(?string $status): string
{
    $s = strtolower(trim((string) $status));
    return $s === 'active' || $s === '1' || $s === 'approved' ? 'Active' : ($s === 'pending' || $s === '' ? 'Pending' : 'Inactive');
}

function professional_row(array $r): array
{
    $status = professional_status_label($r['status'] ?? '');
    return [
        'id'          => (int) $r['id'],
        'code'        => 'P' . str_pad((string) $r['id'], 3, '0', STR_PAD_LEFT),
        'name'        => (string) $r['full_name'],
        'mobile'      => format_mobile($r['mobile'] ?? ''),
        'raw_mobile'  => (string) ($r['mobile'] ?? ''),
        'email'       => (string) ($r['email'] ?? ''),
        'category'    => implode(', ', text_list($r['primary_category'] ?? '')),
        'categories'  => text_list($r['primary_category'] ?? ''),
        'areas'       => text_list($r['serviceable_areas'] ?? ''),
        'area'        => implode(', ', text_list($r['serviceable_areas'] ?? '')),
        'city'        => (string) ($r['city'] ?? ''),
        'experience'  => (string) ($r['experience'] ?? ''),
        'photo'       => image_path($r['photo'] ?? '', 'uploads'),
        'remarks'     => (string) ($r['remarks'] ?? ''),
        'status'      => $status === 'Active',
        'status_label'=> $status,
        'joined'      => $r['created_at'] ?? null,
        'active_jobs' => (int) ($r['active_jobs'] ?? 0),
        'completed'   => (int) ($r['completed_jobs'] ?? 0),
        'rating'      => isset($r['avg_rating']) && $r['avg_rating'] !== null ? round((float) $r['avg_rating'], 1) : null,
        'reviews'     => (int) ($r['review_count'] ?? 0),
    ];
}

/** SELECT for professionals with job counts and rating. */
function professional_select_sql(): string
{
    $active = "IN ('technician_assigned','technician assigned','assigned','ongoing','in progress','in_progress','started')";
    $done   = "IN ('service complete','completed','complete')";
    return "SELECT p.*,
            (SELECT COUNT(*) FROM service_booking b WHERE b.professional_id = p.id AND LOWER(TRIM(b.status)) $active) AS active_jobs,
            (SELECT COUNT(*) FROM service_booking b WHERE b.professional_id = p.id AND LOWER(TRIM(b.status)) $done) AS completed_jobs,
            (SELECT AVG(r.rating) FROM ratings_feedback r JOIN service_booking b ON b.ID = r.unique_booking_id WHERE b.professional_id = p.id) AS avg_rating,
            (SELECT COUNT(*) FROM ratings_feedback r JOIN service_booking b ON b.ID = r.unique_booking_id WHERE b.professional_id = p.id) AS review_count
        FROM service_partners p";
}

/* --------------------------------------------------------------------------
 * Customers (users)
 * ------------------------------------------------------------------------ */

/** "Flat 2, Madhapur, Hyderabad - 500081" -> area / city / pincode. */
function address_parts(?string $address): array
{
    $address = trim((string) $address);
    $pincode = preg_match('/\b([1-9]\d{5})\b/', $address, $m) ? $m[1] : '';
    $clean = trim(preg_replace('/\s*-?\s*\b[1-9]\d{5}\b/', '', $address), " ,-");
    $parts = array_values(array_filter(array_map('trim', explode(',', $clean)), 'strlen'));
    $n = count($parts);
    return [
        'city'    => $n ? $parts[$n - 1] : '',
        'area'    => $n >= 2 ? $parts[$n - 2] : '',
        'pincode' => $pincode,
    ];
}

/** users.status holds '1', '0', 'Active' ... - anything but these is active. */
function customer_inactive_sql(string $col = 'u.status'): string
{
    return "LOWER(TRIM(COALESCE($col, '1'))) IN ('0', 'inactive', 'blocked', 'disabled')";
}

/**
 * Recipients for notifications / SMS campaigns.
 * audience: all | active | no_booking_30 (alias inactive_30) | city | booking | single | custom
 * @return array{0:array<int,array{id:?int,name:string,mobile:?string,email:?string}>,1:string} [recipients, label]
 */
function customer_audience(string $audience, array $in): array
{
    $select = 'SELECT u.ID AS id, u.first_name, u.last_name, u.phone, u.email FROM users u';
    $map = fn(array $rows) => array_map(fn($r) => [
        'id' => $r['id'] !== null ? (int) $r['id'] : null, 'name' => full_name($r['first_name'] ?? '', $r['last_name'] ?? '') ?: 'Customer',
        'mobile' => $r['phone'] ?? null, 'email' => $r['email'] ?? null,
    ], $rows);

    switch ($audience) {
        case 'all':
            return [$map(q_all($select)), 'All customers'];
        case 'active':
            return [$map(q_all("$select WHERE NOT " . customer_inactive_sql())), 'Active customers'];
        case 'no_booking_30':
        case 'inactive_30':
            $since = date('Y-m-d H:i:s', strtotime('-30 days'));
            return [$map(q_all("$select WHERE NOT " . customer_inactive_sql() . ' AND NOT EXISTS (SELECT 1 FROM service_booking b WHERE b.user_id = u.ID AND b.created_at >= ?)', [$since])), 'No booking in last 30 days'];
        case 'city':
            $city = trim((string) ($in['city'] ?? ''));
            if ($city === '') {
                throw new ApiException('Select a city.', 422, ['city' => 'Select a city.']);
            }
            $rows = q_all("$select WHERE NOT " . customer_inactive_sql() . ' AND (u.address LIKE ? OR EXISTS (SELECT 1 FROM service_booking b WHERE b.user_id = u.ID AND b.location LIKE ?))', ['%' . $city . '%', '%' . $city . '%']);
            return [$map($rows), 'Customers in ' . $city];
        case 'booking':
        case 'single':
            $target = trim((string) ($in['target'] ?? ''));
            if ($target === '') {
                throw new ApiException('Enter a booking ID or customer mobile.', 422, ['target' => 'Required.']);
            }
            $mobile = normalize_mobile($target);
            if ($mobile) {
                $rows = q_all("$select WHERE u.phone = ?", [$mobile]);
            } else {
                $code = ltrim($target, '#');
                $rows = q_all("$select JOIN service_booking b ON b.user_id = u.ID WHERE b.unique_booking_id = ? OR b.ID = ? LIMIT 1", [$code, ctype_digit($code) ? (int) $code : 0]);
            }
            if (!$rows) {
                throw new ApiException('No customer found for "' . $target . '".', 422, ['target' => 'No matching customer.']);
            }
            $r = $map($rows)[0];
            return [[$r], $r['name'] . ' (' . $target . ')'];
        case 'custom':
            $numbers = preg_split('/[\s,;]+/', (string) ($in['mobiles'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            $valid = array_values(array_unique(array_filter(array_map('normalize_mobile', $numbers))));
            if (!$valid) {
                throw new ApiException('Enter at least one valid mobile number.', 422, ['mobiles' => 'Enter valid 10-digit numbers.']);
            }
            if (count($valid) > 1000) {
                throw new ApiException('At most 1000 numbers per campaign.', 422, ['mobiles' => 'Too many numbers.']);
            }
            $known = [];
            foreach (array_chunk($valid, 200) as $chunk) {
                foreach (q_all("$select WHERE u.phone IN (" . implode(',', array_fill(0, count($chunk), '?')) . ')', $chunk) as $r) {
                    $known[$r['phone']] = $r;
                }
            }
            $list = array_map(fn($m) => isset($known[$m])
                ? $map([$known[$m]])[0]
                : ['id' => null, 'name' => 'Customer', 'mobile' => $m, 'email' => null], $valid);
            return [$list, count($valid) . ' custom number(s)'];
    }
    throw new ApiException('Unknown audience.', 422, ['audience' => 'Select an audience.']);
}

function customer_is_active(?string $status): bool
{
    return !in_array(strtolower(trim((string) ($status ?? '1'))), ['0', 'inactive', 'blocked', 'disabled'], true);
}
