<?php
/**
 * Bookings: list, detail, timeline, assign/reassign, status, cancel, notes.
 *
 * Assignment and every status change write a booking_status_history row and
 * call notify_booking_customer() (SMS + push hooks, logged with real outcome).
 */

const BOOKING_CLOSED = ['Completed', 'Cancelled'];

function bookings_list(array $in, ?array $admin): array
{
    [$page, $limit, $offset] = page_params($in);
    $order = order_by($in, [
        'created' => 'b.created_at',
        'date'    => 'b.date',
        'id'      => 'b.ID',
    ], 'b.created_at DESC, b.ID DESC');

    [$items, $total] = booking_query($in, $limit, $offset, $order);
    $extra = [];
    if (!empty($in['with_counts'])) {
        $extra['counts'] = booking_status_counts();
    }
    return ok(paginated($items, $total, $page, $limit, $extra));
}

function bookings_counts(array $in, ?array $admin): array
{
    return ok(booking_status_counts());
}

/** Timeline = status history + notification attempts, newest last. */
function bookings_timeline(int $bookingId): array
{
    $history = q_all(
        'SELECT h.*, p.full_name AS professional_name FROM booking_status_history h
         LEFT JOIN service_partners p ON p.id = h.professional_id
         WHERE h.booking_id = ? ORDER BY h.created_at, h.id',
        [$bookingId]
    );
    return array_map(fn($h) => [
        'id'           => (int) $h['id'],
        'event'        => $h['event'],
        'old_status'   => $h['old_status'],
        'new_status'   => $h['new_status'],
        'professional' => $h['professional_name'],
        'note'         => $h['note'],
        'notified'     => (bool) $h['notified'],
        'by'           => $h['changed_by_name'] ?: 'System',
        'at'           => $h['created_at'],
    ], $history);
}

function bookings_get(array $in, ?array $admin): array
{
    $id = (int) ($in['id'] ?? 0);
    $booking = $id ? booking_find($id) : null;
    if (!$booking) {
        throw not_found('Booking');
    }

    $customer = null;
    if ($booking['customer_id']) {
        $u = q_one('SELECT ID, first_name, last_name, email, phone, status, created_at FROM users WHERE ID = ?', [$booking['customer_id']]);
        if ($u) {
            $customer = [
                'id'       => (int) $u['ID'],
                'name'     => full_name($u['first_name'], $u['last_name']),
                'email'    => (string) $u['email'],
                'mobile'   => format_mobile($u['phone']),
                'status'   => customer_is_active($u['status']),
                'joined'   => $u['created_at'],
                'bookings' => (int) q_value('SELECT COUNT(*) FROM Service_booking WHERE user_id = ?', [$u['ID']]),
            ];
        }
    }

    $payment = null;
    if ($booking['transaction_id']) {
        $t = q_one('SELECT transaction_id, amount, status, payment_mode, created_at, refund_status, refund_amount, refund_note, refunded_at FROM transactions WHERE transaction_id = ?', [$booking['transaction_id']]);
        $payment = $t ? [
            'transaction_id' => $t['transaction_id'],
            'amount'         => (float) $t['amount'],
            'status'         => $t['status'],
            'mode'           => $t['payment_mode'] ?: 'PHONEPE',
            'date'           => $t['created_at'],
            'refund_status'  => $t['refund_status'],
            'refund_amount'  => $t['refund_amount'] !== null ? (float) $t['refund_amount'] : null,
            'refund_note'    => $t['refund_note'],
            'refunded_at'    => $t['refunded_at'],
        ] : null;
    }

    $notes = q_all('SELECT id, admin_name, note, created_at FROM booking_notes WHERE booking_id = ? ORDER BY created_at DESC, id DESC', [$id]);
    $notifications = q_all('SELECT id, title, type, channels, status, status_detail, created_at FROM customer_notifications WHERE booking_id = ? ORDER BY created_at DESC, id DESC', [$id]);
    $review = q_one('SELECT id, rating, feedback, status, created_at FROM ratings_feedback WHERE unique_booking_id = ? ORDER BY id DESC LIMIT 1', [$id]);

    return ok([
        'booking'       => $booking,
        'customer'      => $customer,
        'payment'       => $payment,
        'history'       => bookings_timeline($id),
        'notes'         => $notes,
        'notifications' => $notifications,
        'review'        => $review,
    ]);
}

