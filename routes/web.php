<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReadingPlanController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use Illuminate\Support\Facades\Route;

// トップページ
Route::get('/', function () {
    return redirect()->route('books.index');
});

// 書籍一覧・詳細（ログイン不要）
Route::get('/books', [BookController::class, 'index'])
    ->name('books.index');

Route::get('/books/{book}', [BookController::class, 'show'])
    ->whereNumber('book')
    ->name('books.show');

// 書籍登録・編集・削除（ログイン必須）
Route::middleware('auth')->group(function () {
    Route::get('/books/create', [BookController::class, 'create'])
        ->name('books.create');

    Route::post('/books', [BookController::class, 'store'])
        ->name('books.store');

    Route::get('/books/{book}/edit', [BookController::class, 'edit'])
        ->name('books.edit');

    Route::put('/books/{book}', [BookController::class, 'update'])
        ->name('books.update');

    Route::delete('/books/{book}', [BookController::class, 'destroy'])
        ->name('books.destroy');
});

// レビュー機能（ログイン必須）
Route::middleware('auth')->group(function () {
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])
        ->name('reviews.store');

    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])
        ->name('reviews.edit');

    Route::put('/reviews/{review}', [ReviewController::class, 'update'])
        ->name('reviews.update');

    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])
        ->name('reviews.destroy');
});

// お気に入り機能・レビューいいね機能（ログイン必須）
Route::middleware('auth')->group(function () {
    Route::get('/favorites', [FavoriteController::class, 'index'])
        ->name('favorites.index');

    Route::post('/books/{book}/favorite', [FavoriteController::class, 'toggle'])
        ->name('favorites.toggle');

    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])
        ->name('reviews.like');
});

// ランキング機能（ログイン不要）
Route::get('/ranking', [RankingController::class, 'index'])
    ->name('ranking.index');

// ジャンル管理（ログイン必須）
Route::middleware('auth')->group(function () {
    Route::resource('genres', GenreController::class);
});

// ==========================================
// 応用版：マイ読書レポート
// ==========================================
Route::middleware('auth')->group(function () {
    Route::get('/reports', [ReportController::class, 'index'])
        ->name('reports.index');
});

// ==========================================
// 応用版：読書計画
// ==========================================
Route::middleware('auth')->group(function () {
    Route::get('/reading-plans', [ReadingPlanController::class, 'index'])
        ->name('reading-plans.index');

    Route::get('/reading-plans/create', [ReadingPlanController::class, 'create'])
        ->name('reading-plans.create');

    Route::post('/reading-plans', [ReadingPlanController::class, 'store'])
        ->name('reading-plans.store');

    Route::get('/reading-plans/{readingPlan}/edit', [ReadingPlanController::class, 'edit'])
        ->name('reading-plans.edit');

    Route::put('/reading-plans/{readingPlan}', [ReadingPlanController::class, 'update'])
        ->name('reading-plans.update');

    Route::patch('/reading-plans/{readingPlan}/complete', [ReadingPlanController::class, 'complete'])
        ->name('reading-plans.complete');

    Route::delete('/reading-plans/{readingPlan}', [ReadingPlanController::class, 'destroy'])
        ->name('reading-plans.destroy');
});

// ==========================================
// 応用版：通知
// ==========================================
Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');

    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');
});

// ==========================================
// 応用版：Google Books ISBN検索
// ==========================================
Route::middleware('auth')->group(function () {
    Route::get('/books/isbn/{isbn}', [BookController::class, 'lookupIsbn'])
        ->name('books.isbn');
});

// Issue #1で作成した仮のホーム画面
Route::middleware('auth')->get('/home', function () {
    return '
        <h1>ログイン成功！BookShelfへようこそ！</h1>
        <form method="POST" action="/logout">
            <input type="hidden" name="_token" value="' . csrf_token() . '">
            <button type="submit">ログアウト</button>
        </form>
    ';
})->name('home');
