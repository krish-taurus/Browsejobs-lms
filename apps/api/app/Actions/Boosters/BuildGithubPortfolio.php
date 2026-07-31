<?php

declare(strict_types=1);

namespace App\Actions\Boosters;

use App\Enums\AiPurpose;
use App\Models\CareerBooster;
use App\Models\CodeSubmission;
use App\Models\CodingLab;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Services\AI\JsonOutput;
use Throwable;

/**
 * Career+ GitHub portfolio auto-builder (PRD §6.20). Turns the student's REAL passed lab
 * projects — their own code, the lab brief — into portfolio-ready README project write-ups
 * (title, summary, tech, highlights). The model describes what the code does; it never
 * invents a project the student did not build, and the output never names BrowseJobs.
 * AI failure degrades to a deterministic listing from the same submissions.
 */
final readonly class BuildGithubPortfolio
{
    public function __construct(private AiGateway $gateway) {}

    public function handle(User $student): CareerBooster
    {
        $projects = $this->projects($student);
        [$content, $source] = $this->compose($student, $projects);

        return CareerBooster::query()->updateOrCreate(
            ['user_id' => $student->id, 'kind' => CareerBooster::KIND_GITHUB],
            ['tenant_id' => $student->tenant_id, 'content' => $this->scrub($content), 'content_source' => $source],
        );
    }

    /**
     * The student's passed labs with their code + brief.
     *
     * @return list<array{title: string, language: string, brief: string, code: string}>
     */
    private function projects(User $student): array
    {
        return CodeSubmission::query()
            ->where('user_id', $student->id)
            ->where('kind', 'submit')
            ->whereColumn('passed_tests', 'total_tests')
            ->where('total_tests', '>', 0)
            ->with('lesson:id,title')
            ->latest('id')
            ->get()
            ->unique('lesson_id')
            ->take(6)
            ->map(fn (CodeSubmission $s) => [
                'title' => (string) ($s->lesson?->title ?? 'Coding project'),
                'language' => is_string($s->language) ? $s->language : (string) ($s->language->value ?? 'code'),
                'brief' => (string) str((string) CodingLab::query()->where('lesson_id', $s->lesson_id)->value('instructions'))->limit(400),
                'code' => (string) str((string) $s->source)->limit(1500),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $projects
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function compose(User $student, array $projects): array
    {
        if ($projects === []) {
            return [['intro' => 'Complete a coding lab to build your first portfolio project.', 'projects' => []], 'fallback'];
        }

        try {
            $result = $this->gateway->complete($student, AiPurpose::GithubPortfolio, 'github_portfolio', 1, [
                'projects' => collect($projects)->map(fn ($p, $i) => 'PROJECT '.($i + 1)." [{$p['language']}] {$p['title']}\nBrief: {$p['brief']}\nCode:\n{$p['code']}")->implode("\n\n---\n\n"),
            ], ['max_tokens' => 1600]);

            $decoded = JsonOutput::object($result->text);
            if (is_array($decoded) && ! empty($decoded['projects'])) {
                return [$decoded, 'ai'];
            }
        } catch (Throwable) {
            // fall through
        }

        return [$this->fallback($projects), 'fallback'];
    }

    /**
     * @param  list<array<string, mixed>>  $projects
     * @return array<string, mixed>
     */
    private function fallback(array $projects): array
    {
        return [
            'intro' => 'Projects built and tested during hands-on labs.',
            'projects' => collect($projects)->map(fn ($p) => [
                'name' => $p['title'],
                'summary' => "A working {$p['language']} solution with all tests passing.",
                'tech' => [$p['language']],
                'highlights' => ['All automated tests pass', 'Written and debugged from scratch'],
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function scrub(array $content): array
    {
        array_walk_recursive($content, function (&$v): void {
            if (is_string($v)) {
                $v = trim((string) preg_replace('/\s{2,}/', ' ', str_ireplace(['browsejobs.ai', 'browsejobs', 'browse jobs'], '', $v)), " \t-—·,");
            }
        });

        return $content;
    }
}
