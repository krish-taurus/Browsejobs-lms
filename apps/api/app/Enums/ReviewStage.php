<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The funnel moment a review request was sent from (Platform Spec §3 review
 * ladder). A student is prompted at up to five points on the journey; the stage
 * is carried through the magic link so we know where each testimonial came from.
 */
enum ReviewStage: string
{
    case Masterclass = 'masterclass';       // after the free masterclass
    case Bootcamp = 'bootcamp';             // after the free 7-hour bootcamp
    case Enrolment = 'enrolment';           // after paying for the course + counselling
    case CourseComplete = 'course_complete'; // after finishing the course
    case Placement = 'placement';           // after landing a job
    case Manual = 'manual';                 // sent by hand from the admin (not a funnel trigger)

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
