<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracker_page_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                ->constrained('tracker_sessions')
                ->cascadeOnDelete();
            $table->string('method', 8);
            // path is intentionally not indexed — 2048 chars exceeds MySQL 767-byte key limit.
            // Use route_name for aggregation.
            $table->string('path', 2048);
            $table->string('route_name', 128)->nullable()->index();
            $table->string('route_action', 255)->nullable();
            $table->json('route_params')->nullable();
            $table->json('query_params')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_page_views');
    }
};
