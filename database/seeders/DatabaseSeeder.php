<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database: import db.json (original ids kept) and make sure an admin exists.
     */
    public function run(): void
    {
        $this->call([
            DbJsonImportSeeder::class,
            AdminSeeder::class,
        ]);
    }
}
