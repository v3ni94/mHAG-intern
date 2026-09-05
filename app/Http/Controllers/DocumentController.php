<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\EntityType;
use App\Http\Requests\Documents\LinkDocumentRequest;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Requests\Documents\StoreDocumentVersionRequest;
use App\Models\Contract;
use App\Models\CorporateBodyMember;
use App\Models\Document;
use App\Models\Entity;
use App\Models\Guarantee;
use App\Models\IdentityDocument;
use App\Models\Investment;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\Reminder;
use App\Models\Resolution;
use App\Models\Security;
use App\Models\ShareTransaction;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Storage\DocumentStorageInterface;
use App\Services\Storage\FlysystemDocumentStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dokumentenmanagement (Abschnitte 57-64 Masterprompt).
 */
class DocumentController extends Controller
{
    /**
     * Whitelist der verknüpfbaren Modelle (Abschnitt 57). Klassen werden
     * NIE frei aus dem Request übernommen, sondern nur über diese Map.
     */
    public const LINKABLE_TYPES = [
        'entity' => Entity::class,
        'loan' => Loan::class,
        'contract' => Contract::class,
        'security' => Security::class,
        'resolution' => Resolution::class,
        'share_transaction' => ShareTransaction::class,
        'identity_document' => IdentityDocument::class,
        'payment' => Payment::class,
        'guarantee' => Guarantee::class,
        'investment' => Investment::class,
        'corporate_body_member' => CorporateBodyMember::class,
    ];

    /** Deutsche Labels der Verknüpfungsarten. */
    public const LINKABLE_LABELS = [
        'entity' => 'Person / Unternehmen',
        'loan' => 'Darlehen',
        'contract' => 'Vertrag',
        'security' => 'Sicherheit',
        'resolution' => 'Beschluss',
        'share_transaction' => 'Aktienbewegung',
        'identity_document' => 'Ausweisdokument',
        'payment' => 'Zahlung',
        'guarantee' => 'Bürgschaft',
        'investment' => 'Beteiligung',
        'corporate_body_member' => 'Organmitglied (Vorstand, Aufsichtsrat)',
    ];

    /** Dokumenttypen (Abschnitt 57) mit deutschen Labels. */
    public const DOC_TYPES = [
        'id_card' => 'Personalausweis',
        'passport' => 'Reisepass',
        'contract' => 'Vertrag',
        'amendment' => 'Nachtrag',
        'bank_statement' => 'Kontoauszug',
        'payment_receipt' => 'Zahlungsbeleg',
        'commercial_register' => 'Handelsregister',
        'articles' => 'Satzung',
        'land_register' => 'Grundbuchauszug',
        'guarantee' => 'Bürgschaft',
        'security' => 'Sicherheit',
        'dunning' => 'Mahnung',
        'resolution' => 'Beschluss',
        'shareholder_list' => 'Aktionärsliste',
        'signature_protocol' => 'Signaturprotokoll',
        'correspondence' => 'Korrespondenz',
        'other' => 'Sonstiges',
    ];

    public function __construct(
        private readonly DocumentStorageInterface $storage,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $documents = Document::visibleTo($user)
            ->with(['uploader', 'links.linkable'])
            ->when($request->filled('doc_type'), fn ($q) => $q->where('doc_type', $request->string('doc_type')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', 'like', '%'.$request->string('category').'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('tag'), function ($q) use ($request) {
                $q->where('tags', 'like', '%'.$request->string('tag').'%');
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('original_filename', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('category', 'like', $term)
                        ->orWhere('tags', 'like', $term);
                });
            })
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'docTypes' => self::DOC_TYPES,
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->can('documents.upload'), 403);

