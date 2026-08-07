<?php

declare(strict_types=1);

namespace DuneEtCreme\Mail\Templates;

require_once __DIR__ . '/layout.php';

/**
 * Notification email sent to CONTACT_TO_EMAIL when the contact form is submitted.
 *
 * @param array{nomComplet: string, telephone: string, email: string, message: string} $data
 * @return array{subject: string, html: string}
 */
function contactNotification(array $data): array
{
    $nom = e($data['nomComplet'] ?? '');
    $tel = e($data['telephone'] ?? '');
    $email = e($data['email'] ?? '');
    $message = nl2br(e($data['message'] ?? ''));

    $subject = 'Nouveau message de contact — ' . ($data['nomComplet'] ?? 'Visiteur');

    $html = wrapEmail(<<<HTML
      <h1 style="margin:0 0 8px;font-size:22px;line-height:1.3;color:#3b2317;font-family:Georgia,'Times New Roman',serif;">
        Nouveau message de contact
      </h1>
      <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#5c4638;font-family:Arial,Helvetica,sans-serif;">
        Un visiteur vous a écrit depuis le site Dune &amp; Crème.
      </p>
      <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;margin:0 0 20px;">
        <tr>
          <td style="padding:10px 0;border-bottom:1px solid #e8dfd2;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#8a7464;width:120px;vertical-align:top;">Nom</td>
          <td style="padding:10px 0;border-bottom:1px solid #e8dfd2;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#3b2317;vertical-align:top;"><strong>{$nom}</strong></td>
        </tr>
        <tr>
          <td style="padding:10px 0;border-bottom:1px solid #e8dfd2;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#8a7464;vertical-align:top;">Téléphone</td>
          <td style="padding:10px 0;border-bottom:1px solid #e8dfd2;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#3b2317;vertical-align:top;">{$tel}</td>
        </tr>
        <tr>
          <td style="padding:10px 0;border-bottom:1px solid #e8dfd2;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#8a7464;vertical-align:top;">Email</td>
          <td style="padding:10px 0;border-bottom:1px solid #e8dfd2;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#3b2317;vertical-align:top;">
            <a href="mailto:{$email}" style="color:#d99a4e;text-decoration:none;">{$email}</a>
          </td>
        </tr>
      </table>
      <p style="margin:0 0 8px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#8a7464;text-transform:uppercase;letter-spacing:0.06em;">Message</p>
      <div style="padding:16px 18px;background:#fffdf9;border-left:3px solid #d99a4e;border-radius:4px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.7;color:#3b2317;">
        {$message}
      </div>
    HTML);

    return ['subject' => $subject, 'html' => $html];
}
