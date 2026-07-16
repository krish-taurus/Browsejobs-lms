<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
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
        $this->call([
            TenantSeeder::class,
            RolePermissionSeeder::class,
            CurriculumSeeder::class,
            ReviewSeeder::class,
            CrmSeeder::class,
            PaymentsSeeder::class,
            MessagingSeeder::class,
            ConversionSeeder::class,
        ]);

        $browsejobs = Tenant::query()->where('slug', 'browsejobs')->firstOrFail();

        // Local/dev admin (password: "password"). Staff type; 2FA off locally.
        $admin = User::factory()->create([
            'tenant_id' => $browsejobs->id,
            'name' => 'Test Admin',
            'email' => 'test@example.com',
            'user_type' => 'staff',
        ]);

        $admin->assignRole('admin');
    }
}
