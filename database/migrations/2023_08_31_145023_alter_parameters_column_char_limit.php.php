<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PayMe\Remotisan\Models\Execution;

/**
 * Created by PhpStorm.
 * User: matan
 * Date: 31/08/2023
 * Time: 15:00
 */
class AlterParametersColumnCharLimit extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(app(Execution::class)->getTable(), function (Blueprint $table) {
            $table->string("parameters", 1000)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(app(Execution::class)->getTable(), function (Blueprint $table) {
            $table->string("parameters", 255)->change();
        });
    }

}