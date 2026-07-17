<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tags every AI gateway call for cost attribution (PRD §6.4). New purposes are
 * added as later phases wire consumers.
 */
enum AiPurpose: string
{
    case Tutor = 'tutor';
    case Grading = 'grading';
    case Syllabus = 'syllabus';
    case LeadScore = 'lead_score';
    case SupportDeflection = 'support_deflection';
    case SupportReply = 'support_reply';
    case SupportTriage = 'support_triage';
    case QuizGen = 'quiz_gen';
    case Content = 'content';
    case Report = 'report';
    case MarketPulse = 'market_pulse';
    case Mock = 'mock';
    case Coach = 'coach';
    case General = 'general';
}
