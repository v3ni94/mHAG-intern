<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reihenfolge ist verbindlich: Rollen/Rechte -> Initialdaten -> Inhalte.
     * Die Tagesereignisse stehen zuletzt, sie haengen von nichts ab.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            InitialDataSeeder::class,
            ContentSeeder::class,
            DailyFactSeeder::class,
        ]);
    }
}
