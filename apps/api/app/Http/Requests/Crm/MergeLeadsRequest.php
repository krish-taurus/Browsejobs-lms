<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Merge a duplicate (`loser_id`) into the routed lead. `survivor_id` optionally
 * overrides which of the two records survives; the action defaults to the
 * oldest. Tenant + self-merge guards live in the MergeLeads action.
 */
final class MergeLeadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware enforces can:manage-leads.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'loser_id' => ['required', 'integer'],
            'survivor_id' => ['nullable', 'integer'],
        ];
    }
}
