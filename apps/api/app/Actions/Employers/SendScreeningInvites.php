<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerApplicationStage;
use App\Jobs\SendInterviewLinkMessage;
use App\Models\CvDocument;
use App\Models\EmployerCandidate;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\InterviewInvite;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Employers\InterviewTemplates;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;

/**
 * The shortlist agent found someone whose CV matches the JD but who has no AI
 * interview for the role, so it cannot shortlist them on the CV alone. This
 * sends them the AI screening interview on WhatsApp ("your AI screening
 * interview for <role> is ready — reply OK for the link"); the link opens the
 * JD's own interview. When they finish, the agent runs again
 * (RerunShortlistAfterScreening) and shortlists them if they pass.
 *
 * Per person: an account (client/Naukri candidates get a light one), an
 * application at Applied so the client sees them on the pipeline, and one
 * open "screen" invite. Never twice for the same JD.
 */
final readonly class SendScreeningInvites
{
    public function __construct(
        private EnsureCandidateAccount $accounts,
        private InterviewTemplates $templates,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  list<array{candidate_user_id: int|null, employer_candidate_id: int|null}>  $people
     * @return array{queued: int, skipped: array<string, int>}
     */
    public function handle(EmployerJob $job, array $people): array
    {
        if ($people === []) {
            return ['queued' => 0, 'skipped' => []];
        }
        if (! $this->templates->approved(InterviewTemplates::LINK)) {
            return ['queued' => 0, 'skipped' => ['message not approved by Meta yet' => count($people)]];
        }

        return app(TenantContext::class)->run($job->tenant, function () use ($job, $people): array {
            $queued = 0;
            $skipped = [];
            $skip = function (string $why) use (&$skipped): void {
                $skipped[$why] = ($skipped[$why] ?? 0) + 1;
            };

            foreach ($people as $person) {
                $user = $this->account($person);
                $phone = SendInterviewInvites::whatsappNumber($user?->phone);
                if ($user === null || $phone === null) {
                    $skip('no mobile number');

                    continue;
                }

                $application = $this->application($job, $user);
                if ($application->stage->isTerminal()) {
                    $skip('already rejected or withdrawn');

                    continue;
                }

                $invite = InterviewInvite::query()->firstOrNew([
                    'employer_job_application_id' => $application->id,
                    'round' => InterviewInvite::ROUND_SCREEN,
                ]);
                if ($invite->exists && $invite->status !== InterviewInvite::FAILED) {
                    $skip('already invited');

                    continue;
                }

                $invite->fill([
                    'tenant_id' => $job->tenant_id,
                    'employer_job_id' => $job->id,
                    'candidate_user_id' => $user->id,
                    'phone' => $phone,
                    'status' => InterviewInvite::OPEN,
                    'opened_at' => now(),
                    'sent_at' => now(),
                    'token' => $invite->token ?? Str::random(40),
                    'error' => null,
                ])->save();

                // A steady pace, not a burst — WhatsApp watches sending quality.
                SendInterviewLinkMessage::dispatch($invite->id)->delay(now()->addSeconds($queued * 3));
                $queued++;
            }

            $this->audit->log('employer.screening_invites_sent', $job, ['queued' => $queued, 'skipped' => $skipped]);

            return ['queued' => $queued, 'skipped' => $skipped];
        });
    }

    /** @param  array{candidate_user_id: int|null, employer_candidate_id: int|null}  $person */
    private function account(array $person): ?User
    {
        if (! empty($person['candidate_user_id'])) {
            return User::query()->find($person['candidate_user_id']);
        }

        $candidate = ! empty($person['employer_candidate_id'])
            ? EmployerCandidate::query()->find($person['employer_candidate_id'])
            : null;

        return $candidate !== null ? $this->accounts->handle($candidate) : null;
    }

    /** On the JD's pipeline at Applied; someone already further along stays where they are. */
    private function application(EmployerJob $job, User $user): EmployerJobApplication
    {
        return EmployerJobApplication::query()->firstOrCreate(
            ['employer_job_id' => $job->id, 'candidate_id' => $user->id],
            [
                'tenant_id' => $job->tenant_id,
                'cv_document_id' => CvDocument::query()->where('user_id', $user->id)->latest()->value('id'),
                'mock_attempts' => 0,
                'stage' => EmployerApplicationStage::Applied->value,
            ],
        );
    }
}
