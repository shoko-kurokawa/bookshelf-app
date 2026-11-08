<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookController as ApiBookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // 認証不要：ログイン
    Route::post('/login', [AuthController::class, 'login'])
        ->name('api.login');

    // 認証不要：書籍一覧・詳細
    Route::get('/books', [ApiBookController::class, 'index']);
    Route::get('/books/{book}', [ApiBookController::class, 'show'])
        ->whereNumber('book');

    // Sanctum認証必須
    Route::middleware('auth:sanctum')->group(function () {

        // ログアウト（現在のトークンを失効）
        Route::post('/logout', [AuthController::class, 'logout'])
            ->name('api.logout');

        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        // 書籍登録・更新・削除
        Route::post('/books', [ApiBookController::class, 'store']);
        Route::put('/books/{book}', [ApiBookController::class, 'update'])
            ->whereNumber('book');
        Route::patch('/books/{book}', [ApiBookController::class, 'update'])
            ->whereNumber('book');
        Route::delete('/books/{book}', [ApiBookController::class, 'destroy'])
            ->whereNumber('book');
    });
});
