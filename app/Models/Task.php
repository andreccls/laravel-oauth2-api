<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Support\Principal;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $owner
 * @property string $title
 * @property string|null $description
 * @property TaskStatus $status
 * @property Carbon|null $due_at
 */
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'open'];

    protected $fillable = ['title', 'description', 'status', 'due_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => TaskStatus::class, 'due_at' => 'datetime'];
    }

    /** @return HasMany<TaskItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(TaskItem::class)->orderBy('id');
    }

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeOwnedBy(Builder $query, Principal $principal): Builder
    {
        return $query->where('owner', $principal->ownerKey());
    }
}
