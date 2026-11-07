<?php

use App\Http\Controllers\AssetController;
use Illuminate\Support\Facades\Route;

// Redirect homepage to our asset dashboard
Route::get('/', function () {
    return redirect('/assets');
});

Route::get('/assets', [AssetController::class, 'index'])->name('assets.index'); // <--- Added name here
Route::post('/assets/{asset}/transition', [AssetController::class, 'transition'])->name('assets.transition');

Route::get('/assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
Route::post('/assets/{asset}/checklist', [AssetController::class, 'addChecklistItem'])->name('assets.checklist.add');
Route::post('/assets/{asset}/checklist/{checklist}/toggle', [AssetController::class, 'toggleChecklistItem'])->name('assets.checklist.toggle');