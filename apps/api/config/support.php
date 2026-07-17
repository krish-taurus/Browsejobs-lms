<?php

declare(strict_types=1);

return [
    /*
    | Ticket attachment constraints (PRD §6.13). Stored on the s3 disk under
    | tickets/{tenant_id}/… — same tenant-prefixed convention as receipts/recordings.
    */
    'upload' => [
        'max_mb' => 10,
        'mimes' => ['jpg', 'jpeg', 'png', 'pdf'],
    ],

    /*
    | Students may reopen a resolved/closed ticket within this many days.
    */
    'reopen_days' => 7,

    /*
    | Warn the assignee when this fraction of the first-response SLA has elapsed.
    */
    'sla_warning_pct' => 0.75,

    /*
    | AI first-response / deflection layer (PRD §6.13, P3.5d-a). Retrieval is scoped
    | to the `support` corpus — policy documents, not course material.
    |
    | `min_confidence` is the floor for showing an answer at all: below it the student
    | goes straight to a human. Deflection is assistive; a wrong confident answer on a
    | fee dispute costs far more than a ticket a human would have handled anyway.
    */
    'ai' => [
        'enabled' => (bool) env('SUPPORT_DEFLECTION_ENABLED', true),
        'prompt_version' => (int) env('SUPPORT_DEFLECTION_PROMPT_VERSION', 1),
        'retrieve_k' => (int) env('SUPPORT_DEFLECTION_RETRIEVE_K', 4),
        'max_answer_tokens' => (int) env('SUPPORT_DEFLECTION_MAX_TOKENS', 500),
        'min_confidence' => env('SUPPORT_DEFLECTION_MIN_CONFIDENCE', 'medium'),
    ],

    /*
    | Per-category routing + SLA defaults (PRD §6.13 table). Seeds ticket_routes.
    | Each: [routing, team_slug|null, first_response_minutes, resolution_minutes].
    | routing ∈ team | batch_trainer | admin.
    */
    'defaults' => [
        'payments' => ['team', 'accounts', 120, 1440],           // 2h / 24h
        'technical' => ['team', 'technical', 240, 2880],         // 4h / 48h
        'training' => ['batch_trainer', 'training', 240, 1440],  // 4h / 24h
        'academic' => ['batch_trainer', 'training', 240, 1440],  // 4h / 24h — AI Tutor escalations
        'mentorship' => ['team', 'mentorship', 480, 2880],       // 8h / 48h
        'interview_prep' => ['team', 'interview-prep', 480, 2880], // 8h / 48h
        'other' => ['admin', null, 480, 2880],                   // 8h / 48h
    ],
];
