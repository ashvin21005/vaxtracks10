<?php

use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\BhwManagementController;
use App\Http\Controllers\ChoMonitoringController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthStationController;
use App\Http\Controllers\OperationsWorkflowController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\VaccineManagementController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::redirect('/register', '/login')->name('register');

Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'redirect'])->name('dashboard');
    Route::get('/bhw/dashboard', [DashboardController::class, 'index'])->middleware('role:BHW')->name('bhw.dashboard');
    Route::get('/cho/dashboard', [DashboardController::class, 'choDashboard'])->middleware('role:CHO_ADMIN')->name('cho.dashboard');
    Route::get('/password/change', [PasswordChangeController::class, 'edit'])->withoutMiddleware('password.changed')->name('password.change');
    Route::put('/password/change', [PasswordChangeController::class, 'update'])->withoutMiddleware('password.changed')->name('password.update');

    Route::middleware('role:BHW')->group(function () {
        Route::post('/patients/{child}/historical-immunizations', [PatientController::class, 'storeHistoricalImmunization'])
            ->name('patients.historical-immunizations.store');
        Route::resource('patients', PatientController::class)
            ->parameters(['patients' => 'child']);

        Route::get('/immunizations', [OperationsWorkflowController::class, 'immunizations'])->name('immunizations.index');
        Route::get('/immunizations/create', [OperationsWorkflowController::class, 'createImmunization'])->name('immunizations.create');
        Route::post('/immunizations', [OperationsWorkflowController::class, 'storeImmunization'])->name('immunizations.store');
        Route::get('/immunizations/{record}/edit', [OperationsWorkflowController::class, 'editImmunization'])->name('immunizations.edit');
        Route::put('/immunizations/{record}', [OperationsWorkflowController::class, 'updateImmunization'])->name('immunizations.update');
        Route::delete('/immunizations/{record}', [OperationsWorkflowController::class, 'destroyImmunization'])->name('immunizations.destroy');
        Route::get('/vaccination-schedule', [OperationsWorkflowController::class, 'schedule'])->name('schedule.index');
        Route::patch('/vaccination-schedule/{schedule}', [OperationsWorkflowController::class, 'updateScheduleStatus'])->name('schedule.update');
        Route::delete('/vaccination-schedule/{schedule}', [OperationsWorkflowController::class, 'destroySchedule'])->name('schedule.destroy');
        Route::get('/sms-monitoring', [OperationsWorkflowController::class, 'sms'])->name('sms.index');
        Route::post('/sms-monitoring/bulk-send', [OperationsWorkflowController::class, 'bulkSendSms'])->name('sms.bulk-send');
        Route::post('/sms-monitoring/{notification}/send', [OperationsWorkflowController::class, 'sendSms'])->name('sms.send');
        Route::post('/sms-monitoring/{notification}/resend', [OperationsWorkflowController::class, 'resendSms'])->name('sms.resend');
        Route::patch('/sms-monitoring/bulk-update', [OperationsWorkflowController::class, 'bulkUpdateSmsStatus'])->name('sms.bulk-update');
        Route::patch('/sms-monitoring/{notification}', [OperationsWorkflowController::class, 'updateSmsStatus'])->name('sms.update');
    });
    Route::get('/vaccine-inventory', [OperationsWorkflowController::class, 'inventory'])->name('inventory.index');
    Route::get('/settings', [AccountSettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [AccountSettingsController::class, 'update'])->name('settings.update');

    Route::middleware('role:CHO_ADMIN')->prefix('cho')->name('cho.')->group(function () {
        Route::post('/vaccine-inventory', [OperationsWorkflowController::class, 'storeInventory'])->name('inventory.store');
        Route::post('/vaccine-inventory/{stock}/replenish', [OperationsWorkflowController::class, 'adjustInventory'])->name('inventory.adjust');
        Route::get('/demand-forecast', [ChoMonitoringController::class, 'forecast'])->name('forecast');
        Route::get('/stations', [HealthStationController::class, 'index'])->name('stations.index');
        Route::get('/stations/create', [HealthStationController::class, 'create'])->name('stations.create');
        Route::post('/stations', [HealthStationController::class, 'store'])->name('stations.store');
        Route::get('/stations/{station}/edit', [HealthStationController::class, 'edit'])->name('stations.edit');
        Route::put('/stations/{station}', [HealthStationController::class, 'update'])->name('stations.update');
        Route::delete('/stations/{station}', [HealthStationController::class, 'destroy'])->name('stations.destroy');
        Route::get('/stations/{station}', [HealthStationController::class, 'show'])->name('stations.show');
        Route::get('/bhws', [BhwManagementController::class, 'index'])->name('bhws.index');
        Route::get('/bhws/create', [BhwManagementController::class, 'create'])->name('bhws.create');
        Route::post('/bhws', [BhwManagementController::class, 'store'])->name('bhws.store');
        Route::patch('/bhws/{user}', [BhwManagementController::class, 'update'])->name('bhws.update');
        Route::delete('/bhws/{user}', [BhwManagementController::class, 'destroy'])->name('bhws.destroy');
        Route::get('/vaccines', [VaccineManagementController::class, 'index'])->name('vaccines.index');
        Route::get('/vaccines/create', [VaccineManagementController::class, 'create'])->name('vaccines.create');
        Route::post('/vaccines', [VaccineManagementController::class, 'store'])->name('vaccines.store');
        Route::get('/vaccines/{vaccine}/edit', [VaccineManagementController::class, 'edit'])->name('vaccines.edit');
        Route::put('/vaccines/{vaccine}', [VaccineManagementController::class, 'update'])->name('vaccines.update');
        Route::delete('/vaccines/{vaccine}', [VaccineManagementController::class, 'destroy'])->name('vaccines.destroy');
    });
});
