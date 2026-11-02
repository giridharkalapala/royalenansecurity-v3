<?php
/**
 * GoDaddy SMTP Configuration Tester
 * 
 * Diagnostic utility to test GoDaddy SMTP connectivity, SSL encryption, authentication,
 * and PHPMailer configuration.
 * 
 * Usage:
 * - From Browser: http://localhost/test-smtp.php (or your local web server)
 * - From CLI: php test-smtp.php
 * 
 * SECURITY NOTE: Delete or restrict this file before deploying to a public production server.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/mail_helper.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

$isCli = (php_sapi_name() === 'cli');

// Basic security check: if accessed over the web, only allow localhost unless authorized
if (!$isCli) {
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $allowedIps = ['127.0.0.1', '::1', 'localhost'];
    // Uncomment the check below if you want strict localhost-only protection in production:
    // if (!in_array($remoteIp, $allowedIps, true)) {
    //     http_response_code(403);
    //     exit('Forbidden: SMTP tester is restricted to local development.');
    // }
}

$host     = getMailConfig('SMTP_HOST', 'smtpout.secureserver.net');
$port     = (int)getMailConfig('SMTP_PORT', 465);
$secure   = strtolower((string)getMailConfig('SMTP_SECURE', 'ssl'));
$auth     = filter_var(getMailConfig('SMTP_AUTH', true), FILTER_VALIDATE_BOOLEAN);
$username = getMailConfig('SMTP_USERNAME', '');
$password = getMailConfig('SMTP_PASSWORD', '');
$from     = getMailConfig('MAIL_FROM_ADDRESS', $username);
$fromName = getMailConfig('MAIL_FROM_NAME', 'Royal Enan Security Tester');
$toEmail  = getMailConfig('MAIL_TO_ADDRESS', $username);

$testSubmitted = false;
$testResult    = null;
$debugOutput   = [];

if ($isCli || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_test']))) {
    $testSubmitted = true;
    
    if (!$isCli && !empty($_POST['test_recipient'])) {
        $toEmail = trim($_POST['test_recipient']);
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = $auth;
        $mail->Username   = $username;
        $mail->Password   = $password;

        if ($secure === 'ssl' || $port === 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = $port ?: 465;
        } elseif ($secure === 'tls' || $port === 587) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $port ?: 587;
        } else {
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
            $mail->Port       = $port ?: 25;
        }

        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 15;

        // Capture verbose SMTP debug output
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function ($str, $level) use (&$debugOutput, $isCli) {
            $line = trim($str);
            $debugOutput[] = $line;
            if ($isCli) {
                echo "[DEBUG {$level}] {$line}\n";
            }
        };

        $mail->setFrom($from, $fromName);
        $mail->addAddress($toEmail, 'SMTP Tester');
        $mail->Subject = 'GoDaddy SMTP Test - ' . date('Y-m-d H:i:s');
        $mail->isHTML(true);
        $mail->Body = '<h2>GoDaddy SMTP Test Successful!</h2>'
                    . '<p>Your PHPMailer configuration with GoDaddy SMTP (SSL, Port 465) is working properly.</p>'
                    . '<p><strong>Host:</strong> ' . htmlspecialchars($host) . '<br>'
                    . '<strong>Port:</strong> ' . $port . '<br>'
                    . '<strong>Security:</strong> ' . htmlspecialchars($secure) . '<br>'
                    . '<strong>Timestamp:</strong> ' . date('r') . '</p>';
        $mail->AltBody = "GoDaddy SMTP Test Successful!\n\nHost: {$host}\nPort: {$port}\nSecurity: {$secure}\nTimestamp: " . date('r');

        $mail->send();
        $testResult = [
            'success' => true,
            'message' => "Success! Test email was successfully dispatched to {$toEmail}.",
        ];
    } catch (\Throwable $e) {
        $testResult = [
            'success' => false,
            'message' => 'SMTP Test Failed: ' . $e->getMessage(),
            'errorInfo' => $mail->ErrorInfo ?? '',
        ];
    }
}

// -------------------------------------------------------------
// CLI Output
// -------------------------------------------------------------
if ($isCli) {
    echo "====================================================\n";
    echo " GoDaddy SMTP Tester (CLI Mode)\n";
    echo "====================================================\n";
    echo "Host:     {$host}\n";
    echo "Port:     {$port}\n";
    echo "Security: {$secure}\n";
    echo "Auth:     " . ($auth ? 'Yes' : 'No') . "\n";
    echo "Username: {$username}\n";
    echo "Password: " . (empty($password) ? '[EMPTY]' : str_repeat('*', min(8, strlen($password)))) . "\n";
    echo "From:     {$from}\n";
    echo "To:       {$toEmail}\n";
    echo "----------------------------------------------------\n";

    if ($testResult) {
        if ($testResult['success']) {
            echo "[SUCCESS] " . $testResult['message'] . "\n";
        } else {
            echo "[FAILED] " . $testResult['message'] . "\n";
            if (!empty($testResult['errorInfo'])) {
                echo "ErrorInfo: " . $testResult['errorInfo'] . "\n";
            }
        }
    }
    echo "====================================================\n";
    exit($testResult && $testResult['success'] ? 0 : 1);
}

// -------------------------------------------------------------
// HTML Web Output
// -------------------------------------------------------------
$opensslLoaded = extension_loaded('openssl');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>GoDaddy SMTP Configuration Diagnostic</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <style>
    body { background-color: #0b1a30; color: #e2e8f0; font-family: 'Segoe UI', system-ui, sans-serif; }
    .card-custom { background-color: #10233f; border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 10px; }
    .card-header-gold { background: linear-gradient(135deg, #10233f 0%, #173259 100%); border-bottom: 2px solid #d4af37; }
    .text-gold { color: #d4af37 !important; }
    .btn-gold { background-color: #d4af37; color: #0b1a30; font-weight: 700; border: none; }
    .btn-gold:hover { background-color: #e5c358; color: #0b1a30; }
    .debug-box { background-color: #050c17; border: 1px solid #1e3a63; font-family: monospace; font-size: 13px; max-height: 350px; overflow-y: auto; color: #94a3b8; }
    .badge-status { font-size: 13px; padding: 6px 12px; }
  </style>
</head>
<body class="py-5">
  <div class="container" style="max-width: 820px;">
    
    <div class="card card-custom shadow-lg">
      <div class="card-header card-header-gold py-3 px-4 d-flex justify-content-between align-items-center">
        <div>
          <h4 class="mb-0 text-white"><i class="fa-solid fa-shield-halved text-gold me-2"></i> GoDaddy SMTP Diagnostic Tool</h4>
          <small class="text-white-50">Royal Enan Security PHPMailer Configuration Check</small>
        </div>
        <span class="badge bg-dark border border-warning text-gold badge-status">Port 465 &bull; SSL</span>
      </div>

      <div class="card-body p-4">

        <!-- Environment & Extension Status -->
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <div class="p-3 rounded bg-dark border border-secondary">
              <div class="d-flex justify-content-between align-items-center">
                <span>PHP OpenSSL Extension</span>
                <?php if ($opensslLoaded): ?>
                  <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> Enabled</span>
                <?php else: ?>
                  <span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i> Missing</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="p-3 rounded bg-dark border border-secondary">
              <div class="d-flex justify-content-between align-items-center">
                <span>.env File Detected</span>
                <?php if (file_exists(__DIR__ . '/.env')): ?>
                  <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> Loaded</span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark"><i class="fa-solid fa-triangle-exclamation me-1"></i> Missing .env</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Current Configuration Table -->
        <h5 class="text-gold mb-3"><i class="fa-solid fa-sliders me-2"></i> Active SMTP Settings</h5>
        <div class="table-responsive mb-4">
          <table class="table table-dark table-striped table-bordered mb-0">
            <tbody>
              <tr>
                <td width="35%"><strong>SMTP Host</strong></td>
                <td><code><?= htmlspecialchars($host) ?></code></td>
              </tr>
              <tr>
                <td><strong>SMTP Port</strong></td>
                <td><code><?= $port ?></code> <?= ($port === 465) ? '<span class="text-success ms-2">(SSL Standard)</span>' : '' ?></td>
              </tr>
              <tr>
                <td><strong>Encryption</strong></td>
                <td><code><?= htmlspecialchars(strtoupper($secure)) ?></code></td>
              </tr>
              <tr>
                <td><strong>SMTP Authentication</strong></td>
                <td><?= $auth ? '<span class="text-success">Enabled (true)</span>' : '<span class="text-danger">Disabled</span>' ?></td>
              </tr>
              <tr>
                <td><strong>SMTP Username</strong></td>
                <td><code><?= htmlspecialchars($username ?: '[Not Configured]') ?></code></td>
              </tr>
              <tr>
                <td><strong>SMTP Password</strong></td>
                <td><?= empty($password) ? '<span class="text-danger">[Not Set in .env]</span>' : '<span class="text-success">******** (Configured)</span>' ?></td>
              </tr>
              <tr>
                <td><strong>From Address</strong></td>
                <td><code><?= htmlspecialchars($from ?: '[Not Configured]') ?></code></td>
              </tr>
              <tr>
                <td><strong>Admin Destination</strong></td>
                <td><code><?= htmlspecialchars($toEmail ?: '[Not Configured]') ?></code></td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Test Trigger Form -->
        <div class="p-3 rounded bg-dark border border-warning mb-4">
          <form method="POST">
            <h5 class="text-gold mb-2"><i class="fa-solid fa-paper-plane me-2"></i> Send Live Test Email</h5>
            <p class="text-white-50 small mb-3">
              Enter a destination email address to verify that GoDaddy allows SMTP handshake, authentication, and delivery.
            </p>
            <div class="input-group mb-3">
              <span class="input-group-text bg-secondary text-white border-0"><i class="fa-solid fa-envelope"></i></span>
              <input type="email" name="test_recipient" class="form-control" value="<?= htmlspecialchars($toEmail) ?>" placeholder="recipient@example.com" required>
              <button type="submit" name="run_test" class="btn btn-gold px-4">
                <i class="fa-solid fa-play me-2"></i> Run Test
              </button>
            </div>
          </form>
        </div>

        <!-- Test Results Alert -->
        <?php if ($testSubmitted && $testResult): ?>
          <?php if ($testResult['success']): ?>
            <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
              <i class="fa-solid fa-circle-check fs-3 me-3"></i>
              <div>
                <h5 class="alert-heading mb-1">Success!</h5>
                <p class="mb-0"><?= htmlspecialchars($testResult['message']) ?></p>
              </div>
            </div>
          <?php else: ?>
            <div class="alert alert-danger mb-4" role="alert">
              <div class="d-flex align-items-center mb-2">
                <i class="fa-solid fa-circle-xmark fs-3 me-3"></i>
                <h5 class="alert-heading mb-0">Connection or Authentication Failed</h5>
              </div>
              <p class="mb-1"><?= htmlspecialchars($testResult['message']) ?></p>
              <?php if (!empty($testResult['errorInfo'])): ?>
                <div class="small mt-2 p-2 rounded bg-black text-warning">
                  <strong>ErrorInfo:</strong> <?= htmlspecialchars($testResult['errorInfo']) ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <!-- Debug Transcript -->
        <?php if (!empty($debugOutput)): ?>
          <h5 class="text-gold mb-2"><i class="fa-solid fa-terminal me-2"></i> Verbose SMTP Debug Log</h5>
          <div class="p-3 rounded debug-box">
            <?php foreach ($debugOutput as $line): ?>
              <div><?= htmlspecialchars($line) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- GoDaddy Troubleshooting Tips -->
        <div class="mt-4 pt-3 border-top border-secondary">
          <h6 class="text-gold mb-2"><i class="fa-solid fa-circle-info me-2"></i> GoDaddy SMTP Troubleshooting Guide:</h6>
          <ul class="text-white-50 small mb-0 ps-3">
            <li><strong>Port 465 with SSL:</strong> GoDaddy requires Port 465 with SSL encryption for standard cPanel mail. Ensure your firewall allows outbound TCP on 465.</li>
            <li><strong>From Address Match:</strong> GoDaddy will reject emails if <code>MAIL_FROM_ADDRESS</code> does not match the authenticated <code>SMTP_USERNAME</code>.</li>
            <li><strong>Microsoft 365 on GoDaddy:</strong> If your GoDaddy email uses Microsoft 365, change host to <code>smtp.office365.com</code> and port to <code>587</code> with TLS, or generate an App Password in your Microsoft Account.</li>
            <li><strong>Localhost Port Blocking:</strong> Some residential ISPs (Airtel, Jio, Comcast, etc.) block outbound ports 25 and 465 on local machines. Once uploaded to your GoDaddy hosting, it will connect directly over the internal network.</li>
          </ul>
        </div>

      </div>
    </div>

  </div>
</body>
</html>

