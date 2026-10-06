<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BatchMemberStatus;
use App\Models\Batch;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * The student's side of the batch chat: one room per batch they are seated in,
 * where a doubt is asked in front of the whole class rather than in a private
 * message to one trainer.
 *
 * The conversation itself lives in the CRM, where the staff who answer already
 * work. This controller resolves which batches are the student's, then relays
 * to the CRM with the shared secret. The student's browser only ever talks to
 * us; it never sees the CRM or the secret.
 */
final class BatchChatController extends Controller
{
    /** The rooms this student may open. */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => ['batches' => $this->batchesFor($request)]]);
    }

    /** One room and its recent messages. */
    public function show(Request $request, string $batchNumber): JsonResponse
    {
        if (! $this->owns($request, $batchNumber)) {
            return response()->json(['message' => 'That is not one of your batches.'], 403);
        }

        $response = $this->crm($request)->get($this->url($batchNumber), [
            'lms_user_id' => $request->user()->id,
        ]);

        return $this->relay($response);
    }

    /** Ask a question. */
    public function store(Request $request, string $batchNumber): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        if (! $this->owns($request, $batchNumber)) {
            return response()->json(['message' => 'That is not one of your batches.'], 403);
        }

        $response = $this->crm($request)->post($this->url($batchNumber), [
            'lms_user_id' => $request->user()->id,
            'lms_user_name' => $request->user()->name,
            'body' => $validated['body'],
        ]);

        return $this->relay($response);
    }

    /**
     * The batches this student holds a seat in. Checked here rather than
     * trusting the batch number that arrived in the URL.
     *
     * @return list<array{number: string, name: string}>
     */
    private function batchesFor(Request $request): array
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): array {
            $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());

            return Batch::query()
                ->whereHas('members', fn ($q) => $q->where('user_id', $request->user()->id)->whereIn('status', $occupying))
                ->where('status', '!=', 'cancelled')
                ->with('course:id,name')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Batch $b) => [
                    'number' => (string) $b->number,
                    'name' => trim(($b->course?->name ? $b->course->name.' · ' : '').$b->number),
                ])
                ->values()
                ->all();
        });
    }

    private function owns(Request $request, string $batchNumber): bool
    {
        return collect($this->batchesFor($request))->contains('number', $batchNumber);
    }

    private function crm(Request $request)
    {
        return Http::withHeaders([
            'X-Internal-Secret' => (string) config('services.crm.internal_secret'),
            'Accept' => 'application/json',
        ])->timeout(15);
    }

    private function url(string $batchNumber): string
    {
        return rtrim((string) config('services.crm.url'), '/').'/api/internal/batch-chat/'.rawurlencode($batchNumber);
    }

    /**
     * Hand the CRM's answer back unchanged, but never its failure detail — a
     * student has no use for the other app's stack trace, and a chat that is
     * down should read as a chat that is down.
     */
    private function relay($response): JsonResponse
    {
        if ($response->successful()) {
            return response()->json($response->json(), $response->status());
        }

        if ($response->status() === 404) {
            return response()->json(['message' => 'This batch has no chat room yet.'], 404);
        }

        report(new \RuntimeException('Batch chat bridge failed: '.$response->status().' '.mb_substr($response->body(), 0, 300)));

        return response()->json(['message' => 'Chat is unavailable right now — try again shortly.'], 503);
    }
}
