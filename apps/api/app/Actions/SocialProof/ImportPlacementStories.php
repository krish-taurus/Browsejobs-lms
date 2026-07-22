<?php

declare(strict_types=1);

namespace App\Actions\SocialProof;

use App\Models\Course;
use App\Models\PlacementStory;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;

/**
 * Bulk-imports placement stories from a spreadsheet (Platform Spec §3) so a team
 * can capture cards in Excel and upload them all at once. Rows are real students
 * (is_sample=false); a row publishes only when consent is given. When a course
 * gets its first published, consented story, that course's illustrative samples
 * are unpublished — real stories replace the samples, per the honesty rules.
 *
 * @phpstan-type ImportSummary array{created:int, updated:int, published:int, skipped:list<array{row:int, reason:string}>}
 */
final readonly class ImportPlacementStories
{
    private const TRUE = ['1', 'yes', 'y', 'true', 'consented', 'consent'];

    /**
     * @param  list<array<string, string>>  $rows  header-keyed rows
     * @return ImportSummary
     */
    public function handle(Tenant $tenant, array $rows): array
    {
        return app(TenantContext::class)->run($tenant, function () use ($tenant, $rows): array {
            $courses = Course::query()->where('tenant_id', $tenant->id)->get()->keyBy('slug');

            $created = 0;
            $updated = 0;
            $published = 0;
            $skipped = [];
            $coursesWithRealPublish = [];

            foreach ($rows as $i => $row) {
                $line = $i + 2; // header is row 1
                $slug = trim($row['course_slug'] ?? '');
                $name = trim($row['student_name'] ?? '');

                $course = $courses->get($slug);
                if ($course === null) {
                    $skipped[] = ['row' => $line, 'reason' => "Unknown course_slug \"{$slug}\"."];

                    continue;
                }
                if ($name === '' || trim($row['before_label'] ?? '') === '' || trim($row['after_role'] ?? '') === '') {
                    $skipped[] = ['row' => $line, 'reason' => 'student_name, before_label and after_role are required.'];

                    continue;
                }

                $consent = in_array(strtolower(trim($row['consent'] ?? '')), self::TRUE, true);
                $rounds = ((int) ($row['rounds'] ?? 0)) ?: null;
                $color = trim($row['company_color'] ?? '');

                $existing = PlacementStory::query()
                    ->where('tenant_id', $tenant->id)->where('course_id', $course->id)
                    ->where('student_name', $name)->first();

                PlacementStory::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'course_id' => $course->id, 'student_name' => $name],
                    [
                        'before_label' => trim($row['before_label']),
                        'after_role' => trim($row['after_role']),
                        'package_label' => trim($row['package_label'] ?? '') ?: null,
                        'company_name' => trim($row['company_name'] ?? '') ?: null,
                        'company_color' => $color !== '' ? $color : null,
                        'rounds' => $rounds,
                        'quote' => trim($row['quote'] ?? '') ?: null,
                        // Real, team-captured data — never a sample. Published only with consent.
                        'is_sample' => false,
                        'consent' => $consent,
                        'is_published' => $consent,
                    ],
                );

                $existing === null ? $created++ : $updated++;
                if ($consent) {
                    $published++;
                    $coursesWithRealPublish[$course->id] = true;
                }
            }

            // A course now has real, consented stories → retire its samples.
            foreach (array_keys($coursesWithRealPublish) as $courseId) {
                PlacementStory::query()
                    ->where('tenant_id', $tenant->id)->where('course_id', $courseId)
                    ->where('is_sample', true)
                    ->update(['is_published' => false]);
            }

            return ['created' => $created, 'updated' => $updated, 'published' => $published, 'skipped' => $skipped];
        });
    }
}
