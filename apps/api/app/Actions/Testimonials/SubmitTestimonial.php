<?php

declare(strict_types=1);

namespace App\Actions\Testimonials;

use App\Actions\Crm\RecordTimelineEvent;
use App\Enums\TestimonialStatus;
use App\Models\ContactTimelineEvent;
use App\Models\Lead;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\Crm\PhoneNormalizer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Records a student-submitted testimonial (PRD §5 Stage 3). Lands `pending` for
 * admin moderation. An optional video is stored to the s3 disk under
 * testimonials/{tenant_id}/…. One open (pending) testimonial per student+batch.
 */
final readonly class SubmitTestimonial
{
    public function __construct(private RecordTimelineEvent $timeline) {}

    /**
     * @param  array{batch_id?: int|null, course_slug?: string|null, rating: int, body: string, consent_publish?: bool}  $data
     */
    public function handle(User $student, array $data, ?UploadedFile $video = null): Testimonial
    {
        return app(TenantContext::class)->run($student->tenant, function () use ($student, $data, $video): Testimonial {
            $batchId = $data['batch_id'] ?? null;

            $open = Testimonial::query()
                ->where('user_id', $student->id)
                ->when($batchId !== null, fn ($q) => $q->where('batch_id', $batchId))
                ->where('status', TestimonialStatus::Pending->value)
                ->exists();

            if ($open) {
                throw ValidationException::withMessages(['testimonial' => 'You already have a testimonial awaiting review.']);
            }

            $videoPath = $video?->store("testimonials/{$student->tenant_id}", 's3') ?: null;

            $testimonial = Testimonial::query()->create([
                'tenant_id' => $student->tenant_id,
                'user_id' => $student->id,
                'batch_id' => $batchId,
                'course_slug' => $data['course_slug'] ?? null,
                'rating' => (int) $data['rating'],
                'body' => $data['body'],
                'video_path' => $videoPath,
                'consent_publish' => (bool) ($data['consent_publish'] ?? false),
                'status' => TestimonialStatus::Pending->value,
            ]);

            $this->recordOnLead($student);

            return $testimonial;
        });
    }

    private function recordOnLead(User $student): void
    {
        $phone = $student->phone !== null ? PhoneNormalizer::normalize($student->phone) : null;
        $hasPhone = $phone !== null && $phone !== '';
        $hasEmail = $student->email !== null && $student->email !== '';
        if (! $hasPhone && ! $hasEmail) {
            return;
        }

        $lead = Lead::query()
            ->where('tenant_id', $student->tenant_id)
            ->whereNull('merged_into_id')
            ->where(function ($q) use ($phone, $hasPhone, $hasEmail, $student) {
                if ($hasPhone) {
                    $q->where('phone_normalized', $phone);
                }
                if ($hasEmail) {
                    $q->orWhere('email', $student->email);
                }
            })
            ->orderBy('id')
            ->first();

        if ($lead !== null) {
            $this->timeline->handle($lead, ContactTimelineEvent::TYPE_NOTE, 'Submitted a testimonial (pending review).');
        }
    }
}
