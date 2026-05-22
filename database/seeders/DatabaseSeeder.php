<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => 'demo-store'],
            [
                'name' => 'Demo Store',
                'domain' => 'demo.local',
                'status' => 'active',
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'owner@example.com'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Store Owner',
                'password' => Hash::make('password'),
                'role' => 'owner',
                'status' => 'active',
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Store Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'customer@example.com'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Store Customer',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'status' => 'active',
            ]
        );
    }
}
