<?php

use App\Http\Controllers\Api\V1\ApiTokenController;
use App\Http\Controllers\Api\V1\GuardianController;
use App\Http\Controllers\Api\V1\HistoricalDataImportController;
use App\Http\Controllers\Api\V1\ImmunizationController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\PatientController;
use App\Http\Controllers\Api\V1\VaccinationScheduleController;
use App\Http\Controllers\Api\V1\VaccineController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->post('/v1/tokens', [ApiTokenController::class, 'store'])
    ->name('api.v1.tokens.store');

Route::prefix('v1')->middleware(['auth:sanctum', 'active.api'])->group(function () {
    Route::delete('/tokens/current', [ApiTokenController::class, 'destroy'])->name('api.v1.tokens.destroy');
    Route::get('/me', fn (Request $request) => response()->json([
        'data' => [
            'id' => $request->user()->id,
            'name' => $request->user()->name,
            'username' => $request->user()->username,
            'role' => $request->user()->role,
            'health_station_id' => $request->user()->health_station_id,
        ],
    ]))->name('api.v1.me');

    Route::middleware('role:BHW')->group(function () {
        Route::name('api.v1.')->group(function () {
            Route::apiResource('patients', PatientController::class)
                ->parameters(['patients' => 'child'])
                ->except(['destroy']);
            Route::apiResource('guardians', GuardianController::class)->only(['index', 'store', 'show', 'update']);
        });

        Route::delete('/patients/{child}', [PatientController::class, 'destroy'])->name('api.v1.patients.destroy');

        Route::get('/patients/{child}/immunizations', [ImmunizationController::class, 'index'])->name('api.v1.patients.immunizations.index');
        Route::post('/patients/{child}/immunizations', [ImmunizationController::class, 'store'])->name('api.v1.patients.immunizations.store');
        Route::post('/patients/{child}/historical-immunizations', [PatientController::class, 'storeHistoricalImmunization'])->name('api.v1.patients.historical-immunizations.store');
        Route::get('/immunizations/{record}', [ImmunizationController::class, 'show'])->name('api.v1.immunizations.show');
        Route::put('/immunizations/{record}', [ImmunizationController::class, 'update'])->name('api.v1.immunizations.update');
        Route::patch('/immunizations/{record}', [ImmunizationController::class, 'update'])->name('api.v1.immunizations.patch');
        Route::get('/vaccination-schedules', [VaccinationScheduleController::class, 'index'])->name('api.v1.vaccination-schedules.index');
        Route::post('/vaccination-schedules', [VaccinationScheduleController::class, 'store'])->name('api.v1.vaccination-schedules.store');
        Route::patch('/vaccination-schedules/{schedule}', [VaccinationScheduleController::class, 'update'])->name('api.v1.vaccination-schedules.update');
        Route::delete('/vaccination-schedules/{schedule}', [VaccinationScheduleController::class, 'destroy'])->name('api.v1.vaccination-schedules.destroy');
    });

    Route::get('/vaccines', [VaccineController::class, 'index'])->name('api.v1.vaccines.index');
    Route::get('/vaccines/{vaccine}', [VaccineController::class, 'show'])->name('api.v1.vaccines.show');

    Route::middleware('role:BHW,CHO_ADMIN')->get('/inventory', [InventoryController::class, 'index'])->name('api.v1.inventory.index');
    Route::middleware('role:CHO_ADMIN')->group(function () {
        Route::post('/inventory', [InventoryController::class, 'store'])->name('api.v1.inventory.store');
        Route::post('/inventory/{stock}/adjust', [InventoryController::class, 'adjust'])->name('api.v1.inventory.adjust');
        Route::post('/vaccines', [VaccineController::class, 'store'])->name('api.v1.vaccines.store');
        Route::put('/vaccines/{vaccine}', [VaccineController::class, 'update'])->name('api.v1.vaccines.update');
        Route::patch('/vaccines/{vaccine}', [VaccineController::class, 'update'])->name('api.v1.vaccines.patch');
        Route::get('/historical-imports', [HistoricalDataImportController::class, 'index'])->name('api.v1.historical-imports.index');
        Route::post('/historical-imports', [HistoricalDataImportController::class, 'store'])->name('api.v1.historical-imports.store');
        Route::get('/historical-imports/{batch}', [HistoricalDataImportController::class, 'show'])->name('api.v1.historical-imports.show');
    });
});
