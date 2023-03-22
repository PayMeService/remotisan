<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRemotisanExecutionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(app(\PayMe\Remotisan\Models\Execution::class)->getTable(), function (Blueprint $table) {
            $table->increments("id");
            $table->string("job_uuid")->unique();
            $table->string("server_uuid");
            $table->string("user_identifier")->nullable()->index();
            $table->string("command");
            $table->string("parameters");
            $table->integer("executed_at")->unsigned()->index();
            $table->integer("finished_at")->unsigned();
            $table->tinyInteger("process_status")->unsigned();
            $table->string("killed_by")->nullable();
            $table->index(["job_uuid", "server_uuid"]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(app(\PayMe\Remotisan\Models\Execution::class)->getTable());
    }
}
