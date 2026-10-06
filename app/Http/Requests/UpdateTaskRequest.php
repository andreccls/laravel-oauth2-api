<?php

namespace App\Http\Requests;

/** PUT/PATCH: same rules, but every top-level field is optional. */
class UpdateTaskRequest extends StoreTaskRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return collect(parent::rules())
            ->map(fn (array $rules, string $key) => str_contains($key, '.') || $rules[0] === 'sometimes' ? $rules : ['sometimes', ...$rules])
            ->all();
    }
}
