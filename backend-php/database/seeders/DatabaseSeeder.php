<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'ADMIN']);
        Role::firstOrCreate(['name' => 'STAFF']);
        Role::firstOrCreate(['name' => 'CUSTOMER']);

        User::firstOrCreate(
            ['username' => env('ADMIN_USERNAME', 'admin')],
            [
                'email' => env('ADMIN_EMAIL', 'admin@example.com'),
                'password' => password_hash(env('ADMIN_PASSWORD', 'admin123'), PASSWORD_BCRYPT),
                'full_name' => env('ADMIN_FULL_NAME', 'Quản trị viên'),
                'phone' => env('ADMIN_PHONE', '0900000000'),
                'address' => null,
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]
        );
    }
}
