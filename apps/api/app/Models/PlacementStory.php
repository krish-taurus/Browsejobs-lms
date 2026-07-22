<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * A course-page placement story (Platform Spec §3). Publicly served only when
 * both `is_published` and `consent` are true — a real student's numbers and
 * screenshot must never be shown without their consent.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $course_id
 * @property string $student_name
 * @property string $before_label
 * @property string $after_role
 * @property string|null $package_label
 * @property string|null $company_name
 * @property string|null $company_color
 * @property int|null $rounds
 * @property string|null $quote
 * @property string|null $screenshot_path
 * @property bool $consent
 * @property bool $is_published
 * @property bool $is_sample
 * @property int $position
 */
final class PlacementStory extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'course_id', 'student_name', 'before_label', 'after_role',
        'package_label', 'company_name', 'company_color', 'rounds', 'quote',
        'screenshot_path', 'consent', 'is_published', 'is_sample', 'position',
    ];

    protected function casts(): array
    {
        return [
            'rounds' => 'integer',
            'consent' => 'boolean',
            'is_published' => 'boolean',
            'is_sample' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** A short-lived signed URL for the uploaded screenshot, or null. */
    public function screenshotUrl(): ?string
    {
        if ($this->screenshot_path === null) {
            return null;
        }

        try {
            return Storage::disk('s3')->temporaryUrl($this->screenshot_path, now()->addHour());
        } catch (Throwable) {
            return null;
        }
    }
}
