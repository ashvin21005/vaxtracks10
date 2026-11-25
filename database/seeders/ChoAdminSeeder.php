<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class ChoAdminSeeder extends Seeder
{
    public function run(?string $password = null): void
    {
        $password ??= env('CHO_ADMIN_PASSWORD');

        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('Set CHO_ADMIN_PASSWORD to a unique value of at least 12 characters before seeding.');
        }

        User::updateOrCreate(
            ['email' => env('CHO_ADMIN_EMAIL', 'cho.admin@vaxtrack.local')],
            [
                'name' => env('CHO_ADMIN_NAME', 'CHO Administrator'),
                'username' => env('CHO_ADMIN_USERNAME', 'cho.admin'),
                'role' => 'CHO_ADMIN',
                'account_status' => 'ACTIVE',
                'password' => $password,
                'must_change_password' => true,
            ]
        );
    }
}
