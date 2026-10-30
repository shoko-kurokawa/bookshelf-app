<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\ReviewController;
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
