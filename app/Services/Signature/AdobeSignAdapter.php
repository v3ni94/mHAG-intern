<?php

namespace App\Services\Signature;

use App\Models\Document;
use App\Models\SignatureRequest;
use Illuminate\Database\Eloquent\Model;

/**
 * STUB (Abschnitt 143: Schein-Funktionen sind verboten und müssen klar
 * gekennzeichnet werden): Die Anbindung an Adobe Acrobat Sign ist NICHT
 * implementiert. Diese Klasse existiert ausschließlich, damit die abstrakte
 * Signatur-Schnittstelle (Abschnitt 99) anbieterunabhängig bleibt.
 * Vor produktiver Nutzung sind Konfiguration und vollständige
 * Implementierung erforderlich.
 */
class AdobeSignAdapter implements SignatureServiceInterface
{
    public function create(Model $subject, Document $pdf, array $participants): SignatureRequest
    {
        throw new \RuntimeException('Noch nicht implementiert: Anbindung Adobe Acrobat Sign. Konfiguration und Implementierung erforderlich.');
    }

    public function send(SignatureRequest $request): void
    {
        throw new \RuntimeException('Noch nicht implementiert: Anbindung Adobe Acrobat Sign. Konfiguration und Implementierung erforderlich.');
    }

    public function syncStatus(SignatureRequest $request): void
    {
        throw new \RuntimeException('Noch nicht implementiert: Anbindung Adobe Acrobat Sign. Konfiguration und Implementierung erforderlich.');
    }

    public function attachSignedDocument(SignatureRequest $request, Document $signed): void
    {
        throw new \RuntimeException('Noch nicht implementiert: Anbindung Adobe Acrobat Sign. Konfiguration und Implementierung erforderlich.');
    }
}
