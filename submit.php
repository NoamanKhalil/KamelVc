<?php
header('Content-Type: application/json');

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

function san(string $v): string {
    return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8');
}

$first_name = san($_POST['first_name'] ?? '');
$last_name  = san($_POST['last_name']  ?? '');
$email      = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$role       = san($_POST['role']       ?? '');

if (!$first_name || !$last_name || !$email) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

$allowed_roles = ['investor', 'founder', 'mentor', 'other', ''];
if (!in_array($role, $allowed_roles, true)) {
    $role = '';
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    $check = $pdo->prepare('SELECT id FROM signups WHERE email = ? LIMIT 1');
    $check->execute([$email]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You\'re already signed up — we\'ll be in touch!']);
        exit;
    }

    $pdo->prepare('INSERT INTO signups (first_name, last_name, email, role, ip_address) VALUES (?, ?, ?, ?, ?)')
        ->execute([$first_name, $last_name, $email, $role, $_SERVER['REMOTE_ADDR'] ?? '']);

    send_confirmation($email, $first_name);

    echo json_encode(['success' => true, 'first_name' => $first_name]);

} catch (PDOException $e) {
    error_log('Kamel Ventures DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
}

// ── Email ────────────────────────────────────────────────────────────────────

function send_confirmation(string $to_email, string $first_name): void {
    $from      = MAIL_FROM;
    $from_name = MAIL_FROM_NAME;
    $subject   = 'You\'re on the list — Kamel Ventures';

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Welcome to Kamel Ventures</title>
</head>
<body style="margin:0;padding:0;background:#f9fafb;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;padding:40px 16px;">
    <tr><td align="center">
      <table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">

        <!-- Header -->
        <tr>
          <td align="center" style="padding:40px 40px 28px;">
            <img src="https://kamelventures.org/camel.png" alt="Kamel Ventures" width="160" style="width:160px;height:auto;display:block;margin:0 auto 20px;"/>
            <p style="margin:0;font-size:13px;color:#9ca3af;letter-spacing:.5px;text-transform:uppercase;font-weight:500;">Kamel Ventures</p>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:0 40px 36px;">
            <h1 style="margin:0 0 16px;font-size:24px;font-weight:600;color:#111;line-height:1.3;">
              Thanks for signing up, {$first_name}!
            </h1>
            <p style="margin:0 0 16px;font-size:15px;color:#374151;line-height:1.65;">
              You're now on the Kamel Ventures early-access list. We're building a non-profit VC fund where <strong>all money goes into an endowment</strong> to create a lasting system of change for responsible startups.
            </p>
            <p style="margin:0 0 28px;font-size:15px;color:#374151;line-height:1.65;">
              We'll be in touch as we approach our launch in <strong>January 2027</strong> (pending non-profit status). Until then, feel free to reply to this email with any questions.
            </p>

            <!-- Divider -->
            <table width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #f3f4f6;margin-bottom:24px;"><tr><td></td></tr></table>

            <p style="margin:0;font-size:13px;color:#9ca3af;line-height:1.6;">
              The Kamel Ventures Team<br/>
              <a href="mailto:{$from}" style="color:#9ca3af;text-decoration:none;">{$from}</a>
            </p>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f9fafb;padding:20px 40px;border-top:1px solid #f3f4f6;">
            <p style="margin:0;font-size:12px;color:#d1d5db;text-align:center;line-height:1.5;">
              You signed up at kamelventures.org &middot; Non-profit &middot; Launching January 2027
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    $plain = "Hi {$first_name},\n\nThanks for signing up for Kamel Ventures!\n\n"
           . "You're on our early-access list. We're building a non-profit VC fund where all money goes into an endowment to create a lasting system of change for responsible startups.\n\n"
           . "We'll be in touch as we approach our launch in January 2027 (pending non-profit status).\n\n"
           . "Feel free to reply with any questions.\n\n"
           . "— The Kamel Ventures Team\n{$from}";

    $boundary = md5(uniqid((string) rand(), true));

    $headers  = "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= $plain . "\r\n\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $html . "\r\n\r\n";
    $body .= "--{$boundary}--";

    mail($to_email, $subject, $body, $headers);
}
