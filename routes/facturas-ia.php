<?php

declare(strict_types=1);

use Appsur\FacturasIa\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('facturas-ia.routes.middleware', ['web', 'auth']))
    ->prefix((string) config('facturas-ia.routes.prefix', 'facturas-ia/ajustes'))
    ->group(function () {
        Route::get('/', [SettingsController::class, 'edit'])->name('facturas-ia.settings.edit');
        Route::put('/', [SettingsController::class, 'update'])->name('facturas-ia.settings.update');
    });
