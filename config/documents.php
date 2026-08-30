<?php

return [

    // Aktive Dokumentenablage: 'sftp' (bevorzugt, Abschnitt 59) oder 'documents' (lokal).
    'disk' => env('DOCUMENT_DISK', 'local') === 'sftp' ? 'sftp' : 'documents',

    // Maximale Dateigröße in KB (Standard: 50 MB). Administrierbar über Einstellungen.
    'max_size_kb' => 51200,

    /*
     * Erlaubte Dateitypen (MIME-Type => Endungen). Keine ausführbaren Dateien
     * (Abschnitt 131). Über die Administration erweiterbar (settings: documents.allowed_mime_types).
     */
    'allowed_mime_types' => [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/tiff' => ['tif', 'tiff'],
        'image/heic' => ['heic'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'application/vnd.ms-powerpoint' => ['ppt'],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
        'text/plain' => ['txt'],
        'text/csv' => ['csv'],
        'message/rfc822' => ['eml'],
        'application/zip' => ['zip'],
    ],

    // SFTP-Ordnerstruktur (Abschnitt 61)
    'folders' => [
        'persons' => 'personen',
        'companies' => 'unternehmen',
        'loans' => 'darlehen',
        'corporate' => 'gesellschaft',
        'exports' => 'exports',
        'backups' => 'backups',
    ],
];
