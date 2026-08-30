<?php

namespace App\Http\Controllers;

use App\Enums\EntityType;
use App\Models\BankAccount;
use App\Models\ContactDetail;
use App\Models\Document;
use App\Models\Entity;
use App\Models\Loan;
use App\Models\Resolution;
use App\Models\ShareTransaction;
use App\Models\TaxDetail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Globale Suche (Abschnitt 105 Masterprompt): Name, Firma, IBAN, E-Mail,
 * Telefon, Steuernummer, Registernummer, Darlehensnummer, Beschlussnummer,
 * Aktienbewegung, Dokument. Ergebnisse als gruppierte Trefferliste,
 * gefiltert nach Berechtigungen und Entity-Sichtbarkeit.
 */
class EntitySearchController extends Controller
{
    private const LIMIT_PER_GROUP = 10;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $user = $request->user();
        $groups = [];

        if (mb_strlen($q) >= 2) {
            $groups = array_filter([
                $this->searchPersons($q, $user),
                $this->searchCompanies($q, $user),
                $this->searchBankAccounts($q, $user),
                $this->searchContacts($q, $user),
                $this->searchTaxDetails($q, $user),
                $this->searchLoans($q, $user),
                $this->searchResolutions($q, $user),
                $this->searchShareTransactions($q, $user),
                $this->searchDocuments($q, $user),
            ]);
        }

