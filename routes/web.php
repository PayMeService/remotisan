<?php
use Illuminate\Support\Facades\Route;
use PayMe\Remotisan\Http\Controllers\RemotisanController;

Route::middleware('web')
    ->prefix(config("remotisan.base_url_prefix"))->group(function () {
    Route::get('/', [RemotisanController::class, "index"]);
    Route::get('/commands', [RemotisanController::class, "commands"]);
    Route::post('/execute', [RemotisanController::class, "execute"]);
    Route::get('/execute/{executionUuid}', [RemotisanController::class, "read"]);
});
