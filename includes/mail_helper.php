<?php
/**
 * PHPMailer Reusable Email Helper for GoDaddy SMTP
 * 
 * Features:
 * - GoDaddy SMTP configuration (Host: smtpout.secureserver.net, Port: 465, SSL encryption, SMTP Authentication)
 * - Secure credential handling via .env and environment variables
 * - Clean, reusable sendMail() function supporting HTML & plain-text
 * - Detailed file logging in logs/mail.log (passwords are never logged)
 * - Safe error handling preventing sensitive credential exposure
 */

declare(strict_types=1);

// Prevent direct web access
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

// Locate and load Composer autoloader
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    throw new RuntimeException("Composer autoload file not found at: {$autoloadPath}. Run 'composer install' first.");
}
require_once $autoloadPath;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Load environment variables once from .env if vlucas/phpdotenv is available.
 */
function initMailEnvironment(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $projectRoot = dirname(__DIR__);
    if (file_exists($projectRoot . '/.env') && class_exists('Dotenv\Dotenv')) {
        try {
            $dotenv = Dotenv\Dotenv::createImmutable($projectRoot);
            $dotenv->safeLoad();
        } catch (\Throwable $e) {
            error_log('Dotenv initialization error: ' . $e->getMessage());
        }
    }

    $loaded = true;
}

/**
 * Retrieve configuration value from $_ENV, $_SERVER, getenv(), or fallback.
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function getMailConfig(string $key, $default = null)
{
    initMailEnvironment();

    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }

    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return $val;
    }

    return $default;
}

/**
 * Log email events to logs/mail.log.
 *
 * @param string $level 'INFO'|'ERROR'|'WARNING'
 * @param string $message
 * @param array $context
 */
function logMailEvent(string $level, string $message, array $context = []): void
{
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/mail.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';
    $logLine = sprintf("[%s] [%s] %s%s\n", $timestamp, strtoupper($level), $message, $contextStr);

    @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * Create and configure a PHPMailer instance with GoDaddy SMTP settings.
 *
 * @param array $overrides Optional custom configuration overrides
 * @return PHPMailer
 * @throws PHPMailerException
 */
function createMailer(array $overrides = []): PHPMailer
{
    initMailEnvironment();

    $mail = new PHPMailer(true);

    // GoDaddy SMTP Configuration
    $smtpHost   = $overrides['smtp_host'] ?? getMailConfig('SMTP_HOST', 'smtpout.secureserver.net');
    $smtpPort   = (int)($overrides['smtp_port'] ?? getMailConfig('SMTP_PORT', 465));
    $smtpSecure = strtolower((string)($overrides['smtp_secure'] ?? getMailConfig('SMTP_SECURE', 'ssl')));
    $smtpAuth   = filter_var($overrides['smtp_auth'] ?? getMailConfig('SMTP_AUTH', true), FILTER_VALIDATE_BOOLEAN);
    $username   = $overrides['smtp_username'] ?? getMailConfig('SMTP_USERNAME', '');
    $password   = $overrides['smtp_password'] ?? getMailConfig('SMTP_PASSWORD', '');
    $debugLevel = (int)($overrides['mail_debug'] ?? getMailConfig('MAIL_DEBUG', 0));

    // Enable SMTP
    $mail->isSMTP();
    $mail->Host       = $smtpHost;
    $mail->SMTPAuth   = $smtpAuth;
    $mail->Username   = $username;
    $mail->Password   = $password;

    // SSL Encryption on Port 465
    if ($smtpSecure === 'ssl' || $smtpPort === 465) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // 'ssl'
        $mail->Port       = $smtpPort ?: 465;
    } elseif ($smtpSecure === 'tls' || $smtpPort === 587) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // 'tls'
        $mail->Port       = $smtpPort ?: 587;
    } else {
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;
        $mail->Port       = $smtpPort ?: 25;
    }

    // Character set & encoding
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';

    // Connection timeout (seconds)
    $mail->Timeout = 20;

    // Debugging (disabled in production)
    $mail->SMTPDebug = $debugLevel;
    if ($debugLevel > 0) {
        $mail->Debugoutput = function ($str, $level) {
            logMailEvent('DEBUG', "SMTP Debug (level {$level}): " . trim($str));
        };
    }

    return $mail;
}

/**
 * Reusable email sending function.
 *
 * @param string|array $to Single email, array of emails, or ['email' => '...', 'name' => '...']
 * @param string $subject Email subject line
 * @param string $htmlBody HTML email content
 * @param array $options Optional settings:
 *      - 'to_name': string (recipient name if $to is a string)
 *      - 'alt_body': string (plain-text alternative, auto-generated if omitted)
 *      - 'reply_to': string (reply-to email address)
 *      - 'reply_to_name': string (reply-to name)
 *      - 'from_email': string (override from address)
 *      - 'from_name': string (override from name)
 *      - 'cc': string|array
 *      - 'bcc': string|array
 *      - 'attachments': array of file paths or [['path' => '...', 'name' => '...']]
 *      - 'is_html': bool (default true)
 *      - 'debug': int
 * @return array ['success' => bool, 'message' => string, 'error' => ?string]
 */
