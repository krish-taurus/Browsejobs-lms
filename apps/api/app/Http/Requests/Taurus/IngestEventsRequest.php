<?php

declare(strict_types=1);

namespace App\Http\Requests\Taurus;

use App\Enums\TaurusAgentStatus;
use App\Enums\TaurusTaskStatus;
use App\Models\TaurusAgent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A batch of bot reports (ADR 0052). Auth is the bearer token, enforced by
 * the `taurus.ingest` middleware before this runs.
 */
final class IngestEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'floor' => ['sometimes', 'string', Rule::in(TaurusAgent::FLOORS)],
            'events' => ['required', 'array', 'min:1', 'max:100'],
            'events.*' => ['required', 'array'],

            'events.*.agent' => ['required', 'array'],
            'events.*.agent.slug' => ['required', 'string', 'alpha_dash', 'max:64'],
            'events.*.agent.name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'events.*.agent.zone' => ['sometimes', 'nullable', 'string', 'max:80'],
            'events.*.agent.role' => ['sometimes', 'nullable', 'string', 'max:120'],
            'events.*.agent.platform' => ['sometimes', 'nullable', 'string', 'max:40'],

            'events.*.status' => ['sometimes', 'nullable', 'string', Rule::in(TaurusAgentStatus::values())],

            'events.*.task' => ['sometimes', 'nullable', 'array'],
            'events.*.task.ref' => ['required_with:events.*.task', 'string', 'max:120'],
            'events.*.task.title' => ['sometimes', 'nullable', 'string', 'max:240'],
            'events.*.task.status' => ['sometimes', 'nullable', 'string', Rule::in(TaurusTaskStatus::reportable())],
            'events.*.task.progress' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1'],
            'events.*.task.approval' => ['sometimes', 'nullable', 'array'],
            'events.*.task.approval.action' => ['required_with:events.*.task.approval', 'string', 'max:240'],
            'events.*.task.approval.risk' => ['sometimes', 'nullable', 'string', Rule::in(['low', 'high'])],

            'events.*.message' => ['sometimes', 'nullable', 'string', 'max:500'],

            'events.*.spend' => ['sometimes', 'nullable', 'array'],
            'events.*.spend.source' => ['required_with:events.*.spend', 'string', 'max:80'],
            'events.*.spend.amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000000000'],
            'events.*.spend.currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'events.*.spend.units' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'events.*.spend.unit_label' => ['sometimes', 'nullable', 'string', 'max:40'],

            'events.*.occurred_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
