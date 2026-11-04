<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 書籍一覧ページを表示できる
     */
    public function test_book_index_can_be_displayed(): void
    {
        $response = $this->get('/books');

        $response->assertStatus(200);
    }

    /**
     * ログインユーザーは書籍を登録できる
     */
    public function test_authenticated_user_can_create_book(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '小説']);

        $bookData = [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '1234567890123',
            'published_date' => '2026-01-01',
            'description' => 'テスト用の説明文です。',
            'image_url' => null,
            'genres' => [$genre->id],
        ];

        $response = $this->actingAs($user)
            ->post(route('books.store'), $bookData);

        $book = Book::where('isbn', '1234567890123')->first();

        $this->assertNotNull($book);

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $user->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '1234567890123',
        ]);

        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genre->id,
        ]);
    }

    /**
     * 必須項目が不足している場合は登録できない
     */
    public function test_book_creation_requires_mandatory_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('books.store'), []);

        $response->assertSessionHasErrors([
            'title',
            'author',
            'isbn',
            'published_date',
            'genres',
        ]);

        $this->assertDatabaseCount('books', 0);
    }

    /**
     * 未ログインユーザーは書籍を登録できない
     */
    public function test_guest_cannot_create_book(): void
    {
        $response = $this->post(route('books.store'), [
            'title' => '未ログインの書籍',
            'author' => 'テスト著者',
            'isbn' => '1234567890123',
            'published_date' => '2026-01-01',
        ]);

        $response->assertRedirect(route('login'));

        $this->assertDatabaseCount('books', 0);
    }

    /**
     * 書籍詳細ページを表示できる
     */
    public function test_book_detail_can_be_displayed(): void
    {
        $book = Book::factory()->create();

        $response = $this->get(route('books.show', $book));

        $response->assertStatus(200);
        $response->assertSee($book->title);
    }

    /**
     * 書籍の所有者は書籍を更新できる
     */
    public function test_book_owner_can_update_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create([
            'user_id' => $user->id,
        ]);

        $genre = Genre::create([
            'name' => '技術書',
        ]);

        $response = $this->actingAs($user)
            ->put(route('books.update', $book), [
                'title' => '更新後の書籍タイトル',
                'author' => '更新後の著者',
                'isbn' => $book->isbn,
                'published_date' => '2026-01-01',
                'description' => '更新後の説明文',
                'image_url' => null,
                'genres' => [$genre->id],
            ]);

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '更新後の書籍タイトル',
            'author' => '更新後の著者',
        ]);

        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genre->id,
        ]);
    }

    /**
     * 書籍の所有者は書籍を削除できる
     */
    public function test_book_owner_can_delete_book(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->delete(route('books.destroy', $book));

        $response->assertRedirect(route('books.index'));

        $this->assertDatabaseMissing('books', [
            'id' => $book->id,
        ]);
    }

    /**
     * 他人の書籍は更新できない（403）
     */
    public function test_other_user_cannot_update_book(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $owner->id,
        ]);

        $response = $this->actingAs($otherUser)
            ->put(route('books.update', $book), [
                'title' => '不正な更新',
                'author' => '別の著者',
                'isbn' => $book->isbn,
                'published_date' => '2026-01-01',
                'genres' => [],
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => $book->title,
        ]);
    }

    /**
     * 他人の書籍は削除できない（403）
     */
    public function test_other_user_cannot_delete_book(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $owner->id,
        ]);

        $response = $this->actingAs($otherUser)
            ->delete(route('books.destroy', $book));

        $response->assertForbidden();

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
        ]);
    }

    /**
     * ISBNが13文字以外の場合は登録できない
     */
    public function test_book_creation_requires_thirteen_character_isbn(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => '小説',
        ]);

        $response = $this->actingAs($user)
            ->post(route('books.store'), [
                'title' => 'ISBNテスト書籍',
                'author' => 'テスト著者',
                'isbn' => '123456789012',
                'published_date' => '2026-01-01',
                'genres' => [$genre->id],
            ]);

        $response->assertSessionHasErrors('isbn');

        $this->assertDatabaseCount('books', 0);
    }

    /**
     * ISBNが重複している場合は登録できない
     */
    public function test_book_creation_rejects_duplicate_isbn(): void
    {
        $user = User::factory()->create();

        $existingBook = Book::factory()->create([
            'isbn' => '1234567890123',
        ]);

        $genre = Genre::create([
            'name' => '技術書',
        ]);

        $response = $this->actingAs($user)
            ->post(route('books.store'), [
                'title' => '重複ISBNの書籍',
                'author' => 'テスト著者',
                'isbn' => '1234567890123',
                'published_date' => '2026-01-01',
                'genres' => [$genre->id],
            ]);

        $response->assertSessionHasErrors('isbn');

        $this->assertDatabaseCount('books', 1);

        $this->assertDatabaseHas('books', [
            'id' => $existingBook->id,
            'isbn' => '1234567890123',
        ]);
    }

    /**
     * 書籍更新時にジャンルの紐付けが変更される
     */
    public function test_book_genres_are_updated_correctly(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $user->id,
        ]);

        $oldGenre = Genre::create([
            'name' => '小説',
        ]);

        $newGenre = Genre::create([
            'name' => '技術書',
        ]);

        // 更新前は「小説」に紐付ける
        $book->genres()->attach($oldGenre->id);

        // 更新時に「技術書」へ変更する
        $response = $this->actingAs($user)
            ->put(route('books.update', $book), [
                'title' => $book->title,
                'author' => $book->author,
                'isbn' => $book->isbn,
                'published_date' => $book->published_date->format('Y-m-d'),
                'description' => $book->description,
                'image_url' => $book->image_url,
                'genres' => [$newGenre->id],
            ]);

        $response->assertRedirect(route('books.show', $book));

        // 古いジャンルの紐付けが削除されている
        $this->assertDatabaseMissing('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $oldGenre->id,
        ]);

        // 新しいジャンルの紐付けが登録されている
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $newGenre->id,
        ]);
    }
}
