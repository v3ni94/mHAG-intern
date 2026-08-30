{{-- Anzeige eines Verknüpfungsziels (DocumentLink->linkable) --}}
@php
    $target = $link->linkable;
@endphp
@if ($target === null)
    <span class="text-muted">Ziel nicht mehr vorhanden</span>
@elseif ($target instanceof \App\Models\Entity)
    Person/Unternehmen: {{ $target->display_name }}
@elseif ($target instanceof \App\Models\Loan)
    Darlehen: {{ $target->loan_number }}
@elseif ($target instanceof \App\Models\Contract)
    Vertrag: {{ $target->contract_number }}
@elseif ($target instanceof \App\Models\Security)
    Sicherheit: {{ $target->type?->label() ?? 'Sicherheit' }} #{{ $target->id }}
@elseif ($target instanceof \App\Models\Resolution)
    Beschluss: {{ $target->resolution_number }}
@elseif ($target instanceof \App\Models\ShareTransaction)
    Aktienbewegung: {{ $target->transaction_number }}
@elseif ($target instanceof \App\Models\IdentityDocument)
    Ausweisdokument: {{ $target->document_number }}
@else
    {{ class_basename($target) }} #{{ $target->getKey() }}
@endif
