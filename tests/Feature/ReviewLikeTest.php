<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewLikeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * いいねの登録・解除・再登録ができる
     */
    public function test_user_can_toggle_review_like_three_times(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $review = Review::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => 'いいねテスト用レビュー',
        ]);

        $this->actingAs($user);

        // 1回目：いいね登録
        $this->from(route('books.show', $book))
            ->post(route('reviews.like', $review))
            ->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);

        $this->assertDatabaseCount('review_likes', 1);

        // 2回目：いいね解除
        $this->from(route('books.show', $book))
            ->post(route('reviews.like', $review))
            ->assertRedirect(route('books.show', $book));

        $this->assertDatabaseMissing('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);

        $this->assertDatabaseCount('review_likes', 0);

        // 3回目：いいね再登録
        $this->from(route('books.show', $book))
            ->post(route('reviews.like', $review))
            ->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);

        $this->assertDatabaseCount('review_likes', 1);
    }

    /**
     * 自分が投稿したレビューにもいいねできる
     */
    public function test_user_can_like_own_review(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $review = Review::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '自分のレビュー',
        ]);

        $this->actingAs($user)
            ->post(route('reviews.like', $review));

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
    }

    /**
     * 複数ユーザーが同じレビューにいいねできる
     */
    public function test_multiple_users_can_like_same_review(): void
    {
        $owner = User::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $book = Book::factory()->create();

        $review = Review::create([
            'user_id' => $owner->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => '複数ユーザーのいいねテスト',
        ]);

        $this->actingAs($userA)
            ->post(route('reviews.like', $review));

        $this->actingAs($userB)
            ->post(route('reviews.like', $review));

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $userA->id,
            'review_id' => $review->id,
        ]);

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $userB->id,
            'review_id' => $review->id,
        ]);

        $this->assertDatabaseCount('review_likes', 2);
    }

    /**
     * いいね解除しても他人のいいねには影響しない
     */
    public function test_unlike_does_not_affect_other_users(): void
    {
        $owner = User::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $book = Book::factory()->create();

        $review = Review::create([
            'user_id' => $owner->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => 'いいね解除テスト',
        ]);

        // 2人ともいいね登録
        $this->actingAs($userA)
            ->post(route('reviews.like', $review));

        $this->actingAs($userB)
            ->post(route('reviews.like', $review));

        // ユーザーAだけ解除
        $this->actingAs($userA)
            ->post(route('reviews.like', $review));

        $this->assertDatabaseMissing('review_likes', [
            'user_id' => $userA->id,
            'review_id' => $review->id,
        ]);

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $userB->id,
            'review_id' => $review->id,
        ]);

        $this->assertDatabaseCount('review_likes', 1);
    }

    /**
     * 未ログインユーザーはレビューにいいねできない
     */
    public function test_guest_cannot_like_review(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $review = Review::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '未ログインテスト',
        ]);

        $response = $this->post(
            route('reviews.like', $review)
        );

        $response->assertRedirect(route('login'));

        $this->assertDatabaseCount('review_likes', 0);
    }
}
