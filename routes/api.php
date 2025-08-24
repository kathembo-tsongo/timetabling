<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiExamTimetableController;

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

Route::get('/exam-timetable', [ApiExamTimetableController::class, 'index']);
Route::post('/exam-timetable/generate', [ApiExamTimetableController::class, 'generate']);





// CSP Solver routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/csp-solver/optimize', [CSPSolverController::class, 'optimize']);
});

// Alternative route if you're not using Sanctum
Route::middleware(['web'])->group(function () {
    Route::post('/csp-solver/optimize', [CSPSolverController::class, 'optimize']);
});

