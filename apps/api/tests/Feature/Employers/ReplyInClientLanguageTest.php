<?php

declare(strict_types=1);

use App\Models\EmployerJob;
use App\Models\EmployerMember;
use App\Models\EmployerWhatsappLink;
use App\Models\EmployerWorkspace;
use App\Models\JdMock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\AiMessage;
use App\Services\AI\AiResult;
use App\Support\Employers\Agent\AgentWords;
use App\Support\Employers\Agent\ClientLanguage;
use App\Support\Employers\Agent\ReplyLocalizer;
use App\Support\Employers\Agent\WhatsAppChoice;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Stands in for the model: a rewrite request comes back with "[HI] " in front of Taurus's reply
 * (so the test can see it was rewritten and that the facts survived); anything else gets the next
 * queued answer, which is how the command reader is fed.
 */
final class RewritingAi implements AiClient
{
    /** @var list<string> */
    public array $replies = [];

    public int $rewrites = 0;

    public bool $breakLinks = false;

    public bool $fail = false;

    public function complete(AiMessage $message): AiResult
    {
        if (str_contains($message->user, 'You rewrite one message from Taurus')) {
            if ($this->fail) {
                throw new RuntimeException('model is down');
            }
            $this->rewrites++;
            preg_match("/## Taurus's reply \\(in English\\)\n\n(.*)\n\n## Rewrite it/s", $message->user, $m);
            $text = $this->breakLinks ? (string) preg_replace('/\{LINK\d+\}/', '', $m[1]) : $m[1];

            return new AiResult(text: '[HI] '.$text, promptTokens: 10, completionTokens: 10, model: 'fake', stopReason: 'end_turn');
        }

        return new AiResult(text: array_shift($this->replies) ?? '{}', promptTokens: 10, completionTokens: 10, model: 'fake', stopReason: 'end_turn');
    }
}

beforeEach(function (): void {
    config(['app.frontend_url' => 'https://browsejobs.ai']);
    $this->tenant = Tenant::factory()->create();
    $this->recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer', 'name' => 'Meera Iyer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create(['name' => 'Incture']);
    EmployerMember::factory()->for($this->tenant)->create(['employer_workspace_id' => $this->workspace->id, 'user_id' => $this->recruiter->id]);
    $this->job = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id, 'created_by_id' => $this->recruiter->id, 'title' => 'Data Engineer',
    ]);
    JdMock::factory()->for($this->tenant)->ready()->create(['employer_job_id' => $this->job->id]);
    $this->link = EmployerWhatsappLink::create([
        'tenant_id' => $this->tenant->id, 'employer_workspace_id' => $this->workspace->id, 'user_id' => $this->recruiter->id,
        'phone' => '919800000001', 'state' => EmployerWhatsappLink::STATE_IDLE, 'last_inbound_at' => now(),
    ]);
    $this->wa = new FakeWhatsAppClient;
    app()->instance(WhatsAppClient::class, $this->wa);
    $this->ai = new RewritingAi;
    app()->instance(AiClient::class, $this->ai);
    Cache::flush();
});

function clientWrites(object $test, string $text): string
{
    Artisan::call('employer:whatsapp-inbound', ['from' => '919800000001', '--text' => $text, '--id' => 'wamid.'.uniqid()]);

    return (string) collect($test->wa->sent)->where('to', '919800000001')->last()['body'];
}

it('knows Hindi and Kannada from English, in any script', function (): void {
    foreach (['L1 दोबारा करवाओ', 'कुणाल की रिपोर्ट भेजो', 'ಕುನಾಲ್ ರಿಪೋರ್ಟ್ ಕಳುಹಿಸಿ', 'L1 ke invites bhejo', 'Kunal ka report dikhao', 'nanage report beku', 'haan'] as $said) {
        expect(ClientLanguage::needsLocalizing($said))->toBeTrue($said);
    }
    foreach (['retake L1', 'Send the L1 invites for the Java role', 'how many cleared L1?', 'Yes', '', '   ', 'Show me the shortlist'] as $said) {
        expect(ClientLanguage::needsLocalizing($said))->toBeFalse($said);
    }
});

