<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use App\Services\GoogleBooksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BookController extends Controller
{
    // 書籍一覧
    public function index(): View
    {
        $books = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->latest()
            ->paginate(10);

        return view('books.index', compact('books'));
    }

    // 書籍登録画面
    public function create(): View
    {
        $genres = Genre::orderBy('id')->get();

        return view('books.create', compact('genres'));
    }

    // 書籍登録処理
    public function store(StoreBookRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $book = DB::transaction(function () use ($request, $validated) {
            $bookData = collect($validated)->except('genres')->all();
            $bookData['user_id'] = $request->user()->id;

            $book = Book::create($bookData);

            $book->genres()->sync($validated['genres']);

            return $book;
        });

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を登録しました。');
    }

    // 書籍詳細
    public function show(Book $book): View
    {
        $book->load([
            'genres',
            'user',
            'reviews' => function ($query) {
                $query->with('user')
                    ->latest();
            },
        ]);

        return view('books.show', compact('book'));
    }

    // 書籍編集画面
    public function edit(Book $book): View
    {
        Gate::authorize('update', $book);

        $book->load('genres');

        $genres = Genre::orderBy('id')->get();

        return view('books.edit', compact('book', 'genres'));
    }

    // 書籍更新処理
    public function update(
        UpdateBookRequest $request,
        Book $book
    ): RedirectResponse {
        Gate::authorize('update', $book);

        $validated = $request->validated();

        DB::transaction(function () use ($book, $validated) {
            $bookData = collect($validated)->except('genres')->all();

            $book->update($bookData);

            $book->genres()->sync($validated['genres']);
        });

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を更新しました。');
    }

    // 書籍削除処理
    public function destroy(Book $book): RedirectResponse
    {
        Gate::authorize('delete', $book);

        $book->delete();

        return redirect()
            ->route('books.index')
            ->with('success', '書籍を削除しました。');
    }

    /**
     * ISBNからGoogle Booksの書籍情報を取得する。
     */
    public function lookupIsbn(
        Request $request,
        GoogleBooksService $googleBooksService,
        string $isbn
    ): JsonResponse {
        if (strlen($isbn) !== 13) {
            return response()->json([
                'message' => 'ISBNは13文字で入力してください。',
            ], 422);
        }

        $bookData = $googleBooksService->searchByIsbn($isbn);

        if ($bookData === null) {
            return response()->json([
                'message' => '書籍情報を取得できませんでした。手入力で登録してください。',
            ], 404);
        }

        return response()->json([
            'message' => '書籍情報を取得しました。',
            'data' => $bookData,
        ]);
    }
}
