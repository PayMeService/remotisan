<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRemotisanAuditTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(app(\PayMe\Remotisan\Models\Audit::class)->getTable(), function (Blueprint $table) {
            $table->increments("id")->primary();
            $table->integer("pid")->unsigned();
            $table->string("uuid")->unique();
            $table->string("instance_uuid")->unique();
            $table->string("user_identifier")->nullable()->index();
            $table->string("command");
            $table->string("parameters");
            $table->integer("executed_at")->unsigned()->index();
            $table->integer("finished_at")->unsigned();
            $table->tinyInteger("process_status")->unsigned();
            $table->index(["uuid", "instance_uuid"]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(app(\PayMe\Remotisan\Models\Audit::class)->getTable());
    }
}
