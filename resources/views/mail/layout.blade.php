<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Müller Holding AG')</title>
</head>
<body style="margin: 0; padding: 0; background: #FBF6EC; font-family: Calibri, 'Segoe UI', Arial, sans-serif; color: #2E2D2E;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background: #FFFFFF; border: 1px solid #DDDBD6; border-radius: 8px;">
                    <tr>
                        <td style="padding: 28px 32px 8px;">
                            <div style="font-size: 18px; font-weight: bold;">Müller Holding AG</div>
                            <div style="width: 48px; height: 3px; background: #E3AC48; margin-top: 6px;"></div>
                            @hasSection('subtitle')
                                <div style="font-size: 11px; letter-spacing: .09em; text-transform: uppercase; color: #9F9F9F; margin-top: 10px;">
                                    @yield('subtitle')
                                </div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 32px;">
                            @yield('content')
                            <p style="margin: 22px 0 0;">Mit freundlichen Grüßen<br>Müller Holding AG</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 16px 32px; background: #2E2D2E; color: #CFCECC; font-size: 11px; border-radius: 0 0 8px 8px; line-height: 1.7;">
                            Müller Holding AG · Rheinpromenade 13 · 40789 Monheim am Rhein · kontakt@mueller-holding.ag · mueller-holding.ag<br>
                            Sitz: Monheim am Rhein · Registergericht: Amtsgericht Düsseldorf · HRB 104291 · Vorstand: Timo Müller · Aufsichtsratsvorsitzender: Jan Walprecht
                        </td>
                    </tr>
                </table>
                <div style="width: 560px; max-width: 100%; margin-top: 12px; font-size: 11px; color: #9F9F9F; text-align: center;">
                    Diese Nachricht wurde automatisch vom Intranet der Müller Holding AG erzeugt.
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
