<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * マイ読書レポート
     */
    public function index(Request $request): View
    {
        // ログインユーザー本人のレビューのみ取得
        // 書籍・ジャンルを事前読み込みしてN+1を防止
        $reviews = Review::query()
            ->where('user_id', $request->user()->id)
            ->with('book.genres')
            ->get();

        // ① 基本サマリー
        $summary = [
            'total_reviews' => $reviews->count(),
            'books_read' => $reviews->pluck('book_id')->unique()->count(),
            'average_rating' => $reviews->avg('rating') ?? 0,
        ];

        // ② 評価分布（星1〜5を必ず表示）
        $ratingDistribution = collect(range(1, 5))
            ->map(fn ($rating) => $reviews
                ->where('rating', $rating)
                ->count());

        // ③ 高評価書籍TOP5
        // 同じ書籍への複数レビューは、暫定的に最高評価を採用
        $topRatedBooks = $reviews
            ->filter(
                fn ($review) => $review->rating >= 4 && $review->book !== null
            )
            ->groupBy('book_id')
            ->map(function ($bookReviews) {
                $bestReview = $bookReviews
                    ->sortByDesc('rating')
                    ->first();

                return [
                    'id' => $bestReview->book->id,
                    'title' => $bestReview->book->title,
                    'author' => $bestReview->book->author,
                    'rating' => (int) $bestReview->rating,
                ];
            })
            ->sortBy([
                ['rating', 'desc'],
                ['id', 'asc'],
            ])
            ->take(5)
            ->values();

        // ④ ジャンル別評価傾向TOP5
        // 複数ジャンルの書籍は、それぞれのジャンルに計上
        $genreRatings = $reviews
            ->flatMap(function ($review) {
                if ($review->book === null) {
                    return [];
                }

                return $review->book->genres
                    ->map(fn ($genre) => [
                        'id' => $genre->id,
                        'name' => $genre->name,
                        'rating' => $review->rating,
                    ]);
            })
            ->groupBy('id')
            ->map(function ($items) {
                return [
                    'id' => $items->first()['id'],
                    'name' => $items->first()['name'],
                    'count' => $items->count(),
                    'average_rating' => $items->avg('rating'),
                ];
            })
            ->sortBy([
                ['average_rating', 'desc'],
                ['count', 'desc'],
                ['id', 'asc'],
            ])
            ->take(5)
            ->values();

        $stats = [
            'summary' => $summary,
            'rating_distribution' => $ratingDistribution,
            'top_rated_books' => $topRatedBooks,
            'genre_ratings' => $genreRatings,
        ];

        return view('reports.index', compact('stats'));
    }
}
