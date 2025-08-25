<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
{
    \App\Models\User::firstOrCreate(
        ['email' => 'admin@admin.admin'],
        [
            'name' => 'admin@admin.admin',
            'password' => bcrypt('admin@admin.admin'),
        ]
    );
}

    
}
