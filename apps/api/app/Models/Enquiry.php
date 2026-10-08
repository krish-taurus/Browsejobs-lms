<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A public enquiry: an employer who wants to hire, or a learner asking about a course.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $type
 * @property string $status
 * @property string $name
 * @property string $email
 * @property string $email_normalized
 * @property string $phone
 * @property string $phone_normalized
 * @property string|null $company
 * @property string|null $company_size
 * @property string|null $roles
 * @property string|null $city
 * @property string|null $timeline
 * @property string|null $course_slug
 * @property string|null $learner_status
 * @property string|null $preferred_time
 * @property string|null $message
 * @property Carbon $consented_at
 * @property string $consent_version
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $referrer
 * @property string|null $landing_page
 * @property string|null $user_agent
 * @property string|null $ip_hash
 * @property Carbon|null $notified_at
 */
class Enquiry extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const TYPE_EMPLOYER = 'employer';

    public const TYPE_COURSE = 'course';

    /** @var list<string> */
    public const TYPES = [self::TYPE_EMPLOYER, self::TYPE_COURSE];

    /** @var list<string> */
    public const STATUSES = ['new', 'contacted', 'qualified', 'closed'];

    /** @var array<string, string> */
    public const COMPANY_SIZES = [
        '1-10' => '1–10 people',
        '11-50' => '11–50 people',
        '51-200' => '51–200 people',
        '201-1000' => '201–1,000 people',
        '1000+' => '1,000+ people',
    ];

    /** @var array<string, string> */
    public const TIMELINES = [
        'this-week' => 'This week',
        'this-month' => 'This month',
        'this-quarter' => 'This quarter',
        'exploring' => 'Just exploring',
    ];

    /** @var array<string, string> */
    public const LEARNER_STATUSES = [
        'student' => 'Student',
        'working' => 'Working professional',
        'switcher' => 'Career switcher',
    ];

    /** @var array<string, string> */
    public const PREFERRED_TIMES = [
        'morning' => 'Morning, 9:00–12:00 IST',
        'afternoon' => 'Afternoon, 12:00–16:00 IST',
        'evening' => 'Evening, 16:00–19:00 IST',
    ];

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'type', 'status', 'name', 'email', 'email_normalized', 'phone', 'phone_normalized',
        'company', 'company_size', 'roles', 'city', 'timeline',
        'course_slug', 'learner_status', 'preferred_time', 'message',
        'consented_at', 'consent_version',
        'utm_source', 'utm_medium', 'utm_campaign', 'referrer', 'landing_page',
        'user_agent', 'ip_hash', 'notified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function label(string $map, ?string $key): string
    {
        if ($key === null || $key === '') {
            return '';
        }

        /** @var array<string, string> $labels */
        $labels = constant(self::class.'::'.$map);

        return $labels[$key] ?? $key;
    }
}
