<?php

declare(strict_types=1);

namespace App\Enums;

enum SubmissionKind: string
{
    case Run = 'run';        // a free run, no grading
    case Submit = 'submit';  // run against the lab's test cases
}
