<?php

declare(strict_types=1);

namespace App\Support\Employers;

use App\Actions\Employers\CreateEmployerJob;
use App\Actions\Employers\DraftJobDescription;
use App\Actions\Employers\PublishEmployerJob;
use App\Actions\Employers\ReadHiringIntent;
use App\Actions\Employers\ReceiveEmployerWhatsApp;
use App\Actions\Employers\ReviseJobDescription;
use App\Models\EmployerAgentAction;
use App\Models\EmployerWhatsappCode;
use App\Models\EmployerWhatsappLink;
use App\Models\EmployerWhatsappMessage;
use App\Models\EmployerWorkspace;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Employers\Agent\AgentWords;
use App\Support\Employers\Agent\HiringAgent;
use App\Support\Tenancy\TenantContext;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Post jobs on WhatsApp (hiring pipeline step 1).
 *
 * The conversation, one inbound message at a time:
 *   JOIN BJ-XXXXXX      → link this phone to the workspace (as that member)
 *   "need 2 java devs…" → read the request, draft the JD, send a summary
 *   any change          → revise the draft and send the summary again
 *   YES                 → create + publish the JD (the shortlist agent starts)
 *   CANCEL              → throw the draft away
 *
 * And once jobs are live, the same chat runs them (agentic hiring, via
 * HiringAgent): "send L1 for the Java role", "invite the ones who cleared
 * L1 to L2", "make an offer to Priya" each become a proposal; YES carries it
 * out, NO holds it. Taurus also asks first at milestones (ProposeHiringStep)
 * for jobs posted here, which approve each round. Questions ("how many
 * cleared L1?") are answered from the hiring floor.
 *
 * Nothing goes live without the client's YES — the agent drafts, the client
 * decides. All replies are free text inside the 24-hour window the client's
 * own message opened.
 */
final readonly class HiringChatAgent
{
    private const YES = ['yes', 'y', 'yes post', 'post', 'post it', 'ok', 'okay', 'confirm', 'confirmed', 'go', 'go ahead', 'publish', 'haan', 'han', 'ha', 'haa', 'ji', 'done', '👍'];

    private const CANCEL = ['cancel', 'discard', 'stop', 'delete', 'nahi', 'no'];

    private const GREETING = ['hi', 'hello', 'hey', 'help', 'menu', 'start', 'namaste'];

    private const REPORT = ['shortlist', 'report', 'status', 'candidates', 'shortlist report', 'show shortlist', 'list'];

    public function __construct(
        private WhatsAppClient $whatsapp,
        private ReadHiringIntent $intent,
        private DraftJobDescription $draftJd,
        private ReviseJobDescription $reviseJd,
        private CreateEmployerJob $createJob,
        private PublishEmployerJob $publishJob,
        private AuditLogger $audit,
        private VoiceNoteTranscriber $voice,
        private ShortlistReport $reports,
        private HiringAgent $agent,
    ) {}

    public function handle(EmployerWhatsappMessage $in): void
    {
        $text = trim($in->body);

        if (preg_match(ReceiveEmployerWhatsApp::CODE_PATTERN, $text, $m) === 1) {
            $this->join($in, strtoupper($m[0]));

            return;
        }

        $link = $in->link ?? EmployerWhatsappLink::query()->where('phone', $in->phone)->first();
        if ($link === null) {
            $this->reply($in->phone, null, 'To post jobs here, scan the WhatsApp QR code on your BrowseJobs employer page, or send JOIN followed by the code shown there.');

            return;
        }

        $link->update(['last_inbound_at' => now()]);
        $workspace = $link->workspace;
        $user = $link->user;

        if ($workspace === null || $user === null || ! $this->canPost($workspace, $user)) {
            $this->reply($in->phone, $link, 'This number can no longer post jobs for '.($workspace->name ?? 'this company').'. Ask your workspace owner to give you recruiter access, then scan the QR code again.');

            return;
        }

        // A voice note: download it from WhatsApp and transcribe it, then carry on as if typed.
        if ($in->media_id !== null && preg_match('/^\[(audio|voice) message\]$/i', $text) === 1) {
            $heard = $this->voice->transcribe($in->media_id);
            if ($heard === null) {
                $this->reply($in->phone, $link, 'Sorry, I could not hear that voice note clearly. Please try again, or type the job details.');

                return;
            }
            $in->update(['body' => "🎤 {$heard}"]);
            $this->reply($in->phone, $link, "🎤 I heard: \"{$heard}\"");
            $text = $heard;
        }

        $word = $this->word($text);

        if (preg_match('/^\[[a-z]+ message\]$/i', $text) === 1) {
            $this->reply($in->phone, $link, 'I can read typed messages and voice notes. Please type or say the job details: the role, experience, location and how many people you need.');

            return;
        }

        if (in_array($word, self::CANCEL, true) && $link->state === EmployerWhatsappLink::STATE_CONFIRMING) {
            $link->update(['state' => EmployerWhatsappLink::STATE_IDLE, 'draft' => null]);
            $this->reply($in->phone, $link, 'Discarded. Nothing was posted. Send me a new role whenever you are ready.');

            return;
        }

        if ($link->state === EmployerWhatsappLink::STATE_CONFIRMING && in_array($word, self::YES, true)) {
            $this->post($in->phone, $link, $workspace, $user);

            return;
        }

        // Taurus asked something (a milestone, or an instruction from this chat): YES runs it, NO holds it.
        if ($link->state !== EmployerWhatsappLink::STATE_CONFIRMING) {
            $pending = $this->agent->pending($workspace, $user, $link);
            if ($pending !== null && (AgentWords::isYes($text) || AgentWords::isNo($text))) {
                $answer = AgentWords::isYes($text) ? $this->agent->confirm($pending, $user) : $this->agent->hold($pending, $user);
                $this->reply($in->phone, $link, $answer->text);

                return;
            }
        }

        if (in_array($word, self::REPORT, true)) {
            $this->reply($in->phone, $link, $this->reports->latestFor($link));

            return;
        }

        if (in_array($word, self::GREETING, true) || in_array($word, self::CANCEL, true)) {
            $this->reply($in->phone, $link, $this->help($workspace, $link));

            return;
        }

        if ($link->state === EmployerWhatsappLink::STATE_CONFIRMING && is_array($link->draft)) {
            $this->revise($in->phone, $link, $workspace, $user, $text);

            return;
        }

        // An instruction or a question about the pipeline; a new role falls through to the draft.
        if (self::looksLikePipelineTalk($text)) {
            $answer = $this->agent->respond($workspace, $user, $text, EmployerAgentAction::CHANNEL_WHATSAPP, null, $link);
            if (! in_array($answer->intent, ['post_job', 'unknown'], true)) {
                $this->reply($in->phone, $link, $answer->text);

                return;
            }
        }

        $this->draft($in->phone, $link, $workspace, $user, $text);
    }

    private function join(EmployerWhatsappMessage $in, string $code): void
    {
        $row = EmployerWhatsappCode::withoutGlobalScopes()->where('code', $code)->first();
        if ($row === null || $row->expires_at->isPast()) {
            $this->reply($in->phone, null, 'That code is not valid any more. Open your BrowseJobs employer page and scan the new QR code.');

            return;
        }

        $workspace = EmployerWorkspace::withoutGlobalScopes()->find($row->employer_workspace_id);
        $user = User::query()->withoutGlobalScopes()->find($row->user_id);
        if ($workspace === null || $user === null || ! $this->canPost($workspace, $user)) {
            $this->reply($in->phone, null, 'This code cannot post jobs. Ask your workspace owner for recruiter access, then scan the QR code again.');

            return;
        }

        $link = EmployerWhatsappLink::withoutGlobalScopes()->updateOrCreate(
            ['phone' => $in->phone],
            [
                'tenant_id' => $workspace->tenant_id,
                'employer_workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'state' => EmployerWhatsappLink::STATE_IDLE,
                'draft' => null,
                'last_inbound_at' => now(),
            ],
        );
        $in->update(['employer_whatsapp_link_id' => $link->id]);

        $this->audit->log('employer.whatsapp_linked', $link, [
            'workspace_id' => $workspace->id,
            'phone_last4' => substr($in->phone, -4),
        ], $user);

        $this->reply($in->phone, $link, "Connected to *{$workspace->name}*. You can now post jobs from this chat.\n\n".$this->example());
    }

    private function draft(string $phone, EmployerWhatsappLink $link, EmployerWorkspace $workspace, User $user, string $text): void
    {
        try {
            $intent = $this->intent->handle($user, $text);
        } catch (ValidationException) {
            $this->reply($phone, $link, "I could not read that as a job. Tell me the role and what you need.\n\n".$this->example());

            return;
        }

        if ($intent['title'] === '') {
            $this->reply($phone, $link, "Which role are you hiring for?\n\n".$this->example());

            return;
        }

        try {
            $jd = $this->draftJd->handle($user, $workspace, $intent['title'], [
                'notes' => $text,
                'experience_min_years' => $intent['experience_min_years'],
                'experience_max_years' => $intent['experience_max_years'],
                'locations' => $intent['locations'],
            ]);
        } catch (ValidationException $e) {
            $this->reply($phone, $link, 'I could not draft that job right now: '.$this->firstError($e).' Please try again in a minute.');

            return;
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
            'request' => mb_substr($text, 0, 1200),
        ];

        $link->update(['state' => EmployerWhatsappLink::STATE_CONFIRMING, 'draft' => $draft]);
        $this->reply($phone, $link, $this->summary($draft));
    }

    private function revise(string $phone, EmployerWhatsappLink $link, EmployerWorkspace $workspace, User $user, string $text): void
    {
        /** @var array<string, mixed> $draft */
        $draft = $link->draft;

        // Field changes ("make it 3-6 years", "Pune instead", "need 4 people").
        try {
            $intent = $this->intent->handle($user, $text);
        } catch (ValidationException) {
            $intent = null;
        }

        // A different role altogether is a new job, not an edit.
        if ($intent !== null && $intent['title'] !== '' && ! $this->sameRole($intent['title'], (string) $draft['title'])) {
            $this->draft($phone, $link, $workspace, $user, $text);

            return;
        }

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

        $link->update(['draft' => $draft]);
        $this->reply($phone, $link, "Updated.\n\n".$this->summary($draft));
    }

    private function post(string $phone, EmployerWhatsappLink $link, EmployerWorkspace $workspace, User $user): void
    {
        /** @var array<string, mixed> $d */
        $d = $link->draft ?? [];

        try {
            $job = $this->createJob->handle($workspace, $user, [
                'title' => (string) $d['title'],
                'description' => (string) $d['description'],
                'role_family' => $d['role_family'] ?? null,
                'skills' => array_values((array) ($d['skills'] ?? [])),
                'experience_min_years' => (int) ($d['experience_min_years'] ?? 0),
                'experience_max_years' => isset($d['experience_max_years']) ? (int) $d['experience_max_years'] : null,
                'locations' => array_values((array) ($d['locations'] ?? [])),
                'remote' => (bool) ($d['remote'] ?? false),
                'openings' => max(1, (int) ($d['openings'] ?? 1)),
            ]);
            // Remember this chat before publishing, so the shortlist report comes back here,
            // and have Taurus ask here before each interview round goes out.
            $job->update([
                'whatsapp_link_id' => $link->id,
                'shortlist_settings' => ['confirm_rounds' => true] + ShortlistSettings::forJob($job)->toArray(),
            ]);
            $job = $this->publishJob->handle($job, $user);
        } catch (ValidationException $e) {
            $this->reply($phone, $link, 'I could not post it: '.$this->firstError($e));

            return;
        } catch (Throwable $e) {
            report($e);
            $this->reply($phone, $link, 'Something went wrong while posting. Nothing went live — please reply YES again in a minute.');

            return;
        }

        $link->update(['state' => EmployerWhatsappLink::STATE_IDLE, 'draft' => null]);

        $url = rtrim((string) config('app.frontend_url'), '/')."/employer/jobs/{$job->id}?tab=shortlist";
        $this->reply($phone, $link, "✅ *{$job->title}* is live.\n\nThe shortlist agent is searching BrowseJobs students and your own candidate lists now. I will send you the shortlist in this chat in a minute, and ask you before anyone is invited to L1.\n\nFull list: {$url}\n\nSend another role any time, *SHORTLIST* to see the latest shortlist, or just tell me what to do next.");
    }

    /** @param  array<string, mixed>  $d */
    private function summary(array $d): string
    {
        $min = $d['experience_min_years'];
        $max = $d['experience_max_years'];
        $experience = $min === null && $max === null ? 'Not given (any)' : ($min ?? 0).($max !== null ? "–{$max}" : '+').' years';
        $where = $d['remote'] ? 'Remote' : (($d['locations'] ?? []) !== [] ? implode(', ', $d['locations']) : 'Not given');
        $skills = ($d['skills'] ?? []) !== [] ? implode(', ', array_slice($d['skills'], 0, 12)) : 'Not given';
        $about = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $d['description'])) ?? ''), 420);

        return "📝 *Job draft*\n"
            ."*Role:* {$d['title']}\n"
            ."*Experience:* {$experience}\n"
            ."*Location:* {$where}\n"
            .'*Openings:* '.($d['openings'] ?? 1)."\n"
            ."*Skills:* {$skills}\n\n"
            ."{$about}\n\n"
            .'Reply *YES* to post it live, or tell me what to change (for example: "make it 3–6 years", "add Kafka", "Pune instead"). Reply *CANCEL* to discard.';
    }

    private function help(EmployerWorkspace $workspace, EmployerWhatsappLink $link): string
    {
        $waiting = $link->user !== null ? $this->agent->pending($workspace, $link->user, $link) : null;
        $pending = match (true) {
            $link->state === EmployerWhatsappLink::STATE_CONFIRMING => "\n\nYou have a draft waiting: reply *YES* to post it or *CANCEL* to discard it.",
            $waiting !== null => "\n\nWaiting for your answer: {$waiting->summary}\nReply *YES* to go ahead or *NO* to hold.",
            default => '',
        };

        return "Hi! This chat runs hiring for *{$workspace->name}* on BrowseJobs.\n\n".$this->example()
            ."\n\nOnce a job is live, just tell me what to do: _\"send the L1 invites\"_, _\"invite everyone who cleared L1 to L2\"_, _\"make an offer to Priya\"_. I always ask before anything goes out."
            ."\n\nSend *SHORTLIST* to see who the shortlist agent found for your latest job.".$pending;
    }

    /**
     * Worth reading as an instruction or a question rather than a new role.
     * Anything else goes straight to the JD draft, as it always has; a new
     * role that trips this still gets there, one model call later.
     */
    public static function looksLikePipelineTalk(string $text): bool
    {
        return preg_match('/\?\s*$|\b(l\s?[1-9]|round|rounds|interviews?|invites?|invitations?|send|start|proceed|go ahead|move|advance|shortlisted|reject|offers?|hired?|pause|close|publish|candidates?|pipeline|cleared|passed|panel|how many|where are we|who)\b/iu', $text) === 1;
    }

    private function example(): string
    {
        return "Tell me about the role in one message, for example:\n_\"We need 2 Data Engineers in Bengaluru, 3–5 years, Python, SQL, Airflow, AWS.\"_";
    }

    private function reply(string $phone, ?EmployerWhatsappLink $link, string $body): void
    {
        $message = EmployerWhatsappMessage::query()->create([
            'tenant_id' => $link->tenant_id ?? app(TenantContext::class)->id(),
            'employer_whatsapp_link_id' => $link?->id,
            'phone' => $phone,
            'direction' => 'out',
            'body' => $body,
            'status' => 'sent',
        ]);

        try {
            $message->update(['wamid' => $this->whatsapp->sendMessage($phone, $body)]);
        } catch (Throwable $e) {
            report($e);
            $message->update(['status' => 'failed']);
        }
    }

    private function canPost(EmployerWorkspace $workspace, User $user): bool
    {
        $member = $workspace->memberFor($user);

        return $member !== null
            && $member->role->managesPipeline()
            && $workspace->status !== EmployerWorkspace::STATUS_SUSPENDED;
    }

    private function sameRole(string $a, string $b): bool
    {
        $norm = fn (string $s) => trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($s)) ?? '');

        return $norm($a) === $norm($b) || str_contains($norm($a), $norm($b)) || str_contains($norm($b), $norm($a));
    }

    private function word(string $text): string
    {
        return trim(preg_replace('/[^\p{L}\p{N}\s👍]+/u', '', mb_strtolower($text)) ?? '');
    }

    private function firstError(ValidationException $e): string
    {
        return (string) (collect($e->errors())->flatten()->first() ?? $e->getMessage());
    }
}
