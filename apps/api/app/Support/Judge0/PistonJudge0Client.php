<?php

declare(strict_types=1);

namespace App\Support\Judge0;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Runs coding-lab submissions on a self-hosted Piston instance.
 *
 * Judge0 CE stayed unusable here: its stable line needs cgroup v1, and this box
 * runs cgroup v2 — switching would mean a GRUB change and a reboot of the server
 * the LMS and CRM both live on. Piston needs neither, so it runs in Docker on
 * localhost:2000 and this adapter speaks Judge0's shape to the rest of the app.
 *
 * The interface stays {@see Judge0Client} deliberately: language ids, statuses
 * and {@see Judge0Result} are Judge0's vocabulary and are used all through the
 * lab code. Only this class knows Piston exists.
 */
final readonly class PistonJudge0Client implements Judge0Client
{
    /**
     * Judge0 CE language id => the Piston runtime that stands in for it.
     * Keyed on the ids in config/coding_labs.php so the two cannot drift.
     *
     * @var array<int, array{language: string, version: string}>
     */
    private const RUNTIMES = [
        71 => ['language' => 'python', 'version' => '3.12.0'],
        63 => ['language' => 'javascript', 'version' => '20.11.1'],
        46 => ['language' => 'bash', 'version' => '5.2.0'],
        82 => ['language' => 'sqlite3', 'version' => '3.36.0'],
    ];

    /** Judge0 status ids this adapter reports back. */
    private const ACCEPTED = 3;

    private const COMPILE_ERROR = 6;

    private const RUNTIME_ERROR = 11;

    private const INTERNAL_ERROR = 13;

    /**
     * @param  array{url: string, timeout_ms: int}  $config
     */
    public function __construct(private array $config) {}

    public function execute(int $languageId, string $source, ?string $stdin = null): Judge0Result
    {
        $runtime = self::RUNTIMES[$languageId] ?? null;

        if ($runtime === null) {
            return $this->failure("Language {$languageId} is not installed on this runner.");
        }

        try {
            $response = Http::baseUrl($this->config['url'])
                ->acceptJson()
                ->timeout(30)
                ->post('/api/v2/execute', [
                    'language' => $runtime['language'],
                    'version' => $runtime['version'],
                    'files' => [['content' => $source]],
                    'stdin' => $stdin ?? '',
                    // Piston refuses anything above its own configured ceiling,
                    // so clamp rather than let a valid config value 400 the run.
                    'run_timeout' => min($this->config['timeout_ms'], 3_000),
                ]);
        } catch (Throwable $e) {
            report($e);

            return $this->failure('The code runner is not responding. Please try again in a moment.');
        }

        if ($response->failed()) {
            return $this->failure((string) $response->json('message', 'The code runner rejected this submission.'));
        }

        $run = (array) $response->json('run', []);
        $compile = (array) $response->json('compile', []);

        $compileOutput = trim((string) ($compile['stderr'] ?? ''));
        if ($compileOutput !== '' && (int) ($compile['code'] ?? 0) !== 0) {
            return new Judge0Result('', '', $compileOutput, self::COMPILE_ERROR, 'Compilation Error', 0, 0);
        }

        $exitCode = (int) ($run['code'] ?? 0);
        $signal = $run['signal'] ?? null;
        $ok = $exitCode === 0 && $signal === null;

        return new Judge0Result(
            (string) ($run['stdout'] ?? ''),
            (string) ($run['stderr'] ?? ''),
            '',
            $ok ? self::ACCEPTED : self::RUNTIME_ERROR,
            // A killed process reports the signal, which is how a student sees
            // "your loop never finished" rather than a bare non-zero exit.
            $ok ? 'Accepted' : ($signal !== null ? "Killed ({$signal})" : "Runtime Error (exit {$exitCode})"),
            (int) ($run['wall_time'] ?? 0),
            // Piston reports bytes; Judge0Result carries kilobytes.
            (int) round(((int) ($run['memory'] ?? 0)) / 1024),
        );
    }

    private function failure(string $message): Judge0Result
    {
        return new Judge0Result('', $message, '', self::INTERNAL_ERROR, 'Internal Error', 0, 0);
    }
}
