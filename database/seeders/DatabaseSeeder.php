<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reihenfolge ist verbindlich: Rollen/Rechte -> Initialdaten -> Inhalte.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            InitialDataSeeder::class,
            ContentSeeder::class,
        ]);
    }
}
