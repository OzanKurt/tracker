<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracker_sessions', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->char('visitor_uuid', 36)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->string('client_ip', 45)->index();
            $table->text('user_agent');

            $table->string('device_kind', 32);
            $table->string('device_model', 64)->nullable();
            $table->string('device_platform', 32);
            $table->string('device_platform_ver', 32)->nullable();
            $table->string('browser', 64);
            $table->string('browser_version', 32);

            $table->string('language', 10);
            $table->string('language_range', 64);

            $table->boolean('is_robot')->default(false);

            $table->char('country_code', 2)->nullable()->index();
            $table->string('country_name', 128)->nullable();
            $table->string('city', 128)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();

            $table->text('referer_url')->nullable();
            $table->string('referer_domain', 255)->nullable()->index();
            $table->string('referer_medium', 32)->nullable();
            $table->string('referer_source', 64)->nullable();
            $table->string('referer_search_term', 255)->nullable();

            $table->timestamp('started_at')->index();
            $table->timestamp('last_activity_at')->index();
            $table->timestamp('ended_at')->nullable();

            $table->unsignedInteger('page_views_count')->default(0);
            $table->unsignedInteger('events_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_sessions');
    }
};
