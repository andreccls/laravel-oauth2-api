<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $title
 * @property bool $done
 */
class TaskItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['title', 'done'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['done' => 'boolean'];
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
