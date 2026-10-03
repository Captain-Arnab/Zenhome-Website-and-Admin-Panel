<?php
/**
 * Booking status vocabulary.
 *
 * service_booking.status is free text written by several existing APIs
 * ("Pending Confirmation", "booking confirmed", "technician_assigned",
 * "Service Complete" ...). The admin panel works with 6 canonical statuses
 * and maps them both ways, so existing apps keep reading the values they
 * already understand.
 */

const BOOKING_STATUSES = ['New', 'Pending', 'Assigned', 'Ongoing', 'Completed', 'Cancelled'];

/** canonical => raw values (lowercase) that mean the same thing */
function booking_status_aliases(): array
{
    return [
        'New'       => ['pending confirmation', 'new', 'placed', 'booked'],
        'Pending'   => ['pending', 'booking confirmed', 'confirmed'],
        'Assigned'  => ['technician_assigned', 'technician assigned', 'assigned'],
        'Ongoing'   => ['ongoing', 'in progress', 'in_progress', 'started'],
        'Completed' => ['service complete', 'completed', 'complete'],
        'Cancelled' => ['cancelled', 'canceled'],
    ];
}

/** Value written to service_booking.status for a canonical status. */
function booking_status_raw(string $canonical): string
{
    return [
        'New'       => 'Pending Confirmation',   // api/book_appointment.php
        'Pending'   => 'booking confirmed',      // api/admin/confirm_bookings.php
        'Assigned'  => 'technician_assigned',    // api/admin/assignTechnician.php, manageTimeSlot.php
        'Ongoing'   => 'Ongoing',
        'Completed' => 'Service Complete',       // api/serviceComplete.php
        'Cancelled' => 'Cancelled',
    ][$canonical] ?? $canonical;
}

function booking_status_canonical(?string $raw): string
{
    $raw = strtolower(trim((string) $raw));
    foreach (booking_status_aliases() as $canonical => $aliases) {
        if (in_array($raw, $aliases, true)) {
            return $canonical;
        }
    }
    return 'Pending'; // unknown legacy value: treat as awaiting admin action
}

/** SQL CASE expression returning the canonical status of $column. */
function booking_status_sql(string $column = 'b.status'): string
{
    $sql = "CASE LOWER(TRIM($column))";
    foreach (booking_status_aliases() as $canonical => $aliases) {
        foreach ($aliases as $alias) {
            $sql .= " WHEN '" . $alias . "' THEN '" . $canonical . "'";
        }
    }
    return $sql . " ELSE 'Pending' END";
}

/** WHERE fragment matching a canonical status (prepared params appended). */
function booking_status_where(string $canonical, array &$params, string $column = 'b.status'): string
{
    if ($canonical === 'Pending') {
        // "Pending" also covers unknown legacy values (see booking_status_canonical()).
        return booking_status_sql($column) . " = 'Pending'";
    }
    $aliases = booking_status_aliases()[$canonical] ?? [];
    if (!$aliases) {
        return '1 = 0';
    }
    foreach ($aliases as $alias) {
        $params[] = $alias;
    }
    return "LOWER(TRIM($column)) IN (" . implode(',', array_fill(0, count($aliases), '?')) . ')';
}

/** True when nobody is assigned (no internal professional and no legacy technician). */
function booking_unassigned_sql(string $alias = 'b'): string
{
    return "($alias.professional_id IS NULL AND ($alias.technician_name IS NULL OR TRIM($alias.technician_name) = ''))";
}

/** Bookings waiting for a professional: New/Pending and unassigned. */
function booking_needs_assignment_sql(string $alias = 'b'): string
{
    return '(' . booking_status_sql("$alias.status") . " IN ('New', 'Pending') AND " . booking_unassigned_sql($alias) . ')';
}
