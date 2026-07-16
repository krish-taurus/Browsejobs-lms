<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\StoreMessageTemplateRequest;
use App\Http\Resources\MessageTemplateResource;
use App\Models\MessageTemplate;
use App\Support\Messaging\BannedPhraseLinter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin message template manager (PRD §6.9). Every save runs the banned-phrase
 * linter. Gated by `can:manage-messaging`.
 */
final class MessageTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $templates = MessageTemplate::query()->orderBy('key')->orderBy('channel')->get();

        return MessageTemplateResource::collection($templates)->response();
    }

    public function store(StoreMessageTemplateRequest $request, BannedPhraseLinter $linter): JsonResponse
    {
        $this->assertClean($request, $linter);
        $tenant = app(TenantContext::class)->get();

        $template = MessageTemplate::query()->create([
            ...$request->validated(),
            'tenant_id' => $tenant->id,
            'locale' => $request->input('locale', 'en'),
        ]);

        return (new MessageTemplateResource($template))->response()->setStatusCode(201);
    }

    public function update(StoreMessageTemplateRequest $request, MessageTemplate $template, BannedPhraseLinter $linter): JsonResponse
    {
        $this->assertClean($request, $linter);
        $template->update($request->validated());

        return (new MessageTemplateResource($template))->response();
    }

    /** Live banned-phrase check for the editor. */
    public function preview(Request $request, BannedPhraseLinter $linter): JsonResponse
    {
        $text = (string) $request->input('body', '').' '.(string) $request->input('subject', '');

        return response()->json(['data' => ['violations' => $linter->violations($text)]]);
    }

    private function assertClean(StoreMessageTemplateRequest $request, BannedPhraseLinter $linter): void
    {
        $text = (string) $request->input('body').' '.(string) $request->input('subject', '');
        $violations = $linter->violations($text);

        if ($violations !== []) {
            throw ValidationException::withMessages([
                'body' => 'Contains a banned phrase: '.implode(', ', $violations),
            ]);
        }
    }
}
