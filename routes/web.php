<?php

/**
 * Created by PhpStorm.
 * User: omer
 * Date: 04/11/2022
 * Time: 20:35
 */

use Illuminate\Support\Facades\Route;
use PayMe\Remotisan\Http\Controllers\RemotisanController;

Route::prefix(config("remotisan.url"))->group(function () {
    Route::get('/', [RemotisanController::class, "index"]);
    Route::get('/commands', [RemotisanController::class, "commands"]);
    Route::post('/execute', [RemotisanController::class, "execute"]);
    Route::get('/execute/{executionUuid}', [RemotisanController::class, "read"]);
});
