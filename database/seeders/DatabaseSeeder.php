<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Sales\Database\Seeders\SalesDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'role' => 'employee',
            'status' => 'available',
            'phone_number' => '',
            'language_flag' => 'en',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'available',
            'phone_number' => '',
            'language_flag' => 'en',
            'is_active' => true,
        ]);

        $this->call(SalesDatabaseSeeder::class);
        $this->call(AiAssistantUserSeeder::class);
    }
}
