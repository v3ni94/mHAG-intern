<?php

namespace App\Services\Storage;

use App\Models\Document;
use Illuminate\Http\UploadedFile;

/**
 * Zentrale Storage-Abstraktion für das Dokumentenmanagement
 * (Abschnitt 60 Masterprompt). Implementierungen: Flysystem
 * (lokal, SFTP); später S3, MinIO, Azure Blob u. a. möglich.
 */
interface DocumentStorageInterface
{
    /**
     * Sichere Upload-Pipeline (Abschnitt 62): Datei prüfen (MIME, Endung,
     * Größe), UUID erzeugen, SHA-256 berechnen, übertragen, Transfer
     * verifizieren, DB-Eintrag in Transaktion abschließen. Bei Fehlern wird
     * keine Datei zurückgelassen und eine Exception mit deutscher Meldung
     * geworfen.
     *
     * @param  UploadedFile|string  $contents  Upload oder Roh-Inhalt (z. B. erzeugtes PDF)
     * @param  string  $directory  Zielverzeichnis gem. Struktur Abschnitt 61 (z. B. 'darlehen/DAR-2026-00001/vertraege')
     * @param  string  $originalFilename  Ursprünglicher Dateiname (bestimmt die Endung)
     * @param  array  $meta  Metadaten für documents (doc_type, category, document_date, description, tags, expires_on, uploaded_by, ...)
     */
    public function store(UploadedFile|string $contents, string $directory, string $originalFilename, array $meta = []): Document;

    /** Datei-Inhalt lesen (für Download-Response, Abschnitt 64). */
    public function retrieve(Document $document): string;

    public function exists(Document $document): bool;

    public function move(Document $document, string $newDirectory): void;

    public function archive(Document $document): void;

    /** Endgültiges Löschen von Datei(en) und Datensatz; Aufrufer auditiert. */
    public function delete(Document $document): void;

    /** SHA-256 der aktuell gespeicherten Datei (Integritätsprüfung, Abschnitt 63). */
    public function checksum(Document $document): string;
}
