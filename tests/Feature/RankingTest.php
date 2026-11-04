<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ランキング画面を表示できる
     */
    public function test_ranking_page_can_be_displayed(): void
    {
        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertViewIs('ranking.index');
    }

    /**
     * 未ログインユーザーもランキングを閲覧できる
     */
    public function test_guest_can_view_ranking(): void
    {
        $book = Book::factory()->create();

        $this->createReview($book, 5);

        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertViewHas('rankedBooks', function ($books) use ($book) {
            return $books->contains('id', $book->id);
        });
    }

    /**
     * 平均評価が高い書籍から順番に表示される
     */
    public function test_books_are_ranked_by_average_rating(): void
    {
        $lowBook = Book::factory()->create();
        $middleBook = Book::factory()->create();
        $highBook = Book::factory()->create();

        // 平均評価2.0
        $this->createReview($lowBook, 2);

        // 平均評価3.5
        $this->createReview($middleBook, 3);
        $this->createReview($middleBook, 4);

        // 平均評価5.0
        $this->createReview($highBook, 5);

        $response = $this->get(route('ranking.index'));

        $response->assertOk();

        $response->assertViewHas('rankedBooks', function ($books) use ($highBook, $middleBook, $lowBook) {
            return $books->pluck('id')->all() === [
                $highBook->id,
                $middleBook->id,
                $lowBook->id,
            ];
        });
    }

    /**
     * 平均評価が同じ場合は登録日時が古い書籍が先になる
     */
    public function test_same_rating_books_are_ordered_by_created_at(): void
    {
        $oldBook = Book::factory()->create([
            'created_at' => '2026-01-01 10:00:00',
        ]);

        $newBook = Book::factory()->create([
            'created_at' => '2026-02-01 10:00:00',
        ]);

        $this->createReview($oldBook, 4);
        $this->createReview($newBook, 4);

        $response = $this->get(route('ranking.index'));

        $response->assertViewHas('rankedBooks', function ($books) use ($oldBook, $newBook) {
            return $books->pluck('id')->all() === [
                $oldBook->id,
                $newBook->id,
            ];
        });
    }

    /**
     * 平均評価と登録日時が同じ場合はIDが小さい書籍が先になる
     */
    public function test_same_rating_and_date_books_are_ordered_by_id(): void
    {
        $firstBook = Book::factory()->create([
            'created_at' => '2026-01-01 10:00:00',
        ]);

        $secondBook = Book::factory()->create([
            'created_at' => '2026-01-01 10:00:00',
        ]);

        $this->createReview($firstBook, 4);
        $this->createReview($secondBook, 4);

        $response = $this->get(route('ranking.index'));

        $response->assertViewHas('rankedBooks', function ($books) use ($firstBook, $secondBook) {
            return $books->pluck('id')->all() === [
                $firstBook->id,
                $secondBook->id,
            ];
        });
    }

    /**
     * レビューがない書籍はランキングから除外される
     */
    public function test_books_without_reviews_are_excluded(): void
    {
        $reviewedBook = Book::factory()->create();
        $noReviewBook = Book::factory()->create();

        $this->createReview($reviewedBook, 5);

        $response = $this->get(route('ranking.index'));

        $response->assertViewHas('rankedBooks', function ($books) use ($reviewedBook, $noReviewBook) {
            return $books->contains('id', $reviewedBook->id)
                && ! $books->contains('id', $noReviewBook->id);
        });
    }

    /**
     * ランキングには上位10冊までしか表示されない
     */
    public function test_ranking_displays_only_top_ten_books(): void
    {
        $books = Book::factory()->count(12)->create();

        foreach ($books as $book) {
            $this->createReview($book, 4);
        }

        $response = $this->get(route('ranking.index'));

        $response->assertViewHas('rankedBooks', function ($rankedBooks) use ($books) {
            return $rankedBooks->count() === 10
                && $rankedBooks->pluck('id')->all()
                === $books->take(10)->pluck('id')->all();
        });
    }

    /**
     * テスト用レビューを作成する共通処理
     */
    private function createReview(Book $book, int $rating): Review
    {
        $user = User::factory()->create();

        return Review::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => $rating,
            'comment' => 'ランキングテスト用レビュー',
        ]);
    }
}
