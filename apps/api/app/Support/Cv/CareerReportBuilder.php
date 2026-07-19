<?php

declare(strict_types=1);

namespace App\Support\Cv;

/**
 * The free Career Direction Report (homepage lead magnet, server-gated):
 * deterministic and unbiased — skills are detected by keyword, compared
 * against the market directions the engine already publishes, and EVERY
 * career path is scored the same way, including ones BrowseJobs does not
 * teach (those say so plainly). No AI spend; nothing stored but the lead.
 */
final class CareerReportBuilder
{
    /** skill => [detection keywords, market trend] — mirrors content/skills.ts. */
    private const SKILLS = [
        'SQL' => [['sql', 'mysql', 'postgres', 'oracle'], 'steady'],
        'Python' => [['python', 'pandas', 'numpy'], 'rising'],
        'Spark' => [['spark', 'pyspark', 'databricks'], 'rising'],
        'Airflow' => [['airflow', 'dag'], 'rising'],
        'Kubernetes' => [['kubernetes', 'k8s'], 'rising'],
        'Docker' => [['docker', 'container'], 'steady'],
        'Terraform' => [['terraform', 'iac'], 'rising'],
        'AWS' => [['aws', 'ec2', 's3', 'glue', 'lambda'], 'steady'],
        'Azure' => [['azure'], 'steady'],
        'Power BI' => [['power bi', 'powerbi', 'dax'], 'steady'],
        'dbt' => [['dbt'], 'rising'],
        'Kafka' => [['kafka'], 'rising'],
        'Excel' => [['excel', 'vlookup', 'pivot'], 'steady'],
        'Java' => [['java ', 'spring'], 'steady'],
        'Selenium' => [['selenium', 'testng'], 'steady'],
        'React' => [['react', 'next.js', 'nextjs'], 'steady'],
        'Machine Learning' => [['machine learning', 'tensorflow', 'scikit', 'pytorch', ' ml '], 'rising'],
    ];

    /** path => [core skills, taught by BrowseJobs?] — every path scored identically. */
    private const PATHS = [
        'Data Engineering' => [['SQL', 'Python', 'Spark', 'Airflow', 'AWS', 'dbt', 'Kafka'], true],
        'Data Analytics' => [['SQL', 'Excel', 'Python', 'Power BI'], true],
        'DevOps & Cloud' => [['Docker', 'Kubernetes', 'Terraform', 'AWS'], true],
        'Python Backend' => [['Python', 'SQL', 'Docker', 'AWS'], true],
        'Java Full Stack' => [['Java', 'SQL', 'React'], false],
        'QA Automation' => [['Selenium', 'Java', 'Python'], false],
        'ML / AI Engineering' => [['Python', 'Machine Learning', 'SQL', 'Spark'], false],
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(string $text, QuickAtsCheck $ats): array
    {
        $lower = ' '.mb_strtolower($text).' ';

        $detected = [];
        foreach (self::SKILLS as $skill => [$keywords, $trend]) {
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    $detected[$skill] = $trend;
                    break;
                }
            }
        }

        $paths = [];
        foreach (self::PATHS as $path => [$core, $taught]) {
            $have = array_values(array_intersect($core, array_keys($detected)));
            $missing = array_values(array_diff($core, array_keys($detected)));
            $paths[] = [
                'path' => $path,
                'fit_pct' => (int) round(count($have) / count($core) * 100),
                'have' => $have,
                'missing' => $missing,
                'taught_here' => $taught,
            ];
        }
        // Rank by how much of YOUR resume applies (depth), then by coverage —
        // otherwise narrow paths beat broad ones on percentage alone.
        usort($paths, fn ($a, $b) => [count($b['have']), $b['fit_pct']] <=> [count($a['have']), $a['fit_pct']]);

        // Focus list: the best path's missing skills first (rising before steady),
        // then rising skills already held that deserve depth.
        $best = $paths[0];
        $focus = [];
        foreach ($best['missing'] as $skill) {
            $focus[] = [
                'skill' => $skill,
                'trend' => self::SKILLS[$skill][1],
                'why' => "Core to {$best['path']} and missing from your resume.",
            ];
        }
        foreach ($detected as $skill => $trend) {
            if ($trend === 'rising' && in_array($skill, $best['have'], true) && count($focus) < 6) {
                $focus[] = ['skill' => $skill, 'trend' => $trend, 'why' => 'Already on your resume and rising in demand — go deeper, it compounds.'];
            }
        }
        usort($focus, fn ($a, $b) => ($b['trend'] === 'rising' ? 1 : 0) <=> ($a['trend'] === 'rising' ? 1 : 0));

        $strengths = [];
        foreach ($detected as $skill => $trend) {
            $strengths[] = ['skill' => $skill, 'trend' => $trend];
        }

        return [
            'ats' => $ats->run($text),
            'skills' => $strengths,
            'paths' => array_slice($paths, 0, 5),
            'focus' => array_slice($focus, 0, 6),
            'summary' => [
                'best_path' => $best['path'],
                'best_fit_pct' => $best['fit_pct'],
                'taught_here' => $best['taught_here'],
                'rising_held' => count(array_filter($detected, fn ($t) => $t === 'rising')),
                'total_detected' => count($detected),
            ],
        ];
    }
}