it('reads Hindi and Kannada yes and no, with their marks intact', function (): void {
    foreach (['हाँ', 'हां जी', 'ठीक है', 'जी', 'ಹೌದು', 'ಸರಿ', 'houdu', 'Theek hai!', 'haan bhej do'] as $yes) {
        expect(AgentWords::isYes($yes))->toBeTrue($yes);
    }
    foreach (['नहीं', 'रुको', 'ಬೇಡ', 'ಇಲ್ಲ', 'ruko', 'abhi nahi'] as $no) {
        expect(AgentWords::isNo($no))->toBeTrue($no);
        expect(AgentWords::isYes($no))->toBeFalse($no);
    }
    // A yes that carries a new instruction is read as an instruction.
    expect(AgentWords::isYes('हाँ लेकिन सिर्फ 2 को'))->toBeFalse()
        ->and(AgentWords::isYes('haan lekin sirf top 2'))->toBeFalse();
});

it('starts a retake or a report from Hindi and Kannada, with Devanagari or Kannada digits', function (): void {
    expect(WhatsAppChoice::detect('L1 dobara karna hai'))->toBe(['type' => 'retake', 'round' => 'l1'])
        ->and(WhatsAppChoice::detect('L१ दोबारा करवाओ'))->toBe(['type' => 'retake', 'round' => 'l1'])
        ->and(WhatsAppChoice::detect('ಮತ್ತೆ L2 ರೌಂಡ್'))->toBe(['type' => 'retake', 'round' => 'l2'])
        ->and(WhatsAppChoice::detect('कुणाल की रिपोर्ट भेजो'))->toBe(['type' => 'report', 'round' => null])
        ->and(WhatsAppChoice::detect('ಕುನಾಲ್ ರಿಪೋರ್ಟ್ ಕಳುಹಿಸಿ'))->toBe(['type' => 'report', 'round' => null])
        ->and(WhatsAppChoice::detect('retake L1'))->toBe(['type' => 'retake', 'round' => 'l1'])
        ->and(WhatsAppChoice::detect('Need 2 data engineers in Pune'))->toBeNull();
    expect(WhatsAppChoice::roundIn('२'))->toBe('l2'); // a bare Devanagari digit answers "which round?"
});

it('does not call the model for English', function (): void {
    $localizer = app(ReplyLocalizer::class);

    expect($localizer->localize($this->recruiter, 'retake L1', 'Which role?'))->toBe('Which role?')
        ->and($localizer->localize($this->recruiter, null, 'Which role?'))->toBe('Which role?')
        ->and($this->ai->rewrites)->toBe(0);
});

it('rewrites the reply for a Hindi or Kannada speaker, and keeps links and the typed words', function (): void {
    $localizer = app(ReplyLocalizer::class);
    $reply = "Retake L1 for *Kunal Shah* (28/100)?\nReport: https://browsejobs.ai/hire-interview/abc123\nReply *YES* to go ahead or *NO* to hold.";

    $out = $localizer->localize($this->recruiter, 'L1 ke liye dobara bhejo', $reply);

    expect($out)->toBe('[HI] '.$reply)->and($this->ai->rewrites)->toBe(1);
});

it('sends the English reply untouched when the rewrite drops a link or the model is down', function (): void {
    $localizer = app(ReplyLocalizer::class);
    $reply = "Report: https://browsejobs.ai/hire-interview/abc123\nReply *YES* to go ahead.";

    $this->ai->breakLinks = true;
    expect($localizer->localize($this->recruiter, 'रिपोर्ट भेजो', $reply))->toBe($reply);

    $this->ai->breakLinks = false;
    $this->ai->fail = true;
    expect($localizer->localize($this->recruiter, 'रिपोर्ट भेजो', $reply))->toBe($reply);
});

