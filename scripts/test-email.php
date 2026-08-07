<?php

declare(strict_types=1);

/**
 * SMTP end-to-end test for Dune et Crème contact mailer.
 *
 * Usage (from project root):
 *   php scripts/test-email.php me@gmail.com
 *
 * Never prints SMTP_PASSWORD.
 */

$to = $argv[1] ?? null;
if (!is_string($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/test-email.php me@example.com\n");
    exit(1);
}

require_once dirname(__DIR__) . '/lib/mail/transporter.php';
require_once dirname(__DIR__) . '/lib/mail/templates/contact-confirmation.php';

use function DuneEtCreme\Mail\sendMail;
use function DuneEtCreme\Mail\verifyConnection;
use function DuneEtCreme\Mail\Templates\contactConfirmation;

echo "1) Vérification de la connexion SMTP…\n";
$verify = verifyConnection();
if (!$verify['success']) {
    fwrite(STDERR, "Échec SMTP: " . ($verify['error'] ?? 'inconnu') . "\n");
    exit(1);
}
echo "   OK — connexion SMTP réussie.\n";

echo "2) Envoi d’un email de confirmation test à {$to}…\n";
$tpl = contactConfirmation(['nomComplet' => 'Test']);
$result = sendMail([
    'to' => $to,
    'subject' => '[TEST] ' . $tpl['subject'],
    'html' => $tpl['html'],
]);

if (!$result['success']) {
    fwrite(STDERR, "Échec envoi: " . ($result['error'] ?? 'inconnu') . "\n");
    exit(1);
}

echo "   OK — email envoyé";
if (!empty($result['messageId'])) {
    echo " (messageId: {$result['messageId']})";
}
echo ".\n";
echo "Terminé. Vérifiez la boîte de réception (et les spams).\n";
exit(0);
