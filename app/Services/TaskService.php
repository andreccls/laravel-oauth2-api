<?php

namespace App\Services;

use App\Models\Task;
use App\Support\Principal;
use Illuminate\Support\Facades\DB;

final class TaskService
{
    /** @param array<string, mixed> $data validated payload (title, description, status, due_at, items) */
    public function create(Principal $owner, array $data): Task
    {
        return DB::transaction(function () use ($owner, $data) {
            $task = new Task(collect($data)->except('items')->all());
            $task->owner = $owner->ownerKey();
            $task->save();
            $this->replaceItems($task, $data);

            return $task->load('items');
        });
    }

    /** @param array<string, mixed> $data only the keys present are changed; `items`, when present, replaces all items */
    public function update(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data) {
            $task->fill(collect($data)->except('items')->all())->save();
            $this->replaceItems($task, $data);

            return $task->load('items');
        });
    }

    /** @param array<string, mixed> $data */
    private function replaceItems(Task $task, array $data): void
    {
        if (! array_key_exists('items', $data)) {
            return;
        }
        $task->items()->delete();
        $task->items()->createMany($data['items']);
    }
}