it('answers a Hindi message on WhatsApp in Hindi, and an English one in English with no extra model call', function (): void {
    // Two roles, so Taurus has to ask which one.
    EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id, 'created_by_id' => $this->recruiter->id, 'title' => 'Java Developer',
    ]);
    $hindi = clientWrites($this, 'L1 dobara karna hai');
    expect($hindi)->toStartWith('[HI] ')->and($hindi)->toContain('Which role?')->and($this->ai->rewrites)->toBe(1);

    // Their answer is a bare number: it still gets the reply in Hindi, because they were writing Hindi.
    $next = clientWrites($this, '1');
    expect($next)->toStartWith('[HI] ')->and($this->ai->rewrites)->toBe(2);

    Cache::flush();
    $this->link->update(['choice' => null]);
    $english = clientWrites($this, 'retake L1 for the data engineer');
    expect($english)->not->toStartWith('[HI] ')->and($this->ai->rewrites)->toBe(2);
});

it('answers the web Ask bar in the language the recruiter used', function (): void {
    $this->actingAs($this->recruiter);
    $url = "/api/v1/employer/workspaces/{$this->workspace->id}/agent/command";
    $this->ai->replies = [json_encode([
        'intent' => 'question', 'action' => null, 'job' => null, 'round' => null, 'names' => [], 'top_n' => null,
        'from_stage' => null, 'to_stage' => null, 'reason' => null, 'reply' => '',
    ]), 'You have 0 candidates in L1 for the Data Engineer role.'];

    $reply = $this->postJson($url, ['text' => 'L1 mein kitne log hain?'])->assertOk()->json('data.reply');
    expect($reply)->toStartWith('[HI] ');

    $this->ai->replies = [json_encode([
        'intent' => 'question', 'action' => null, 'job' => null, 'round' => null, 'names' => [], 'top_n' => null,
        'from_stage' => null, 'to_stage' => null, 'reason' => null, 'reply' => '',
    ]), 'You have 0 candidates in L1 for the Data Engineer role.'];
    $before = $this->ai->rewrites;
    $english = $this->postJson($url, ['text' => 'How many are in L1?'])->assertOk()->json('data.reply');
    expect($english)->not->toStartWith('[HI] ')->and($this->ai->rewrites)->toBe($before);
});

it('answers in the language picked in the console even when the words typed are plain English', function (): void {
    $localizer = app(ReplyLocalizer::class);

    expect($localizer->localize($this->recruiter, 'Where are we in hiring?', 'One open role.', 'hi'))->toBe('[HI] One open role.')
        ->and($localizer->localize($this->recruiter, 'Where are we in hiring?', 'One open role.', 'kn'))->toBe('[HI] One open role.')
        ->and($localizer->localize($this->recruiter, 'Where are we in hiring?', 'One open role.', 'en'))->toBe('One open role.')
        ->and($localizer->localize($this->recruiter, 'Where are we in hiring?', 'One open role.', 'fr'))->toBe('One open role.')
        ->and($this->ai->rewrites)->toBe(2);

    $this->actingAs($this->recruiter);
    $this->ai->replies = [json_encode([
        'intent' => 'question', 'action' => null, 'job' => null, 'round' => null, 'names' => [], 'top_n' => null,
        'from_stage' => null, 'to_stage' => null, 'reason' => null, 'reply' => '',
    ]), 'You have 1 open role.'];
    $url = "/api/v1/employer/workspaces/{$this->workspace->id}/agent/command";
    expect($this->postJson($url, ['text' => 'Where are we in hiring?', 'lang' => 'hi'])->assertOk()->json('data.reply'))->toStartWith('[HI] ');
    $this->postJson($url, ['text' => 'hello', 'lang' => 'de'])->assertStatus(422);
});
