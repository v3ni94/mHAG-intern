<?php

namespace App\Services\Storage;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Flysystem-basierte Dokumentenablage (Abschnitte 59-63 Masterprompt).
 * Aktive Disk über config('documents.disk'): 'documents' (lokal) oder 'sftp'.
 */
class FlysystemDocumentStorage implements DocumentStorageInterface
{
    /**
     * Endungen, die unabhängig von der MIME-Whitelist niemals angenommen
     * werden (Abschnitt 131: keine ausführbaren Dateien).
     */
    private const FORBIDDEN_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
        'exe', 'com', 'bat', 'cmd', 'sh', 'bash', 'ps1', 'msi', 'dll',
        'js', 'mjs', 'vbs', 'jar', 'py', 'pl', 'cgi', 'asp', 'aspx', 'jsp',
        'htaccess', 'html', 'htm', 'svg',
    ];

    public function store(UploadedFile|string $contents, string $directory, string $originalFilename, array $meta = []): Document
    {
        // 1. Datei prüfen (Inhalt, Endung, echter MIME-Type, Größe)
        $binary = $contents instanceof UploadedFile
            ? (string) file_get_contents($contents->getRealPath())
            : $contents;

        if ($binary === '') {
            throw new \RuntimeException('Die Datei ist leer und kann nicht gespeichert werden.');
        }

        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $mimeType = $this->detectMime($binary);
        $this->assertAllowed($extension, $mimeType);
        $this->assertSize(strlen($binary));

        // 2. UUID und technischer Dateiname
        $uuid = (string) Str::uuid();
        $storedFilename = $uuid.'.'.$extension;
        $directory = trim($directory, '/');
        $path = ($directory !== '' ? $directory.'/' : '').$storedFilename;

        // 3. SHA-256 vor der Übertragung
        $sha256 = hash('sha256', $binary);

        $disk = config('documents.disk');

        // 4. Übertragen
        try {
            Storage::disk($disk)->put($path, $binary);
        } catch (\Throwable $e) {
            Log::error('Dokument-Upload fehlgeschlagen (Übertragung).', ['path' => $path, 'error' => $e->getMessage()]);
            throw new \RuntimeException('Die Datei konnte nicht in der Dokumentenablage gespeichert werden. Bitte versuchen Sie es erneut oder prüfen Sie die Speicherverbindung.', 0, $e);
        }

        // 5. Transfer verifizieren (Existenz, Größe, Prüfsumme wo möglich)
        try {
            $this->verifyTransfer($disk, $path, strlen($binary), $sha256);
        } catch (\Throwable $e) {
            $this->cleanup($disk, $path);
            throw new \RuntimeException('Die Übertragung der Datei konnte nicht verifiziert werden. Der Upload wurde verworfen.', 0, $e);
        }

        // 6. DB-Eintrag in Transaktion abschließen; bei Fehler Datei aufräumen
        try {
            return DB::transaction(function () use ($meta, $uuid, $originalFilename, $storedFilename, $binary, $mimeType, $sha256, $disk, $path) {
                $document = Document::create([
                    'uuid' => $uuid,
                    'original_filename' => $originalFilename,
                    'stored_filename' => $storedFilename,
                    'doc_type' => $meta['doc_type'] ?? 'other',
                    'category' => $meta['category'] ?? null,
                    'file_size' => strlen($binary),
                    'mime_type' => $mimeType,
                    'sha256' => $sha256,
                    'document_date' => $meta['document_date'] ?? null,
                    'description' => $meta['description'] ?? null,
                    'tags' => $meta['tags'] ?? null,
                    'storage_disk' => $disk,
                    'storage_path' => $path,
                    'version' => 1,
                    'status' => DocumentStatus::Active,
                    'expires_on' => $meta['expires_on'] ?? null,
                    'uploaded_by' => $meta['uploaded_by'] ?? auth()->id(),
                ]);

                $document->versions()->create([
                    'version' => 1,
                    'stored_filename' => $storedFilename,
                    'storage_path' => $path,
                    'file_size' => strlen($binary),
                    'sha256' => $sha256,
                    'uploaded_by' => $meta['uploaded_by'] ?? auth()->id(),
                ]);

                return $document;
            });
        } catch (\Throwable $e) {
            $this->cleanup($disk, $path);
            Log::error('Dokument-Upload fehlgeschlagen (Datenbank).', ['path' => $path, 'error' => $e->getMessage()]);
            throw new \RuntimeException('Der Dokumenteneintrag konnte nicht gespeichert werden. Der Upload wurde verworfen.', 0, $e);
        }
    }

    /**
     * Neue Dokumentversion speichern (document_versions, version++).
     * Gleiche Prüf-Pipeline wie store(); das Dokument zeigt danach auf die
     * neue Datei, ältere Versionen bleiben erhalten.
     */
    public function storeVersion(Document $document, UploadedFile $file, ?User $user = null): Document
    {
        $binary = (string) file_get_contents($file->getRealPath());
        if ($binary === '') {
            throw new \RuntimeException('Die Datei ist leer und kann nicht gespeichert werden.');
        }

        $originalFilename = $file->getClientOriginalName();
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $mimeType = $this->detectMime($binary);
        $this->assertAllowed($extension, $mimeType);
        $this->assertSize(strlen($binary));

        $uuid = (string) Str::uuid();
        $storedFilename = $uuid.'.'.$extension;
        $directory = trim(dirname($document->storage_path), '/.');
        $path = ($directory !== '' ? $directory.'/' : '').$storedFilename;
        $sha256 = hash('sha256', $binary);
        $disk = $document->storage_disk;

        try {
            Storage::disk($disk)->put($path, $binary);
            $this->verifyTransfer($disk, $path, strlen($binary), $sha256);
        } catch (\Throwable $e) {
            $this->cleanup($disk, $path);
            throw new \RuntimeException('Die neue Dokumentversion konnte nicht gespeichert werden. Der Upload wurde verworfen.', 0, $e);
        }

        try {
            return DB::transaction(function () use ($document, $originalFilename, $storedFilename, $binary, $mimeType, $sha256, $path, $user) {
                $nextVersion = ((int) $document->version) + 1;

                $document->versions()->create([
                    'version' => $nextVersion,
                    'stored_filename' => $storedFilename,
                    'storage_path' => $path,
                    'file_size' => strlen($binary),
                    'sha256' => $sha256,
                    'uploaded_by' => $user?->id ?? auth()->id(),
                ]);

                $document->update([
                    'original_filename' => $originalFilename,
                    'stored_filename' => $storedFilename,
                    'file_size' => strlen($binary),
                    'mime_type' => $mimeType,
                    'sha256' => $sha256,
                    'storage_path' => $path,
                    'version' => $nextVersion,
                ]);

                return $document->refresh();
            });
        } catch (\Throwable $e) {
            $this->cleanup($disk, $path);
            throw new \RuntimeException('Die neue Dokumentversion konnte nicht gespeichert werden. Der Upload wurde verworfen.', 0, $e);
        }
    }

    /**
     * Inhalt eines Dokuments lesen.
     *
     * Der Rueckfall auf null war unerreichbar: Storage::get() wirft bei einer
     * fehlenden oder nicht lesbaren Datei eine Flysystem-Ausnahme, statt null
     * zu liefern. Die vorgesehene deutsche Meldung konnte deshalb nie greifen,
     * und die Aufrufer sahen eine englische Ausnahme als Serverfehler 500.
     */
    public function retrieve(Document $document): string
    {
        try {
            $contents = Storage::disk($document->storage_disk)->get($document->storage_path);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Die Datei wurde in der Dokumentenablage nicht gefunden oder ist nicht lesbar.',
                0,
                $e,
            );
        }

        if ($contents === null) {
            throw new \RuntimeException('Die Datei wurde in der Dokumentenablage nicht gefunden.');
        }

        return $contents;
    }

    public function exists(Document $document): bool
    {
        try {
            return Storage::disk($document->storage_disk)->exists($document->storage_path);
        } catch (\Throwable) {
            return false;
        }
    }

    public function move(Document $document, string $newDirectory): void
    {
        $newDirectory = trim($newDirectory, '/');
        $newPath = ($newDirectory !== '' ? $newDirectory.'/' : '').$document->stored_filename;
        if ($newPath === $document->storage_path) {
            return;
        }

        Storage::disk($document->storage_disk)->move($document->storage_path, $newPath);
        $document->update(['storage_path' => $newPath]);
        $document->versions()->where('version', $document->version)->update(['storage_path' => $newPath]);
    }

    public function archive(Document $document): void
    {
        $document->update(['status' => DocumentStatus::Archived]);
    }

    public function delete(Document $document): void
    {
        $disk = Storage::disk($document->storage_disk);

        // Alle Versionsdateien entfernen, danach den Datensatz (Soft Delete,
        // der Metadaten-Nachweis bleibt für den Audit-Trail erhalten).
        $paths = $document->versions()->pluck('storage_path')
            ->push($document->storage_path)->unique();

        foreach ($paths as $path) {
            try {
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }
            } catch (\Throwable $e) {
                Log::warning('Dokumentdatei konnte nicht gelöscht werden.', ['path' => $path, 'error' => $e->getMessage()]);
            }
        }

        $document->update(['status' => DocumentStatus::Deleted]);
        $document->delete();
    }

    public function checksum(Document $document): string
    {
        return hash('sha256', $this->retrieve($document));
    }

    // ------------------------------------------------------------------

    private function detectMime(string $binary): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($binary);

        return is_string($mime) && $mime !== '' ? strtolower($mime) : 'application/octet-stream';
    }

    /**
     * MIME-Whitelist und Endungsprüfung (Abschnitte 62/131): Der echte
     * MIME-Type muss erlaubt sein und die Dateiendung muss zu ihm passen.
     */
    private function assertAllowed(string $extension, string $mimeType): void
    {
        if ($extension === '' || in_array($extension, self::FORBIDDEN_EXTENSIONS, true)) {
            throw new \RuntimeException('Dieser Dateityp ist aus Sicherheitsgründen nicht zulässig.');
        }

        $allowed = (array) config('documents.allowed_mime_types', []);

        // Normalisierung: csv/txt werden von finfo teils als text/plain gemeldet.
        $extensionsForMime = $allowed[$mimeType] ?? null;

        if ($extensionsForMime === null) {
            throw new \RuntimeException(sprintf(
                'Der Dateityp "%s" ist nicht zulässig. Erlaubt sind unter anderem PDF, Bilder und Office-Dokumente.',
                $mimeType,
            ));
        }

        // Sonderfall text/plain deckt auch csv ab (finfo unterscheidet nicht zuverlässig).
        if ($mimeType === 'text/plain') {
            $extensionsForMime = array_unique(array_merge($extensionsForMime, $allowed['text/csv'] ?? []));
        }

        if (! in_array($extension, array_map('strtolower', $extensionsForMime), true)) {
            throw new \RuntimeException(sprintf(
                'Die Dateiendung ".%s" passt nicht zum tatsächlichen Dateityp (%s). Die Datei wurde abgelehnt.',
                $extension,
                $mimeType,
            ));
        }
    }

    private function assertSize(int $bytes): void
    {
        $maxKb = (int) config('documents.max_size_kb', 51200);
        if ($bytes > $maxKb * 1024) {
            throw new \RuntimeException(sprintf(
                'Die Datei ist zu groß (%s KB). Maximal zulässig sind %s KB.',
                number_format((int) ceil($bytes / 1024), 0, ',', '.'),
                number_format($maxKb, 0, ',', '.'),
            ));
        }
    }

    /**
     * Transfer-Verifizierung (Abschnitt 62): Existenz und Größe immer,
     * Prüfsumme wo mit vertretbarem Aufwand möglich.
     */
    private function verifyTransfer(string $disk, string $path, int $expectedSize, string $expectedSha256): void
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            throw new \RuntimeException('Datei nach Übertragung nicht vorhanden.');
        }

        $size = $storage->size($path);
        if ($size !== $expectedSize) {
            throw new \RuntimeException(sprintf('Größe nach Übertragung abweichend (%d statt %d Bytes).', $size, $expectedSize));
        }

        try {
            $written = $storage->get($path);
        } catch (\Throwable) {
            $written = null; // Prüfsumme nicht verifizierbar; Existenz+Größe wurden geprüft.
        }

        if ($written !== null && hash('sha256', $written) !== $expectedSha256) {
            throw new \RuntimeException('Prüfsumme nach Übertragung abweichend.');
        }
    }

    private function cleanup(string $disk, string $path): void
    {
        try {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        } catch (\Throwable $e) {
            Log::warning('Aufräumen nach fehlgeschlagenem Upload nicht möglich.', ['path' => $path, 'error' => $e->getMessage()]);
        }
    }
}
