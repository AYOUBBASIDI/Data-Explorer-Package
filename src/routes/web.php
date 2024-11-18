<?php

use Illuminate\Support\Facades\Route;
use Basidi\DataExplorer\Http\Controllers\ExportImportController;


Route::group(['prefix' => 'data-explorer'], function () {
    Route::get('/', [ExportImportController::class, 'index'])->name('data-explorer.index');
    Route::post('/export', [ExportImportController::class, 'export'])->name('data-explorer.export');
    Route::post('/import', [ExportImportController::class, 'import'])->name('data-explorer.import');
});