<?php

return [

    /*
    |--------------------------------------------------------------------------
    | DocuSign eSignature (Abschnitte 99 bis 102 Masterprompt)
    |--------------------------------------------------------------------------
    |
    | Anmeldung über JWT Grant (Service-Integration, ohne Benutzeranmeldung).
    | Zwingend erforderlich sind Basis-URL, API-Konto, API-Benutzer,
    | Integrationsschlüssel und der private RSA-Schlüssel.
    |
    | Ohne vollständige Angaben wird nichts versendet und nichts abgefragt;
    | die Oberfläche weist auf die fehlenden Angaben hin. Es wird kein
    | Zugang erraten und keine Verbindung vorgetäuscht.
    |
    */

    // Basis-URL der eSignature-API, mit oder ohne /restapi:
    // Test:  https://demo.docusign.net/restapi
    // Live:  https://eu.docusign.net/restapi (bzw. die im Konto genannte)
    'base_url' => env('DOCUSIGN_BASE_URL'),

    // API-Konto (Account-ID, GUID) aus Einstellungen, Apps und Schlüssel
    'account_id' => env('DOCUSIGN_ACCOUNT_ID'),

    // API-Benutzer (User-ID, GUID) des Kontos, in dessen Namen gehandelt wird
    'user_id' => env('DOCUSIGN_USER_ID'),

    // Integrationsschlüssel der App (Client-ID, GUID)
    'integration_key' => env('DOCUSIGN_INTEGRATION_KEY'),

    /*
     * Privater RSA-Schlüssel: entweder ein Pfad zur Schlüsseldatei
     * (empfohlen, außerhalb des öffentlichen Verzeichnisses) oder der
     * Schlüssel selbst im PEM-Format. Der Schlüssel wird niemals angezeigt.
     */
    'private_key' => env('DOCUSIGN_PRIVATE_KEY'),

    // Anmeldeserver: Test account-d.docusign.com, Live account.docusign.com
    'oauth_host' => env('DOCUSIGN_OAUTH_HOST', 'account-d.docusign.com'),

    // Geheimnis der Connect-Benachrichtigung (HMAC). Ohne Geheimnis wird
    // keine Benachrichtigung angenommen.
    'webhook_secret' => env('DOCUSIGN_WEBHOOK_SECRET'),

    // Betreff der Signatur-E-Mail an die Unterzeichner
    'email_subject' => env('DOCUSIGN_EMAIL_SUBJECT', 'Dokument zur Unterschrift, Müller Holding AG'),

    // Zeitlimit einzelner Aufrufe in Sekunden
    'timeout' => (int) (env('DOCUSIGN_TIMEOUT') ?: 20),

    /*
     * Ankertext für das Signaturfeld im PDF. Findet DocuSign den Text im
     * Dokument, wird das Signaturfeld dort platziert; andernfalls greift die
     * feste Position unten.
     */
    'anchor_string' => env('DOCUSIGN_ANCHOR_STRING', 'Unterschrift'),

];
