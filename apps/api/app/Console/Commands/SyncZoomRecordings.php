<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\LiveClasses\RegisterCompletedRecording;
use App\Models\LiveSession;
use App\Models\Recording;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use App\Support\Zoom\ZoomClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Pulls finished Zoom Cloud recordings for ended classes so absentees can
 * replay them from the student portal — the polling counterpart to the Zoom
 * webhook (which can't reach a localhost install). The MP4 is also downloaded
 * onto our own storage so it plays INSIDE the portal (no Zoom page, no
 * passcode). Idempotent per session: once a recording is stored with a local
 * copy the session is skipped; classes older than a week stop being polled.
 */
final class SyncZoomRecordings extends Command
{
    protected $signature = 'zoom:sync-recordings';

    protected $description = 'Register + self-host finished Zoom Cloud recordings for ended classes';

    public function handle(ZoomClient $zoom, RegisterCompletedRecording $register, TenantContext $tenants): int
    {
        $done = Recording::query()->withoutGlobalScope(TenantScope::class)
            ->where('status', 'stored')
            ->whereNotNull('storage_path')
            // A recording under RegisterCompletedRecording::MIN_REAL_SECONDS
            // is treated as a stray host test-join, not the real class — it
            // never counts as "done", so the real recording (once Zoom has
            // it) still gets picked up on a later run instead of being
            // permanently skipped.
            ->where(function ($q) {
                $q->whereNull('duration_seconds')
                    ->orWhere('duration_seconds', '>=', RegisterCompletedRecording::MIN_REAL_SECONDS);
            })
            ->select('live_session_id');

        $candidates = LiveSession::query()->withoutGlobalScope(TenantScope::class)
            ->whereNotNull('zoom_meeting_id')
            ->where('scheduled_start', '<', now())
            ->where('scheduled_start', '>', now()->subWeek())
            ->whereNotIn('id', $done)
            ->get();

        $registered = 0;

        foreach ($candidates as $session) {
            try {
                $data = $zoom->meetingRecordings((string) $session->zoom_meeting_id);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            if ($data === null) {
                continue; // not recorded, or Zoom is still processing
            }

            $mp4 = collect($data['recording_files'] ?? [])
                ->first(fn (array $f) => ($f['file_type'] ?? '') === 'MP4' && ($f['status'] ?? '') === 'completed');

            $playUrl = $mp4['play_url'] ?? $data['share_url'] ?? null;

            if ($playUrl === null) {
                continue;
            }

            $tenant = Tenant::query()->find($session->tenant_id);
            if ($tenant === null) {
                continue;
            }

            $recording = $tenants->run($tenant, function () use ($session, $playUrl, $data, $register): Recording {
                return $register->handle(
                    $session,
                    $session->title,
                    $playUrl,
                    $data['password'] ?? null,
                    isset($data['duration']) ? ((int) $data['duration']) * 60 : null,
                );
            });

            // Self-host the MP4 so the portal can play it inline. Best-effort:
            // if the download fails the Zoom play page (+ passcode) still works.
            if ($recording->storage_path === null && filled($mp4['download_url'] ?? null)) {
                try {
                    $bytes = $zoom->downloadRecording((string) $mp4['download_url']);
                    $path = "recordings/{$session->tenant_id}/session-{$session->id}.mp4";

                    // put() returns false rather than throwing (filesystems.php
                    // has 'throw' => false for this disk) — a permissions or
                    // disk problem would otherwise silently record a
                    // storage_path pointing at a file that was never actually
                    // written, permanently stuck since the "already stored"
                    // check above then skips it on every later run.
                    if (Storage::disk('public')->put($path, $bytes) === false) {
                        throw new \RuntimeException("Storage::put() returned false for {$path}");
                    }

                    $recording->update(['storage_path' => $path, 'size_bytes' => strlen($bytes)]);
                    $this->info("Local copy saved for class #{$session->id} (".round(strlen($bytes) / 1_048_576, 1).' MB).');
                } catch (\Throwable $e) {
                    report($e);
                    $this->warn("Could not self-host class #{$session->id} — the Zoom play page remains the fallback.");
                }
            }

            $registered++;
            $this->info("Recording registered for class #{$session->id} ({$session->title}).");
        }

        $this->info("Done — {$registered} recording(s) processed from {$candidates->count()} candidate class(es).");

        return self::SUCCESS;
    }
}
