<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('owner', 64); // "user:{id}" or "client:{uuid}"
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 16)->default('open');
            $table->timestamp('due_at')->nullable();
            $table->timestamps();

            // List newest-first per owner. Declaration order matters: with equal prefixes MySQL 8.4 picks the first
            // index it finds, and (owner, status, id) would turn the unfiltered list into a filesort (checked with EXPLAIN).
            $table->index(['owner', 'id']);
            $table->index(['owner', 'status', 'id']); // same list filtered by status: backward index scan, no filesort
        });

        Schema::create('task_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete(); // FK also creates the index used by eager loading
            $table->string('title');
            $table->boolean('done')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_items');
        Schema::dropIfExists('tasks');
    }
};
