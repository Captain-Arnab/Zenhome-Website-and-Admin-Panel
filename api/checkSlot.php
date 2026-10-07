<?php
/**
 * Free time slots for a service date (public).
 * POST {"date": "Y-m-d"} (also ?date= on GET)
 * 200 {"statusCode":200,"status":"success","message":...,"data":["10:00 AM - 11:00 AM", ...]}
 *
 * A slot is taken by any non-cancelled booking on that date; for today,
 * slots that have already started are left out. Past dates return [].
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/booking_helper.php';
require_once __DIR__ . '/legacy_access.php';

public_cors('GET, POST, OPTIONS');
public_require_method('GET', 'POST');
$legacy = legacy_access('checkSlot');

$in = public_input();
$raw = public_str($in, 'date');
if ($raw === '') {
    public_json(400, 'Date parameter is missing', null, ['date' => 'Date is required.']);
}
$date = zc_valid_date($raw);
if ($date === null) {
    public_json(422, 'Invalid date. Use YYYY-MM-DD.', null, ['date' => 'Use YYYY-MM-DD.']);
}

$conn = public_db();
$now = zc_app_now();
$booked = zc_booked_slots($conn, $date);

$available = array_values(array_filter(ZC_BOOKING_SLOTS, fn(string $slot) =>
    !in_array($slot, $booked, true) && !zc_slot_elapsed($date, $slot, $now)));

public_json(200, 'Available slots fetched successfully', $available);
