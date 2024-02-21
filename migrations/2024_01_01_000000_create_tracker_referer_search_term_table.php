<?php

use Kurt\Tracker\Support\Migration;

class CreateTrackerRefererSearchTermTable extends Migration
{
    /**
     * Table related to this migration.
     *
     * @var string
     */
    private $table = 'tracker_referers_search_terms';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function migrateUp()
    {
        $this->builder->create(
            $this->table,
            function ($table) {
                $table->id();

                $table->bigInteger('referer_id')->unsigned()->index();
                $table->string('search_term')->index();

                $table->timestamps();
            }
        );
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
