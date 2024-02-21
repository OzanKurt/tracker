<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(config('tracker.tables.referers'), function ($table) {
            $table->id();

            $table->bigInteger('domain_id')->unsigned()->index();
            $table->string('url')->index();
            $table->string('host');

            $table->string('medium')->nullable()->index();
            $table->string('source')->nullable()->index();
            $table->string('search_terms_hash')->nullable()->index();

            $table->foreign('referer_id', 'tracker_referers_referer_id_fk')
                ->references('id')
                ->on('tracker_referers')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function migrateDown()
    {
        $this->drop($this->table);
    }
}
