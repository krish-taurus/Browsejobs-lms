<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The `activity_events` telemetry stream (PRD §6.4). Quiz-attempt telemetry is
 * added in P3.4 when the quiz model exists.
 */
enum ActivityType: string
{
    case Login = 'login';
    case LessonViewed = 'lesson_viewed';
    case WatchTime = 'watch_time';
    case TopicCompleted = 'topic_completed';
    case ModuleCompleted = 'module_completed';
    case CodeRun = 'code_run';
    case CodeSubmitted = 'code_submitted';
    case QuizSubmitted = 'quiz_submitted';
    case ContentViewed = 'content_viewed';

    /** Event types a client (student browser) may self-report. */
    public static function clientReportable(): array
    {
        return [self::WatchTime, self::LessonViewed];
    }
}
