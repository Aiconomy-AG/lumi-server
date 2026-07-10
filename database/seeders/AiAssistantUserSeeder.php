<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AiAssistantUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('chat_ai.user_email');
        $name = config('chat_ai.user_name');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(Str::random(64)),
                'role' => UserRole::Employee,
                'status' => 'available',
                'phone_number' => '',
                'language_flag' => 'en',
                'is_active' => true,
                'must_change_password' => false,
            ]
        );
    }
}
