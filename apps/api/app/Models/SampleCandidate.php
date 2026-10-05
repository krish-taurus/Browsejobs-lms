<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Synthetic candidates for the employer "preview with sample data" toggle —
 * entirely separate from real students. Never joined into LmsTalentMatcher,
 * never targeted by any messaging feature, never counted in any real
 * dashboard or export. Merged into the Talent Pool response only when a
 * request explicitly opts in, and only ever tagged as sample data in the
 * response — see TalentPoolController::index().
 */
final class SampleCandidate extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'location', 'education', 'experience_summary',
        'experience_years', 'skills', 'cv_summary', 'ai_readiness_score',
        'cv_mock_score', 'lead_status', 'bgb_verified',
    ];

    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'bgb_verified' => 'boolean',
        ];
    }
}
