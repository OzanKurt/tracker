<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(config('tracker.tables.logs'), function ($table) {
            $table->id();
            $table->foreignId('session_id')->index();
            $table->foreignId('path_id')->nullable()->index();
            $table->foreignId('query_id')->nullable()->index();
            $table->string('method', 10)->index();
            $table->foreignId('route_path_id')->nullable()->index();
            $table->boolean('is_ajax');
            $table->boolean('is_secure');
            $table->boolean('is_json');
            $table->boolean('wants_json');
            $table->foreignId('error_id')->nullable()->index();
            $table->foreignId('referer_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('tracker.tables.logs'));
    }
};
