<?php

namespace App\Services\Signature;

use App\Models\Document;
use App\Models\SignatureRequest;
use Illuminate\Database\Eloquent\Model;

/**
 * STUB (Abschnitt 143: Schein-Funktionen sind verboten und müssen klar
 * gekennzeichnet werden): Die Anbindung an DocuSign ist NICHT implementiert.
 * Diese Klasse existiert ausschließlich, damit die abstrakte
 * Signatur-Schnittstelle (Abschnitt 99) anbieterunabhängig bleibt.
 * Vor produktiver Nutzung sind Konfiguration (API-Schlüssel, Konto) und
 * vollständige Implementierung erforderlich.
 */
class DocuSignAdapter implements SignatureServiceInterface
{
    public function create(Model $subject, Document $pdf, array $participants): SignatureRequest
    {
        throw new \RuntimeException('Noch nicht implementiert: Anbindung DocuSign. Konfiguration und Implementierung erforderlich.');
    }

    public function send(SignatureRequest $request): void
    {
        throw new \RuntimeException('Noch nicht implementiert: Anbindung DocuSign. Konfiguration und Implementierung erforderlich.');
    }

    public function syncStatus(SignatureRequest $request): void
    {
        throw new \RuntimeException('Noch nicht implementiert: Anbindung DocuSign. Konfiguration und Implementierung erforderlich.');
    }

    public function attachSignedDocument(SignatureRequest $request, Document $signed): void
    {
        throw new \RuntimeException('Noch nicht implementiert: Anbindung DocuSign. Konfiguration und Implementierung erforderlich.');
    }
}
