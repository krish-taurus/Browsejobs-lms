<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Market intelligence
|--------------------------------------------------------------------------
| The landing boards used to show numbers a model invented — the prompt
| literally asked for estimates that were "order-of-magnitude correct" —
| badged as live market analysis. These are computed from job_feed_items
| instead: real postings we ingested, counted.
|
| That means the numbers are only as good as the feed, and the honest
| consequence is that below `min_sample` we say we do not have enough
| postings rather than printing a precise-looking figure over a handful of
| rows. A visitor deciding what to learn deserves to know which it is.
*/

return [

    // Postings needed before a track's numbers are shown at all. Below this,
    // the surface says so; it never rounds a thin sample into confidence.
    'min_sample' => (int) env('MARKET_MIN_SAMPLE', 40),

    // Postings needed before a skill's rise or fall is reported. Trend needs
    // more evidence than a count: a move from 3 to 6 mentions is +100% and
    // means nothing.
    'min_skill_sample' => (int) env('MARKET_MIN_SKILL_SAMPLE', 25),

    // The comparison window. Each period is this many days; the trend is the
    // most recent period against the one before it.
    'window_days' => (int) env('MARKET_WINDOW_DAYS', 30),

    // Tracks we teach, and the skills that identify a posting as belonging to
    // one. Matched against extracted_skills and role_title.
    'tracks' => [
        'data-engineering' => [
            'label' => 'Data Engineering',
            'skills' => ['spark', 'airflow', 'dbt', 'databricks', 'snowflake', 'etl', 'data modeling', 'kafka', 'redshift', 'bigquery'],
            'titles' => ['data engineer', 'analytics engineer', 'etl developer', 'big data'],
        ],
        'devops-cloud' => [
            'label' => 'DevOps & Cloud',
            'skills' => ['kubernetes', 'terraform', 'docker', 'jenkins', 'ansible', 'aws', 'azure', 'gcp', 'ci/cd', 'helm'],
            'titles' => ['devops', 'site reliability', 'sre', 'cloud engineer', 'platform engineer'],
        ],
        'python-backend' => [
            'label' => 'Python Backend',
            'skills' => ['django', 'flask', 'fastapi', 'rest apis', 'postgresql', 'celery', 'microservices'],
            'titles' => ['python developer', 'backend engineer', 'backend developer', 'python engineer'],
        ],
        'data-analytics' => [
            'label' => 'Data Analytics',
            'skills' => ['power bi', 'tableau', 'sql', 'excel', 'looker', 'statistics', 'dax'],
            'titles' => ['data analyst', 'business analyst', 'bi analyst', 'reporting analyst'],
        ],
    ],
];
