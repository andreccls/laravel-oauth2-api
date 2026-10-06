<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskItem;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaskPerformanceTest extends TestCase
{
    private function seedTasks(string $owner, int $count): void
    {
        Task::factory()->count($count)->ownedBy($owner)->create()
            ->each(fn (Task $t) => TaskItem::query()->insert([
                ['task_id' => $t->id, 'title' => 'a', 'done' => false],
                ['task_id' => $t->id, 'title' => 'b', 'done' => true],
            ]));
    }

    /** Counts only the application queries of the list call (token lookups excluded by measuring a warmed request). */
    private function queriesForList(string $token, int $perPage): int
    {
        $this->getJson("/api/tasks?per_page=$perPage", $this->bearer($token))->assertOk(); // warm-up
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson("/api/tasks?per_page=$perPage", $this->bearer($token))->assertOk()->assertJsonCount($perPage, 'data');
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_list_query_count_does_not_grow_with_the_number_of_tasks(): void
    {
        $client = $this->machineClient();
        $token = $this->clientCredentialsToken($client);
        $this->seedTasks('client:'.$client->getKey(), 25);

        $small = $this->queriesForList($token, 2);
        $large = $this->queriesForList($token, 25);

        $this->assertSame($small, $large, 'N+1 detected: query count changed with page size');
        $this->assertLessThanOrEqual(5, $large);
    }

    public function test_lazy_loading_is_forbidden_outside_production(): void
    {
        Task::factory()->count(2)->create();
        $tasks = Task::query()->get(); // the guard only applies to models loaded as a collection

        $this->expectException(LazyLoadingViolationException::class);
        $tasks->firstOrFail()->items->count(); // relation not eager loaded: must blow up in dev/test
    }
}
