<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 未ログインでも公開ページを閲覧できる
     */
    public function test_guest_can_access_public_pages(): void
    {
        $book = Book::factory()->create();

        $this->get(route('books.index'))->assertOk();
        $this->get(route('books.show', $book))->assertOk();
        $this->get(route('ranking.index'))->assertOk();
    }

    /**
     * 未ログインでは書籍管理画面にアクセスできない
     */
    public function test_guest_cannot_access_book_management_pages(): void
    {
        $book = Book::factory()->create();

        $this->get(route('books.create'))
            ->assertRedirect(route('login'));

        $this->get(route('books.edit', $book))
            ->assertRedirect(route('login'));
    }

    /**
     * 未ログインでは書籍の登録・更新・削除ができない
     */
    public function test_guest_cannot_modify_books(): void
    {
        $book = Book::factory()->create();

        $this->post(route('books.store'), [])
            ->assertRedirect(route('login'));

        $this->put(route('books.update', $book), [])
            ->assertRedirect(route('login'));

        $this->delete(route('books.destroy', $book))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
        ]);

        $this->assertDatabaseCount('books', 1);
    }

    /**
     * 未ログインではレビューの登録・編集・削除ができない
     */
    public function test_guest_cannot_manage_reviews(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $review = $this->createReview($user, $book);

        $this->post(route('reviews.store', $book), [
            'rating' => 5,
            'comment' => '未ログイン投稿',
        ])->assertRedirect(route('login'));

        $this->get(route('reviews.edit', $review))
            ->assertRedirect(route('login'));

        $this->put(route('reviews.update', $review), [
            'rating' => 3,
            'comment' => '未ログイン更新',
        ])->assertRedirect(route('login'));

        $this->delete(route('reviews.destroy', $review))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('reviews', 1);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 5,
        ]);
    }

    /**
     * 未ログインではお気に入り・レビューいいねを操作できない
     */
    public function test_guest_cannot_access_favorites_or_review_likes(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $review = $this->createReview($user, $book);

        $this->get(route('favorites.index'))
            ->assertRedirect(route('login'));

        $this->post(route('favorites.toggle', $book))
            ->assertRedirect(route('login'));

        $this->post(route('reviews.like', $review))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('favorites', 0);
        $this->assertDatabaseCount('review_likes', 0);
    }

    /**
     * 未ログインではジャンル管理を利用できない
     */
    public function test_guest_cannot_access_genre_management(): void
    {
        $genre = Genre::create([
            'name' => '認証テスト',
        ]);

        $this->get(route('genres.index'))
            ->assertRedirect(route('login'));

        $this->get(route('genres.create'))
            ->assertRedirect(route('login'));

        $this->get(route('genres.show', $genre))
            ->assertRedirect(route('login'));

        $this->get(route('genres.edit', $genre))
            ->assertRedirect(route('login'));

        $this->post(route('genres.store'), [
            'name' => '不正登録',
        ])->assertRedirect(route('login'));

        $this->put(route('genres.update', $genre), [
            'name' => '不正更新',
        ])->assertRedirect(route('login'));

        $this->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '認証テスト',
        ]);
    }

    /**
     * 他人の書籍は編集・更新・削除できない
     */
    public function test_other_user_cannot_modify_book(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $owner->id,
        ]);

        $this->actingAs($otherUser)
            ->get(route('books.edit', $book))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->put(route('books.update', $book), [])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->delete(route('books.destroy', $book))
            ->assertForbidden();

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $owner->id,
        ]);
    }

    /**
     * 他人のレビューは編集・更新・削除できない
     */
    public function test_other_user_cannot_modify_review(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create();

        $review = $this->createReview($owner, $book);

        $this->actingAs($otherUser)
            ->get(route('reviews.edit', $review))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->put(route('reviews.update', $review), [
                'rating' => 3,
                'comment' => '他人による更新',
            ])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->delete(route('reviews.destroy', $review))
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'user_id' => $owner->id,
            'rating' => 5,
        ]);
    }

    /**
     * 基本版の書籍APIは未ログインでも閲覧できる
     */
    public function test_guest_can_access_public_book_api(): void
    {
        $book = Book::factory()->create();

        $this->getJson('/api/v1/books')
            ->assertOk();

        $this->getJson('/api/v1/books/'.$book->id)
            ->assertOk();
    }

    /**
     * テスト用レビューを作成する共通処理
     */
    private function createReview(User $user, Book $book): Review
    {
        return Review::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '認証・認可テスト用レビュー',
        ]);
    }
}
