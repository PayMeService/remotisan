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
        Schema::create(app(\PayMe\Remotisan\Models\RemotisanAudit::class)->getTable(), function (Blueprint $table) {
            $table->increments("id");
            $table->integer("pid")->unsigned();
            $table->string("uuid")->unique();
            $table->string("user_name")->index();
            $table->string("command");
            $table->string("parameters");
            $table->integer("executed_at")->unsigned()->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(app(\PayMe\Remotisan\Models\RemotisanAudit::class)->getTable());
    }
}
