<?php
// sendmail.php

// 1. Load .env
function loadEnv($path) {
    if (!file_exists($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}
loadEnv(__DIR__ . '/.env');

// 2. PHPMailer bootstrap
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

// 3. Credentials
$G_USER = $_ENV['GMAIL_USER'] ?? '';
$G_PASS = $_ENV['GMAIL_PASS'] ?? '';
$G_NAME = $_ENV['GMAIL_NAME'] ?? 'Website';

// 4. Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host       = 'smtp.gmail.com';
$mail->SMTPAuth   = true;
$mail->Username   = $G_USER;
$mail->Password   = $G_PASS;
$mail->SMTPSecure = 'tls';
$mail->Port       = 587;
$mail->setFrom($G_USER, $G_NAME);
$mail->isHTML(true);

try {
    // —— Quote Request Form ——
    if (isset($_POST['Service'])) {
        // grab fields
        $name    = trim($_POST['Name'] ?? '');
        $email   = trim($_POST['Email'] ?? '');
        $mobile  = trim($_POST['Mobile'] ?? '');
        $service = trim($_POST['Service'] ?? '');
        $info    = trim($_POST['AdditionalInfo'] ?? '');

        // validation
        if (!$name || !$email || !$mobile || !$service) {
            throw new Exception('Please fill in all required fields.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address.');
        }
        if (!preg_match('/^[0-9\+\-\s]{7,15}$/', $mobile)) {
            throw new Exception('Invalid mobile number.');
        }

        // admin email
        $subject = "🚛 New Quote Request from $name";
        $htmlBody = "
            <div style='font-family:Arial,sans-serif;color:#333;'>
              <h2 style='color:#2c3e50;'>🚛 New Service Request</h2>
              <table style='width:100%;max-width:600px;border:1px solid #ddd;border-collapse:collapse;'>
                <tr style='background:#f4f4f4;'><th style='padding:12px'>Name</th><td style='padding:12px'>$name</td></tr>
                <tr><th style='padding:12px'>Email</th><td style='padding:12px'><a href=\"mailto:$email\">$email</a></td></tr>
                <tr style='background:#f4f4f4;'><th style='padding:12px'>Mobile</th><td style='padding:12px'>$mobile</td></tr>
                <tr><th style='padding:12px'>Service</th><td style='padding:12px'>$service</td></tr>
                <tr style='background:#f4f4f4;'><th style='padding:12px'>Additional Info</th><td style='padding:12px'>$info</td></tr>
              </table>
              <p style='margin-top:20px'>📬 Please respond promptly.</p>
            </div>";
        $altBody = "Quote Request from $name ($email)\nMobile: $mobile\nService: $service\nInfo: $info";

        // send to admin
        $mail->addAddress($G_USER);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody;
        $mail->send();
        $mail->clearAddresses();

        // confirmation to user
        $mail->addAddress($email, $name);
        $mail->Subject = "Thank you for your request, $name!";
        $mail->Body    = "
            <div style='font-family:Arial,sans-serif;color:#333;'>
              <h2 style='color:#27ae60;'>🎉 Thank You, $name!</h2>
              <p>We’ve received your request for <strong>$service</strong> and will get back to you soon.</p>
              <ul>
                <li><strong>Email:</strong> $email</li>
                <li><strong>Mobile:</strong> $mobile</li>
                <li><strong>Service:</strong> $service</li>
                <li><strong>Additional Info:</strong> $info</li>
              </ul>
              <p>👋 Regards,<br><strong>$G_NAME</strong></p>
            </div>";
        $mail->AltBody = "Hi $name,\nThanks for your request for $service.";
        $mail->send();

    // —— Contact Message Form ——
    } elseif (isset($_POST['Subject'])) {
        $name    = trim($_POST['Name'] ?? '');
        $email   = trim($_POST['Email'] ?? '');
        $subj    = trim($_POST['Subject'] ?? 'No Subject');
        $msg     = trim($_POST['Message'] ?? '');

        if (!$name || !$email || !$msg) {
            throw new Exception('Please fill in all required fields.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address.');
        }

        // admin email
        $subject = "Website Contact: $subj";
        $htmlBody = "
            <div style='font-family:Arial,sans-serif;color:#333;'>
              <h2>📬 New Contact Message</h2>
              <p><strong>From:</strong> $name &lt;$email&gt;<br>
                 <strong>Subject:</strong> $subj</p>
              <p><strong>Message:</strong><br>$msg</p>
            </div>";
        $altBody = "Message from $name ($email)\nSubject: $subj\n\n$msg";

        // send to admin
        $mail->addAddress($G_USER);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody;
        $mail->send();
        $mail->clearAddresses();

        // confirmation to user
        $mail->addAddress($email, $name);
        $mail->Subject = "Thank you for contacting us";
        $mail->Body    = "<p>🎉  Hi $name,</p><p>We received your message and will reply shortly.</p> 
        <p>👋 Regards,<br><strong>$G_NAME</strong></p>";
        $mail->AltBody = "Thanks for reaching out. We'll be in touch soon.";
        $mail->send();

    } else {
        throw new Exception('Unknown form submitted.');
    }

    // success → alert + redirect
    echo "<script>
            alert('Form submitted successfully.');
            window.location.href = 'thank-you.html';
          </script>";

} catch (Exception $e) {
    // error → alert + back
    $msg = addslashes($e->getMessage());
    echo "<script>
            alert('Error: {$msg}');
            window.history.back();
          </script>";
}
