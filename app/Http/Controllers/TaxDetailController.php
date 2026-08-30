<?php

namespace App\Http\Controllers;

use App\Enums\EntityType;
use App\Http\Requests\MasterData\TaxDetailRequest;
use App\Models\Entity;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Steuerdaten einer Akte (Steuer-ID, Steuernummer, Finanzamt).
 * Je Entity wird genau ein Datensatz geführt (Anlage und Aktualisierung
 * laufen über dieselbe Aktion).
 */
class TaxDetailController extends Controller
{
    public function store(TaxDetailRequest $request, Entity $entity): RedirectResponse
    {
        $data = $request->validated();

        $existing = $entity->taxDetail()->first();
        $old = $existing?->only(array_keys($data)) ?? [];

        $taxDetail = $entity->taxDetail()->updateOrCreate([], $data);

        AuditService::log(
            $existing ? 'entities.tax_detail_updated' : 'entities.tax_detail_created',
            $taxDetail,
            $old,
            $data,
            ['entity_id' => $entity->id],
        );

        return $this->redirectToTab($entity)
            ->with('success', 'Die Steuerdaten wurden gespeichert.');
    }

    public function destroy(Request $request, Entity $entity): RedirectResponse
    {
        $this->ensureVisible($request, $entity);

        $taxDetail = $entity->taxDetail()->first();
        if ($taxDetail) {
            $old = $taxDetail->toArray();
            $taxDetail->delete();
            AuditService::log('entities.tax_detail_deleted', null, $old, [], ['entity_id' => $entity->id]);
        }

        return $this->redirectToTab($entity)
            ->with('success', 'Die Steuerdaten wurden entfernt.');
    }

    private function ensureVisible(Request $request, Entity $entity): void
    {
        $user = $request->user();
        abort_unless($user->isInternal() || $user->accessibleEntityIds()->contains($entity->id), 404);
    }

    private function redirectToTab(Entity $entity): RedirectResponse
    {
        return $entity->type === EntityType::Person
            ? redirect()->route('persons.show', [$entity, 'tab' => 'steuerdaten'])
            : redirect()->route('companies.show', [$entity, 'tab' => 'stammdaten']);
    }
}