        return view('documents.create', [
            'docTypes' => self::DOC_TYPES,
            'linkableLabels' => self::LINKABLE_LABELS,
            'preselectedType' => $request->query('link_type'),
            'preselectedId' => $request->query('link_id'),
            'loans' => Loan::visibleTo($request->user())->orderBy('loan_number')->get(['id', 'loan_number', 'title']),
            'entities' => Entity::visibleTo($request->user())->orderBy('display_name')->get(['id', 'display_name']),
        ]);
    }

    public function store(StoreDocumentRequest $request)
    {
        $linkable = null;
        if ($request->filled('link_type')) {
            $linkable = $this->resolveLinkable($request->input('link_type'), (int) $request->input('link_id'), $request->user());
            if (! $linkable) {
                return back()->withInput()->withErrors(['link_id' => 'Das angegebene Verknüpfungsziel wurde nicht gefunden.']);
            }
        }

        $meta = [
            'doc_type' => $request->input('doc_type'),
            'category' => $request->input('category'),
            'document_date' => $request->input('document_date'),
            'expires_on' => $request->input('expires_on'),
            'description' => $request->input('description'),
            'tags' => $request->tagsArray(),
            'uploaded_by' => $request->user()->id,
        ];

        try {
            $document = $this->storage->store(
                $request->file('file'),
                $this->directoryFor($linkable, $request->input('doc_type')),
                $request->file('file')->getClientOriginalName(),
                $meta,
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['file' => $e->getMessage()]);
        }

        if ($linkable) {
            $document->links()->firstOrCreate([
                'linkable_type' => $linkable->getMorphClass(),
                'linkable_id' => $linkable->getKey(),
            ]);
        }

        // Ablaufdatum: Wiedervorlage anlegen (Abschnitt 58/73)
        if ($document->expires_on) {
            Reminder::create([
                'title' => 'Dokument läuft ab: '.$document->original_filename,
                'description' => 'Das Dokument "'.$document->original_filename.'" ('.(self::DOC_TYPES[$document->doc_type] ?? $document->doc_type).') läuft am '.format_date($document->expires_on).' ab.',
                'due_date' => $document->expires_on,
                'assigned_to' => $request->user()->id,
                'priority' => 'normal',
                'status' => 'open',
                'remindable_type' => $document->getMorphClass(),
                'remindable_id' => $document->id,
                'created_by' => $request->user()->id,
            ]);
        }

        AuditService::log('documents.uploaded', $document, [], [
            'original_filename' => $document->original_filename,
            'doc_type' => $document->doc_type,
            'sha256' => $document->sha256,
            'storage_path' => $document->storage_path,
        ]);

        return redirect()->route('documents.show', $document)
            ->with('success', 'Das Dokument wurde erfolgreich hochgeladen und verifiziert.');
    }

    public function show(Request $request, Document $document)
    {
        $document = Document::visibleTo($request->user())
            ->with(['versions', 'links.linkable', 'uploader'])
            ->findOrFail($document->id);

        // Benutzernamen der Versions-Uploader (DocumentVersion hat keine
        // uploader-Relation; Model gehört der Foundation und bleibt unberührt).
        $versionUploaders = User::query()
            ->whereIn('id', $document->versions->pluck('uploaded_by')->filter()->unique())
            ->pluck('name', 'id');

        // Integritätsprüfung (Abschnitt 63) auf Anforderung
        $integrity = null;
        if ($request->boolean('pruefen')) {
            try {
                $integrity = $this->storage->checksum($document) === $document->sha256;
            } catch (\Throwable) {
                $integrity = false;
            }
            AuditService::log('documents.integrity_checked', $document, [], ['result' => $integrity ? 'ok' : 'fehlgeschlagen']);
        }

        return view('documents.show', [
            'document' => $document,
            'integrity' => $integrity,
            'docTypes' => self::DOC_TYPES,
            'linkableLabels' => self::LINKABLE_LABELS,
            'versionUploaders' => $versionUploaders,
        ]);
    }

    /** Sicherer Download (Abschnitt 64): Berechtigung + Datenscope + Audit. */
    public function download(Request $request, Document $document): StreamedResponse
    {
        abort_unless($request->user()->can('documents.download'), 403);
        Document::visibleTo($request->user())->findOrFail($document->id);

        abort_unless($this->storage->exists($document), 404, 'Die Datei wurde in der Dokumentenablage nicht gefunden.');

        AuditService::log('documents.downloaded', $document, [], ['original_filename' => $document->original_filename]);

        $contents = $this->storage->retrieve($document);

        return response()->streamDownload(
            fn () => print $contents,
            $document->original_filename,
            [
                'Content-Type' => $document->mime_type,
                'Content-Length' => (string) strlen($contents),
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    /** Neue Version hochladen (document_versions, version++). */
    public function storeVersion(StoreDocumentVersionRequest $request, Document $document, FlysystemDocumentStorage $storage)
    {
        Document::visibleTo($request->user())->findOrFail($document->id);

        $oldVersion = $document->version;

        try {
            $storage->storeVersion($document, $request->file('file'), $request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        AuditService::log('documents.version_uploaded', $document, ['version' => $oldVersion], ['version' => $document->version]);

        return redirect()->route('documents.show', $document)
            ->with('success', 'Die neue Dokumentversion ('.$document->version.') wurde gespeichert.');
    }

    /** Nachträgliche Verknüpfung (documents.link). */
    public function link(LinkDocumentRequest $request, Document $document)
    {
        Document::visibleTo($request->user())->findOrFail($document->id);

        $linkable = $this->resolveLinkable($request->input('link_type'), (int) $request->input('link_id'), $request->user());
        if (! $linkable) {
            return back()->withErrors(['link_id' => 'Das angegebene Verknüpfungsziel wurde nicht gefunden.']);
        }

        $document->links()->firstOrCreate([
            'linkable_type' => $linkable->getMorphClass(),
            'linkable_id' => $linkable->getKey(),
        ]);

        AuditService::log('documents.linked', $document, [], [
            'linkable_type' => $linkable->getMorphClass(),
            'linkable_id' => $linkable->getKey(),
        ]);

        return back()->with('success', 'Die Verknüpfung wurde angelegt.');
    }

    public function archive(Request $request, Document $document)
    {
        abort_unless($request->user()->can('documents.archive'), 403);
        Document::visibleTo($request->user())->findOrFail($document->id);

        $this->storage->archive($document);

        AuditService::log('documents.archived', $document, ['status' => DocumentStatus::Active->value], ['status' => DocumentStatus::Archived->value]);

        return back()->with('success', 'Das Dokument wurde archiviert.');
    }

    /** Endgültiges Löschen: nur mit Berechtigung documents.delete. */
    public function destroy(Request $request, Document $document)
    {
        abort_unless($request->user()->can('documents.delete'), 403);
        Document::visibleTo($request->user())->findOrFail($document->id);

        AuditService::log('documents.deleted', $document, [
            'original_filename' => $document->original_filename,
            'storage_path' => $document->storage_path,
            'sha256' => $document->sha256,
        ], []);

        $this->storage->delete($document);

        return redirect()->route('documents.index')
            ->with('success', 'Das Dokument wurde endgültig gelöscht.');
    }

    // ------------------------------------------------------------------

    /**
     * Verknüpfungsziel ausschließlich über die Whitelist-Map auflösen und
     * anschließend prüfen, ob der Benutzer darauf überhaupt Zugriff hat.
     * Ohne diese Prüfung könnte ein externer Benutzer ein Dokument an einen
     * fremden Vorgang hängen, indem er dessen Nummer errät.
     */
    private function resolveLinkable(string $type, int $id, ?User $user = null): ?Model
    {
        $class = self::LINKABLE_TYPES[$type] ?? null;
        if (! $class) {
            return null;
        }

        $linkable = $class::query()->find($id);
        if (! $linkable) {
            return null;
        }

        $user = $user ?: request()->user();
        if ($user && ! $this->mayLinkTo($linkable, $user)) {
            return null;
        }

        return $linkable;
    }

    /**
     * Darf der Benutzer Dokumente an dieses Objekt hängen?
     *
     * Interne Rollen sehen den Gesamtbestand. Externe Benutzer nur Vorgänge
     * ihrer zugeordneten Entities; gesellschaftsrechtliche Objekte bleiben
     * internen Rollen und den dafür berechtigten Organen vorbehalten.
     */
    private function mayLinkTo(Model $linkable, User $user): bool
    {
        if ($user->isInternal()) {
            return true;
        }

        $eigeneEntities = $user->accessibleEntityIds();

        if ($linkable instanceof Entity) {
            return $eigeneEntities->contains($linkable->id);
        }

        if ($linkable instanceof Loan) {
            return Loan::visibleTo($user)->whereKey($linkable->id)->exists();
        }

        // Vorgänge, die über ein Darlehen zugeordnet sind
        foreach ([Contract::class, Security::class, Guarantee::class, Payment::class] as $klasse) {
            if ($linkable instanceof $klasse) {
                $linkable->loadMissing('loan');

                return $linkable->loan !== null
                    && Loan::visibleTo($user)->whereKey($linkable->loan->id)->exists();
            }
        }

        if ($linkable instanceof IdentityDocument) {
            return $eigeneEntities->contains($linkable->entity_id);
        }

        // Gesellschaftsrechtliche Objekte: nur mit der jeweiligen Berechtigung
        if ($linkable instanceof ShareTransaction || $linkable instanceof Investment) {
            return $user->can('shares.prepare');
        }

        if ($linkable instanceof Resolution) {
            return $user->can('resolutions.update');
        }

        if ($linkable instanceof CorporateBodyMember) {
            return $user->can('shares.prepare');
        }

        return false;
    }

    /** Ablagestruktur gem. Abschnitt 61 aus Verknüpfung und Dokumenttyp ableiten. */
    private function directoryFor(?Model $linkable, string $docType): string
    {
        $folders = config('documents.folders');

        $loanSubfolder = match ($docType) {
            'contract', 'amendment' => 'vertraege',
            'bank_statement', 'payment_receipt' => 'zahlungen',
            'guarantee', 'security', 'land_register' => 'sicherheiten',
            'dunning' => 'mahnungen',
            default => 'sonstiges',
        };

        if ($linkable instanceof Loan) {
            return ($folders['loans'] ?? 'darlehen').'/'.$linkable->loan_number.'/'.$loanSubfolder;
        }

        if ($linkable instanceof Contract) {
            $linkable->loadMissing('loan');

            return $linkable->loan
                ? ($folders['loans'] ?? 'darlehen').'/'.$linkable->loan->loan_number.'/vertraege'
                : 'vertraege';
        }

        if ($linkable instanceof Security) {
            $linkable->loadMissing('loan');

            return $linkable->loan
                ? ($folders['loans'] ?? 'darlehen').'/'.$linkable->loan->loan_number.'/sicherheiten'
                : 'sicherheiten';
        }

        if ($linkable instanceof Entity) {
            $base = $linkable->type === EntityType::Person
                ? ($folders['persons'] ?? 'personen')
                : ($folders['companies'] ?? 'unternehmen');

            return $base.'/'.($linkable->internal_number ?: 'ENT-'.$linkable->id);
        }

        if ($linkable instanceof IdentityDocument) {
            $linkable->loadMissing('entity');
            $entity = $linkable->entity;

            return ($folders['persons'] ?? 'personen').'/'.($entity?->internal_number ?: 'ENT-'.$linkable->entity_id).'/ausweise';
        }

        if ($linkable instanceof ShareTransaction) {
            return ($folders['corporate'] ?? 'gesellschaft').'/aktienbewegungen';
        }

        if ($linkable instanceof Resolution) {
            return ($folders['corporate'] ?? 'gesellschaft').'/beschluesse';
        }

        if ($linkable instanceof Payment) {
            $linkable->loadMissing('loan');

            return $linkable->loan
                ? ($folders['loans'] ?? 'darlehen').'/'.$linkable->loan->loan_number.'/zahlungen'
                : 'zahlungen';
        }

        if ($linkable instanceof Guarantee) {
            $linkable->loadMissing('loan');

            return $linkable->loan
                ? ($folders['loans'] ?? 'darlehen').'/'.$linkable->loan->loan_number.'/sicherheiten'
                : 'sicherheiten';
        }

        if ($linkable instanceof Investment) {
            return ($folders['corporate'] ?? 'gesellschaft').'/beteiligungen';
        }

        if ($linkable instanceof CorporateBodyMember) {
            $linkable->loadMissing('body');

            // Ablage getrennt nach Gremium (Masterprompt 61)
            $unterordner = match ($linkable->body?->type) {
                'board' => 'vorstand',
                'supervisory_board' => 'aufsichtsrat',
                default => 'gesellschaft',
            };

            return ($folders['corporate'] ?? 'gesellschaft').'/'.$unterordner;
        }

        return 'sonstiges';
    }
}
