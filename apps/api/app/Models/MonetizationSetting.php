<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-tenant freemium config (PRD §6.17). One row per tenant. Every field
 * here is CRM-editable — crm.browsejobs.ai writes this row directly (see the
 * monetization_settings grant on the browsejobs_crm MySQL user).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $cv_free_grants
 * @property int $voice_included_live
 * @property int $voice_included_self_paced
 * @property int $general_mock_attempts_per_blueprint
 * @property int $employer_mock_attempts_per_job
 * @property int $free_job_application_limit
 * @property int $wider_market_job_limit
 * @property int $self_paced_pct_bps
 * @property bool $text_practice_enabled
 * @property bool $auto_shortlist_enabled
 * @property int $auto_shortlist_min_score
 * @property int $auto_shortlist_min_cv_match_pct
 * @property int $cv_mock_attempts_limit
 */
class MonetizationSetting extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'cv_free_grants', 'voice_included_live', 'voice_included_self_paced',
        'general_mock_attempts_per_blueprint', 'employer_mock_attempts_per_job', 'free_job_application_limit',
        'wider_market_job_limit', 'self_paced_pct_bps', 'text_practice_enabled',
        'auto_shortlist_enabled', 'auto_shortlist_min_score', 'auto_shortlist_min_cv_match_pct',
        'cv_mock_attempts_limit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cv_free_grants' => 'integer',
            'voice_included_live' => 'integer',
            'voice_included_self_paced' => 'integer',
            'general_mock_attempts_per_blueprint' => 'integer',
            'employer_mock_attempts_per_job' => 'integer',
            'free_job_application_limit' => 'integer',
            'wider_market_job_limit' => 'integer',
            'self_paced_pct_bps' => 'integer',
            'text_practice_enabled' => 'boolean',
            'auto_shortlist_enabled' => 'boolean',
            'auto_shortlist_min_score' => 'integer',
            'auto_shortlist_min_cv_match_pct' => 'integer',
            'cv_mock_attempts_limit' => 'integer',
        ];
    }
}
