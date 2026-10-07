<?php
/**
 * Website "Send an Enquiry" form (contact.php). No login needed.
 * Saved as a General support ticket so admins answer it in
 * Admin > Support Tickets. When a customer Bearer token is sent the
 * ticket is linked to that customer, otherwise it shows as Guest.
 *
 * POST {name, phone, email?, service, address?, message, website?}
 *      website: honeypot field, must stay empty.
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/auth_helper.php';

const ENQUIRY_DAILY_LIMIT = 3;

public_cors('POST, OPTIONS');
public_require_method('POST');

$in = public_input();
$name = public_str($in, 'name');
$phone = (string) zc_normalize_phone(public_str($in, 'phone'));
$email = public_str($in, 'email');
$service = public_str($in, 'service');
$address = public_str($in, 'address');
$message = public_str($in, 'message');

if (public_str($in, 'website') !== '') {
    public_json(201, 'Thank you. Our team will call you soon.');
}

$errors = [];
if ($name === '' || mb_strlen($name) > 100) {
    $errors['name'] = $name === '' ? 'Enter your name.' : 'Name can be at most 100 characters.';
}
if (!preg_match('/^[6-9]\d{9}$/', $phone)) {
    $errors['phone'] = 'Enter a valid 10-digit mobile number.';
}
if ($email !== '' && (mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
    $errors['email'] = 'Enter a valid email address.';
}
if ($service === '' || mb_strlen($service) > 100) {
    $errors['service'] = $service === '' ? 'Choose a service.' : 'Service can be at most 100 characters.';
}
if (mb_strlen($address) > 255) {
    $errors['address'] = 'Address can be at most 255 characters.';
}
if ($message === '' || mb_strlen($message) > 2000) {
    $errors['message'] = $message === '' ? 'Tell us about your requirement.' : 'Message can be at most 2000 characters.';
}
if ($errors) {
    public_json(422, 'Please check the highlighted fields.', null, $errors);
}

$conn = public_db();
$tz = $_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: 'Asia/Kolkata';
$now = (new DateTime('now', new DateTimeZone($tz)))->format('Y-m-d H:i:s');
$phoneLine = 'Phone: ' . $phone;

try {
    $auth = getUserIdFromRequest($conn);
    $userId = $auth ? (int) $auth['user_id'] : null;

    $recent = $conn->prepare("SELECT COUNT(*) FROM support_tickets WHERE subject LIKE 'Website enquiry:%' AND message LIKE ? AND created_at >= ?");
    $recent->execute(['%' . $phoneLine . '%', date('Y-m-d H:i:s', strtotime($now . ' -1 day'))]);
    if ((int) $recent->fetchColumn() >= ENQUIRY_DAILY_LIMIT) {
        public_json(429, 'We have already received your enquiries today. Our team will call you soon.');
    }

    $clean = fn(string $v): string => trim(strip_tags($v));
    $body = implode("\n", array_filter([
        'Name: ' . $clean($name),
        $phoneLine,
        $email !== '' ? 'Email: ' . $clean($email) : null,
        'Service: ' . $clean($service),
        $address !== '' ? 'Address: ' . $clean($address) : null,
        '',
        $clean($message),
    ], fn($line) => $line !== null));
    $subject = mb_substr('Website enquiry: ' . $clean($service) . ' - ' . $clean($name), 0, 200);

    $conn->beginTransaction();
    $conn->prepare('INSERT INTO support_tickets (user_id, booking_id, subject, category, priority, status, message, created_at, updated_at) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$userId, $subject, 'General', 'Medium', 'Open', $body, $now, $now]);
    $id = (int) $conn->lastInsertId();
    $ticketNo = 'TK-' . (1000 + $id);
    $conn->prepare('UPDATE support_tickets SET ticket_no = ? WHERE id = ?')->execute([$ticketNo, $id]);
    $conn->commit();

    public_json(201, 'Thank you. Your enquiry ' . $ticketNo . ' has been received and our team will call you soon.', ['ticket_no' => $ticketNo]);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('[api/contact_enquiry] ' . $e->getMessage());
    public_json(500, 'Could not send your enquiry. Please try again or call us.');
}