        return view('search.index', [
            'q' => $q,
            'groups' => $groups,
            'total' => array_sum(array_map(fn ($g) => count($g['items']), $groups)),
        ]);
    }

    private function searchPersons(string $q, User $user): ?array
    {
        if (! $user->can('persons.view')) {
            return null;
        }
        $like = '%'.$q.'%';

        $items = Entity::query()
            ->visibleTo($user)
            ->where('type', EntityType::Person)
            ->with('person')
            ->where(function ($sub) use ($like) {
                $sub->where('display_name', 'like', $like)
                    ->orWhere('internal_number', 'like', $like)
                    ->orWhereHas('person', function ($p) use ($like) {
                        $p->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('birth_name', 'like', $like);
                    });
            })
            ->orderBy('display_name')
            ->limit(self::LIMIT_PER_GROUP)
            ->get()
            ->map(fn (Entity $e) => [
                'title' => $e->display_name,
                'subtitle' => trim(($e->internal_number ? $e->internal_number.' · ' : '').($e->person?->date_of_birth ? 'geb. '.format_date($e->person->date_of_birth) : ''), ' ·'),
                'url' => route('persons.show', $e),
                'badge' => $e->status === 'archived' ? 'Archiviert' : null,
            ]);

        return $this->group('persons', 'Personen', 'bi-person', $items);
    }

    private function searchCompanies(string $q, User $user): ?array
    {
        if (! $user->can('companies.view')) {
            return null;
        }
        $like = '%'.$q.'%';

        $items = Entity::query()
            ->visibleTo($user)
            ->whereIn('type', [EntityType::Company, EntityType::Organization])
            ->with('company')
            ->where(function ($sub) use ($like) {
                $sub->where('display_name', 'like', $like)
                    ->orWhere('internal_number', 'like', $like)
                    ->orWhereHas('company', function ($c) use ($like) {
                        $c->where('name', 'like', $like)
                            ->orWhere('short_name', 'like', $like)
                            ->orWhere('register_number', 'like', $like)
                            ->orWhere('vat_id', 'like', $like)
                            ->orWhere('tax_number', 'like', $like);
                    });
            })
            ->orderBy('display_name')
            ->limit(self::LIMIT_PER_GROUP)
            ->get()
            ->map(fn (Entity $e) => [
                'title' => $e->display_name,
                'subtitle' => trim(implode(' · ', array_filter([
                    $e->company?->legal_form,
                    $e->company?->register_number,
                    $e->internal_number,
                ]))),
                'url' => route('companies.show', $e),
                'badge' => $e->status === 'archived' ? 'Archiviert' : null,
            ]);

        return $this->group('companies', 'Unternehmen', 'bi-building', $items);
    }

    private function searchBankAccounts(string $q, User $user): ?array
    {
        if (! $user->can('persons.view') && ! $user->can('companies.view')) {
            return null;
        }
        $like = '%'.$q.'%';
        $ibanLike = '%'.strtoupper(str_replace(' ', '', $q)).'%';

        $items = $this->scopeEntityChildren(BankAccount::query(), $user)
            ->with('entity')
            ->where(function ($sub) use ($like, $ibanLike) {
                $sub->where('iban', 'like', $ibanLike)
                    ->orWhere('account_holder', 'like', $like)
                    ->orWhere('bank_name', 'like', $like);
            })
            ->limit(self::LIMIT_PER_GROUP)
            ->get()
            ->map(fn (BankAccount $a) => [
                'title' => $a->iban,
                'subtitle' => implode(' · ', array_filter([$a->entity?->display_name, $a->account_holder, $a->bank_name])),
                'url' => $a->entity ? $this->entityUrl($a->entity, 'bankkonten') : null,
                'badge' => $a->is_active ? null : 'Inaktiv',
            ]);

        return $this->group('bank_accounts', 'Bankkonten (IBAN)', 'bi-bank', $items);
    }

    private function searchContacts(string $q, User $user): ?array
    {
        if (! $user->can('persons.view') && ! $user->can('companies.view')) {
            return null;
        }
        $like = '%'.$q.'%';

        $items = $this->scopeEntityChildren(ContactDetail::query(), $user)
            ->with('entity')
            ->where(function ($sub) use ($like) {
                $sub->where('value', 'like', $like)->orWhere('label', 'like', $like);
            })
            ->limit(self::LIMIT_PER_GROUP)
            ->get()
            ->map(fn (ContactDetail $c) => [
                'title' => $c->value,
                'subtitle' => implode(' · ', array_filter([
                    $c->entity?->display_name,
                    \App\Http\Requests\MasterData\ContactDetailRequest::typeOptions()[$c->type] ?? $c->type,
                ])),
                'url' => $c->entity ? $this->entityUrl($c->entity, $c->entity->type === EntityType::Person ? 'kontakt' : 'ansprechpartner') : null,
                'badge' => null,
            ]);

        return $this->group('contacts', 'Kontaktdaten (E-Mail / Telefon)', 'bi-envelope', $items);
    }

    private function searchTaxDetails(string $q, User $user): ?array
    {
        if (! $user->can('persons.view') && ! $user->can('companies.view')) {
            return null;
        }
        $like = '%'.$q.'%';

        $items = $this->scopeEntityChildren(TaxDetail::query(), $user)
            ->with('entity')
            ->where(function ($sub) use ($like) {
                $sub->where('tax_number', 'like', $like)->orWhere('tax_id', 'like', $like);
            })
            ->limit(self::LIMIT_PER_GROUP)
            ->get()
            ->map(fn (TaxDetail $t) => [
                'title' => implode(' / ', array_filter([$t->tax_number, $t->tax_id])),
                'subtitle' => implode(' · ', array_filter([$t->entity?->display_name, $t->tax_office])),
                'url' => $t->entity ? $this->entityUrl($t->entity, $t->entity->type === EntityType::Person ? 'steuerdaten' : 'stammdaten') : null,
                'badge' => null,
            ]);

        return $this->group('tax_details', 'Steuerdaten', 'bi-percent', $items);
    }

    private function searchLoans(string $q, User $user): ?array
    {
        if (! $user->can('loans.view')) {
            return null;
        }
        $like = '%'.$q.'%';

        $items = Loan::query()
            ->visibleTo($user)
            ->with(['lender', 'borrower'])
            ->where(function ($sub) use ($like) {
                $sub->where('loan_number', 'like', $like)->orWhere('title', 'like', $like);
            })
            ->limit(self::LIMIT_PER_GROUP)
            ->get()
            ->map(fn (Loan $l) => [
                'title' => trim($l->loan_number.' · '.($l->title ?? '')),
                'subtitle' => implode(' an ', array_filter([$l->lender?->display_name, $l->borrower?->display_name])),
                'url' => Route::has('loans.show') ? route('loans.show', $l) : null,
                'badge' => $l->status,
            ]);

        return $this->group('loans', 'Darlehen', 'bi-cash-stack', $items);
    }

    private function searchResolutions(string $q, User $user): ?array
    {
        if (! $user->can('resolutions.view')) {
            return null;
        }
        $like = '%'.$q.'%';

        $query = Resolution::query()
            ->with('company')
            ->where(function ($sub) use ($like) {
                $sub->where('resolution_number', 'like', $like)->orWhere('title', 'like', $like);
            });

        if (! $user->isInternal()) {
            $ids = $user->accessibleEntityIds();
            $query->where(function ($sub) use ($ids) {
                $sub->whereIn('company_entity_id', $ids)
                    ->orWhereIn('applicant_entity_id', $ids)
                    ->orWhereHas('participants', fn ($p) => $p->whereIn('entity_id', $ids));
            });
        }

        $items = $query->limit(self::LIMIT_PER_GROUP)->get()
            ->map(fn (Resolution $r) => [
                'title' => trim($r->resolution_number.' · '.$r->title),
                'subtitle' => (string) $r->company?->display_name,
                'url' => Route::has('resolutions.show') ? route('resolutions.show', $r) : null,
                'badge' => $r->status,
            ]);

        return $this->group('resolutions', 'Beschlüsse', 'bi-journal-check', $items);
    }

    private function searchShareTransactions(string $q, User $user): ?array
    {
        if (! $user->can('shares.view')) {
            return null;
        }
        $like = '%'.$q.'%';

        $query = ShareTransaction::query()
            ->with(['buyer.entity', 'seller.entity'])
            ->where(function ($sub) use ($like) {
                $sub->where('transaction_number', 'like', $like)->orWhere('note', 'like', $like);
            });

        if (! $user->isInternal()) {
            $ids = $user->accessibleEntityIds();
            $query->where(function ($sub) use ($ids) {
                $sub->whereHas('buyer', fn ($b) => $b->whereIn('entity_id', $ids))
                    ->orWhereHas('seller', fn ($s) => $s->whereIn('entity_id', $ids));
            });
        }

        $items = $query->limit(self::LIMIT_PER_GROUP)->get()
            ->map(fn (ShareTransaction $t) => [
                'title' => trim(($t->transaction_number ?? 'Aktienbewegung').' · '.$t->type->label()),
                'subtitle' => trim(implode(' · ', array_filter([
                    $t->share_count ? number_format((int) $t->share_count, 0, ',', '.').' Aktien' : null,
                    $t->seller?->entity?->display_name,
                    $t->buyer?->entity?->display_name,
                ]))),
                'url' => Route::has('share-transactions.show') ? route('share-transactions.show', $t) : null,
                'badge' => $t->status,
            ]);

        return $this->group('share_transactions', 'Aktienbewegungen', 'bi-graph-up-arrow', $items);
    }

    private function searchDocuments(string $q, User $user): ?array
    {
        if (! $user->can('documents.view')) {
            return null;
        }
        $like = '%'.$q.'%';

        $items = Document::query()
            ->visibleTo($user)
            ->where(function ($sub) use ($like) {
                $sub->where('original_filename', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('doc_type', 'like', $like);
            })
            ->limit(self::LIMIT_PER_GROUP)
            ->get()
            ->map(fn (Document $d) => [
                'title' => $d->original_filename,
                'subtitle' => implode(' · ', array_filter([
                    $d->doc_type,
                    $d->document_date ? format_date($d->document_date) : null,
                ])),
                'url' => Route::has('documents.show') ? route('documents.show', $d) : null,
                'badge' => $d->status,
            ]);

        return $this->group('documents', 'Dokumente', 'bi-folder2-open', $items);
    }

    /** Kind-Tabellen einer Entity nach Sichtbarkeit und Aktentyp-Berechtigung filtern. */
    private function scopeEntityChildren($query, User $user)
    {
        if (! $user->isInternal()) {
            $query->whereIn('entity_id', $user->accessibleEntityIds());
        }

        $canPersons = $user->can('persons.view');
        $canCompanies = $user->can('companies.view');

        if ($canPersons && $canCompanies) {
            return $query;
        }

        return $query->whereHas('entity', function ($e) use ($canPersons) {
            $canPersons
                ? $e->where('type', EntityType::Person)
                : $e->whereIn('type', [EntityType::Company, EntityType::Organization]);
        });
    }

    private function entityUrl(Entity $entity, string $tab): string
    {
        return $entity->type === EntityType::Person
            ? route('persons.show', [$entity, 'tab' => $tab])
            : route('companies.show', [$entity, 'tab' => $tab]);
    }

    private function group(string $key, string $label, string $icon, $items): ?array
    {
        if ($items->isEmpty()) {
            return null;
        }

        return ['key' => $key, 'label' => $label, 'icon' => $icon, 'items' => $items->all()];
    }
}
