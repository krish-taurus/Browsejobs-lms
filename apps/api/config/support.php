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
