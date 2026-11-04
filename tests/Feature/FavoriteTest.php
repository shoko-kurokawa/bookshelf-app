<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Favorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ログインユーザーはお気に入り一覧を表示できる
     */
    public function test_authenticated_user_can_view_favorites(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('favorites.index'));

        $response->assertOk();
    }

    /**
     * お気に入りの登録・解除・再登録ができる
     */
    public function test_user_can_toggle_favorite_three_times(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user);

        // 1回目：お気に入り登録
        $this->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book))
            ->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $this->assertDatabaseCount('favorites', 1);

        // 2回目：お気に入り解除
        $this->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book))
            ->assertRedirect(route('books.show', $book));

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $this->assertDatabaseCount('favorites', 0);

        // 3回目：お気に入り再登録
        $this->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book))
            ->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $this->assertDatabaseCount('favorites', 1);
    }

    /**
     * 他のユーザーのお気に入りには影響しない
     */
    public function test_favorite_toggle_does_not_affect_other_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $book = Book::factory()->create();

        // ユーザーAがお気に入り登録
        $this->actingAs($userA)
            ->post(route('favorites.toggle', $book));

        // ユーザーBも同じ書籍をお気に入り登録
        $this->actingAs($userB)
            ->post(route('favorites.toggle', $book));

        $this->assertDatabaseCount('favorites', 2);

        // ユーザーAだけがお気に入り解除
        $this->actingAs($userA)
            ->post(route('favorites.toggle', $book));

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $userA->id,
            'book_id' => $book->id,
        ]);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $userB->id,
            'book_id' => $book->id,
        ]);

        $this->assertDatabaseCount('favorites', 1);
    }

    /**
     * 未ログインユーザーはお気に入り一覧を表示できない
     */
    public function test_guest_cannot_view_favorites(): void
    {
        $response = $this->get(route('favorites.index'));

        $response->assertRedirect(route('login'));
    }

    /**
     * 未ログインユーザーはお気に入り登録できない
     */
    public function test_guest_cannot_toggle_favorite(): void
    {
        $book = Book::factory()->create();

        $response = $this->post(
            route('favorites.toggle', $book)
        );

        $response->assertRedirect(route('login'));

        $this->assertDatabaseCount('favorites', 0);
    }

    /**
     * お気に入り一覧には自分が登録した書籍だけが表示される
     */
    public function test_favorites_index_shows_only_own_books(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownBook = Book::factory()->create([
            'title' => '自分のお気に入り書籍',
        ]);

        $otherBook = Book::factory()->create([
            'title' => '他人のお気に入り書籍',
        ]);

        Favorite::create([
            'user_id' => $user->id,
            'book_id' => $ownBook->id,
        ]);

        Favorite::create([
            'user_id' => $otherUser->id,
            'book_id' => $otherBook->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('favorites.index'));

        $response->assertOk();
        $response->assertSee('自分のお気に入り書籍');
        $response->assertDontSee('他人のお気に入り書籍');
    }
}
