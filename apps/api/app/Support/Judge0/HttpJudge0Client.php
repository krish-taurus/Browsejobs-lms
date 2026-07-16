<?php

declare(strict_types=1);

namespace App\Support\Judge0;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Real Judge0 client (self-hosted, PRD §6.5). Submits synchronously (`wait=true`)
 * so the interactive Run returns output directly. Unit tests use {@see FakeJudge0Client}.
 */
final class HttpJudge0Client implements Judge0Client
{
    /**
     * @param  array{url: string, auth_token: string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function execute(int $languageId, string $source, ?string $stdin = null): Judge0Result
    {
        $response = $this->request()->post('/submissions?base64_encoded=false&wait=true', array_filter([
            'language_id' => $languageId,
            'source_code' => $source,
            'stdin' => $stdin,
            'cpu_time_limit' => (int) config('coding_labs.time_limit_ms', 5000) / 1000,
        ], static fn ($v) => $v !== null))->throw()->json();

        return new Judge0Result(
            stdout: (string) ($response['stdout'] ?? ''),
            stderr: (string) ($response['stderr'] ?? ''),
            compileOutput: (string) ($response['compile_output'] ?? ''),
            statusId: (int) data_get($response, 'status.id', 0),
            statusText: (string) data_get($response, 'status.description', ''),
            timeMs: (int) round(((float) ($response['time'] ?? 0)) * 1000),
            memoryKb: (int) ($response['memory'] ?? 0),
        );
    }

    private function request(): PendingRequest
    {
        $request = Http::baseUrl($this->config['url'])->acceptJson();

        if (($this->config['auth_token'] ?? '') !== '') {
            $request = $request->withHeaders(['X-Auth-Token' => $this->config['auth_token']]);
        }

        return $request;
    }
}
