<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Einladung zum Intranet der Müller Holding AG</title>
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
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 32px;">
                            <p style="margin: 0 0 12px;">Guten Tag,</p>
                            <p style="margin: 0 0 12px;">
                                Sie wurden zum Intranet der Müller Holding AG eingeladen.
                                @if (! empty($roles))
                                    Ihnen {{ count($roles) === 1 ? 'ist folgende Rolle' : 'sind folgende Rollen' }} zugeordnet:
                                    <strong>{{ implode(', ', $roles) }}</strong>.
                                @endif
                            </p>
                            <p style="margin: 0 0 20px;">
                                Über die folgende Schaltfläche legen Sie Ihr persönliches Passwort fest und aktivieren Ihr Konto:
                            </p>
                            <p style="margin: 0 0 20px;" align="center">
                                <a href="{{ $url }}"
                                   style="display: inline-block; background: #2E2D2E; color: #FFFFFF; text-decoration: none; padding: 10px 24px; border-radius: 4px;">
                                    Konto aktivieren
                                </a>
                            </p>
                            <p style="margin: 0 0 12px; font-size: 13px; color: #55534f;">
                                Falls die Schaltfläche nicht funktioniert, kopieren Sie diesen Link in Ihren Browser:<br>
                                <a href="{{ $url }}" style="color: #1D5FA6; word-break: break-all;">{{ $url }}</a>
                            </p>
                            <p style="margin: 0 0 12px; font-size: 13px; color: #55534f;">
                                Der Link ist bis zum <strong>{{ format_datetime($expiresAt) }} Uhr</strong> gültig und kann nur einmal verwendet werden.
                                Sollten Sie diese Einladung nicht erwarten, ignorieren Sie diese E-Mail bitte.
                            </p>
                            <p style="margin: 20px 0 0;">Mit freundlichen Grüßen<br>Müller Holding AG</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 16px 32px; background: #2E2D2E; color: #CFCECC; font-size: 11px; border-radius: 0 0 8px 8px;">
                            Müller Holding AG · Rheinpromenade 13 · 40789 Monheim am Rhein · kontakt@mueller-holding.ag · mueller-holding.ag<br>
                            Sitz: Monheim am Rhein · Registergericht: Amtsgericht Düsseldorf · HRB 104291 · Vorstand: Timo Müller · Aufsichtsratsvorsitzender: Jan Walprecht
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
