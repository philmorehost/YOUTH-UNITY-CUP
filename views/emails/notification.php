<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title><?= $subjectHtml ?></title>
    <style>
        @media only screen and (max-width: 620px) {
            .email-shell { width: 100% !important; }
            .email-outer { padding: 12px !important; }
            .email-content { padding: 25px 20px !important; }
            .email-subject { font-size: 26px !important; }
            .email-brand-copy { font-size: 16px !important; }
            .email-kicker { font-size: 9px !important; }
            .email-meta { display: block !important; text-align: left !important; padding-top: 15px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#edf2f5;color:#19324b;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;mso-hide:all;">&#8203;<?= $preheader ?>&#8203;</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;background:#edf2f5;">
        <tr>
            <td class="email-outer" align="center" style="padding:34px 16px;">
                <table class="email-shell" role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;border-collapse:separate;border-spacing:0;overflow:hidden;border-radius:18px;background:#ffffff;box-shadow:0 16px 42px rgba(14,37,61,.12);">
                    <tr>
                        <td height="6" style="height:6px;background:#c7e747;font-size:0;line-height:0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="padding:23px 30px;background:#0a213a;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">
                                <tr>
                                    <td valign="middle" style="width:52px;">
                                        <div style="width:42px;height:42px;border-radius:12px;background:#c7e747;color:#0a213a;font-family:Arial,Helvetica,sans-serif;font-size:24px;font-weight:900;line-height:42px;text-align:center;">Y</div>
                                    </td>
                                    <td valign="middle" style="padding-left:12px;">
                                        <div class="email-brand-copy" style="color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:800;letter-spacing:.04em;line-height:1.1;">YOUTH UNITY CUP</div>
                                        <div style="padding-top:5px;color:#adc1d2;font-family:Arial,Helvetica,sans-serif;font-size:10px;font-weight:700;letter-spacing:.16em;line-height:1.3;">OFFICIAL TOURNAMENT UPDATE</div>
                                    </td>
                                    <td class="email-meta" align="right" valign="middle" style="color:#c7e747;font-family:Arial,Helvetica,sans-serif;font-size:10px;font-weight:800;letter-spacing:.15em;white-space:nowrap;">MATCHDAY DESK</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="height:1px;background:#e9eff4;font-size:0;line-height:0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="email-content" style="padding:36px 40px 30px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 18px;border-collapse:collapse;">
                                <tr>
                                    <td style="padding:7px 10px;border-radius:5px;background:#eef6d5;color:#476117;font-family:Arial,Helvetica,sans-serif;font-size:10px;font-weight:800;letter-spacing:.12em;">TOURNAMENT NOTICE</td>
                                    <td style="padding-left:9px;color:#91a2af;font-family:Arial,Helvetica,sans-serif;font-size:11px;">PLAY · UNITY · COMMUNITY</td>
                                </tr>
                            </table>
                            <h1 class="email-subject" style="margin:0 0 21px;color:#102c48;font-family:Arial,Helvetica,sans-serif;font-size:31px;font-weight:800;letter-spacing:-.035em;line-height:1.13;word-break:break-word;"><?= $subjectHtml ?></h1>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:0 0 24px;border-collapse:collapse;">
                                <tr>
                                    <td width="4" style="width:4px;background:#c7e747;font-size:0;line-height:0;">&nbsp;</td>
                                    <td style="padding:2px 0 1px 17px;">
                                        <?= $bodyHtml ?>
                                    </td>
                                </tr>
                            </table>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">
                                <tr>
                                    <td style="padding:14px 15px;border:1px solid #e4ebf0;border-radius:10px;background:#f7f9fb;color:#627486;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;">
                                        Keep this message for your tournament records. If action is needed, use the official Youth Unity Cup website or contact your tournament administrator.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 30px;background:#f7f9fb;border-top:1px solid #e8eef2;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">
                                <tr>
                                    <td style="color:#64778a;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:1.6;">Youth Unity Cup<br><span style="color:#91a0ad;">Grassroots football · Mushin, Lagos</span></td>
                                    <td align="right" valign="middle" style="color:#91a0ad;font-family:Arial,Helvetica,sans-serif;font-size:10px;">&copy; <?= (int) $year ?> YUC</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <div style="padding:15px 8px 0;color:#91a0ad;font-family:Arial,Helvetica,sans-serif;font-size:10px;line-height:1.5;text-align:center;">This is an automated message from the Youth Unity Cup notification system.</div>
            </td>
        </tr>
    </table>
</body>
</html>
