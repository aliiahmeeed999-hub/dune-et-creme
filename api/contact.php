<?php

declare(strict_types=1);

/**
 * POST /api/contact.php — contact form endpoint (Hostinger SMTP via PHPMailer).
 * Only handles the "Nous Contacter" form. No orders / accounts / newsletter.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Méthode non autorisée. Utilisez POST.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once dirname(__DIR__) . '/lib/mail/transporter.php';
require_once dirname(__DIR__) . '/lib/mail/templates/contact-notification.php';
require_once dirname(__DIR__) . '/lib/mail/templates/contact-confirmation.php';
require_once dirname(__DIR__) . '/lib/validation/contact.php';

use function DuneEtCreme\Mail\env;
use function DuneEtCreme\Mail\sendMail;
use function DuneEtCreme\Mail\Templates\contactConfirmation;
use function DuneEtCreme\Mail\Templates\contactNotification;
use function DuneEtCreme\Validation\validateContact;

try {
    // --- Rate limit: file-backed (PHP requests don't share memory). ---
    // TODO: move to Redis/Upstash if traffic grows.
    $clientIp = clientIp();
    if (!allowRequest($clientIp, 5, 600)) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Trop de messages envoyés. Réessayez dans quelques minutes.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = file_get_contents('php://input');
    $body = [];
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $body = $decoded;
        }
    }
    if ($body === []) {
        // Fallback for classic form posts
        $body = $_POST;
    }

    // Honeypot: real users never fill "website". Bots that do get a fake success.
    $honeypot = trim((string) ($body['website'] ?? ''));
    if ($honeypot !== '') {
        http_response_code(200);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $validation = validateContact($body);
    if (!$validation['success']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Veuillez corriger les champs indiqués.',
            'errors' => $validation['errors'] ?? [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @var array{nomComplet: string, telephone: string, email: string, message: string} $data */
    $data = $validation['data'];

    $to = env('CONTACT_TO_EMAIL', 'contact@duneetcreme.com') ?? 'contact@duneetcreme.com';
    $notification = contactNotification($data);
    $notifyResult = sendMail([
        'to' => $to,
        'subject' => $notification['subject'],
        'html' => $notification['html'],
        'replyTo' => $data['email'],
    ]);

    if (!$notifyResult['success']) {
        $detail = (string) ($notifyResult['error'] ?? '');
        error_log('Contact notification failed: ' . $detail);
        $publicError = 'Impossible d’envoyer votre message pour le moment. Réessayez plus tard.';
        $status = 502;
        if (
            str_contains($detail, 'SMTP_PASSWORD')
            || str_contains($detail, 'SMTP_USER')
            || str_contains($detail, '.env')
            || str_contains($detail, 'PHPMailer introuvable')
        ) {
            $publicError = 'Configuration email incomplète. Ajoutez SMTP_PASSWORD dans le fichier .env, puis réessayez.';
            $status = 503;
        } elseif (
            stripos($detail, 'authenticate') !== false
            || stripos($detail, 'authentication') !== false
        ) {
            $publicError = 'Identifiants email refusés par Hostinger. Vérifiez que la boîte SMTP_USER existe et que SMTP_PASSWORD est le mot de passe de cette boîte (pas le mot de passe du compte Hostinger).';
            $status = 502;
        }
        http_response_code($status);
        echo json_encode([
            'success' => false,
            'error' => $publicError,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Confirmation to the customer is best-effort — notification is the critical part.
    $confirmation = contactConfirmation($data);
    $confirmResult = sendMail([
        'to' => $data['email'],
        'subject' => $confirmation['subject'],
        'html' => $confirmation['html'],
    ]);
    if (!$confirmResult['success']) {
        error_log(
            'Contact confirmation email failed for '
            . $data['email']
            . ': '
            . ($confirmResult['error'] ?? 'unknown')
        );
    }

    http_response_code(200);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    // Never log SMTP_PASSWORD; exception messages from our code don't include it.
    error_log('Contact API error: ' . $e->getMessage());
    $message = 'Une erreur est survenue. Réessayez plus tard.';
    $msg = $e->getMessage();
    if (
        str_contains($msg, 'SMTP_PASSWORD')
        || str_contains($msg, 'SMTP_USER')
        || str_contains($msg, '.env')
        || str_contains($msg, 'PHPMailer introuvable')
    ) {
        $message = 'Configuration email incomplète. Vérifiez le fichier .env (SMTP_PASSWORD) et PHPMailer.';
        http_response_code(503);
    } else {
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'error' => $message,
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Best-effort client IP (behind common proxies).
 */
function clientIp(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];
    foreach ($candidates as $value) {
        if (!is_string($value) || $value === '') {
            continue;
        }
        // X-Forwarded-For may be a list
        $ip = trim(explode(',', $value)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return '0.0.0.0';
}

/**
 * Allow at most $maxRequests from $ip within $windowSeconds.
 * File-backed store under sys temp — replace with Redis/Upstash at scale.
 */
function allowRequest(string $ip, int $maxRequests, int $windowSeconds): bool
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dune-contact-rate';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        // If we can't rate-limit, fail open rather than blocking all mail.
        return true;
    }

    $file = $dir . DIRECTORY_SEPARATOR . hash('sha256', $ip) . '.json';
    $now = time();
    $timestamps = [];

    $fh = fopen($file, 'c+');
    if ($fh === false) {
        return true;
    }

    try {
        flock($fh, LOCK_EX);
        $raw = stream_get_contents($fh);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $timestamps = array_values(array_filter(
                    $decoded,
                    static fn ($t) => is_int($t) && ($now - $t) < $windowSeconds
                ));
            }
        }

        if (count($timestamps) >= $maxRequests) {
            return false;
        }

        $timestamps[] = $now;
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($timestamps));
        fflush($fh);
        return true;
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}