function bookings_history(array $in, ?array $admin): array
{
    $id = (int) ($in['id'] ?? 0);
    if (!$id || !q_value('SELECT 1 FROM Service_booking WHERE ID = ?', [$id])) {
        throw not_found('Booking');
    }
    return ok(['items' => bookings_timeline($id)]);
}

/* --------------------------------------------------------------------------
 * State changes
 * ------------------------------------------------------------------------ */

function booking_for_update(int $id): array
{
    $booking = $id ? booking_find($id) : null;
    if (!$booking) {
        throw not_found('Booking');
    }
    return $booking;
}

function booking_assert_open(array $booking): void
{
    if (in_array($booking['status'], BOOKING_CLOSED, true)) {
        throw new ApiException("Booking {$booking['code']} is {$booking['status']} and can no longer be changed.", 409);
    }
}

function booking_log_history(int $bookingId, string $event, ?string $old, ?string $new, ?int $professionalId, ?string $note, array $admin): int
{
    q(
        'INSERT INTO booking_status_history (booking_id, event, old_status, new_status, professional_id, note, notified, changed_by, changed_by_name, created_at)
         VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
        [$bookingId, $event, $old, $new, $professionalId, $note !== '' ? $note : null, $admin['id'], $admin['name'], now()]
    );
    return (int) db()->lastInsertId();
}

/** Runs the notification hook and stores whether it reached the customer. */
function booking_notify(int $historyId, array $booking, string $event, bool $notify, array $vars, array $admin): ?array
{
    if (!$notify) {
        return null;
    }
    $result = notify_booking_customer($booking, $event, $vars, $admin);
    if (in_array($result['status'], ['Sent', 'Partially sent'], true)) {
        q('UPDATE booking_status_history SET notified = 1 WHERE id = ?', [$historyId]);
    }
    return ['status' => $result['status'], 'detail' => $result['detail']];
}

function booking_notify_message(?array $notification): string
{
    if ($notification === null) {
        return '';
    }
    return $notification['status'] === 'Sent'
        ? ' Customer notified.'
        : ' Customer notification: ' . $notification['status'] . '.';
}

