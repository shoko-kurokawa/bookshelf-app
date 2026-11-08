<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBookRequest;
use App\Http\Requests\Api\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    /**
     * AP01: 書籍一覧API
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'keyword' => ['nullable', 'string'],
            'genre_id' => ['nullable', 'integer', 'exists:genres,id'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 20;

        $query = Book::query()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        if (!empty($validated['keyword'])) {
            $keyword = $validated['keyword'];

            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', '%' . $keyword . '%')
                    ->orWhere('author', 'like', '%' . $keyword . '%');
            });
        }

        if (!empty($validated['genre_id'])) {
            $genreId = $validated['genre_id'];

            $query->whereHas('genres', function ($q) use ($genreId) {
                $q->where('genres.id', $genreId);
            });
        }

        $books = $query
            ->orderByDesc('books.created_at')
            ->orderByDesc('books.id')
            ->paginate($perPage);

        return BookResource::collection($books);

        // キーワード検索（タイトル・著者名）
        if (!empty($validated['keyword'])) {
            $keyword = $validated['keyword'];

            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', '%' . $keyword . '%')
                    ->orWhere('author', 'like', '%' . $keyword . '%');
            });
        }

        // ジャンルで絞り込み
        if (!empty($validated['genre_id'])) {
            $genreId = $validated['genre_id'];

            $query->whereHas('genres', function ($q) use ($genreId) {
                $q->where('genres.id', $genreId);
            });
        }

        $books = $query
            ->orderByDesc('books.created_at')
            ->orderByDesc('books.id')
            ->paginate($perPage);

        return BookResource::collection($books);
    }

    /**
     * AP02: 書籍詳細API
     */
    public function show(Book $book): BookResource
    {
        $book->load([
            'genres',
            'reviews' => function ($query) {
                $query->with('user')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id');
            },
        ]);

        $book->loadAvg('reviews', 'rating');
        $book->loadCount('reviews');

        return new BookResource($book);
    }

    /**
     * AP03: 書籍登録API
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // user_idはリクエストから採用せず、認証ユーザーから取得する
        unset($validated['user_id']);

        $validated['user_id'] = $request->user()->id;

        $book = DB::transaction(function () use ($validated) {
            $genres = $validated['genres'];
            unset($validated['genres']);

            $book = Book::create($validated);
            $book->genres()->sync($genres);

            return $book;
        });

        $book->load('genres');
        $book->loadAvg('reviews', 'rating');
        $book->loadCount('reviews');

        return (new BookResource($book))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * AP04: 書籍更新API
     */
    public function update(
        UpdateBookRequest $request,
        Book $book
    ): BookResource {
        // 所有者以外は更新不可
        abort_unless($book->user_id === $request->user()->id, 403);

        $validated = $request->validated();

        // 所有者の変更を禁止
        unset($validated['user_id']);

        DB::transaction(function () use ($book, $validated) {
            $genres = $validated['genres'];
            unset($validated['genres']);

            $book->update($validated);
            $book->genres()->sync($genres);
        });

        $book->refresh();
        $book->load('genres');
        $book->loadAvg('reviews', 'rating');
        $book->loadCount('reviews');

        return new BookResource($book);
    }

    /**
     * AP05: 書籍削除API
     */
    public function destroy(Request $request, Book $book): Response
    {
        // 所有者以外は削除不可
        abort_unless($book->user_id === $request->user()->id, 403);

        DB::transaction(function () use ($book) {
            // 関連するお気に入りを削除
            $book->favorites()->delete();

            // レビューに紐づくいいねを削除
            foreach ($book->reviews as $review) {
                $review->reviewLikes()->delete();
            }

            // レビューを削除
            $book->reviews()->delete();

            // ジャンルの紐付けを解除
            $book->genres()->detach();

            // 書籍を削除
            $book->delete();
        });

        return response()->noContent();
    }
}
