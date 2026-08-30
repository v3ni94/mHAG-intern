<?php

namespace App\Services\Signature;

use App\Models\Document;
use App\Models\SignatureRequest;
use Illuminate\Database\Eloquent\Model;

/**
 * Abstrakte Signatur-Schnittstelle (Abschnitt 99 Masterprompt).
 * Keine harte Abhängigkeit von einem Anbieter; der aktive Adapter wird im
 * AppServiceProvider gebunden (Standard: ManualSignatureAdapter).
 */
interface SignatureServiceInterface
{
    /**
     * Signaturanfrage aus Vorgang, PDF und Unterzeichnern erstellen.
     *
     * @param  Model  $subject  Vorgang (Resolution, Contract, ShareTransaction, ShareholderListSnapshot ...)
     * @param  Document  $pdf  zu unterzeichnendes PDF
     * @param  array  $participants  [['entity_id' => .., 'role' => .., 'email' => ..], ...]
     */
    public function create(Model $subject, Document $pdf, array $participants): SignatureRequest;

    /** Anfrage versenden: Status der Anfrage und der Teilnehmer auf "versendet". */
    public function send(SignatureRequest $request): void;

    /** Gesamtstatus der Anfrage aus den Teilnehmerstatus ableiten bzw. beim Anbieter abfragen. */
    public function syncStatus(SignatureRequest $request): void;

    /**
     * Signiertes Dokument übernehmen: Anfrage abschließen (completed) und den
     * Status des zugrunde liegenden Vorgangs fortschreiben (z. B. Beschluss
     * auf "unterschrieben").
     */
    public function attachSignedDocument(SignatureRequest $request, Document $signed): void;
}
