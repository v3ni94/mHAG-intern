<?php

return [

    /*
     * Ablageort der Datenbank-Backups (Abschnitt 129 Masterprompt).
     * Relativer Pfad wird ab Projektwurzel aufgelöst.
     */
    'path' => env('BACKUP_PATH', 'storage/backups'),

    /*
     * Aufbewahrungsdauer in Tagen. Nach jedem erfolgreichen Lauf werden
     * ältere Sicherungen im selben Verzeichnis entfernt. Ohne Grenze füllt
     * der tägliche Lauf das Dateisystem; auf einem Server, den sich mehrere
     * Anwendungen teilen, trifft das auch die anderen.
     * 0 schaltet das Aufräumen ab.
     */
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),

    /*
     * Sicherung nach dem Erstellen komprimieren. Ein SQL-Abzug lässt sich
     * damit auf einen Bruchteil verkleinern.
     */
    'compress' => filter_var(env('BACKUP_COMPRESS', true), FILTER_VALIDATE_BOOL),
];
