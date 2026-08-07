<?php

declare(strict_types=1);

namespace DuneEtCreme\Mail;

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Shared Hostinger SMTP helper (PHPMailer).
 * Never logs SMTP_PASSWORD.
 */

function projectRoot(): string
{
    return dirname(__DIR__, 2);
}

function loadEnv(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $envPath = projectRoot() . DIRECTORY_SEPARATOR . '.env';
    if (!is_readable($envPath)) {
        throw new \RuntimeException(
            'Fichier .env introuvable. Copiez .env.example vers .env et renseignez SMTP_PASSWORD.'
        );
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new \RuntimeException('Impossible de lire le fichier .env.');
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        // Do not overwrite real server env vars if already set.
        if (getenv($name) === false) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
        }
    }

    $loaded = true;
}

function env(string $key, ?string $default = null): ?string
{
    loadEnv();
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string) $value;
}

function requireSmtpCredentials(): void
{
    loadEnv();
    $user = env('SMTP_USER');
    $password = env('SMTP_PASSWORD');

    if ($user === null || $user === '') {
        throw new \RuntimeException('SMTP_USER est manquant dans .env.');
    }
    if ($password === null || $password === '') {
        throw new \RuntimeException('SMTP_PASSWORD est manquant dans .env.');
    }
}

function requirePhpMailer(): void
{
    static $booted = false;
    if ($booted) {
        return;
    }

    $root = projectRoot();
    $autoload = $root . '/vendor/autoload.php';
    if (is_readable($autoload)) {
        require_once $autoload;
    } else {
        $base = $root . '/vendor/phpmailer/phpmailer/src';
        foreach (['Exception.php', 'PHPMailer.php', 'SMTP.php'] as $file) {
            $path = $base . '/' . $file;
            if (!is_readable($path)) {
                throw new \RuntimeException(
                    'PHPMailer introuvable. Exécutez: composer install --no-dev'
                );
            }
            require_once $path;
        }
    }

    $booted = true;
}

/**
 * Build a configured PHPMailer instance from SMTP_* env vars.
 */
function createTransporter(): PHPMailer
{
    requireSmtpCredentials();
    requirePhpMailer();

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = env('SMTP_HOST', 'smtp.hostinger.com') ?? 'smtp.hostinger.com';
    $mail->SMTPAuth = true;
    $mail->Username = env('SMTP_USER') ?? '';
    $mail->Password = env('SMTP_PASSWORD') ?? '';
    $mail->Port = (int) (env('SMTP_PORT', '465') ?? '465');

    $secure = strtolower((string) (env('SMTP_SECURE', 'true') ?? 'true'));
    $mail->SMTPSecure = in_array($secure, ['true', '1', 'ssl'], true)
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;

    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);

    return $mail;
}

/**
 * Verify SMTP credentials / connectivity (equivalent to Nodemailer verify()).
 *
 * @return array{success: bool, error?: string}
 */
function verifyConnection(): array
{
    try {
        $mail = createTransporter();
        $connected = $mail->smtpConnect();
        if ($connected) {
            $mail->smtpClose();
        }
        if (!$connected) {
            return [
                'success' => false,
                'error' => 'Connexion SMTP refusée.',
            ];
        }
        return ['success' => true];
    } catch (MailException | \Throwable $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Send an HTML email. Never throws; never logs the password.
 *
 * @param array{
 *   to: string,
 *   subject: string,
 *   html: string,
 *   from?: string|null,
 *   replyTo?: string|null
 * } $options
 *
 * @return array{success: bool, messageId?: string, error?: string}
 */
function sendMail(array $options): array
{
    try {
        $to = trim((string) ($options['to'] ?? ''));
        $subject = (string) ($options['subject'] ?? '');
        $html = (string) ($options['html'] ?? '');

        if ($to === '' || $subject === '' || $html === '') {
            return [
                'success' => false,
                'error' => 'Paramètres to, subject et html requis.',
            ];
        }

        $mail = createTransporter();

        $fromRaw = $options['from'] ?? env('EMAIL_FROM', 'Dune et Crème <hello@duneetcreme.com>');
        [$fromEmail, $fromName] = parseFromAddress((string) $fromRaw);
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);

        $replyTo = $options['replyTo'] ?? null;
        if (is_string($replyTo) && $replyTo !== '') {
            $mail->addReplyTo($replyTo);
        }

        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        $mail->send();

        $messageId = $mail->getLastMessageID();
        $result = ['success' => true];
        if (is_string($messageId) && $messageId !== '') {
            $result['messageId'] = $messageId;
        }
        return $result;
    } catch (MailException | \Throwable $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Parse EMAIL_FROM like: Dune et Crème <hello@duneetcreme.com>
 *
 * @return array{0: string, 1: string} [email, name]
 */
function parseFromAddress(string $from): array
{
    if (preg_match('/^(.*)<([^>]+)>$/u', trim($from), $m)) {
        return [trim($m[2]), trim($m[1], " \t\"'")];
    }
    return [trim($from), 'Dune et Crème'];
}
