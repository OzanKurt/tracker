<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracker_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                ->constrained('tracker_sessions')
                ->cascadeOnDelete();
            $table->string('name', 128)->index();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_events');
    }
};
