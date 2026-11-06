<?php

use App\Http\Controllers\AssetController;

Route::put('/assets/{asset}/lifecycle', [AssetController::class, 'updateLifecycle']);