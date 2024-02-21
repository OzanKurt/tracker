<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(config('tracker.tables.agents'), function ($table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('browser')->index();
            $table->string('browser_version');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('tracker.tables.agents'));
    }
};
