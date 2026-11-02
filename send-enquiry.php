<?php
/**
 * Contact Form Enquiry Processor
 * Handles security assessment quote requests and sends email notifications to admin via PHPMailer.
 */

declare(strict_types=1);

// Start session if needed for CSRF or flash messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/mail_helper.php';

// Set response headers for AJAX calls
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

/**
 * Send JSON response and terminate.
 */
function sendJsonResponse(bool $success, string $message, array $errors = [], int $httpStatus = 200): void
{
    http_response_code($httpStatus);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'errors'  => $errors,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        sendJsonResponse(false, 'Invalid request method.', [], 405);
    }
    header('Location: contact.html');
    exit;
}

// 2. Parse input (support both form-data/x-www-form-urlencoded and JSON body)
$inputData = $_POST;
$rawBody = file_get_contents('php://input');
if (empty($inputData) && !empty($rawBody)) {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $inputData = $decoded;
    }
}

// 3. Honeypot anti-spam check (if present and filled, silently drop spam)
if (!empty($inputData['website_hp'])) {
    if ($isAjax) {
        sendJsonResponse(true, 'Your request has been received.');
    }
    header('Location: contact.html?status=success#contact-form-container');
    exit;
}

// 4. Extract and sanitize input fields
$name         = trim((string)($inputData['name'] ?? ''));
$organization = trim((string)($inputData['organization'] ?? ''));
$phone        = trim((string)($inputData['phone'] ?? ''));
$email        = trim((string)($inputData['email'] ?? ''));
$service      = trim((string)($inputData['service'] ?? ''));
$location     = trim((string)($inputData['location'] ?? ''));
$message      = trim((string)($inputData['message'] ?? ''));

// Strip HTML tags and control characters to prevent injection
$name         = htmlspecialchars(strip_tags($name), ENT_QUOTES, 'UTF-8');
$organization = htmlspecialchars(strip_tags($organization), ENT_QUOTES, 'UTF-8');
$phone        = preg_replace('/[^\d\+\-\(\)\s]/', '', $phone);
$service      = htmlspecialchars(strip_tags($service), ENT_QUOTES, 'UTF-8');
$location     = htmlspecialchars(strip_tags($location), ENT_QUOTES, 'UTF-8');
$message      = htmlspecialchars(strip_tags($message), ENT_QUOTES, 'UTF-8');

// 5. Validation
$errors = [];

if (empty($name) || mb_strlen($name) < 2) {
    $errors['name'] = 'Please enter your full name.';
}

if (empty($organization) || mb_strlen($organization) < 2) {
    $errors['organization'] = 'Please enter your company or society name.';
}

if (empty($phone) || strlen(preg_replace('/\D/', '', $phone)) < 7) {
    $errors['phone'] = 'Please enter a valid phone number.';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please provide a valid email address.';
}

if (empty($service)) {
    $errors['service'] = 'Please select a security service.';
}

if (empty($location) || mb_strlen($location) < 2) {
    $errors['location'] = 'Please specify the facility location or city.';
}

// Return errors if any field is invalid
if (!empty($errors)) {
    if ($isAjax) {
        sendJsonResponse(false, 'Please correct the highlighted errors and try again.', $errors, 422);
    }
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data']   = $inputData;
    header('Location: contact.html?status=validation_error#contact-form-container');
    exit;
}

// 6. Metadata for tracking
$clientIp   = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$userAgent  = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 250);
$timestamp  = date('d M Y, h:i A T');

// 7. Compose Simple, Clean HTML Email for Admin
$adminEmail = getMailConfig('MAIL_TO_ADDRESS', 'hyderabad@royalenansecurity.com');
$adminName  = getMailConfig('MAIL_TO_NAME', 'Royal Enan Security Command');

$subject = "New Security Assessment Enquiry: {$organization} ({$name})";

$safeMessageHtml = !empty($message) ? nl2br($message) : '<em style="color: #888;">No additional notes provided.</em>';

$emailBodyHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{$subject}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; line-height: 1.6; color: #222222; margin: 0; padding: 20px; background-color: #f7f9fc;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #dbe2ea; border-radius: 6px; padding: 25px;">
    
    <h2 style="color: #0b1a30; margin-top: 0; margin-bottom: 6px; font-size: 20px; border-bottom: 2px solid #d4af37; padding-bottom: 10px;">
      New Security Assessment Request
    </h2>
    <p style="color: #555555; font-size: 14px; margin-top: 10px; margin-bottom: 20px;">
      A new enquiry was submitted through the website contact form:
    </p>

    <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
      <tr>
        <td style="padding: 10px 8px; border-bottom: 1px solid #eeeeee; font-weight: bold; width: 35%; color: #0b1a30;">Full Name:</td>
        <td style="padding: 10px 8px; border-bottom: 1px solid #eeeeee; color: #333333;">{$name}</td>
      </tr>
      <tr>
        <td style="padding: 10px 8px; border-bottom: 1px solid #eeeeee; font-weight: bold; color: #0b1a30;">Company / Society:</td>
        <td style="padding: 10px 8px; border-bottom: 1px solid #eeeeee; color: #333333;">{$organization}</td>
      </tr>
      <tr>
        <td style="padding: 10px 8px; border-bottom: 1px solid #eeeeee; font-weight: bold; color: #0b1a30;">Phone Number:</td>
        <td style="padding: 10px 8px; border-bottom: 1px solid #eeeeee; color: #333333;"><a href="tel:{$phone}" style="color: #0d6efd; text-decoration: none;">{$phone}</a></td>
      </tr>
      <tr>
        <td style="padding: 10px 8px; border-bottom: 1px solid #eeeeee; font-weight: bold; color: #0b1a30;">Email Address:</td>
        <td style="padding: 10px 8px; border-bottom: 1px solid #eeeeee; color: #333333;"><a href="mailto:{$email}" style="color: #0d6efd; text-decoration: none;">{$email}</a></td>
      </tr>
      <tr>
        <td style="padding: 10px 8px; border-bottom: 1px solid #eeeeee; font-weight: bold; color: #0b1a30;">Service Required:</td>
        <td style="padding: 10px 8px; border-bottom: 1px solid #eeeeee; color: #333333;"><strong>{$service}</strong></td>
      </tr>
      <tr>
        <td style="padding: 10px 8px; border-bottom: 1px solid #eeeeee; font-weight: bold; color: #0b1a30;">Facility Location:</td>
        <td style="padding: 10px 8px; border-bottom: 1px solid #eeeeee; color: #333333;">{$location}</td>
      </tr>
      <tr>
        <td style="padding: 10px 8px; vertical-align: top; font-weight: bold; color: #0b1a30;">Scope &amp; Message:</td>
        <td style="padding: 10px 8px; vertical-align: top; color: #333333; line-height: 1.5;">{$safeMessageHtml}</td>
      </tr>
    </table>

    <div style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #eeeeee; font-size: 12px; color: #777777;">
      <p style="margin: 0 0 4px 0;"><strong>Submitted:</strong> {$timestamp}</p>
      <p style="margin: 0;"><strong>Client IP:</strong> {$clientIp}</p>
    </div>

  </div>
</body>
</html>
HTML;

// 8. Plain Text Fallback
$plainTextBody = <<<TEXT
=====================================================
ROYAL ENAN SECURITY SERVICES - NEW ENQUIRY
=====================================================

Received On: {$timestamp}

Lead Details:
- Name: {$name}
- Company/Society: {$organization}
- Phone: {$phone}
- Email: {$email}
- Service: {$service}
- Location: {$location}

Scope & Requirements:
{$message}

Technical Information:
- Client IP: {$clientIp}
=====================================================
TEXT;

// 9. Send Enquiry Notification to Admin via Reusable Mail Function
$mailResult = sendMail($adminEmail, $subject, $emailBodyHtml, [
    'to_name'       => $adminName,
    'reply_to'      => $email,
    'reply_to_name' => $name,
    'alt_body'      => $plainTextBody,
]);

