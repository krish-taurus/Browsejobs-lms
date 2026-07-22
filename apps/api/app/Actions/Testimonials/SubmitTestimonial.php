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
 * Records a student-submitted review (Platform Spec §3 review gate). A rating at
 * or above the public threshold lands `pending` for admin confirmation — the
 * candidate self-attests they followed us on Instagram/YouTube and posted on
 * Google, and once an admin confirms, it publishes to the wall and issues the
 * voucher. A rating below the threshold lands `private_feedback`: never
 * published, never rewarded, so the candidate is thanked privately instead of
 * being sent to Google. An optional video is stored to the s3 disk under
 * testimonials/{tenant_id}/…. One open review per student+stage.
 */
final readonly class SubmitTestimonial
{
    public function __construct(private RecordTimelineEvent $timeline) {}

    /**
     * @param  array{batch_id?: int|null, course_slug?: string|null, stage?: string|null, rating: int, body: string, consent_publish?: bool, followed_instagram?: bool, subscribed_youtube?: bool, posted_google_review?: bool}  $data
     */
    public function handle(User $student, array $data, ?UploadedFile $video = null): Testimonial
    {
        return app(TenantContext::class)->run($student->tenant, function () use ($student, $data, $video): Testimonial {
            $batchId = $data['batch_id'] ?? null;
            $stage = $data['stage'] ?? null;
            $rating = (int) $data['rating'];
            $isPublic = $rating >= (int) config('vouchers.review_request.min_public_rating', 4);

            $open = Testimonial::query()
                ->where('user_id', $student->id)
                ->when($stage !== null, fn ($q) => $q->where('stage', $stage))
                ->when($stage === null && $batchId !== null, fn ($q) => $q->where('batch_id', $batchId))
                ->where('status', TestimonialStatus::Pending->value)
                ->exists();

            if ($open) {
                throw ValidationException::withMessages(['testimonial' => 'You already have a review awaiting confirmation.']);
            }

            $videoPath = $video?->store("testimonials/{$student->tenant_id}", 's3') ?: null;

            // Below the threshold: kept as private feedback, no publish, no reward.
            $status = $isPublic ? TestimonialStatus::Pending : TestimonialStatus::PrivateFeedback;

            $testimonial = Testimonial::query()->create([
                'tenant_id' => $student->tenant_id,
                'user_id' => $student->id,
                'batch_id' => $batchId,
                'course_slug' => $data['course_slug'] ?? null,
                'stage' => $stage,
                'rating' => $rating,
                'body' => $data['body'],
                'video_path' => $videoPath,
                'consent_publish' => $isPublic && (bool) ($data['consent_publish'] ?? false),
                'followed_instagram' => $isPublic && (bool) ($data['followed_instagram'] ?? false),
                'subscribed_youtube' => $isPublic && (bool) ($data['subscribed_youtube'] ?? false),
                'posted_google_review' => $isPublic && (bool) ($data['posted_google_review'] ?? false),
                'status' => $status->value,
            ]);

            $this->recordOnLead($student, $isPublic);

            return $testimonial;
        });
    }

    private function recordOnLead(User $student, bool $isPublic): void
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
            $note = $isPublic
                ? 'Submitted a review (pending confirmation).'
                : 'Left private feedback (below the public threshold).';
            $this->timeline->handle($lead, ContactTimelineEvent::TYPE_NOTE, $note);
        }
    }
}
