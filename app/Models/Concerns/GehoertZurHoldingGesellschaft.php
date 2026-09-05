<?php

namespace App\Models\Concerns;

use App\Models\Entity;
use App\Models\Setting;
use App\Models\User;

/**
 * Datensätze, die sich auf die Anteile AN der Holding-Gesellschaft beziehen.
 *
 * Aktionäre, Aktienbewegungen und Aktionärslisten führen keine eigene
 * Gesellschaft als Feld, weil es nur eine gibt: die Müller Holding AG. Ohne
 * diesen Zusatz würde ein Ausschluss dieser Gesellschaft das Aktienregister
 * nicht verbergen, obwohl es genau ihre Angelegenheit ist. Die fachliche
 * Vorgabe lautet "alles außer Müller Holding", und dazu gehört die
 * Aktionärsstruktur.
 *
 * Ist die Gesellschaft in den Einstellungen nicht hinterlegt, gilt der
 * Bereich für Benutzer mit Einschränkung als nicht sichtbar. Das ist die
 * vorsichtige Richtung: eine fehlende Einstellung darf eine Schranke nicht
 * stillschweigend aufheben. Interne Rollen sind davon nicht betroffen.
 */
trait GehoertZurHoldingGesellschaft
{
    protected static function holdingGesellschaftSichtbar(User $user): bool
    {
        if ($user->isInternal()) {
            return true;
        }

        $id = Setting::get('holding', 'company_entity_id');

        if ($id === null || $id === '') {
            return false;
        }

        return Entity::query()->visibleTo($user)->whereKey((int) $id)->exists();
    }
}