function sendMail($to, string $subject, string $htmlBody, array $options = []): array
{
    try {
        $mail = createMailer($options);

        // Sender Details (GoDaddy requires From address to match authenticated account)
        $defaultFromEmail = getMailConfig('MAIL_FROM_ADDRESS', getMailConfig('SMTP_USERNAME', ''));
        $defaultFromName  = getMailConfig('MAIL_FROM_NAME', 'Royal Enan Security Services');

        $fromEmail = !empty($options['from_email']) ? $options['from_email'] : $defaultFromEmail;
        $fromName  = !empty($options['from_name']) ? $options['from_name'] : $defaultFromName;

        if (empty($fromEmail)) {
            throw new InvalidArgumentException('Sender email (MAIL_FROM_ADDRESS / SMTP_USERNAME) is not configured.');
        }

        $mail->setFrom($fromEmail, $fromName);

        // Primary Recipients
        $toLog = [];
        if (is_array($to)) {
            if (isset($to['email'])) {
                $name = $to['name'] ?? '';
                $mail->addAddress($to['email'], $name);
                $toLog[] = $to['email'];
            } else {
                foreach ($to as $recipient) {
                    if (is_array($recipient) && isset($recipient['email'])) {
                        $mail->addAddress($recipient['email'], $recipient['name'] ?? '');
                        $toLog[] = $recipient['email'];
                    } elseif (is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                        $mail->addAddress($recipient);
                        $toLog[] = $recipient;
                    }
                }
            }
        } elseif (is_string($to) && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $toName = $options['to_name'] ?? '';
            $mail->addAddress($to, $toName);
            $toLog[] = $to;
        } else {
            throw new InvalidArgumentException('Invalid recipient email address provided.');
        }

        if (empty($toLog)) {
            throw new InvalidArgumentException('No valid recipient email address could be added.');
        }

        // Reply-To Header (crucial for contact enquiries so admin can reply directly)
        if (!empty($options['reply_to'])) {
            $replyToEmail = $options['reply_to'];
            $replyToName  = $options['reply_to_name'] ?? '';
            if (filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyToEmail, $replyToName);
            }
        }

        // CC Recipients
        if (!empty($options['cc'])) {
            $ccs = is_array($options['cc']) ? $options['cc'] : [$options['cc']];
            foreach ($ccs as $cc) {
                if (filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                    $mail->addCC($cc);
                }
            }
        }

        // BCC Recipients
        if (!empty($options['bcc'])) {
            $bccs = is_array($options['bcc']) ? $options['bcc'] : [$options['bcc']];
            foreach ($bccs as $bcc) {
                if (filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
                    $mail->addBCC($bcc);
                }
            }
        }

        // Attachments
        if (!empty($options['attachments']) && is_array($options['attachments'])) {
            foreach ($options['attachments'] as $attachment) {
                if (is_string($attachment) && file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                } elseif (is_array($attachment) && isset($attachment['path']) && file_exists($attachment['path'])) {
                    $mail->addAttachment($attachment['path'], $attachment['name'] ?? '');
                }
            }
        }

        // Content
        $isHtml = $options['is_html'] ?? true;
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;

        // Plain Text Alternative
        if ($isHtml) {
            if (!empty($options['alt_body'])) {
                $mail->AltBody = $options['alt_body'];
            } else {
                // Generate clean plain text from HTML
                $plainText = preg_replace('/<br\s*\/?>/i', "\r\n", $htmlBody);
                $plainText = preg_replace('/<\/p>/i', "\r\n\r\n", $plainText);
                $plainText = strip_tags($plainText);
                $mail->AltBody = trim(html_entity_decode($plainText, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }

        // Send Email
        $mail->send();

        // Log successful delivery
        logMailEvent('INFO', "Email successfully sent to: " . implode(', ', $toLog), [
            'subject' => $subject,
            'reply_to' => $options['reply_to'] ?? null,
        ]);

        return [
            'success' => true,
            'message' => 'Email sent successfully.',
            'error'   => null,
        ];
    } catch (PHPMailerException $e) {
        $errorMessage = $mail->ErrorInfo ?? $e->getMessage();
        logMailEvent('ERROR', "PHPMailer error: {$errorMessage}", [
            'subject' => $subject,
            'to'      => $toLog ?? $to,
        ]);

        return [
            'success' => false,
            'message' => 'Failed to send email due to a mail server error.',
            'error'   => $errorMessage,
        ];
    } catch (\Throwable $e) {
        logMailEvent('ERROR', "General error during mail send: {$e->getMessage()}", [
            'subject' => $subject,
            'to'      => $toLog ?? $to,
        ]);

        return [
            'success' => false,
            'message' => 'Failed to send email due to an internal error.',
            'error'   => $e->getMessage(),
        ];
    }
}

