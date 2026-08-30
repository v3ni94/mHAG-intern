<?php

namespace App\Http\Controllers;

use App\Enums\EntityType;
use App\Http\Requests\MasterData\BankAccountRequest;
use App\Models\BankAccount;
use App\Models\Entity;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bankkonten einer Akte (Abschnitt 6/7 Masterprompt).
 */
class BankAccountController extends Controller
{
    public function store(BankAccountRequest $request, Entity $entity): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['is_primary'])) {
            $entity->bankAccounts()->update(['is_primary' => false]);
        }

        $account = $entity->bankAccounts()->create($data);

        AuditService::log('entities.bank_account_created', $account, [], [
            'account_holder' => $data['account_holder'],
            'iban' => $data['iban'],
            'bank_name' => $data['bank_name'] ?? null,
        ], ['entity_id' => $entity->id]);

        return $this->redirectToTab($entity)
            ->with('success', 'Das Bankkonto wurde gespeichert.');
    }

    public function update(BankAccountRequest $request, Entity $entity, BankAccount $bankAccount): RedirectResponse
    {
        $data = $request->validated();
        $old = $bankAccount->only(array_keys($data));

        if (! empty($data['is_primary'])) {
            $entity->bankAccounts()->whereKeyNot($bankAccount->id)->update(['is_primary' => false]);
        }

        $bankAccount->update($data);

        AuditService::log('entities.bank_account_updated', $bankAccount, $old, $data, ['entity_id' => $entity->id]);

        return $this->redirectToTab($entity)
            ->with('success', 'Das Bankkonto wurde aktualisiert.');
    }

    public function destroy(Request $request, Entity $entity, BankAccount $bankAccount): RedirectResponse
    {
        $this->ensureVisible($request, $entity);

        $old = $bankAccount->toArray();
        $bankAccount->delete();

        AuditService::log('entities.bank_account_deleted', null, $old, [], ['entity_id' => $entity->id]);

        return $this->redirectToTab($entity)
            ->with('success', 'Das Bankkonto wurde gelöscht.');
    }

    private function ensureVisible(Request $request, Entity $entity): void
    {
        $user = $request->user();
        abort_unless($user->isInternal() || $user->accessibleEntityIds()->contains($entity->id), 404);
    }

    private function redirectToTab(Entity $entity): RedirectResponse
    {
        $route = $entity->type === EntityType::Person ? 'persons.show' : 'companies.show';

        return redirect()->route($route, [$entity, 'tab' => 'bankkonten']);
    }
}
