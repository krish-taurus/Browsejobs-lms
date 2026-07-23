<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ModuleCompleted;
use App\Models\InAppNotification;
use App\Support\Messaging\Messenger;
use App\Support\Scoring\CandidatePerformance;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * After every module a candidate gets their performance report (ADR 0050):
 * attendance, mock participation/score, and assignment completion — the same
 * weighted blend the admin Candidate Performance dashboard ranks by. Honest
 * numbers, no invented claims; sent over the messaging hub (WhatsApp/email
 * per preference) plus an in-app card.
 */
final class SendModuleReport implements ShouldQueue
{
    public function __construct(
        private readonly Messenger $messenger,
        private readonly CandidatePerformance $performance,
    ) {}

    public function handle(ModuleCompleted $event): void
    {
        app(TenantContext::class)->run($event->user->tenant, function () use ($event): void {
            $student = $event->user;
            $c = $this->performance->componentsFor($student);

            $this->messenger->send($student, 'module_report', [
                'name' => $student->name,
                'module' => $event->module->name,
                'total' => number_format($c['total'], 1),
                'attendance' => number_format($c['attendance_pct'], 0),
                'mocks_done' => (string) $c['mocks_done'],
                'mock_score' => number_format($c['mock_pct'], 0),
                'assignments' => "{$c['assignments_done']}/{$c['assignments_total']}",
            ], ['magic' => ['action' => 'report.view', 'payload' => ['redirect' => '/reports']]]);

            InAppNotification::query()->create([
                'tenant_id' => $student->tenant_id,
                'user_id' => $student->id,
                'title' => "Module report: {$event->module->name}",
                'body' => sprintf(
                    'Score %s/100 (%s) · attendance %s%% · %d mocks (avg %s) · assignments %s.',
                    number_format($c['total'], 1),
                    $c['band'],
                    number_format($c['attendance_pct'], 0),
                    $c['mocks_done'],
                    number_format($c['mock_pct'], 0),
                    "{$c['assignments_done']}/{$c['assignments_total']}",
                ),
                'url' => '/reports',
            ]);
        });
    }
}
