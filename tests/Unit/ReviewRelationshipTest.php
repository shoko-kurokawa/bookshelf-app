<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Review;
use App\Models\ReviewLike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewRelationshipTest extends TestCase
{
    use RefreshDatabase;

    /**
     * レビューから投稿ユーザーを取得できる
     */
    public function test_review_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $review = $this->createReview($user, $book);

        $this->assertInstanceOf(User::class, $review->user);
        $this->assertEquals($user->id, $review->user->id);
    }

    /**
     * レビューから対象書籍を取得できる
     */
    public function test_review_belongs_to_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $review = $this->createReview($user, $book);

        $this->assertInstanceOf(Book::class, $review->book);
        $this->assertEquals($book->id, $review->book->id);
    }

    /**
     * レビューに付いた複数のいいねを取得できる
     */
    public function test_review_has_many_review_likes(): void
    {
        $owner = User::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $book = Book::factory()->create();

        $review = $this->createReview($owner, $book);

        ReviewLike::create([
            'user_id' => $userA->id,
            'review_id' => $review->id,
        ]);

        ReviewLike::create([
            'user_id' => $userB->id,
            'review_id' => $review->id,
        ]);

        $likes = $review->fresh()->reviewLikes;

        $this->assertCount(2, $likes);
        $this->assertTrue($likes->contains('user_id', $userA->id));
        $this->assertTrue($likes->contains('user_id', $userB->id));
    }

    /**
     * いいねからユーザーを取得できる
     */
    public function test_review_like_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $review = $this->createReview($user, $book);

        $like = ReviewLike::create([
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);

        $this->assertInstanceOf(User::class, $like->user);
        $this->assertEquals($user->id, $like->user->id);
    }

    /**
     * いいねから対象レビューを取得できる
     */
    public function test_review_like_belongs_to_review(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $review = $this->createReview($user, $book);

        $like = ReviewLike::create([
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);

        $this->assertInstanceOf(Review::class, $like->review);
        $this->assertEquals($review->id, $like->review->id);
    }

    /**
     * ユーザーから自分が付けたいいねを取得できる
     */
    public function test_user_has_many_review_likes(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $reviewA = $this->createReview($user, $book);
        $reviewB = $this->createReview($user, $book);

        ReviewLike::create([
            'user_id' => $user->id,
            'review_id' => $reviewA->id,
        ]);

        ReviewLike::create([
            'user_id' => $user->id,
            'review_id' => $reviewB->id,
        ]);

        $likes = $user->fresh()->reviewLikes;

        $this->assertCount(2, $likes);
        $this->assertTrue($likes->contains('review_id', $reviewA->id));
        $this->assertTrue($likes->contains('review_id', $reviewB->id));
    }

    /**
     * ユーザーからいいねしたレビューを取得できる
     */
    public function test_user_can_get_liked_reviews(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $book = Book::factory()->create();

        $review = $this->createReview($owner, $book);

        ReviewLike::create([
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);

        $likedReviews = $user->fresh()->likedReviews;

        $this->assertCount(1, $likedReviews);
        $this->assertTrue(
            $likedReviews->contains('id', $review->id)
        );
    }

    /**
     * レビューからいいねしたユーザーを取得できる
     */
    public function test_review_can_get_users_who_liked_it(): void
    {
        $owner = User::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $book = Book::factory()->create();

        $review = $this->createReview($owner, $book);

        ReviewLike::create([
            'user_id' => $userA->id,
            'review_id' => $review->id,
        ]);

        ReviewLike::create([
            'user_id' => $userB->id,
            'review_id' => $review->id,
        ]);

        $users = $review->fresh()->likedByUsers;

        $this->assertCount(2, $users);
        $this->assertTrue($users->contains('id', $userA->id));
        $this->assertTrue($users->contains('id', $userB->id));
    }

    /**
     * likes()でもレビューのいいねを取得できる
     */
    public function test_review_likes_relation_returns_likes(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $review = $this->createReview($user, $book);

        ReviewLike::create([
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);

        $this->assertCount(1, $review->fresh()->likes);
    }

    /**
     * テスト用レビュー作成の共通処理
     */
    private function createReview(User $user, Book $book): Review
    {
        return Review::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => 'リレーションテスト用レビュー',
        ]);
    }
}
