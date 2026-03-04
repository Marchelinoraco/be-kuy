<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SoalController;
use App\Http\Controllers\Api\TryoutController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TryoutResultController;
use App\Http\Controllers\Api\RankingController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);


/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/tryouts', [TryoutController::class, 'index']);
Route::get('/tryouts/{tryout}', [TryoutController::class, 'show']);


/*
|--------------------------------------------------------------------------
| USER ROUTES (LOGIN REQUIRED)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Logout
    Route::post('/logout', [AuthController::class, 'logout']);

    // Start Tryout
    Route::post('/tryouts/{id}/start', [TryoutController::class, 'start']);

    // Autosave answers
    Route::post('/tryouts/{id}/autosave', [TryoutController::class, 'autosave']);

    // Submit tryout
    Route::post('/tryouts/{id}/submit', [TryoutController::class, 'submit']);

    // Remaining time
    Route::get('/tryouts/{id}/remaining-time', [TryoutController::class, 'remainingTime']);

    // Tryout result
    Route::get('/tryouts/{id}/result', [TryoutResultController::class, 'show']);

    // User history
    Route::get('/history', [TryoutResultController::class, 'history']);

    // Ranking
    Route::get('/tryouts/{id}/ranking', [RankingController::class, 'index']);
    Route::get('/tryouts/{id}/my-rank', [RankingController::class, 'myRank']);

});


/*
|--------------------------------------------------------------------------
| ADMIN ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum','admin'])->group(function () {

    // Soal Management
    Route::apiResource('/soal', SoalController::class);

    // Tryout Management
    Route::apiResource('/tryouts', TryoutController::class)
        ->except(['index','show']);

    // Attach soal
    Route::post('/tryouts/{id}/attach', [TryoutController::class, 'attachSoal']);

    Route::post('/tryouts/{id}/attach-multiple', [TryoutController::class, 'attachMultiple']);

    // Detach soal
    Route::delete('/tryouts/{id}/detach/{soalId}', [TryoutController::class, 'detachSoal']);

    // Publish tryout
    Route::post('/tryouts/{id}/publish', [TryoutController::class, 'publish']);

});