<?php

declare(strict_types=1);

namespace App\Support\Dpdp;

use App\Models\BatchMember;
use App\Models\CvProfile;
use App\Models\Instalment;
use App\Models\JobApplication;
use App\Models\MockInterview;
use App\Models\TopicCompletion;
use App\Models\User;

/**
 * Compiles a student's own data into a portable export (PRD §8, DPDP right to
 * access). Reads ONLY the subject's own records — never another student's — and
 * returns plain data the request stores and the student downloads.
 */
final class DataExporter
{
    /**
     * @return array<string, mixed>
     */
    public function for(User $student): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'profile' => [
                'name' => $student->name,
                'email' => $student->email,
                'phone' => $student->phone,
                'user_type' => $student->user_type,
                'joined_at' => $student->created_at?->toIso8601String(),
            ],
            'cv_profile' => CvProfile::query()->where('user_id', $student->id)->value('data'),
            'enrolments' => BatchMember::query()
                ->where('user_id', $student->id)
                ->with('batch:id,number,course_id')
                ->get()
                ->map(fn (BatchMember $m) => ['batch' => $m->batch?->number, 'status' => $m->status])
                ->all(),
            'applications' => JobApplication::query()
                ->where('user_id', $student->id)
                ->with('posting:id,title,company')
                ->get()
                ->map(fn (JobApplication $a) => ['role' => $a->posting?->title, 'company' => $a->posting?->company, 'status' => $a->status])
                ->all(),
            'payments' => Instalment::query()
                ->whereHas('feePlan', fn ($q) => $q->where('user_id', $student->id))
                ->get(['amount_paise', 'status', 'due_on', 'paid_at'])
                ->all(),
            'activity_summary' => [
                'topics_completed' => TopicCompletion::query()->where('user_id', $student->id)->count(),
                'mock_interviews' => MockInterview::query()->where('user_id', $student->id)->count(),
            ],
        ];
    }
}
