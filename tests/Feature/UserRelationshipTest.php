<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\Favorite;
use App\Models\ReadingPlan;
use App\Models\Review;
use App\Models\ReviewLike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRelationshipTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ユーザーが登録した書籍を取得できる
     */
    public function test_user_has_many_books(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->assertTrue($user->books->contains($book));
        $this->assertCount(1, $user->books);
    }

    /**
     * ユーザーが投稿したレビューを取得できる
     */
    public function test_user_has_many_reviews(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $review = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $this->assertTrue($user->reviews->contains($review));
    }

    /**
     * お気に入り登録情報を取得できる
     */
    public function test_user_has_many_favorites(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $favorite = Favorite::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $this->assertTrue($user->favorites->contains($favorite));
    }

    /**
     * お気に入り書籍を中間テーブル経由で取得できる
     */
    public function test_user_can_access_favorite_books(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $user->favoriteBooks()->attach($book->id);

        $this->assertTrue(
            $user->favoriteBooks->contains($book)
        );

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    /**
     * レビューへのいいね情報を取得できる
     */
    public function test_user_has_many_review_likes(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $review = Review::factory()->create([
            'book_id' => $book->id,
        ]);

        $like = ReviewLike::create([
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);

        $this->assertTrue(
            $user->reviewLikes->contains($like)
        );
    }

    /**
     * いいねしたレビューを中間テーブル経由で取得できる
     */
    public function test_user_can_access_liked_reviews(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $review = Review::factory()->create([
            'book_id' => $book->id,
        ]);

        $user->likedReviews()->attach($review->id);

        $this->assertTrue(
            $user->likedReviews->contains($review)
        );

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
    }

    /**
     * ユーザーの読書計画を取得できる
     */
    public function test_user_has_many_reading_plans(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $plan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => '2026-12-01',
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $this->assertTrue(
            $user->readingPlans->contains($plan)
        );
    }
}
