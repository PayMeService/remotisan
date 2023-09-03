<?php
use Illuminate\Support\Facades\Route;
use PayMe\Remotisan\Http\Controllers\RemotisanController;

Route::middleware("web")
    ->prefix(config("remotisan.url"))->group(function () {
        Route::get("/", [RemotisanController::class, "index"]);
        Route::get("/commands", [RemotisanController::class, "commands"]);
        Route::post("/execute", [RemotisanController::class, "execute"]);
        Route::get("/execute/{executionUuid}", [RemotisanController::class, "read"]);
        Route::post("/kill/{uuid}", [RemotisanController::class, "sendKillSignal"]);
        Route::get("/history", [RemotisanController::class, "history"]);
    });
