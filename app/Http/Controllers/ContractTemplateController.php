<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\StoreContractTemplateRequest;
use App\Http\Requests\Documents\StoreContractTemplateVersionRequest;
use App\Http\Requests\Documents\UpdateContractTemplateRequest;
use App\Models\ContractTemplate;
use App\Services\AuditService;
use App\Services\ContractGenerationService;
use Illuminate\Http\Request;

/**
 * Vertragsvorlagen mit Versionierung (Abschnitte 53/54 Masterprompt).
 * Vorlagentexte werden nie geändert; Änderungen erzeugen immer eine
 * neue Version. Bestehende Verträge bleiben unberührt (Snapshot-Prinzip).
 */
class ContractTemplateController extends Controller
{
    /** Standardplatzhalter (Abschnitt 53) zur Anzeige im Editor. */
    public const STANDARD_PLACEHOLDERS = [
        'darlehensnummer', 'darlehensgeber.name', 'darlehensgeber.anschrift',
        'darlehensnehmer.name', 'darlehensnehmer.anschrift', 'darlehensbetrag',
        'zinssatz', 'vertragsdatum', 'beginn', 'ende', 'faelligkeit',
        'auszahlungstag', 'zinsfaelligkeit', 'zinsmethode', 'tilgungsregelung',
        'sicherheit',
    ];

    public function __construct(
        private readonly ContractGenerationService $generator,
    ) {}

    public function index(Request $request)
    {
        $templates = ContractTemplate::query()
            ->withCount(['versions'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('category', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('contract-templates.index', ['templates' => $templates]);
    }

    public function create()
    {
        return view('contract-templates.create', [
            'placeholders' => self::STANDARD_PLACEHOLDERS,
        ]);
    }

    public function store(StoreContractTemplateRequest $request)
    {
        $template = ContractTemplate::create([
            'name' => $request->input('name'),
            'category' => $request->input('category'),
            'description' => $request->input('description'),
            'is_active' => true,
        ]);

        $body = (string) $request->input('body');
        $template->versions()->create([
            'version' => $request->input('version') ?: '1.0',
            'body' => $body,
            'placeholders' => $this->generator->placeholdersInBody($body),
            'created_by' => $request->user()->id,
        ]);

        AuditService::log('contract_templates.created', $template, [], [
            'name' => $template->name,
            'version' => $request->input('version') ?: '1.0',
        ]);

        return redirect()->route('contract-templates.show', $template)
            ->with('success', 'Die Vertragsvorlage wurde angelegt (Version '.($request->input('version') ?: '1.0').').');
    }

    public function show(ContractTemplate $contractTemplate)
    {
        $contractTemplate->load(['versions.creator']);

        return view('contract-templates.show', [
            'template' => $contractTemplate,
            'placeholders' => self::STANDARD_PLACEHOLDERS,
        ]);
    }

    public function edit(ContractTemplate $contractTemplate)
    {
        return view('contract-templates.edit', ['template' => $contractTemplate]);
    }

    /** Nur Metadaten; der Vorlagentext wird ausschließlich über neue Versionen geändert. */
    public function update(UpdateContractTemplateRequest $request, ContractTemplate $contractTemplate)
    {
        $old = $contractTemplate->only(['name', 'category', 'description', 'is_active']);

        $contractTemplate->update([
            'name' => $request->input('name'),
            'category' => $request->input('category'),
            'description' => $request->input('description'),
            'is_active' => $request->boolean('is_active'),
        ]);

        AuditService::log('contract_templates.updated', $contractTemplate, $old, $contractTemplate->only(['name', 'category', 'description', 'is_active']));

        return redirect()->route('contract-templates.show', $contractTemplate)
            ->with('success', 'Die Vorlagen-Stammdaten wurden aktualisiert.');
    }

    /** Neue Version anlegen (Abschnitt 54): bestehende Versionen bleiben unverändert. */
    public function storeVersion(StoreContractTemplateVersionRequest $request, ContractTemplate $contractTemplate)
    {
        $body = (string) $request->input('body');

        $version = $contractTemplate->versions()->create([
            'version' => $request->input('version'),
            'body' => $body,
            'placeholders' => $this->generator->placeholdersInBody($body),
            'created_by' => $request->user()->id,
        ]);

        AuditService::log('contract_templates.version_created', $contractTemplate, [], [
            'version' => $version->version,
        ]);

        return redirect()->route('contract-templates.show', $contractTemplate)
            ->with('success', 'Die neue Vorlagenversion '.$version->version.' wurde angelegt. Bestehende Verträge bleiben unverändert.');
    }
}
