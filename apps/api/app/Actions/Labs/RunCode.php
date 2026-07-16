<?php

declare(strict_types=1);

namespace App\Actions\Labs;

use App\Actions\Telemetry\RecordActivity;
use App\Enums\ActivityType;
use App\Enums\SubmissionKind;
use App\Models\CodeSubmission;
use App\Models\CodingLab;
use App\Models\User;
use App\Support\Judge0\Judge0Client;
use App\Support\Tenancy\TenantContext;

/**
 * Runs a student's code against Judge0 synchronously (PRD §6.5): a free `run`, or
 * a `submit` graded against the lab's hidden test cases. Stores a `code_submission`
 * and emits code telemetry (PRD §6.4). Expected outputs never leave the server.
 */
final readonly class RunCode
{
    public function __construct(
        private Judge0Client $judge0,
        private RecordActivity $activity,
    ) {}

    public function handle(User $user, CodingLab $lab, string $source, SubmissionKind $kind): CodeSubmission
    {
        return app(TenantContext::class)->run($user->tenant, function () use ($user, $lab, $source, $kind): CodeSubmission {
            $languageId = $lab->language->judge0Id();

            $outcome = $kind === SubmissionKind::Submit
                ? $this->grade($lab, $languageId, $source)
                : $this->run($languageId, $source);

            $submission = CodeSubmission::query()->create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'lesson_id' => $lab->lesson_id,
                'language' => $lab->language->value,
                'source' => $source,
                'stdout' => $outcome['stdout'],
                'stderr' => $outcome['stderr'],
                'status' => $outcome['status'],
                'passed_tests' => $outcome['passed'],
                'total_tests' => $outcome['total'],
                'run_time_ms' => $outcome['time'],
                'memory_kb' => $outcome['memory'],
                'kind' => $kind->value,
            ]);

            $this->activity->handle(
                $user,
                $kind === SubmissionKind::Submit ? ActivityType::CodeSubmitted : ActivityType::CodeRun,
                $lab->lesson,
                $outcome['passed'],
                [
                    'total' => $outcome['total'],
                    'status' => $outcome['status'],
                    'language' => $lab->language->value,
                    'error' => $outcome['stderr'] !== '' ? substr($outcome['stderr'], 0, 120) : null,
                ],
            );

            return $submission;
        });
    }

    /**
     * @return array{stdout: string, stderr: string, status: string, time: int, memory: int, passed: int, total: int}
     */
    private function run(int $languageId, string $source): array
    {
        $exec = $this->judge0->execute($languageId, $source);

        return [
            'stdout' => $exec->stdout, 'stderr' => $exec->stderr, 'status' => $exec->statusText,
            'time' => $exec->timeMs, 'memory' => $exec->memoryKb, 'passed' => 0, 'total' => 0,
        ];
    }

    /**
     * @return array{stdout: string, stderr: string, status: string, time: int, memory: int, passed: int, total: int}
     */
    private function grade(CodingLab $lab, int $languageId, string $source): array
    {
        $cases = $lab->test_cases ?? [];
        $passed = 0;
        $stdout = '';
        $stderr = '';
        $status = 'Accepted';
        $time = 0;
        $memory = 0;

        foreach ($cases as $case) {
            $exec = $this->judge0->execute($languageId, $source, $case['stdin'] ?? null);
            $stdout = $exec->stdout;
            $stderr = $exec->stderr;
            $status = $exec->statusText;
            $time = max($time, $exec->timeMs);
            $memory = max($memory, $exec->memoryKb);

            if ($exec->accepted() && trim($exec->stdout) === trim((string) ($case['expected_stdout'] ?? ''))) {
                $passed++;
            }
        }

        return compact('stdout', 'stderr', 'status', 'time', 'memory', 'passed') + ['total' => count($cases)];
    }
}
