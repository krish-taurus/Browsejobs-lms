<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Role taxonomy
|--------------------------------------------------------------------------
| The shared vocabulary behind job titles, skill pickers, JD generation and
| the mock designer. Everything that offers a dropdown reads from here, so a
| skill named on a JD is the same string the mock weights and the talent
| matcher compares against — that only holds while there is one source.
|
| Deliberately covers non-IT as well: hiring is not only engineering, and a
| finance or sales employer meeting an empty dropdown will type free text
| that never matches anything downstream.
|
| `core` skills are the ones a JD for that title almost always names, and
| are pre-selected. `optional` broadens the picker without cluttering it.
| `competencies` are the non-technical dimensions a mock can score.
*/

return [

    'families' => [

        /* ---------------------------- technology ---------------------------- */

        'engineering' => [
            'label' => 'Software engineering',
            'sector' => 'it',
            'titles' => [
                'Backend Engineer', 'Frontend Engineer', 'Full Stack Engineer',
                'Mobile Engineer (Android)', 'Mobile Engineer (iOS)', 'Staff Engineer',
                'Engineering Manager', 'QA Engineer', 'SDET', 'Embedded Engineer',
            ],
            'core' => ['data structures', 'algorithms', 'system design', 'debugging', 'version control'],
            'optional' => [
                'java', 'python', 'javascript', 'typescript', 'go', 'rust', 'c++', 'c#', 'php', 'ruby',
                'react', 'next.js', 'angular', 'vue', 'node.js', 'spring boot', 'django', 'laravel',
                '.net', 'kotlin', 'swift', 'flutter', 'react native', 'graphql', 'rest apis',
                'microservices', 'unit testing', 'tdd', 'ci/cd', 'docker', 'kubernetes',
            ],
        ],

        'data' => [
            'label' => 'Data & analytics',
            'sector' => 'it',
            'titles' => [
                'Data Engineer', 'Analytics Engineer', 'Data Analyst', 'Business Intelligence Analyst',
                'Data Scientist', 'Machine Learning Engineer', 'MLOps Engineer', 'Data Architect',
            ],
            'core' => ['sql', 'data modeling', 'etl', 'data quality'],
            'optional' => [
                'python', 'r', 'spark', 'airflow', 'dbt', 'snowflake', 'databricks', 'bigquery',
                'redshift', 'kafka', 'pandas', 'numpy', 'scikit-learn', 'pytorch', 'tensorflow',
                'power bi', 'tableau', 'looker', 'statistics', 'a/b testing', 'feature engineering',
                'mlflow', 'vector databases', 'llm evaluation',
            ],
        ],

        'infrastructure' => [
            'label' => 'Infrastructure & security',
            'sector' => 'it',
            'titles' => [
                'DevOps Engineer', 'Site Reliability Engineer', 'Cloud Engineer', 'Platform Engineer',
                'Security Engineer', 'Network Engineer', 'Database Administrator', 'IT Support Engineer',
            ],
            'core' => ['linux', 'networking', 'ci/cd', 'monitoring', 'incident response'],
            'optional' => [
                'aws', 'azure', 'gcp', 'terraform', 'ansible', 'kubernetes', 'docker', 'helm',
                'prometheus', 'grafana', 'elk', 'bash', 'python', 'jenkins', 'github actions',
                'iam', 'vulnerability management', 'penetration testing', 'siem', 'zero trust',
                'disaster recovery', 'cost optimisation',
            ],
        ],

        'product_design' => [
            'label' => 'Product & design',
            'sector' => 'it',
            'titles' => [
                'Product Manager', 'Associate Product Manager', 'Technical Program Manager',
                'Product Designer', 'UX Designer', 'UX Researcher', 'Business Analyst', 'Scrum Master',
            ],
            'core' => ['requirement gathering', 'stakeholder management', 'prioritisation', 'user research'],
            'optional' => [
                'figma', 'wireframing', 'prototyping', 'design systems', 'usability testing',
                'roadmapping', 'okrs', 'jira', 'agile', 'scrum', 'kanban', 'sql', 'product analytics',
                'a/b testing', 'go-to-market', 'accessibility',
            ],
        ],

        /* ------------------------------ non-IT ------------------------------ */

        'sales' => [
            'label' => 'Sales & business development',
            'sector' => 'non_it',
            'titles' => [
                'Sales Development Representative', 'Inside Sales Executive', 'Account Executive',
                'Key Account Manager', 'Business Development Manager', 'Sales Manager',
                'Channel Partner Manager', 'Pre-Sales Consultant',
            ],
            'core' => ['prospecting', 'objection handling', 'negotiation', 'pipeline management'],
            'optional' => [
                'crm', 'salesforce', 'hubspot', 'cold calling', 'email outreach', 'linkedin sales navigator',
                'solution selling', 'b2b sales', 'b2c sales', 'territory planning', 'forecasting',
                'contract negotiation', 'upselling', 'channel sales', 'rfp response',
            ],
        ],

        'marketing' => [
            'label' => 'Marketing & content',
            'sector' => 'non_it',
            'titles' => [
                'Digital Marketing Executive', 'Performance Marketing Manager', 'SEO Specialist',
                'Content Writer', 'Social Media Manager', 'Brand Manager', 'Marketing Manager',
                'Growth Marketer',
            ],
            'core' => ['campaign planning', 'copywriting', 'analytics', 'audience segmentation'],
            'optional' => [
                'google ads', 'meta ads', 'seo', 'sem', 'google analytics', 'email marketing',
                'marketing automation', 'hubspot', 'content strategy', 'influencer marketing',
                'crm', 'conversion rate optimisation', 'canva', 'video editing', 'pr',
            ],
        ],

        'finance' => [
            'label' => 'Finance & accounting',
            'sector' => 'non_it',
            'titles' => [
                'Accountant', 'Accounts Payable Executive', 'Financial Analyst', 'Finance Manager',
                'Internal Auditor', 'Tax Associate', 'Credit Analyst', 'Payroll Executive',
            ],
            'core' => ['bookkeeping', 'reconciliation', 'financial reporting', 'attention to detail'],
            'optional' => [
                'tally', 'sap fico', 'quickbooks', 'zoho books', 'advanced excel', 'gst', 'tds',
                'ifrs', 'ind as', 'budgeting', 'forecasting', 'variance analysis', 'audit',
                'accounts receivable', 'accounts payable', 'financial modelling',
            ],
        ],

        'operations' => [
            'label' => 'Operations & supply chain',
            'sector' => 'non_it',
            'titles' => [
                'Operations Executive', 'Operations Manager', 'Supply Chain Analyst',
                'Logistics Coordinator', 'Procurement Executive', 'Warehouse Manager',
                'Quality Analyst', 'Process Excellence Lead',
            ],
            'core' => ['process improvement', 'coordination', 'reporting', 'problem solving'],
            'optional' => [
                'advanced excel', 'erp', 'sap mm', 'inventory management', 'vendor management',
                'lean', 'six sigma', 'kaizen', 'sop documentation', 'demand planning',
                'logistics', 'procurement', 'root cause analysis', 'sla management',
            ],
        ],

        'people' => [
            'label' => 'HR & people',
            'sector' => 'non_it',
            'titles' => [
                'HR Executive', 'HR Business Partner', 'Technical Recruiter', 'Talent Acquisition Specialist',
                'Learning & Development Executive', 'HR Operations Executive', 'Compensation Analyst',
            ],
            'core' => ['interviewing', 'stakeholder management', 'confidentiality', 'communication'],
            'optional' => [
                'ats', 'boolean search', 'linkedin recruiter', 'onboarding', 'payroll',
                'employee engagement', 'performance management', 'hrms', 'labour law',
                'compensation benchmarking', 'training delivery', 'exit management',
            ],
        ],

        'customer' => [
            'label' => 'Customer support & success',
            'sector' => 'non_it',
            'titles' => [
                'Customer Support Executive', 'Technical Support Engineer', 'Customer Success Manager',
                'Service Desk Analyst', 'Voice Process Executive', 'Escalation Manager',
            ],
            'core' => ['communication', 'empathy', 'issue resolution', 'documentation'],
            'optional' => [
                'zendesk', 'freshdesk', 'jira service management', 'crm', 'ticketing',
                'sla management', 'churn reduction', 'onboarding', 'upselling',
                'troubleshooting', 'knowledge base authoring', 'chat support', 'voice support',
            ],
        ],
    ],

    /*
    | Non-technical dimensions a mock can score. Every role draws on these;
    | the employer chooses which to weight, so a support hire can be weighted
    | to communication where an engineer is weighted to technical depth.
    */
    'competencies' => [
        ['key' => 'technical_depth', 'label' => 'Technical depth', 'hint' => 'Correct, concrete detail in the role\'s core skills.'],
        ['key' => 'problem_solving', 'label' => 'Problem solving', 'hint' => 'Structured reasoning and trade-offs under ambiguity.'],
        ['key' => 'communication', 'label' => 'Communication', 'hint' => 'Clear, concise spoken explanation.'],
        ['key' => 'experience_evidence', 'label' => 'Evidence of experience', 'hint' => 'Real work with outcomes, not generalities.'],
        ['key' => 'interpersonal', 'label' => 'Interpersonal skills', 'hint' => 'Collaboration, conflict handling, stakeholder tone.'],
        ['key' => 'domain_knowledge', 'label' => 'Domain knowledge', 'hint' => 'Understanding of the industry the role sits in.'],
        ['key' => 'ownership', 'label' => 'Ownership', 'hint' => 'Follow-through, accountability, bias to act.'],
    ],

    /*
    | Question formats a mock can mix. Weighting is set per JD by the
    | employer in the mock designer.
    */
    'question_formats' => [
        ['key' => 'scenario', 'label' => 'Scenario', 'hint' => 'Open situational question, spoken answer.'],
        ['key' => 'technical', 'label' => 'Technical', 'hint' => 'Role-specific knowledge, spoken answer.'],
        ['key' => 'mcq', 'label' => 'Multiple choice', 'hint' => 'Objective, auto-scored.'],
        ['key' => 'communication', 'label' => 'Communication', 'hint' => 'Assesses clarity and structure rather than content.'],
        ['key' => 'behavioural', 'label' => 'Behavioural', 'hint' => 'Past behaviour as evidence.'],
    ],
];
