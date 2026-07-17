<?php

declare(strict_types=1);

namespace App\Support\Syllabus;

use App\Models\Syllabus;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Storage;

/**
 * Renders a course syllabus as branded HTML (tenant colours/fonts) and stores it on
 * the render disk. The markdown-light AI prose is converted to safe HTML with a tiny
 * dependency-free converter. A PDF step can swap in behind {@see SyllabusRenderer}.
 */
final class HtmlSyllabusRenderer implements SyllabusRenderer
{
    public function __construct(private readonly ViewFactory $view) {}

    public function render(Syllabus $syllabus): string
    {
        $syllabus->loadMissing(['course', 'tenant']);

        /** @var array<string, mixed> $branding */
        $branding = $syllabus->tenant?->branding ?? [];

        $html = $this->view->make('syllabus.default', [
            'tenantName' => $syllabus->tenant?->name ?? 'BrowseJobs',
            'courseName' => $syllabus->course?->name ?? 'the course',
            'title' => $syllabus->title,
            'bodyHtml' => $this->toHtml((string) $syllabus->content),
            'colors' => (array) ($branding['colors'] ?? []),
            'fonts' => (array) ($branding['fonts'] ?? []),
        ])->render();

        $slug = $syllabus->course?->slug ?? 'course-'.$syllabus->course_id;
        $path = "syllabus/{$syllabus->tenant_id}/{$slug}-v{$syllabus->version}.html";
        Storage::disk((string) config('syllabus.render_disk', 's3'))->put($path, $html);

        return $path;
    }

    /**
     * Minimal, safe markdown-light → HTML: `##/###` headings, `-`/`*` bullet lists,
     * blank-line-separated paragraphs. All text is HTML-escaped first.
     */
    private function toHtml(string $markdown): string
    {
        $lines = preg_split('/\R/', trim($markdown)) ?: [];
        $out = [];
        $inList = false;

        $closeList = function () use (&$out, &$inList): void {
            if ($inList) {
                $out[] = '</ul>';
                $inList = false;
            }
        };

        foreach ($lines as $raw) {
            $line = trim($raw);

            if ($line === '') {
                $closeList();

                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m) === 1) {
                $closeList();
                $level = min(6, max(2, strlen($m[1]))); // clamp to h2..h6
                $out[] = "<h{$level}>".e($m[2])."</h{$level}>";

                continue;
            }

            if (preg_match('/^[-*]\s+(.*)$/', $line, $m) === 1) {
                if (! $inList) {
                    $out[] = '<ul>';
                    $inList = true;
                }
                $out[] = '<li>'.e($m[1]).'</li>';

                continue;
            }

            $closeList();
            $out[] = '<p>'.e($line).'</p>';
        }

        $closeList();

        return implode("\n", $out);
    }
}
