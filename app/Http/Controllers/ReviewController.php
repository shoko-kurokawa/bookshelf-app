<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReviewController extends Controller
{
    // レビュー投稿
    public function store(
        StoreReviewRequest $request,
        Book $book
    ): RedirectResponse {
        $validated = $request->validated();

        $book->reviews()->create([
            'user_id' => $request->user()->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? '',
        ]);

        return redirect()
            ->route('books.show', $book)
            ->with('success', 'レビューを投稿しました。');
    }

    // レビュー編集画面
    public function edit(Review $review): View
    {
        Gate::authorize('update', $review);

        $review->load('book');

        return view('reviews.edit', compact('review'));
    }

    // レビュー更新
    public function update(
        UpdateReviewRequest $request,
        Review $review
    ): RedirectResponse {
        Gate::authorize('update', $review);

        $validated = $request->validated();

        $validated['comment'] = $validated['comment'] ?? '';

        $review->update($validated);

        return redirect()
            ->route('books.show', $review->book_id)
            ->with('success', 'レビューを更新しました。');
    }

    // レビュー削除
    public function destroy(Review $review): RedirectResponse
    {
        Gate::authorize('delete', $review);

        $bookId = $review->book_id;

        $review->delete();

        return redirect()
            ->route('books.show', $bookId)
            ->with('success', 'レビューを削除しました。');
    }
}