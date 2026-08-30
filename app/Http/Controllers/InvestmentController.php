<?php

namespace App\Http\Controllers;

use App\Enums\EntityType;
use App\Http\Requests\Holding\StoreInvestmentRequest;
use App\Models\Entity;
use App\Models\Investment;
use App\Models\OrganizationRole;
use App\Models\ResolutionLink;
use App\Services\AuditService;
use Illuminate\Http\Request;

/**
 * Beteiligungsverwaltung (Abschnitt 84). Der aktuelle interne Wert wird
 * ausschließlich manuell gepflegt (Abschnitt 140: keine erfundenen Werte).
 */
class InvestmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Investment::query()->with('company.company');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $investments = $query->orderBy('status')->orderByDesc('acquired_on')
            ->paginate(25)->withQueryString();

        return view('investments.index', [
            'investments' => $investments,
            'filters' => $request->only(['status']),
        ]);
    }

    public function create()
    {
        return view('investments.create', [
            'companies' => $this->companyOptions(),
            'investment' => new Investment(['status' => 'active']),
        ]);
    }

    public function store(StoreInvestmentRequest $request)
    {
        $investment = Investment::create($request->validated());

        AuditService::log('investments.created', $investment, [], $request->validated());

        return redirect()
            ->route('investments.show', $investment)
            ->with('success', 'Beteiligung wurde angelegt.');
    }

    public function show(Investment $investment)
    {
        $investment->load(['company.company', 'company.addresses', 'documentLinks.document']);

        // Geschäftsführung/Vorstand der Beteiligung aus den Organstellungen
        $management = OrganizationRole::query()
            ->with('person')
            ->where('company_entity_id', $investment->company_entity_id)
            ->whereIn('role', ['managing_director', 'board_member', 'authorized_officer'])
            ->where('is_active', true)
            ->orderBy('role')
            ->get();

        $resolutionLinks = ResolutionLink::query()
            ->with('resolution')
            ->where('linkable_type', $investment->getMorphClass())
            ->where('linkable_id', $investment->id)
            ->get();

        return view('investments.show', [
            'investment' => $investment,
            'management' => $management,
            'resolutionLinks' => $resolutionLinks,
        ]);
    }

    public function edit(Investment $investment)
    {
        $investment->load('company');

        return view('investments.edit', [
            'investment' => $investment,
            'companies' => $this->companyOptions(),
        ]);
    }

    public function update(StoreInvestmentRequest $request, Investment $investment)
    {
        $old = $investment->only([
            'company_entity_id', 'share_percentage', 'share_count', 'acquired_on',
            'acquisition_cost', 'current_value', 'status', 'notes',
        ]);

        $investment->update($request->validated());

        AuditService::log('investments.updated', $investment, $old, $request->validated());

        return redirect()
            ->route('investments.show', $investment)
            ->with('success', 'Beteiligung wurde aktualisiert.');
    }

    private function companyOptions()
    {
        return Entity::query()
            ->where('type', EntityType::Company)
            ->where('status', 'active')
            ->orderBy('display_name')
            ->get(['id', 'display_name']);
    }
}
