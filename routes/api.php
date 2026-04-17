<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Admin\DeployCenterExecutorController;
use Illuminate\Support\Facades\Route;
use App\Support\LocalSafeBoot;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('internal/deploy-center')->group(function () {
    Route::get('/status', [DeployCenterExecutorController::class, 'status']);
    Route::post('/execute', [DeployCenterExecutorController::class, 'execute']);
});

Route::get('/ping', function () {
    return response()->json([
        'ok' => true,
        'message' => 'api pong',
        'local_safe_boot' => LocalSafeBoot::status(),
    ]);
});
