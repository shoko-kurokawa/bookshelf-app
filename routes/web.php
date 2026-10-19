<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth')->get('/home', function () {
    return '
        <h1>ログイン成功！BookShelfへようこそ！</h1>
        <form method="POST" action="/logout">
            <input type="hidden" name="_token" value="' . csrf_token() . '">
            <button type="submit">ログアウト</button>
        </form>
    ';
})->name('home');

Route::get('/', function () {
    return redirect()->route('login');
});