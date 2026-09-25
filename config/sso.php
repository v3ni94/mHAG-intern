<?php

/*
|--------------------------------------------------------------------------
| Zentrale Anmeldung über das CRM (OpenID Connect)
|--------------------------------------------------------------------------
|
| Das CRM der Müller Holding AG ist OIDC-Anbieter (Authorization Code mit
| PKCE). Das Intranet meldet sich dort als Relying Party an. Die Anmeldung
| selbst, einschließlich der Zwei-Faktor-Prüfung, findet im CRM statt.
|
| Solange `enabled` nicht gesetzt ist, ändert sich an der Anmeldung nichts.
|
*/

return [

    // Zentrale Anmeldung anbieten. Ohne gültige Angaben bleibt sie aus,
    // damit eine unvollständige Einrichtung niemanden aussperrt.
    'enabled' => filter_var(env('SSO_ENABLED', false), FILTER_VALIDATE_BOOL),

    // Aussteller laut Discovery-Dokument des CRM, ohne Schrägstrich am Ende.
    'issuer' => rtrim((string) env('SSO_ISSUER', ''), '/'),

    // Abweichende Adresse des Discovery-Dokuments. Leer: Aussteller plus
    // /.well-known/openid-configuration.
    'discovery_url' => env('SSO_DISCOVERY_URL'),

    // Im CRM hinterlegte Kennung dieser Anwendung.
    'client_id' => env('SSO_CLIENT_ID'),

    // Geheimnis der Anwendung. Das CRM lässt auch öffentliche Clients ohne
    // Geheimnis zu; für eine Anwendung mit eigenem Server ist ein Geheimnis
    // die richtige Wahl.
    'client_secret' => env('SSO_CLIENT_SECRET'),

    // Muss im CRM als redirect_uri eingetragen sein, Zeichen für Zeichen.
    'redirect_uri' => env('SSO_REDIRECT_URI'),

    // Seite des CRM, die die Anmeldung führt und danach an den
    // Autorisierungsendpunkt weiterreicht. Leer: der Autorisierungsendpunkt
    // aus dem Discovery-Dokument wird unmittelbar aufgerufen.
    'login_url' => env('SSO_LOGIN_URL'),

    'scopes' => ['openid', 'profile', 'email'],

    /*
     * Die Zwei-Faktor-Prüfung des CRM anerkennen. Der Sinn einer zentralen
     * Anmeldung ist, dass die Prüfung der Identität einmal an einer Stelle
     * stattfindet; das CRM verlangt dafür TOTP. Wird der Wert auf false
     * gesetzt, verlangt das Intranet zusätzlich seinen eigenen zweiten
     * Faktor.
     *
     * Die Entscheidung ist von der Geschäftsführung zu bestätigen und in
     * jedem Anmeldevorgang protokolliert.
     */
    'trust_mfa' => filter_var(env('SSO_TRUST_MFA', true), FILTER_VALIDATE_BOOL),

    /*
     * Benutzer, die im Intranet nicht vorhanden sind, werden NICHT angelegt.
     * Wer Zugang erhält, entscheidet die Benutzerverwaltung des Intranets;
     * eine Anmeldung am CRM allein begründet keinen Zugang zu den Daten der
     * Holding.
     */
    'auto_provision' => false,

    /*
     * Örtliche Anmeldung mit Kennwort. Bleibt als Notzugang bestehen, damit
     * die Administration bei einer Störung des CRM handlungsfähig ist.
     * Jede Nutzung wird als solche protokolliert.
     */
    'allow_local_login' => filter_var(env('SSO_ALLOW_LOCAL_LOGIN', true), FILTER_VALIDATE_BOOL),

    // Zeitgrenze der Aufrufe zum CRM in Sekunden.
    'timeout' => (int) env('SSO_TIMEOUT', 10),

    // Zulässige Abweichung der Uhren beim Prüfen der Laufzeit, in Sekunden.
    'leeway' => (int) env('SSO_LEEWAY', 60),

    // Zwischenspeicher für Discovery-Dokument und Signaturschlüssel.
    'cache_seconds' => (int) env('SSO_CACHE_SECONDS', 600),
];
