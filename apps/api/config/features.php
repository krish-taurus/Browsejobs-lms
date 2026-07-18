<?php

declare(strict_types=1);

/*
| Feature flags for staged / backlog capabilities. Off by default; flip via env.
*/
return [
    // Apply Copilot (PRD §6.22, Phase 5): human-in-the-loop ATS pre-fill. When on,
    // an agent pre-fills supported ATS forms from the student's profile + tailored
    // CV for the student to review and confirm. NEVER auto-submits.
    'apply_copilot' => (bool) env('FEATURE_APPLY_COPILOT', false),
];
