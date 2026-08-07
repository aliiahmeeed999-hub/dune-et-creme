<?php

declare(strict_types=1);

namespace DuneEtCreme\Mail\Templates;

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function wrapEmail(string $body): string
{
    return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f5efe6;">
  <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f5efe6;padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e8dfd2;">
          <tr>
            <td style="background:#3b2317;padding:22px 28px;text-align:center;">
              <div style="font-family:Georgia,'Times New Roman',serif;font-size:22px;letter-spacing:0.12em;color:#f5efe6;text-transform:uppercase;">
                Dune &amp; Crème
              </div>
              <div style="margin-top:6px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#d99a4e;letter-spacing:0.04em;">
                Des créations gourmandes pour les moments qui comptent
              </div>
            </td>
          </tr>
          <tr>
            <td style="padding:28px;">
              {$body}
            </td>
          </tr>
          <tr>
            <td style="padding:16px 28px 24px;border-top:1px solid #e8dfd2;text-align:center;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#8a7464;">
              Galeries Indigo, Rabat · contact@duneetcreme.com
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}
