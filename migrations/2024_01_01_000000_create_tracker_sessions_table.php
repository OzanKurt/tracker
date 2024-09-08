<?php

use OzanKurt\Tracker\Support\Migration;

class CreateTrackerSessionsTable extends Migration
{
    /**
     * Table related to this migration.
     *
     * @var string
     */
    private $table = 'tracker_sessions';

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

                $table->string('uuid')->unique()->index();
                $table->bigInteger('user_id')->unsigned()->nullable()->index();
                $table->bigInteger('device_id')->unsigned()->nullable()->index();
                $table->bigInteger('agent_id')->unsigned()->nullable()->index();
                $table->string('client_ip')->index();
                $table->bigInteger('referer_id')->unsigned()->nullable()->index();
                $table->bigInteger('cookie_id')->unsigned()->nullable()->index();
                $table->bigInteger('geoip_id')->unsigned()->nullable()->index();
                $table->boolean('is_robot');

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