function bookings_assign(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $bookingId = $v->int('booking_id', 'Booking', ['required' => true, 'min' => 1]);
    $proId     = $v->int('professional_id', 'Professional', ['required' => true, 'min' => 1]);
    $note      = $v->str('note', 'Note', ['max' => 500, 'default' => '']);
    $reason    = $v->str('reassign_reason', 'Reason', ['max' => 100, 'default' => '']);
    $notify    = $v->bool('notify_customer', true);
    $v->check();

    $booking = booking_for_update($bookingId);
    booking_assert_open($booking);

    $pro = q_one('SELECT id, full_name, mobile, status FROM service_partners WHERE id = ?', [$proId]);
    if (!$pro) {
        throw new ApiException('Professional not found.', 422, ['professional_id' => 'Professional not found.']);
    }
    if (professional_status_label($pro['status']) !== 'Active') {
        throw new ApiException('This professional is not active.', 422, ['professional_id' => 'Choose an active professional.']);
    }
    if ($booking['professional_id'] === (int) $pro['id']) {
        throw new ApiException("{$pro['full_name']} is already assigned to this booking.", 422, ['professional_id' => 'Already assigned.']);
    }

    $isReassign = $booking['professional'] !== '';
    if ($isReassign && $reason === '') {
        throw new ApiException('Select a reason for reassigning.', 422, ['reassign_reason' => 'Reason is required when reassigning.']);
    }

    $newStatus = in_array($booking['status'], ['New', 'Pending'], true) ? 'Assigned' : $booking['status'];
    $historyNote = trim(
        $pro['full_name'] . ($isReassign ? ' assigned (replacing ' . $booking['professional'] . '). Reason: ' . $reason : ' assigned.')
        . ($note !== '' ? ' Note: ' . $note : '')
    );

    $pdo = db();
    $pdo->beginTransaction();
    try {
        q(
            'UPDATE Service_booking SET professional_id = ?, technician_name = ?, technician_phone = ?, status = ?, assigned_at = ?, updated_at = ? WHERE ID = ?',
            [
                $pro['id'], mb_substr($pro['full_name'], 0, 50), normalize_mobile($pro['mobile']) ?? mb_substr((string) $pro['mobile'], 0, 10),
                booking_status_raw($newStatus), now(), now(), $bookingId,
            ]
        );
        $historyId = booking_log_history($bookingId, $isReassign ? 'reassigned' : 'assigned', $booking['status'], $newStatus, (int) $pro['id'], $historyNote, $admin);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $updated = booking_find($bookingId);
    $notification = booking_notify($historyId, $updated, 'assignment', $notify, [
        'professional_name'   => $pro['full_name'],
        'professional_mobile' => $updated['professional_mobile'],
    ], $admin);

    return ok(
        ['booking' => $updated, 'notification' => $notification],
        $pro['full_name'] . ($isReassign ? ' reassigned to ' : ' assigned to ') . $updated['code'] . '.' . booking_notify_message($notification)
    );
}

function bookings_update_status(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $bookingId = $v->int('booking_id', 'Booking', ['required' => true, 'min' => 1]);
    $status    = $v->enum('status', 'Status', ['New', 'Pending', 'Assigned', 'Ongoing', 'Completed'], ['required' => true]);
    $remarks   = $v->str('remarks', 'Remarks', ['max' => 500, 'default' => '']);
    $notify    = $v->bool('notify', true);
    $v->check();

    $booking = booking_for_update($bookingId);
    booking_assert_open($booking);
    if ($booking['status'] === $status) {
        throw new ApiException("Booking is already $status.", 422, ['status' => 'Choose a different status.']);
    }
    if (in_array($status, ['Assigned', 'Ongoing', 'Completed'], true) && $booking['professional'] === '') {
        throw new ApiException('Assign a professional before marking the booking ' . $status . '.', 422, ['status' => 'Assign a professional first.']);
    }

    $sets = ['status = ?', 'updated_at = ?'];
    $params = [booking_status_raw($status), now()];
    if ($status === 'Completed') {
        $sets[] = 'completed_at = ?';
        $params[] = now();
    }
    $params[] = $bookingId;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('UPDATE Service_booking SET ' . implode(', ', $sets) . ' WHERE ID = ?', $params);
        $historyId = booking_log_history($bookingId, 'status', $booking['status'], $status, $booking['professional_id'], $remarks, $admin);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $updated = booking_find($bookingId);
    $notification = booking_notify($historyId, $updated, 'status_update', $notify, [
        'professional_name' => $updated['professional'],
    ], $admin);

    return ok(['booking' => $updated, 'notification' => $notification], "Status updated to $status." . booking_notify_message($notification));
}

function bookings_cancel(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $bookingId = $v->int('booking_id', 'Booking', ['required' => true, 'min' => 1]);
    $reason    = $v->str('reason', 'Reason', ['required' => true, 'max' => 120]);
    $details   = $v->str('details', 'Details', ['max' => 500, 'default' => '']);
    $refund    = $v->bool('refund');
    $notify    = $v->bool('notify', true);
    $v->check();

    $booking = booking_for_update($bookingId);
    booking_assert_open($booking);

    $refundQueued = false;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q(
            'UPDATE Service_booking SET status = ?, cancel_reason = ?, cancelled_at = ?, updated_at = ? WHERE ID = ?',
            [booking_status_raw('Cancelled'), mb_substr($reason . ($details !== '' ? ' - ' . $details : ''), 0, 255), now(), now(), $bookingId]
        );
        if ($booking['coupon_id']) {
            // A cancelled booking does not count towards the coupon's usage limits.
            q('UPDATE coupons SET used_count = GREATEST(used_count - 1, 0) WHERE id = ?', [$booking['coupon_id']]);
        }
        if ($refund && $booking['transaction_id'] && $booking['transaction_status'] === 'success' && !$booking['refund_status']) {
            // TODO: call the PhonePe refund API. Until then the refund is queued
            // here and an admin marks it Processed from Payments after refunding.
            q(
                "UPDATE transactions SET refund_status = 'Refund Pending', refund_amount = amount, refund_note = ? WHERE transaction_id = ?",
                ['Booking cancelled: ' . mb_substr($reason, 0, 200), $booking['transaction_id']]
            );
            $refundQueued = true;
        }
        $note = $reason . ($details !== '' ? ' - ' . $details : '') . ($refundQueued ? ' (refund queued)' : '');
        $historyId = booking_log_history($bookingId, 'cancelled', $booking['status'], 'Cancelled', $booking['professional_id'], $note, $admin);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $updated = booking_find($bookingId);
    $notification = booking_notify($historyId, $updated, 'cancellation', $notify, ['reason' => $reason], $admin);

    return ok(
        ['booking' => $updated, 'refund_queued' => $refundQueued, 'notification' => $notification],
        "Booking {$updated['code']} cancelled." . ($refundQueued ? ' Refund marked as pending.' : '') . booking_notify_message($notification)
    );
}

function bookings_add_note(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $bookingId = $v->int('booking_id', 'Booking', ['required' => true, 'min' => 1]);
    $note      = $v->str('note', 'Note', ['required' => true, 'max' => 1000]);
    $v->check();
    booking_for_update($bookingId);

    q('INSERT INTO booking_notes (booking_id, admin_id, admin_name, note, created_at) VALUES (?, ?, ?, ?, ?)', [$bookingId, $admin['id'], $admin['name'], $note, now()]);
    return ok([
        'note' => ['id' => (int) db()->lastInsertId(), 'admin_name' => $admin['name'], 'note' => $note, 'created_at' => now()],
    ], 'Note saved.', 201);
}

/** Cash collected by the professional. */
function bookings_mark_paid(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $bookingId = $v->int('booking_id', 'Booking', ['required' => true, 'min' => 1]);
    $v->check();

    $booking = booking_for_update($bookingId);
    if ($booking['transaction_id']) {
        throw new ApiException('This booking was paid online; its status comes from the payment gateway.', 409);
    }
    if ($booking['payment_status'] === 'Paid') {
        throw new ApiException('Payment is already marked as received.', 409);
    }
    if ($booking['status'] === 'Cancelled') {
        throw new ApiException('Cannot record payment for a cancelled booking.', 409);
    }

    $amount = $booking['amount'];
    q(
        "UPDATE Service_booking SET payment_method = 'Cash', payment_status = 'Paid', price = COALESCE(price, ?), updated_at = ? WHERE ID = ?",
        [$amount > 0 ? (int) round($amount) : null, now(), $bookingId]
    );
    booking_log_history($bookingId, 'payment', $booking['status'], $booking['status'], $booking['professional_id'], 'Cash payment of Rs ' . number_format($amount, 2) . ' received.', $admin);

    return ok(['booking' => booking_find($bookingId)], 'Cash payment marked as received.');
}

return [
    'list'          => ['GET',  'bookings_list'],
    'counts'        => ['GET',  'bookings_counts'],
    'get'           => ['GET',  'bookings_get'],
    'history'       => ['GET',  'bookings_history'],
    'assign'        => ['POST', 'bookings_assign'],
    'update_status' => ['POST', 'bookings_update_status'],
    'cancel'        => ['POST', 'bookings_cancel'],
    'add_note'      => ['POST', 'bookings_add_note'],
    'mark_paid'     => ['POST', 'bookings_mark_paid'],
];
