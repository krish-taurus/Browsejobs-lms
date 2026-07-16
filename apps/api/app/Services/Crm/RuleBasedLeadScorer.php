<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Models\Lead;

/**
 * P2.1 rule-based lead score (stub — playbook: "rule-based from attendance/
 * engagement", the AI telemetry model arrives in P3). Points reward reachable,
 * high-intent leads from a live course so counselors can triage the inbox.
 */
final class RuleBasedLeadScorer implements LeadScorer
{
    /** @var list<string> High-intent lead types (the free funnel spine). */
    private const HOT_TYPES = ['masterclass', 'counselling'];

    /** @var list<string> Courses currently accepting cohorts (PRD §14.5). */
    private const LIVE_COURSES = ['data-engineering', 'devops-cloud', 'python-backend', 'data-analytics'];

    public function score(Lead $lead): int
    {
        $score = 0;

        if (in_array($lead->lead_type, self::HOT_TYPES, true)) {
            $score += 40;
        }

        if ($lead->email !== null && $lead->email !== '') {
            $score += 20;
        }

        if ($lead->course_slug !== null && in_array($lead->course_slug, self::LIVE_COURSES, true)) {
            $score += 25;
        }

        if ($lead->utm_source !== null && $lead->utm_source !== '' && $lead->utm_source !== 'organic') {
            $score += 15;
        }

        return min($score, 100);
    }
}