// 10. Send Confirmation / Acknowledgement Email to the User
if ($mailResult['success'] && !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $userSubject = "Thank You for Contacting Royal Enan Security Services - Request Received";

    $userEmailHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{$userSubject}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; line-height: 1.6; color: #222222; margin: 0; padding: 20px; background-color: #f7f9fc;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #dbe2ea; border-radius: 6px; padding: 25px;">
    
    <h2 style="color: #0b1a30; margin-top: 0; margin-bottom: 8px; font-size: 20px; border-bottom: 2px solid #d4af37; padding-bottom: 10px;">
      Royal Enan Security Services
    </h2>

    <p style="font-size: 15px; color: #111111; margin-top: 15px;">
      Dear <strong>{$name}</strong>,
    </p>

    <p style="font-size: 14px; color: #444444; line-height: 1.6;">
      Thank you for contacting <strong>Royal Enan Security Services</strong>. We have successfully received your security assessment request for <strong>{$organization}</strong>.
    </p>

    <p style="font-size: 14px; color: #444444; line-height: 1.6;">
      Our operations command team is reviewing your requirements. A dedicated security officer will reach out to you shortly at <strong>{$phone}</strong> to discuss your customized security framework.
    </p>

    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 5px; padding: 15px; margin: 20px 0;">
      <h4 style="margin: 0 0 10px 0; color: #0b1a30; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
        Summary of Your Request:
      </h4>
      <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
        <tr>
          <td style="padding: 5px 0; font-weight: bold; width: 40%; color: #555555;">Selected Service:</td>
          <td style="padding: 5px 0; color: #111111;">{$service}</td>
        </tr>
        <tr>
          <td style="padding: 5px 0; font-weight: bold; color: #555555;">Facility Location:</td>
          <td style="padding: 5px 0; color: #111111;">{$location}</td>
        </tr>
        <tr>
          <td style="padding: 5px 0; font-weight: bold; color: #555555;">Company / Society:</td>
          <td style="padding: 5px 0; color: #111111;">{$organization}</td>
        </tr>
      </table>
    </div>

    <p style="font-size: 14px; color: #444444; line-height: 1.6;">
      Need immediate emergency response or urgent guard deployment? You can contact our 24/7 Command Center directly:
    </p>

    <p style="font-size: 14px; margin: 10px 0; line-height: 1.8;">
      &bull; <strong>Helpline:</strong> <a href="tel:8096947362" style="color: #0d6efd; text-decoration: none;">+91 8096947362</a> / <a href="tel:+919100547892" style="color: #0d6efd; text-decoration: none;">+91 91005 47892</a><br>
      &bull; <strong>Email:</strong> <a href="mailto:info@royalenansecurity.com" style="color: #0d6efd; text-decoration: none;">info@royalenansecurity.com</a>
    </p>

    <div style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #eeeeee; font-size: 13px; color: #666666;">
      <p style="margin: 0 0 4px 0;">Warm regards,</p>
      <p style="margin: 0; font-weight: bold; color: #0b1a30;">Royal Enan Security Services Private Limited</p>
      <p style="margin: 0; font-size: 12px; color: #888888;">Professional Security &bull; Facility Management &bull; 24/7 Operations</p>
    </div>

  </div>
</body>
</html>
HTML;

    $userPlainText = <<<TEXT
Dear {$name},

Thank you for contacting Royal Enan Security Services Pvt Ltd. We have received your security assessment request for {$organization}.

Our operations command team is reviewing your requirements. A dedicated security officer will reach out to you shortly at {$phone}.

Summary of Request:
- Service: {$service}
- Facility Location: {$location}
- Company / Society: {$organization}

For urgent queries or immediate guard deployment:
Call: +91 8096947362 / +91 91005 47892
Email: info@royalenansecurity.com

Warm regards,
Royal Enan Security Services Private Limited
TEXT;

    // Send acknowledgement to customer
    sendMail($email, $userSubject, $userEmailHtml, [
        'to_name'       => $name,
        'reply_to'      => getMailConfig('MAIL_FROM_ADDRESS', 'hyderabad@royalenansecurity.com'),
        'reply_to_name' => 'Royal Enan Security Services',
        'alt_body'      => $userPlainText,
    ]);
}

// 11. Return Response
if ($mailResult['success']) {
    if ($isAjax) {
        sendJsonResponse(true, 'Thank you! Your assessment request has been received. Our command team will contact you within 15 minutes.');
    }
    header('Location: contact.html?status=success#contact-form-container');
    exit;
} else {
    // Return friendly message to client without exposing credentials or internal errors
    $userErrorMessage = 'We could not submit your request at this moment due to a temporary mail server issue. Please call our 24/7 command center at +91 8096947362 or try again shortly.';
    
    if ($isAjax) {
        sendJsonResponse(false, $userErrorMessage, ['mail' => $mailResult['error'] ?? 'Mail send error'], 500);
    }
    header('Location: contact.html?status=mail_error#contact-form-container');
    exit;
}

