<?php

declare(strict_types=1);

namespace App\Support\Employers;

use App\Actions\Employers\CreateEmployerJob;
use App\Actions\Employers\DraftJobDescription;
use App\Actions\Employers\OpenInterviewNow;
use App\Actions\Employers\PublishEmployerJob;
use App\Actions\Employers\ReadHiringIntent;
use App\Actions\Employers\ReceiveEmployerWhatsApp;
use App\Actions\Employers\ReviseJobDescription;
use App\Enums\EmployerJobStatus;
use App\Jobs\BookInterviewMeeting;
use App\Jobs\SendInterviewLinkMessage;
use App\Models\EmployerAgentAction;
use App\Models\EmployerJob;
use App\Models\EmployerWhatsappCode;
use App\Models\EmployerWhatsappLink;
use App\Models\EmployerWhatsappMessage;
use App\Models\EmployerWorkspace;
use App\Models\InterviewInvite;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Employers\Agent\AgentWords;
use App\Support\Employers\Agent\ClientLanguage;
use App\Support\Employers\Agent\ClientSaid;
use App\Support\Employers\Agent\HiringAgent;
use App\Support\Employers\Agent\ReplyLocalizer;
use App\Support\Employers\Agent\WhatsAppChoice;
use App\Support\Tenancy\TenantContext;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
    private const YES = ['yes', 'y', 'yes post', 'post', 'post it', 'ok', 'okay', 'confirm', 'confirmed', 'go', 'go ahead', 'publish', 'haan', 'han', 'ha', 'haa', 'ji', 'done', '👍',
        'हाँ', 'हां', 'जी', 'ठीक है', 'theek hai', 'thik hai', 'houdu', 'haudu', 'sari', 'ಹೌದು', 'ಸರಿ', 'ಆಯ್ತು', 'post karo', 'पोस्ट करो'];

    private const CANCEL = ['cancel', 'discard', 'stop', 'delete', 'nahi', 'no', 'नहीं', 'रद्द', 'रद्द करो', 'radd', 'beda', 'illa', 'ಬೇಡ', 'ಇಲ್ಲ', 'ರದ್ದು'];

    private const GREETING = ['hi', 'hello', 'hey', 'help', 'menu', 'start', 'namaste'];

    private const REPORT = ['shortlist', 'report', 'status', 'candidates', 'shortlist report', 'show shortlist', 'list'];

    /** A reply that only asks for the interview reports waiting outside the 24-hour window. */
    private const FETCH_REPORTS = ['ok', 'okay', 'send', 'send it', 'send report', 'send reports', 'reports', 'get report', 'hi', 'hello', 'thanks', 'thank you', '👍'];

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
        private InterviewReportDelivery $interviewReports,
        private OpenInterviewNow $openNow,
        private WhatsAppChoice $choices,
        private ReplyLocalizer $localizer,
        private ClientSaid $said,
    ) {}

    public function handle(EmployerWhatsappMessage $in): void
    {
        $text = trim($in->body);
        $this->said->remember(null);

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

        // Interview reports that waited for WhatsApp's 24-hour window go now. A
        // bare "ok" / "send" was only asking for them: nothing more to say. Any
        // other message (a YES to a draft, a new role) carries on as usual.
        if ($this->interviewReports->deliverPending($link) > 0 && in_array($this->word($text), self::FETCH_REPORTS, true)) {
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

        // Answer in the language they write in (Hindi, Kannada, English), from here on.
        $this->said->remember($this->languageOf($link, $text));

        $word = $this->word($text);

        if (preg_match('/^\[[a-z]+ message\]$/i', $text) === 1) {
            $this->reply($in->phone, $link, 'I can read typed messages and voice notes. Please type or say the job details: the role, experience, location and how many people you need.');

            return;
        }

        // A question Taurus asked in this chat ("which role?", "which candidates?"): this message answers it.
        if ($this->choices->active($link) !== null) {
            $this->reply($in->phone, $link, $this->choices->answer($workspace, $user, $link, $text));

            return;
        }

        // "Retake L1", "send Kunal's report": ask which role, then which candidates. This comes before a
        // waiting job draft, which would otherwise read it as an edit to the draft.
        if (($request = WhatsAppChoice::detect($text)) !== null && $this->canPost($workspace, $user)) {
            $reply = $this->choices->start($workspace, $link, $request, $text);
            $draftWaiting = $link->state === EmployerWhatsappLink::STATE_CONFIRMING && is_array($link->draft);
            $this->reply($in->phone, $link, $reply.($draftWaiting ? "\n\n(Your new job draft is still waiting. Reply *YES* to post it or *CANCEL* to discard it when we are done.)" : ''));

            return;
        }

        // A proposal made after the draft (a retake, an invite) is what a bare YES or NO answers.
        $newer = $link->state === EmployerWhatsappLink::STATE_CONFIRMING ? $this->proposalNewerThanDraft($workspace, $user, $link) : null;
        if ($newer !== null && (AgentWords::isYes($text) || AgentWords::isNo($text))) {
            $answer = AgentWords::isYes($text) ? $this->agent->confirm($newer, $user) : $this->agent->hold($newer, $user);
            $this->reply($in->phone, $link, $answer->text);

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

        // "Can we do the interview now": open the interviews the candidates have booked, today.
        if (self::wantsInterviewNow($text) && $this->canPost($workspace, $user)) {
            $this->reply($in->phone, $link, $this->startInterviewsNow($workspace, $link));

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

    /** The open proposal in this chat, if it was made after the waiting job draft. */
    private function proposalNewerThanDraft(EmployerWorkspace $workspace, User $user, EmployerWhatsappLink $link): ?EmployerAgentAction
    {
        $proposal = $this->agent->pending($workspace, $user, $link);
        $draftAt = is_array($link->draft) && isset($link->draft['at']) ? Carbon::parse((string) $link->draft['at']) : null;

        return $proposal !== null && $draftAt !== null && $proposal->created_at !== null && $proposal->created_at->gt($draftAt) ? $proposal : null;
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

        $draft['at'] = now()->toIso8601String();
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
            // Remember this chat before publishing, so the shortlist report, and an update at
            // every later stage, come back here. The agents then run each step on their own;
            // a client who wants to approve each round switches that on in the AI shortlist tab.
            $job->update(['whatsapp_link_id' => $link->id]);
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
        $this->reply($phone, $link, "✅ *{$job->title}* is live.\n\nThe shortlist agent is searching BrowseJobs students and your own candidate lists now. I will send you the shortlist in this chat in a minute, invite them to L1 on WhatsApp by myself, and tell you at every stage: booked, scored, cleared.\n\nFull list: {$url}\n\nSend another role any time, *SHORTLIST* to see the latest shortlist, or just tell me what to change.");
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
            ."\n\nI can also let someone *retake* a round (_\"retake L1\"_) and send a candidate's *report* with the recording (_\"send Kunal's report\"_). I ask which role and which candidates, one question at a time."
            ."\n\nSend *SHORTLIST* to see who the shortlist agent found for your latest job.".$pending;
    }

    /** "Can we do the interview now?" — asking for the booked interviews to start today, not a question about them. */
    public static function wantsInterviewNow(string $text): bool
    {
        return mb_strlen($text) <= 160
            && preg_match('/\b(interview|interviews|l\s?[12]|round)\b/iu', $text) === 1
            && preg_match('/\b(now|right now|right away|immediately|asap|abhi|aaj|today)\b/iu', $text) === 1
            && preg_match('/\b(how many|what|who|which|where|when|status|kitne|kaun|kab|kya)\b/iu', $text) !== 1;
    }

    /**
     * Open the interviews people have booked for the client's latest live role,
     * now instead of at their slot, and send each their link. People who were
     * invited but have not picked a slot are left to reply NOW themselves.
     */
    private function startInterviewsNow(EmployerWorkspace $workspace, EmployerWhatsappLink $link): string
    {
        $job = EmployerJob::query()->where('employer_workspace_id', $workspace->id)
            ->where('status', EmployerJobStatus::Published->value)
            ->orderByRaw('CASE WHEN whatsapp_link_id = ? THEN 0 ELSE 1 END', [$link->id])
            ->latest('id')
            ->first();
        if ($job === null) {
            return 'You have no live role yet. Tell me about a role and I will post it.';
        }

        $invites = InterviewInvite::query()->where('employer_job_id', $job->id)
            ->where('round', '!=', InterviewInvite::ROUND_SCREEN)
            ->whereIn('status', [InterviewInvite::SENT, InterviewInvite::SCHEDULED])
            ->with('candidate:id,name')
            ->get();
        $booked = $invites->where('status', InterviewInvite::SCHEDULED);
        $unbooked = $invites->where('status', InterviewInvite::SENT)->count();
        $tail = $unbooked > 0 ? "\n\n{$unbooked} more ".($unbooked === 1 ? 'is invited but has not' : 'are invited but have not').' picked a slot yet. They can reply *NOW* to start straight away.' : '';

        if ($booked->isEmpty()) {
            return "Nobody has booked an interview for *{$job->title}* yet.".($unbooked > 0 ? $tail : ' Interviews are offered to the shortlist automatically.');
        }

        $done = [];
        foreach ($booked as $invite) {
            $result = $this->openNow->handle($invite);
            if ($result['mode'] === 'room') {
                SendInterviewLinkMessage::dispatch($invite->id);
                $done[] = (string) ($invite->candidate?->name ?? 'A candidate');
            } elseif ($result['mode'] === 'zoom' && $result['at'] !== null) {
                BookInterviewMeeting::dispatch($invite->id);
                $done[] = ($invite->candidate?->name ?? 'A candidate').' (Zoom at '.$result['at']->copy()->setTimezone('Asia/Kolkata')->format('g:i A').')';
            }
        }

        return $done === []
            ? "I could not open the interviews just now for *{$job->title}*. They stay booked for their slots.{$tail}"
            : '⚡ Opened the '.strtoupper((string) $booked->first()->round).' interview now for '.implode(', ', $done)." (*{$job->title}*). They have been sent their link. I will tell you as each one finishes.{$tail}";
    }

    /**
     * Worth reading as an instruction or a question rather than a new role.
     * Anything else goes straight to the JD draft, as it always has; a new
     * role that trips this still gets there, one model call later.
     */
    public static function looksLikePipelineTalk(string $text): bool
    {
        return preg_match('/\?\s*$|\b(l\s?[1-9]|round|rounds|interviews?|invites?|invitations?|send|start|proceed|go ahead|move|advance|shortlisted|reject|offers?|hired?|pause|close|publish|candidates?|pipeline|cleared|passed|panel|how many|where are we|who|bhejo|bhejiye|kitne|kaun|kaunsa|kya|batao|dikhao|shuru|kalisi|torisi|yeshtu|yaaru)\b/iu', $text) === 1
            || preg_match('/(?<![\p{L}\p{M}])(इंटरव्यू|राउंड|रिपोर्ट|उम्मीदवार|शॉर्टलिस्ट|भेजो|भेजिए|कितने|कौन|बताओ|दिखाओ|शुरू|ऑफर|ಇಂಟರ್ವ್ಯೂ|ರೌಂಡ್|ರಿಪೋರ್ಟ್|ಅಭ್ಯರ್ಥಿ|ಕಳುಹಿಸಿ|ಎಷ್ಟು|ಯಾರು|ತೋರಿಸಿ|ಶುರು)/u', $text) === 1;
    }

    private function example(): string
    {
        return "Tell me about the role in one message, for example:\n_\"We need 2 Data Engineers in Bengaluru, 3–5 years, Python, SQL, Airflow, AWS.\"_";
    }

    private function reply(string $phone, ?EmployerWhatsappLink $link, string $body): void
    {
        $body = $this->localizer->localize($link?->user, $this->said->text(), $body);

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
        return trim(preg_replace('/[^\p{L}\p{M}\p{N}\s👍]+/u', '', mb_strtolower($text)) ?? '');
    }

    /**
     * The text whose language the reply should follow. A short answer ("ok", "haan") carries no
     * language of its own, so it follows the last longer message this person wrote in Hindi or
     * Kannada; a fresh sentence in English switches back.
     */
    private function languageOf(EmployerWhatsappLink $link, string $text): string
    {
        $key = "hiring-chat-language:{$link->id}";
        try {
            if (ClientLanguage::needsLocalizing($text)) {
                if (mb_strlen($text) >= 12) {
                    Cache::put($key, mb_substr($text, 0, 300), now()->addHours(12));
                }

                return $text;
            }

            if (str_word_count($text) > 3) {
                Cache::forget($key);

                return $text;
            }

            return (string) Cache::get($key, $text);
        } catch (Throwable) {
            return $text;
        }
    }

    private function firstError(ValidationException $e): string
    {
        return (string) (collect($e->errors())->flatten()->first() ?? $e->getMessage());
    }
}
