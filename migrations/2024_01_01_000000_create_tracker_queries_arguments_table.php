<?php

use Kurt\Tracker\Support\Migration;

class CreateTrackerQueriesArgumentsTable extends Migration
{
    /**
     * Table related to this migration.
     *
     * @var string
     */
    private $table = 'tracker_query_arguments';

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

                $table->bigInteger('query_id')->unsigned()->index();
                $table->string('argument')->index();
                $table->string('value')->index();

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
