<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\StoreContractAmendmentRequest;
use App\Models\Contract;
use App\Services\AuditService;

/**
 * Vertragsnachträge (Abschnitt 56 Masterprompt): Laufzeitverlängerung,
 * Zinssatzänderung, Tilgungsänderung, Stundung, Kapitaländerung,
 * Sicherheitenänderung, sonstige Nachträge. Anzeige auf der Vertragsseite.
 */
class ContractAmendmentController extends Controller
{
    public function store(StoreContractAmendmentRequest $request, Contract $contract)
    {
        $amendment = $contract->amendments()->create([
            'amendment_type' => $request->input('amendment_type'),
            'description' => $request->input('description'),
            'effective_date' => $request->input('effective_date'),
        ]);

        AuditService::log('contracts.amendment_created', $contract, [], [
            'amendment_id' => $amendment->id,
            'amendment_type' => $amendment->amendment_type,
            'effective_date' => $amendment->effective_date?->toDateString(),
        ]);

        return redirect()->route('contracts.show', $contract)
            ->with('success', 'Der Nachtrag ('.(StoreContractAmendmentRequest::AMENDMENT_TYPES[$amendment->amendment_type] ?? $amendment->amendment_type).') wurde erfasst.');
    }
}
