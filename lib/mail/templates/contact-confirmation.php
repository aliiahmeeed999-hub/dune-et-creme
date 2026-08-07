<?php

declare(strict_types=1);

namespace DuneEtCreme\Mail\Templates;

require_once __DIR__ . '/layout.php';

/**
 * Auto-reply confirmation sent to the customer after contact form submission.
 *
 * @param array{nomComplet: string} $data
 * @return array{subject: string, html: string}
 */
function contactConfirmation(array $data): array
{
    $nom = e($data['nomComplet'] ?? '');
    $greeting = $nom !== '' ? "Bonjour {$nom}," : 'Bonjour,';

    $subject = 'Nous avons bien reçu votre message — Dune et Crème';

    $html = wrapEmail(<<<HTML
      <h1 style="margin:0 0 12px;font-size:22px;line-height:1.3;color:#3b2317;font-family:Georgia,'Times New Roman',serif;">
        Merci pour votre message
      </h1>
      <p style="margin:0 0 14px;font-size:15px;line-height:1.7;color:#5c4638;font-family:Arial,Helvetica,sans-serif;">
        {$greeting}
      </p>
      <p style="margin:0 0 14px;font-size:15px;line-height:1.7;color:#5c4638;font-family:Arial,Helvetica,sans-serif;">
        Nous avons bien reçu votre message et nous vous répondrons dans les plus brefs délais.
      </p>
      <p style="margin:0 0 22px;font-size:15px;line-height:1.7;color:#5c4638;font-family:Arial,Helvetica,sans-serif;">
        Chez Dune &amp; Crème, chaque création est pensée pour les moments qui comptent —
        nous avons hâte d’échanger avec vous.
      </p>
      <p style="margin:0;font-size:15px;line-height:1.7;color:#3b2317;font-family:Arial,Helvetica,sans-serif;">
        À très bientôt,<br>
        <strong style="color:#d99a4e;">L’équipe Dune &amp; Crème</strong>
      </p>
    HTML);

    return ['subject' => $subject, 'html' => $html];
}
