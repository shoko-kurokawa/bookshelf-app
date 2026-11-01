<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    // お気に入り一覧
    public function index(): View
    {
        $books = auth()->user()
            ->favoriteBooks()
            ->latest('books.created_at')
            ->paginate(10);

        return view('favorites.index', compact('books'));
    }

    // お気に入り登録・解除
    public function toggle(Book $book): RedirectResponse
    {
        $user = auth()->user();

        DB::transaction(function () use ($user, $book) {
            $favorite = $user->favorites()
                ->where('book_id', $book->id)
                ->lockForUpdate()
                ->first();

            if ($favorite) {
                $favorite->delete();
            } else {
                $user->favorites()->create([
                    'book_id' => $book->id,
                ]);
            }
        });

        return back();
    }
}
