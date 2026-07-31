<?php

declare(strict_types=1);

namespace App\Support\Employers;

use App\Support\Roles\RoleTaxonomy;

/**
 * An employer's interview design for one JD (PRD-E F16).
 *
 * Weights are stored as the employer entered them and normalised to 100 on
 * read. Forcing them to sum to 100 in the form is the kind of arithmetic
 * homework that makes people give up on a feature; normalising means
 * "communication 3, technical 1" expresses a ratio and still produces a
 * usable rubric.
 *
 * Unknown competency or format keys are dropped rather than trusted — the
 * taxonomy is the vocabulary, and a key outside it would reach the grading
 * prompt as an instruction nobody wrote.
 */
final readonly class MockDesign
{
    /**
     * @param  list<string>  $focusSkills
     * @param  array<string, int>  $competencyWeights  competency key => raw weight
     * @param  array<string, int>  $formatMix  format key => raw weight
     */
    public function __construct(
        public array $focusSkills = [],
        public array $competencyWeights = [],
        public array $formatMix = [],
        public ?int $questionCount = null,
        public ?string $notes = null,
    ) {}

    /**
     * Build from stored or submitted data, keeping only vocabulary the
     * taxonomy knows.
     *
     * @param  array<string, mixed>|null  $raw
     */
    public static function fromArray(?array $raw, RoleTaxonomy $taxonomy): self
    {
        if ($raw === null || $raw === []) {
            return new self;
        }

        $validCompetencies = array_column($taxonomy->competencies(), 'key');
        $validFormats = array_column($taxonomy->questionFormats(), 'key');

        return new self(
            focusSkills: self::strings($raw['focus_skills'] ?? [], 12),
            competencyWeights: self::weights($raw['competency_weights'] ?? [], $validCompetencies),
            formatMix: self::weights($raw['format_mix'] ?? [], $validFormats),
            questionCount: isset($raw['question_count']) ? max(4, min(20, (int) $raw['question_count'])) : null,
            notes: is_string($raw['notes'] ?? null) && trim($raw['notes']) !== ''
                ? mb_substr(trim($raw['notes']), 0, 800)
                : null,
        );
    }

    public function isEmpty(): bool
    {
        return $this->focusSkills === []
            && $this->competencyWeights === []
            && $this->formatMix === []
            && $this->questionCount === null
            && $this->notes === null;
    }

    /**
     * Competency weights as percentages summing to 100. Empty when the
     * employer set none, so the generator falls back to its own rubric.
     *
     * @return array<string, int>
     */
    public function normalisedCompetencies(): array
    {
        return self::normalise($this->competencyWeights);
    }

    /**
     * @return array<string, int>
     */
    public function normalisedFormats(): array
    {
        return self::normalise($this->formatMix);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'focus_skills' => $this->focusSkills,
            'competency_weights' => $this->competencyWeights,
            'format_mix' => $this->formatMix,
            'question_count' => $this->questionCount,
            'notes' => $this->notes,
        ];
    }

    /**
     * Human-readable instruction for the generation prompt. Empty string
     * when nothing was designed, so the prompt stays as it was.
     */
    public function toPromptInstruction(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $parts = [];

        if ($this->focusSkills !== []) {
            $parts[] = 'Focus the questions on these skills above all others: '.implode(', ', $this->focusSkills).'.';
        }

        $competencies = $this->normalisedCompetencies();
        if ($competencies !== []) {
            $pairs = [];
            foreach ($competencies as $key => $pct) {
                $pairs[] = str_replace('_', ' ', $key)." {$pct}%";
            }
            $parts[] = 'Weight the rubric dimensions in these proportions: '.implode(', ', $pairs).'.';
        }

        $formats = $this->normalisedFormats();
        if ($formats !== []) {
            $pairs = [];
            foreach ($formats as $key => $pct) {
                $pairs[] = "{$key} {$pct}%";
            }
            $parts[] = 'Mix question formats roughly in these proportions: '.implode(', ', $pairs).'.';
        }

        if ($this->questionCount !== null) {
            $parts[] = "Produce {$this->questionCount} questions.";
        }

        if ($this->notes !== null) {
            $parts[] = 'The employer added: '.$this->notes;
        }

        return implode(' ', $parts);
    }

    /**
     * @param  list<string>  $allowed
     * @return array<string, int>
     */
    private static function weights(mixed $raw, array $allowed): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $key => $value) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                continue;
            }
            $weight = (int) $value;
            if ($weight > 0) {
                $out[$key] = min(100, $weight);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, int>  $weights
     * @return array<string, int>
     */
    private static function normalise(array $weights): array
    {
        $total = array_sum($weights);

        if ($total <= 0) {
            return [];
        }

        $out = [];
        foreach ($weights as $key => $weight) {
            $out[$key] = (int) round(($weight / $total) * 100);
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $raw, int $limit): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (! is_string($item)) {
                continue;
            }
            $value = mb_strtolower(trim($item));
            if ($value !== '' && mb_strlen($value) <= 60 && ! in_array($value, $out, true)) {
                $out[] = $value;
            }
        }

        return array_slice($out, 0, $limit);
    }
}
