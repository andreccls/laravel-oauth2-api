<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    /** Authorization is the `oauth:tasks:write` route middleware (scope check) + owner-scoped queries. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'due_at' => ['nullable', 'date'],
            'items' => ['sometimes', 'array', 'max:20'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.done' => ['sometimes', 'boolean'],
        ];
    }
}
