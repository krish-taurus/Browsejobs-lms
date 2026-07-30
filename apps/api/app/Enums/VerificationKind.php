<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The checks that make up a candidate's verified profile (PRD-E F9).
 *
 * Each maps to a real-world source: identity and education to DigiLocker's
 * issued documents, employment to the EPFO passbook (a PF contribution
 * history is hard to fabricate, which is exactly why employers ask for it),
 * and documents to anything a human reviewer accepts — offer letters,
 * payslips, relieving letters.
 */
enum VerificationKind: string
{
    case Identity = 'identity';
    case Education = 'education';
    case Employment = 'employment';
    case Documents = 'documents';

    public function label(): string
    {
        return match ($this) {
            self::Identity => 'Identity',
            self::Education => 'Education',
            self::Employment => 'Employment history',
            self::Documents => 'Supporting documents',
        };
    }

    /** What the candidate is asked to provide, in their words. */
    public function prompt(): string
    {
        return match ($this) {
            self::Identity => 'Confirm who you are with a government ID.',
            self::Education => 'Pull your degree or marksheet from the issuing body.',
            self::Employment => 'Confirm your work history against your PF record.',
            self::Documents => 'Upload offer letters or payslips for a human to check.',
        };
    }

    /** Why an employer cares — shown so the ask never feels arbitrary. */
    public function employerValue(): string
    {
        return match ($this) {
            self::Identity => 'The person in the interview is the person on the CV.',
            self::Education => 'The qualification on the CV was actually awarded.',
            self::Employment => 'The roles and dates claimed match contribution records.',
            self::Documents => 'Titles and compensation history stand up to a check.',
        };
    }
}
