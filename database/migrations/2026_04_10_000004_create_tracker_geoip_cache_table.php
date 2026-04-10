<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracker_geoip_cache', function (Blueprint $table) {
            $table->id();
            $table->char('ip_hash', 64)->unique();
            $table->char('country_code', 2)->nullable();
            $table->string('country_name', 128)->nullable();
            $table->string('city', 128)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('provider', 32);
            $table->timestamp('cached_until')->index();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_geoip_cache');
    }
};
