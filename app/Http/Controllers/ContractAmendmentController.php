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
        /*
         * Sichtbarkeitspruefung. Sie fehlte hier vollstaendig, waehrend alle
         * Aktionen des ContractController sie durchlaufen. Eine externe Rolle
         * mit contracts.update, etwa Partner, konnte damit Nachtraege zu
         * Laufzeit, Zinssatz, Tilgung oder Stundung an Vertraegen
         * ausgeschlossener Gesellschaften erfassen. Der Schreibvorgang blieb
         * wirksam, obwohl die anschliessende Weiterleitung 404 ergab.
         */
        $contract = Contract::query()
            ->visibleTo($request->user())
            ->whereKey($contract->getKey())
            ->firstOrFail();

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
