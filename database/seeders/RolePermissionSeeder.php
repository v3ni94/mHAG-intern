<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * RBAC (Abschnitte 9 und 15 Masterprompt): granulare Berechtigungen,
 * Standardrollen, frei erweiterbar über die Administration.
 */
class RolePermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        'dashboard.view',
        // Personen
        'persons.view', 'persons.create', 'persons.update', 'persons.archive',
        // Unternehmen
        'companies.view', 'companies.create', 'companies.update', 'companies.archive',
        // Darlehen
        'loans.view', 'loans.create', 'loans.update', 'loans.approve', 'loans.archive',
        // Zahlungen
        'payments.view', 'payments.record', 'payments.correct', 'payments.cancel', 'payments.approve',
        // Verträge
        'contracts.view', 'contracts.create', 'contracts.update', 'contracts.finalize', 'contracts.sign',
        // Dokumente
        'documents.view', 'documents.upload', 'documents.download', 'documents.archive', 'documents.delete',
        // Aktien
        'shares.view', 'shares.prepare', 'shares.finalize', 'shares.list',
        // Beschlüsse
        'resolutions.view', 'resolutions.create', 'resolutions.update', 'resolutions.vote',
        'resolutions.finalize', 'resolutions.sign',
        // Reports
        'reports.view',
        // Administration
        'admin.users', 'admin.roles', 'admin.settings', 'admin.sftp', 'admin.audit',
        'admin.backups', 'admin.templates',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission);
        }

        $all = Permission::all();

        $viewAll = [
            'dashboard.view', 'persons.view', 'companies.view', 'loans.view', 'payments.view',
            'contracts.view', 'documents.view', 'documents.download', 'shares.view',
            'resolutions.view', 'reports.view',
        ];

        $roles = [
            'Administrator' => $all->pluck('name')->all(),
            'Vorstand' => array_merge($viewAll, [
                'persons.create', 'persons.update', 'persons.archive',
                'companies.create', 'companies.update', 'companies.archive',
                'loans.create', 'loans.update', 'loans.approve', 'loans.archive',
                'payments.record', 'payments.correct', 'payments.cancel', 'payments.approve',
                'contracts.create', 'contracts.update', 'contracts.finalize', 'contracts.sign',
                'documents.upload', 'documents.archive', 'documents.delete',
                'shares.prepare', 'shares.finalize', 'shares.list',
                'resolutions.create', 'resolutions.update', 'resolutions.vote', 'resolutions.finalize', 'resolutions.sign',
            ]),
            'Aufsichtsratsvorsitzender' => [
                'dashboard.view', 'resolutions.view', 'resolutions.vote', 'resolutions.sign',
                'shares.view', 'documents.view', 'documents.download', 'reports.view', 'loans.view',
            ],
            'Aufsichtsratsmitglied' => [
                'dashboard.view', 'resolutions.view', 'resolutions.vote', 'resolutions.sign',
                'shares.view', 'documents.view', 'documents.download',
            ],
            'Aktionär' => [
                'dashboard.view', 'shares.view', 'documents.view', 'documents.download',
            ],
            'Darlehensgeber' => [
                'dashboard.view', 'loans.view', 'payments.view', 'contracts.view',
                'documents.view', 'documents.download', 'reports.view',
            ],
            'Darlehensnehmer' => [
                'dashboard.view', 'loans.view', 'payments.view', 'contracts.view',
                'documents.view', 'documents.download',
            ],
            /*
             * Partner (Anforderung 30.08.2026): externe Rolle mit
             * Bearbeitungsrechten am Bestand. Der Datenumfang wird NICHT über
             * die Rolle geregelt, sondern je Benutzer über den
             * Sichtbarkeitsmodus: entweder nur die zugeordneten
             * Gesellschaften oder alles außer den zugeordneten. Ohne
             * Zuordnung sieht ein Partner im Einschlussmodus nichts.
             *
             * Bewusst NICHT enthalten: Aktien, Beschlüsse und Organe der
             * Müller Holding AG sowie jede Administration.
             */
            'Partner' => [
                'dashboard.view', 'persons.view', 'companies.view',
                'loans.view', 'loans.create', 'loans.update',
                'payments.view', 'payments.record',
                'contracts.view', 'contracts.create', 'contracts.update',
                'documents.view', 'documents.upload', 'documents.download',
                'reports.view',
            ],
            'Buchhaltung' => array_merge($viewAll, [
                'payments.record', 'payments.correct', 'payments.cancel',
                'documents.upload',
            ]),
            'Sachbearbeiter' => array_merge($viewAll, [
                'persons.create', 'persons.update',
                'companies.create', 'companies.update',
                'loans.create', 'loans.update',
                'payments.record',
                'contracts.create', 'contracts.update',
                'documents.upload',
            ]),
            'Mitarbeiter' => $viewAll,
            'Nur Lesen' => $viewAll,
        ];

        foreach ($roles as $name => $permissions) {
            $role = Role::findOrCreate($name);
            $role->syncPermissions($permissions);
        }
    }
}
