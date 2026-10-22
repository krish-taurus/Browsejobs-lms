<?php

declare(strict_types=1);

namespace App\Support\Employers\Agent;

use App\Actions\Employers\DraftJobDescription;
use App\Actions\Employers\ReadHiringIntent;
use App\Actions\Employers\ReviseJobDescription;
use App\Models\EmployerAgentAction;
use App\Models\EmployerWorkspace;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Posting a role from the hiring floor's Ask bar, the way WhatsApp does it:
 * "need 2 data engineers in Pune" becomes a job draft the client reads, changes
 * ("make it 3 to 6 years", "add Kafka", "Pune instead") and then approves.
 *
 * The draft is an EmployerAgentAction of kind post_job, so it gets the same
 * Go ahead / Not now buttons, the same expiry and the same audit trail as every
 * other proposal. Nothing goes live until HiringActionExecutor confirms it.
 */
final readonly class WebJobPosting
{
    public function __construct(
        private ReadHiringIntent $intent,
        private DraftJobDescription $draftJd,
        private ReviseJobDescription $reviseJd,
    ) {}

    /** A new draft, or a change to the draft that is already waiting. */
    public function handle(EmployerWorkspace $workspace, User $user, string $text, ?EmployerAgentAction $open): AgentReply
    {
        if (! HiringActionExecutor::canAct($workspace, $user)) {
            return new AgentReply('chat', 'Only a workspace owner or recruiter can post a role. Ask one of them, or ask your owner for recruiter access.');
        }

        try {
            $intent = $this->intent->handle($user, $text);
        } catch (ValidationException) {
            $intent = null;
        }

        if ($open !== null && ($intent === null || $intent['title'] === '' || $this->sameRole($intent['title'], (string) ($open->params['title'] ?? '')))) {
            return $this->revise($open, $user, $text, $intent);
        }

        if ($intent === null) {
            return new AgentReply('chat', 'I could not read that as a role. Tell me the role and what you need, for example: "We need 2 Data Engineers in Bengaluru, 3 to 5 years, Python, SQL, Airflow."');
        }
        if ($intent['title'] === '') {
            return new AgentReply('chat', 'Which role are you hiring for? For example: "We need 2 Data Engineers in Bengaluru, 3 to 5 years, Python, SQL, Airflow."');
        }

        try {
            $jd = $this->draftJd->handle($user, $workspace, $intent['title'], [
                'notes' => $text,
                'experience_min_years' => $intent['experience_min_years'],
                'experience_max_years' => $intent['experience_max_years'],
                'locations' => $intent['locations'],
            ]);
        } catch (ValidationException $e) {
            return new AgentReply('chat', 'I could not draft that role right now: '.(string) (collect($e->errors())->flatten()->first() ?? $e->getMessage()).' Please try again in a minute.');
        }

        // A new role replaces a draft that was still waiting.
        if ($open !== null) {
            $open->update(['status' => EmployerAgentAction::CANCELLED, 'result' => 'Replaced by a new draft.', 'decided_by' => $user->id, 'decided_at' => now()]);
        }

        $draft = [
            'title' => $intent['title'],
            'description' => $jd['description'],
            'skills' => $jd['skills'],
            'role_family' => $jd['role_family'],
            'experience_min_years' => $intent['experience_min_years'],
            'experience_max_years' => $intent['experience_max_years'],
            'locations' => $intent['locations'],
            'remote' => $intent['remote'],
            'openings' => $intent['openings'],
        ];

        $action = EmployerAgentAction::query()->create([
            'tenant_id' => $workspace->tenant_id,
            'employer_workspace_id' => $workspace->id,
            'employer_job_id' => null,
            'user_id' => $user->id,
            'channel' => EmployerAgentAction::CHANNEL_WEB,
            'kind' => EmployerAgentAction::KIND_POST_JOB,
            'params' => $draft,
            'summary' => $this->summary($draft),
            'status' => EmployerAgentAction::PROPOSED,
            'expires_at' => now()->addHours(12),
        ]);

        return new AgentReply('proposed', $action->summary.self::ASK, $action);
    }

    private const ASK = "\n\nShall I post it live? Or tell me what to change, for example: \"make it 3 to 6 years\", \"add Kafka\", \"Pune instead\".";

    /**
     * @param  array{title: string, experience_min_years: int|null, experience_max_years: int|null, locations: list<string>, openings: int|null, remote: bool}|null  $intent
     */
    private function revise(EmployerAgentAction $open, User $user, string $text, ?array $intent): AgentReply
    {
        /** @var array<string, mixed> $draft */
        $draft = $open->params;

        if ($intent !== null) {
            foreach (['experience_min_years', 'experience_max_years', 'openings'] as $field) {
                if ($intent[$field] !== null) {
                    $draft[$field] = $intent[$field];
                }
            }
            if ($intent['locations'] !== []) {
                $draft['locations'] = $intent['locations'];
            }
            if ($intent['remote']) {
                $draft['remote'] = true;
            }
        }

        try {
            $revised = $this->reviseJd->handle($user, (string) $draft['title'], (string) $draft['description'], (array) $draft['skills'], $text);
            $draft['description'] = $revised['description'];
            $draft['skills'] = $revised['skills'];
        } catch (ValidationException) {
            // The field changes above still apply; the description stays as it was.
        }

        $open->update(['params' => $draft, 'summary' => $this->summary($draft), 'expires_at' => now()->addHours(12)]);

        return new AgentReply('proposed', 'Updated. '.$open->summary.self::ASK, $open);
    }

    /** @param  array<string, mixed>  $d */
    private function summary(array $d): string
    {
        $min = $d['experience_min_years'] ?? null;
        $max = $d['experience_max_years'] ?? null;
        $experience = $min === null && $max === null ? 'not given (any)' : ($min ?? 0).($max !== null ? " to {$max}" : ' or more').' years';
        $where = ($d['remote'] ?? false) ? 'Remote' : (($d['locations'] ?? []) !== [] ? implode(', ', (array) $d['locations']) : 'not given');
        $skills = ($d['skills'] ?? []) !== [] ? implode(', ', array_slice((array) $d['skills'], 0, 12)) : 'not given';
        $about = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($d['description'] ?? ''))) ?? ''), 360);

        return "Job draft: {$d['title']}.\n"
            ."Experience: {$experience}. Location: {$where}. Openings: ".($d['openings'] ?? 1).".\n"
            ."Skills: {$skills}.\n"
            .$about;
    }

    private function sameRole(string $a, string $b): bool
    {
        $norm = static fn (string $t): string => preg_replace('/[^a-z0-9]+/', '', mb_strtolower($t)) ?? '';

        return $norm($a) === $norm($b)
            || str_contains($norm($a), $norm($b)) && $norm($b) !== ''
            || str_contains($norm($b), $norm($a)) && $norm($a) !== '';
    }
}
