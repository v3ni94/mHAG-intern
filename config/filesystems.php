<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         * Profilbilder. Bewusst nicht in public/: die Auslieferung läuft über
         * einen berechtigungsgeprüften Controller, damit Bilder nicht ohne
         * Anmeldung abrufbar sind.
         */
        'avatars' => [
            'driver' => 'local',
            'root' => storage_path('app/avatars'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        // Dokumentenablage lokal (Fallback bzw. Entwicklungsumgebung).
        // Niemals in public/: Downloads laufen ausschließlich über
        // authentifizierte, berechtigungsgeprüfte Controller (Abschnitt 64).
        'documents' => [
            'driver' => 'local',
            'root' => storage_path('app/documents'),
            'serve' => false,
            'throw' => true,
            'report' => true,
        ],

        /*
         * Bevorzugte Dokumentenablage: SFTP (Abschnitt 59 Masterprompt).
         * SSH-Key-Authentifizierung bevorzugt, Host-Key-Prüfung über Fingerprint.
         *
         * Leere Werte werden bewusst auf null gesetzt. Ein in der .env
         * vorhandener, aber leerer SFTP_PRIVATE_KEY ist ein leerer String und
         * damit nicht "nicht gesetzt": der Adapter versucht dann, diesen leeren
         * Wert als Schlüssel zu laden, und bricht mit "Unable to load private
         * key" ab, obwohl eine Anmeldung per Passwort vorgesehen ist.
         */
        'sftp' => array_filter([
            'driver' => 'sftp',
            'host' => env('SFTP_HOST'),
            // Leere Angaben nicht als 0 durchlassen: ein leerer SFTP_PORT
            // ergaebe Port 0 und damit einen unerklaerlichen Verbindungsfehler.
            'port' => (int) (env('SFTP_PORT') ?: 22),
            'username' => env('SFTP_USERNAME'),
            'privateKey' => env('SFTP_PRIVATE_KEY'),
            'passphrase' => env('SFTP_PASSPHRASE'),
            'password' => env('SFTP_PASSWORD'),
            'root' => env('SFTP_ROOT_PATH', '/mueller-holding'),
            'timeout' => (int) (env('SFTP_TIMEOUT') ?: 15),
            'hostFingerprint' => env('SFTP_HOST_FINGERPRINT'),
            'throw' => true,
            'report' => true,
        ], fn ($value) => $value !== null && $value !== ''),

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
