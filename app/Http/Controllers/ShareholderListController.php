<?php

namespace App\Http\Controllers;

use App\Http\Requests\Holding\CreateListSnapshotRequest;
use App\Models\ShareholderListSnapshot;
use App\Services\AuditService;
use App\Services\Holding\ShareholdingService;
use App\Services\Storage\DocumentStorageInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Offizielle Aktionärslisten (Abschnitte 82/83): Snapshot mit PDF,
 * SHA-256 und unveränderlichem Daten-Snapshot.
 */
class ShareholderListController extends Controller
{
    public function __construct(
        private readonly ShareholdingService $shareholding,
        private readonly DocumentStorageInterface $storage,
    ) {}

    public function create(CreateListSnapshotRequest $request)
    {
        $asOf = $request->filled('as_of')
            ? Carbon::parse($request->input('as_of'))
            : Carbon::now();

        $snapshot = $this->shareholding->createListSnapshot($asOf, $request->user());

        return redirect()
            ->route('shareholders.index')
            ->with('success', sprintf(
                'Aktionärsliste %s zum Stichtag %s wurde erstellt (SHA-256: %s).',
                $snapshot->document_number,
                $asOf->format('d.m.Y'),
                substr((string) $snapshot->sha256, 0, 12).'...',
            ));
    }

    public function download(Request $request, ShareholderListSnapshot $snapshot)
    {
        // Eine Aktionaersliste ist der vollstaendige Bestand. Sie erhaelt nur,
        // wem kein Aktionaer verborgen ist.
        abort_unless(
            ShareholderListSnapshot::query()->visibleTo($request->user())->whereKey($snapshot->getKey())->exists(),
            404,
        );

        $document = $snapshot->document()->first();
        abort_if(! $document, 404, 'Zu dieser Aktionärsliste ist kein Dokument hinterlegt.');

        try {
            $content = $this->storage->retrieve($document);
        } catch (\RuntimeException $e) {
            return back()->with('danger', 'Die abgelegte Aktionärsliste ist in der '
                .'Dokumentenablage nicht auffindbar. Bitte die Ablage prüfen. Der Eintrag zur '
                .'Liste bleibt bestehen.');
        }

        AuditService::log('shareholders.list-downloaded', $snapshot, [], [], [
            'document_number' => $snapshot->document_number,
        ]);

        return response($content, 200, [
            'Content-Type' => $document->mime_type ?: 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$document->original_filename.'"',
        ]);
    }
}
