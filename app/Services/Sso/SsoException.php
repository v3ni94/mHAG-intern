<?php

namespace App\Services\Sso;

/**
 * Fehler im Ablauf der zentralen Anmeldung.
 *
 * Die Meldung ist für den Benutzer bestimmt und nennt nie einen Schlüssel,
 * ein Token oder ein Geheimnis. Einzelheiten für die Fehlersuche gehören in
 * das Protokoll, nicht auf den Bildschirm.
 */
class SsoException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $grund = 'unbekannt',
        public readonly array $protokoll = [],
    ) {
        parent::__construct($message);
    }
}
