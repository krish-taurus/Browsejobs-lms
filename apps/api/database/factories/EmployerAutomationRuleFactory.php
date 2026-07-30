<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EmployerAutomationRule;
use App\Models\EmployerJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmployerAutomationRule> */
final class EmployerAutomationRuleFactory extends Factory
{
    protected $model = EmployerAutomationRule::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employer_job_id' => EmployerJob::factory(),
            'created_by_id' => User::factory(),
            'trigger' => EmployerAutomationRule::TRIGGER_APPLICATION_GRADED,
            'round' => null,
            'min_score' => 70,
            'action' => EmployerAutomationRule::ACTION_ADVANCE,
            'target_stage' => 'shortlisted',
            'enabled' => true,
        ];
    }

    public function interviewGraded(string $round = 'l1', string $targetStage = 'l2'): self
    {
        return $this->state([
            'trigger' => EmployerAutomationRule::TRIGGER_INTERVIEW_GRADED,
            'round' => $round,
            'target_stage' => $targetStage,
        ]);
    }
}
