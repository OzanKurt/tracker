<?php

use OzanKurt\Tracker\Support\Migration;

class CreateTrackerSqlQueryBindingsParametersTable extends Migration
{
    /**
     * Table related to this migration.
     *
     * @var string
     */
    private $table = 'tracker_sql_query_bindings_parameters';

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

                $table->bigInteger('sql_query_bindings_id')->unsigned()->nullable();
                $table->string('name')->nullable()->index();
                $table->text('value')->nullable();

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
