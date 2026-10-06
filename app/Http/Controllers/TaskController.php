<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListTasksRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use App\Support\Principal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function index(ListTasksRequest $request): AnonymousResourceCollection
    {
        $page = Task::query()
            ->ownedBy(Principal::from($request))
            ->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status))
            ->with('items') // eager loading: 1 extra query for the whole page, never N
            ->orderByDesc('id')
            ->paginate((int) $request->validated('per_page', 15));

        return TaskResource::collection($page);
    }

    public function show(Request $request, int $id): TaskResource
    {
        return new TaskResource($this->owned($request, $id));
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $this->tasks->create(Principal::from($request), $request->validated());

        return (new TaskResource($task))->response()->setStatusCode(201)->header('Location', url("/api/tasks/{$task->id}"));
    }

    public function update(UpdateTaskRequest $request, int $id): TaskResource
    {
        return new TaskResource($this->tasks->update($this->owned($request, $id), $request->validated()));
    }

    public function destroy(Request $request, int $id): Response
    {
        $this->owned($request, $id)->delete();

        return response()->noContent();
    }

    /** Someone else's task is indistinguishable from a missing one (404, not 403): no existence leak. */
    private function owned(Request $request, int $id): Task
    {
        return Task::query()->ownedBy(Principal::from($request))->with('items')->findOrFail($id);
    }
}
