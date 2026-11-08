<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Favorite;
use App\Models\Genre;
use App\Models\Review;
use App\Models\ReviewLike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 書籍一覧APIが正常に取得できる
     */
    public function test_book_index_returns_json(): void
    {
        Book::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/books');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'title',
                        'author',
                        'isbn',
                        'published_date',
                        'genres',
                        'reviews_count',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    /**
     * 一覧APIのデフォルト表示件数は20件
     */
    public function test_book_index_defaults_to_twenty_per_page(): void
    {
        Book::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/books');

        $response->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 25);
    }

    /**
     * per_pageで表示件数を指定できる
     */
    public function test_book_index_accepts_custom_per_page(): void
    {
        Book::factory()->count(12)->create();

        $response = $this->getJson('/api/v1/books?per_page=5');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.last_page', 3);
    }

    /**
     * per_pageの上限は100件
     */
    public function test_book_index_rejects_per_page_over_one_hundred(): void
    {
        $response = $this->getJson('/api/v1/books?per_page=101');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    /**
     * 不正なページ番号は拒否される
     */
    public function test_book_index_rejects_invalid_page(): void
    {
        $response = $this->getJson('/api/v1/books?page=0');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('page');
    }

    /**
     * タイトルでキーワード検索できる
     */
    public function test_book_index_can_search_by_title(): void
    {
        Book::factory()->create([
            'title' => 'Laravel入門',
            'author' => '山田太郎',
        ]);

        Book::factory()->create([
            'title' => 'PHP実践',
            'author' => '佐藤花子',
        ]);

        $response = $this->getJson('/api/v1/books?keyword=Laravel');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Laravel入門');
    }

    /**
     * 著者名でもキーワード検索できる
     */
    public function test_book_index_can_search_by_author(): void
    {
        Book::factory()->create([
            'title' => '書籍A',
            'author' => 'テスト著者',
        ]);

        Book::factory()->create([
            'title' => '書籍B',
            'author' => '別の著者',
        ]);

        $response = $this->getJson(
            '/api/v1/books?keyword='.urlencode('テスト著者')
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', '書籍A');
    }

    /**
     * ジャンルで書籍を絞り込める
     */
    public function test_book_index_can_filter_by_genre(): void
    {
        $genreA = Genre::create(['name' => '技術書']);
        $genreB = Genre::create(['name' => '小説']);

        $bookA = Book::factory()->create([
            'title' => '技術書テスト',
        ]);

        $bookB = Book::factory()->create([
            'title' => '小説テスト',
        ]);

        $bookA->genres()->attach($genreA->id);
        $bookB->genres()->attach($genreB->id);

        $response = $this->getJson(
            '/api/v1/books?genre_id='.$genreA->id
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $bookA->id);
    }

    /**
     * 存在しないジャンルIDは拒否される
     */
    public function test_book_index_rejects_invalid_genre_id(): void
    {
        $response = $this->getJson('/api/v1/books?genre_id=999999');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('genre_id');
    }

    /**
     * 書籍詳細APIで書籍情報を取得できる
     */
    public function test_book_show_returns_book_details(): void
    {
        $book = Book::factory()->create([
            'title' => 'API詳細テスト',
        ]);

        $genre = Genre::create(['name' => '科学']);
        $book->genres()->attach($genre->id);

        $response = $this->getJson('/api/v1/books/'.$book->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.title', 'API詳細テスト')
            ->assertJsonPath('data.genres.0.name', '科学')
            ->assertJsonStructure([
                'data' => [
                    'reviews',
                    'reviews_count',
                ],
            ]);
    }

    /**
     * 存在しない書籍の詳細は404
     */
    public function test_book_show_returns_not_found(): void
    {
        $this->getJson('/api/v1/books/999999')
            ->assertNotFound();
    }

    /**
     * 認証済みユーザーが書籍を登録できる
     */
    public function test_authenticated_user_can_create_book_via_api(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $genre = Genre::create(['name' => 'ビジネス']);

        $response = $this->postJson('/api/v1/books', [
            'title' => 'API登録テスト',
            'author' => 'テスト著者',
            'isbn' => '1234567890123',
            'published_date' => '2026-01-01',
            'description' => 'APIから登録しました',
            'image_url' => null,
            'genres' => [$genre->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'API登録テスト');

        $book = Book::where('isbn', '1234567890123')->firstOrFail();

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $user->id,
            'title' => 'API登録テスト',
        ]);

        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genre->id,
        ]);
    }

    /**
     * 書籍登録時に必須項目がない場合は422
     */
    public function test_book_store_requires_mandatory_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/books', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title',
                'author',
                'isbn',
                'genres',
            ]);

        $this->assertDatabaseCount('books', 0);
    }

    /**
     * リクエストにuser_idを指定して登録できない
     */
    public function test_book_store_rejects_user_id_parameter(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $genre = Genre::create(['name' => '歴史']);

        $response = $this->postJson('/api/v1/books', [
            'user_id' => 999999,
            'title' => 'ユーザー不正テスト',
            'author' => 'テスト著者',
            'isbn' => '1234567890123',
            'published_date' => '2026-01-01',
            'genres' => [$genre->id],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');

        $this->assertDatabaseCount('books', 0);
    }

    /**
     * 重複ISBNでは書籍を登録できない
     */
    public function test_book_store_rejects_duplicate_isbn(): void
    {
        $existingBook = Book::factory()->create([
            'isbn' => '1234567890123',
        ]);

        Sanctum::actingAs(User::factory()->create());
        $genre = Genre::create(['name' => '旅行']);

        $response = $this->postJson('/api/v1/books', [
            'title' => '重複ISBNテスト',
            'author' => 'テスト著者',
            'isbn' => '1234567890123',
            'published_date' => '2026-01-01',
            'genres' => [$genre->id],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('isbn');

        $this->assertDatabaseCount('books', 1);
    }

    /**
     * 所有者が書籍を更新できる
     */
    public function test_owner_can_update_book_via_api(): void
    {
        $book = Book::factory()->create();

        $oldGenre = Genre::create(['name' => '旧ジャンル']);
        $newGenre = Genre::create(['name' => '新ジャンル']);

        $book->genres()->attach($oldGenre->id);
        Sanctum::actingAs($book->user);

        $response = $this->putJson('/api/v1/books/'.$book->id, [
            'title' => 'API更新後タイトル',
            'author' => '更新後著者',
            'isbn' => $book->isbn,
            'published_date' => '2026-02-01',
            'description' => null,
            'image_url' => null,
            'genres' => [$newGenre->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'API更新後タイトル');

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'API更新後タイトル',
        ]);

        $this->assertDatabaseMissing('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $oldGenre->id,
        ]);

        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $newGenre->id,
        ]);
    }

    /**
     * 所有者が書籍を削除でき、関連データも削除される
     */
    public function test_owner_can_delete_book_and_related_data(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create([
            'user_id' => $user->id,
        ]);

        $genre = Genre::create(['name' => '削除テスト']);
        $book->genres()->attach($genre->id);

        $review = Review::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '削除対象レビュー',
        ]);

        Favorite::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        ReviewLike::create([
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson(
            '/api/v1/books/'.$book->id
        );

        $response->assertNoContent();

        $this->assertDatabaseMissing('books', [
            'id' => $book->id,
        ]);

        $this->assertDatabaseMissing('reviews', [
            'id' => $review->id,
        ]);

        $this->assertDatabaseMissing('favorites', [
            'book_id' => $book->id,
        ]);

        $this->assertDatabaseMissing('review_likes', [
            'review_id' => $review->id,
        ]);

        $this->assertDatabaseMissing('book_genre', [
            'book_id' => $book->id,
        ]);

        // ジャンル自体は削除されない
        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
        ]);
    }

    /**
     * 未認証ユーザーは書籍を登録・更新・削除できない。
     */
    public function test_guest_cannot_write_books(): void
    {
        $book = Book::factory()->create();

        $this->postJson('/api/v1/books', [])->assertUnauthorized();
        $this->putJson('/api/v1/books/'.$book->id, [])->assertUnauthorized();
        $this->deleteJson('/api/v1/books/'.$book->id)->assertUnauthorized();

        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    /**
     * 他人の書籍は更新できない。
     */
    public function test_other_user_cannot_update_book(): void
    {
        $book = Book::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $this->putJson('/api/v1/books/'.$book->id, [])->assertForbidden();
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    /**
     * 他人の書籍は削除できない。
     */
    public function test_other_user_cannot_delete_book(): void
    {
        $book = Book::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $this->deleteJson('/api/v1/books/'.$book->id)->assertForbidden();
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    /**
     * 更新時にuser_idを送信して所有者を変更できない。
     */
    public function test_owner_cannot_change_book_user_id(): void
    {
        $book = Book::factory()->create();
        $otherUser = User::factory()->create();
        $genre = Genre::create(['name' => '所有者変更テスト']);
        Sanctum::actingAs($book->user);

        $this->putJson('/api/v1/books/'.$book->id, [
            'user_id' => $otherUser->id,
            'title' => $book->title,
            'author' => $book->author,
            'isbn' => $book->isbn,
            'published_date' => '2026-01-01',
            'genres' => [$genre->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('user_id');

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $book->user_id,
        ]);
    }
}
