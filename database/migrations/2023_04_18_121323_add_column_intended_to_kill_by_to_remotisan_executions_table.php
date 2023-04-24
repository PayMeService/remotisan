<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnIntendedToKillByToRemotisanExecutionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(app(\PayMe\Remotisan\Models\Execution::class)->getTable(), function (Blueprint $table) {
            $table->string("intended_to_kill_by")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(app(\PayMe\Remotisan\Models\Execution::class)->getTable(), function (Blueprint $table) {
            $table->dropColumn("intended_to_kill_by");
        });
    }
}
