{{--
    Anzeige eines Bankkontos (Bank, IBAN in Vierergruppen, Kontoinhaber).

    Datenschutz (DSGVO): Die IBAN ist ein personenbezogenes Datum. Interne
    Rollen sehen beide Kontoseiten; externe Benutzer ausschließlich Konten
    ihrer eigenen Entities. Ist das Konto nicht sichtbar, erscheint ein
    neutraler Hinweis statt der Kontodaten.

    Erwartete Variablen: $account (?BankAccount), $canSeeAccounts (bool),
    $visibleEntityIds (array<int>)
--}}
@php
    $canSee = ($canSeeAccounts ?? false)
        || ($account && in_array((int) $account->entity_id, array_map('intval', $visibleEntityIds ?? []), true));
@endphp
@if (! $account)
    <span class="text-muted">ohne Angabe</span>
@elseif (! $canSee)
    <span class="text-muted" title="Kontodaten der Gegenseite werden nicht angezeigt">nicht sichtbar</span>
@else
    <span>
        {{ $account->bank_name ?: 'Bank ohne Angabe' }}<br>
        <span class="font-monospace small">{{ \App\Http\Controllers\PaymentController::formatIban($account->iban) }}</span><br>
        <span class="small text-muted">{{ $account->account_holder }}</span>
    </span>
@endif
