<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Aktiver Signaturweg (Abschnitt 99 Masterprompt)
    |--------------------------------------------------------------------------
    |
    | manual   = Unterschrift außerhalb des Systems, Status werden gepflegt,
    |            die signierte Fassung wird hochgeladen (Standard).
    | docusign = Anbindung an DocuSign eSignature über JWT Grant.
    |
    | Die Umstellung erfolgt ausschließlich über die Konfiguration. Ist
    | DocuSign gewählt, aber nicht vollständig konfiguriert, weist die
    | Oberfläche auf die fehlenden Angaben hin, statt einen Versand
    | vorzutäuschen.
    |
    */

    'provider' => env('SIGNATURE_PROVIDER', 'manual'),

];
