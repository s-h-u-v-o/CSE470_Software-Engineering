<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\TicketController;
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
Route::post('/assets/{asset}/holds', [AssetController::class, 'createHold'])->name('assets.holds.create');
Route::post('/assets/{asset}/holds/{hold}/release', [AssetController::class, 'releaseHold'])->name('assets.holds.release');
Route::post('/assets/{asset}/downtime', [AssetController::class, 'startDowntime'])->name('assets.downtime.start');
Route::post('/assets/{asset}/downtime/{event}/end', [AssetController::class, 'endDowntime'])->name('assets.downtime.end');

// F16/F18: ticket workflow and SLA clock
Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
Route::post('/tickets/{ticket}/triage', [TicketController::class, 'triage'])->name('tickets.triage');
Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
Route::post('/tickets/{ticket}/transition', [TicketController::class, 'transition'])->name('tickets.transition');
Route::post('/tickets/{ticket}/reclassify', [TicketController::class, 'reclassify'])->name('tickets.reclassify');
Route::post('/tickets/{ticket}/sla/pause', [TicketController::class, 'pause'])->name('tickets.sla.pause');
Route::post('/tickets/{ticket}/sla/resume', [TicketController::class, 'resume'])->name('tickets.sla.resume');
