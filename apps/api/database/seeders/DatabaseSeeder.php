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
            LiveClassSeeder::class,
            VoucherSeeder::class,
            SupportSeeder::class,
            MonetizationSeeder::class,
            LabSeeder::class,
            ScoringSeeder::class,
            TutorSeeder::class,
            // After SupportSeeder (teams/routes) and PaymentsSeeder (the instalment a
            // deflection answer quotes back to the student).
            SupportCorpusSeeder::class,
            QuizSeeder::class,
            ContentSeeder::class,
            SyllabusSeeder::class,
            AssignmentSeeder::class,
            CertificateSeeder::class,
            ReportsSeeder::class,
            MockBlueprintSeeder::class,
            // After MockBlueprintSeeder — bank rows link to blueprint courses.
            InterviewBankSeeder::class,
            MarketJdSeeder::class,
            SalaryBenchmarkSeeder::class,
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
