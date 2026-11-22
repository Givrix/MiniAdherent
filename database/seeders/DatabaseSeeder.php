<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@exemple.org');

        User::where('email', '!=', $email)->delete();

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Gestionnaire des adhérents',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'M1niAdherent!')),
            ],
        );
    }
}
