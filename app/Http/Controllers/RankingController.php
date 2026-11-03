<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\View\View;

class RankingController extends Controller
{
    public function index(): View
    {
        $rankedBooks = Book::query()
            ->has('reviews')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->orderByDesc('reviews_avg_rating')
            ->orderBy('books.created_at')
            ->orderBy('books.id')
            ->limit(10)
            ->get();

        return view('ranking.index', compact('rankedBooks'));
    }
}
