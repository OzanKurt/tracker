<?php

use OzanKurt\Tracker\Support\Migration;

class CreateTrackerEventsLogTable extends Migration
{
    /**
     * Table related to this migration.
     *
     * @var string
     */
    private $table = 'tracker_events_log';

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

                $table->bigInteger('event_id')->unsigned()->index();
                $table->bigInteger('class_id')->unsigned()->nullable()->index();
                $table->bigInteger('log_id')->unsigned()->index();

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
