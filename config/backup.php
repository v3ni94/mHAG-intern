<?php

return [

    /*
     * Ablageort der Datenbank-Backups (Abschnitt 129 Masterprompt).
     * Relativer Pfad wird ab Projektwurzel aufgelöst.
     */
    'path' => env('BACKUP_PATH', 'storage/backups'),
];
