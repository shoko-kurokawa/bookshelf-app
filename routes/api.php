<?php

use App\Http\Controllers\Api\V1\BookController as ApiBookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// 基本版：認証不要の書籍API
Route::prefix('v1')->group(function () {
    Route::apiResource('books', ApiBookController::class);
});

// 応用版で使用するSanctum認証ルート
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
