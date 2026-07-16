<?php

declare(strict_types=1);

namespace App\Http\Requests\Support;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $mimes = implode(',', (array) config('support.upload.mimes', ['jpg', 'jpeg', 'png', 'pdf']));
        $maxKb = (int) config('support.upload.max_mb', 10) * 1024;

        return [
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:5', 'max:5000'],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', "mimes:{$mimes}", "max:{$maxKb}"],
        ];
    }
}
