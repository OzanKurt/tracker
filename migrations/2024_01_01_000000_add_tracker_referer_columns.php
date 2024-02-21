<?php

use Kurt\Tracker\Support\Migration;

class AddTrackerRefererColumns extends Migration
{
    /**
     * Table related to this migration.
     *
     * @var string
     */
    private $table = 'tracker_referers';

    private $foreign = 'tracker_referers_search_terms';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function migrateUp()
    {
        $this->builder->table(
            $this->table,
            function ($table) {
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function migrateDown()
    {
        $this->builder->table(
            $this->table,
            function ($table) {
                $table->dropColumn('medium');
                $table->dropColumn('source');
                $table->dropColumn('search_terms_hash');
            }
        );

        $this->builder->table(
            $this->foreign,
            function ($table) {
                $table->dropForeign('tracker_referers_referer_id_fk');
            }
        );
    }
}
