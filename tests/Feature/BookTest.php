<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use App\Services\GoogleBooksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
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

    /**
     * タイトルでキーワード検索できる
     */
    public function test_books_can_be_searched_by_title(): void
    {
        $matched = Book::factory()->create([
            'title' => 'Laravel実践入門',
            'author' => '山田太郎',
        ]);

        Book::factory()->create([
            'title' => 'PHP基礎',
            'author' => '鈴木花子',
        ]);

        $this->get(route('books.index', ['keyword' => 'Laravel']))
            ->assertOk()
            ->assertViewHas('books', function ($books) use ($matched) {
                return $books->total() === 1
                    && $books->first()->id === $matched->id;
            });
    }

    /**
     * 著者名でキーワード検索できる
     */
    public function test_books_can_be_searched_by_author(): void
    {
        $matched = Book::factory()->create([
            'title' => 'テスト書籍A',
            'author' => '夏目漱石',
        ]);

        Book::factory()->create([
            'title' => 'テスト書籍B',
            'author' => '芥川龍之介',
        ]);

        $this->get(route('books.index', ['keyword' => '夏目']))
            ->assertOk()
            ->assertViewHas('books', function ($books) use ($matched) {
                return $books->total() === 1
                    && $books->first()->id === $matched->id;
            });
    }

    /**
     * ジャンルで絞り込める
     */
    public function test_books_can_be_filtered_by_genre(): void
    {
        $novel = Genre::create(['name' => '小説']);
        $technical = Genre::create(['name' => '技術書']);

        $matched = Book::factory()->create();
        $other = Book::factory()->create();

        $matched->genres()->attach($novel->id);
        $other->genres()->attach($technical->id);

        $this->get(route('books.index', ['genre' => $novel->id]))
            ->assertOk()
            ->assertViewHas('books', function ($books) use ($matched) {
                return $books->total() === 1
                    && $books->first()->id === $matched->id;
            });
    }

    /**
     * キーワードとジャンルを組み合わせて検索できる
     */
    public function test_books_can_be_searched_by_keyword_and_genre(): void
    {
        $novel = Genre::create(['name' => '小説']);
        $technical = Genre::create(['name' => '技術書']);

        $matched = Book::factory()->create(['title' => 'Laravel小説']);
        $sameKeyword = Book::factory()->create(['title' => 'Laravel技術書']);
        $sameGenre = Book::factory()->create(['title' => '別の小説']);

        $matched->genres()->attach($novel->id);
        $sameKeyword->genres()->attach($technical->id);
        $sameGenre->genres()->attach($novel->id);

        $this->get(route('books.index', [
            'keyword' => 'Laravel',
            'genre' => $novel->id,
        ]))
            ->assertOk()
            ->assertViewHas('books', function ($books) use ($matched) {
                return $books->total() === 1
                    && $books->first()->id === $matched->id;
            });
    }

    /**
     * 古い順に並び替えられる
     */
    public function test_books_can_be_sorted_by_oldest(): void
    {
        $old = Book::factory()->create([
            'created_at' => '2026-01-01 10:00:00',
        ]);

        $new = Book::factory()->create([
            'created_at' => '2026-02-01 10:00:00',
        ]);

        $this->get(route('books.index', ['sort' => 'oldest']))
            ->assertOk()
            ->assertViewHas('books', function ($books) use ($old, $new) {
                return $books->pluck('id')->all() === [
                    $old->id,
                    $new->id,
                ];
            });
    }

    /**
     * 評価の高い順に並び替えられる
     */
    public function test_books_can_be_sorted_by_rating(): void
    {
        $user = User::factory()->create();

        $high = Book::factory()->create();
        $low = Book::factory()->create();

        Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $high->id,
            'rating' => 5,
        ]);

        Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $low->id,
            'rating' => 3,
        ]);

        $this->get(route('books.index', ['sort' => 'rating']))
            ->assertOk()
            ->assertViewHas('books', function ($books) use ($high, $low) {
                return $books->pluck('id')->all() === [
                    $high->id,
                    $low->id,
                ];
            });
    }

    /**
     * タイトル順に並び替えられる
     */
    public function test_books_can_be_sorted_by_title(): void
    {
        $bookB = Book::factory()->create(['title' => 'Bの書籍']);
        $bookA = Book::factory()->create(['title' => 'Aの書籍']);

        $this->get(route('books.index', ['sort' => 'title']))
            ->assertOk()
            ->assertViewHas('books', function ($books) use ($bookA, $bookB) {
                return $books->pluck('id')->all() === [
                    $bookA->id,
                    $bookB->id,
                ];
            });
    }

    /**
     * デフォルトでは新着順に並ぶ
     */
    public function test_books_are_sorted_by_latest_by_default(): void
    {
        $old = Book::factory()->create([
            'created_at' => '2026-01-01 10:00:00',
        ]);

        $new = Book::factory()->create([
            'created_at' => '2026-02-01 10:00:00',
        ]);

        $this->get(route('books.index'))
            ->assertOk()
            ->assertViewHas('books', function ($books) use ($old, $new) {
                return $books->pluck('id')->all() === [
                    $new->id,
                    $old->id,
                ];
            });
    }

    /**
     * latestを指定すると新着順に並ぶ
     */
    public function test_books_can_be_sorted_by_latest(): void
    {
        $old = Book::factory()->create([
            'created_at' => '2026-01-01 10:00:00',
        ]);

        $new = Book::factory()->create([
            'created_at' => '2026-02-01 10:00:00',
        ]);

        $this->get(route('books.index', ['sort' => 'latest']))
            ->assertOk()
            ->assertViewHas('books', function ($books) use ($old, $new) {
                return $books->pluck('id')->all() === [
                    $new->id,
                    $old->id,
                ];
            });
    }

    /**
     * 旧指定のnewestは受け付けない
     */
    public function test_old_newest_sort_is_rejected(): void
    {
        $this->get(route('books.index', ['sort' => 'newest']))
            ->assertSessionHasErrors(['sort']);
    }

    /**
     * 不正なソート指定はバリデーションエラーになる
     */
    public function test_invalid_sort_is_rejected(): void
    {
        $this->get(route('books.index', ['sort' => 'invalid']))
            ->assertSessionHasErrors(['sort']);
    }

    /**
     * ページ移動時も検索条件が維持される
     */
    public function test_search_conditions_are_preserved_in_pagination(): void
    {
        for ($i = 0; $i < 12; $i++) {
            Book::factory()->create([
                'title' => 'Laravel書籍'.$i,
            ]);
        }

        $this->get(route('books.index', [
            'keyword' => 'Laravel',
            'sort' => 'title',
        ]))
            ->assertOk()
            ->assertViewHas('books', function ($books) {
                return $books->total() === 12
                    && $books->count() === 10
                    && $books->lastPage() === 2
                    && str_contains(
                        $books->url(2),
                        'keyword=Laravel'
                    )
                    && str_contains(
                        $books->url(2),
                        'sort=title'
                    );
            });
    }

    /**
     * ISBN検索成功時に書籍情報を返す
     */
    public function test_isbn_lookup_returns_book_information(): void
    {
        $user = User::factory()->create();

        $bookData = [
            'isbn' => '9781234567890',
            'title' => 'Laravel入門',
            'author' => '山田太郎',
            'published_date' => '2026-01-01',
            'description' => 'テスト用の説明文',
            'image_url' => null,
        ];

        $mock = Mockery::mock(GoogleBooksService::class);

        $mock->shouldReceive('searchByIsbn')
            ->once()
            ->with('9781234567890')
            ->andReturn($bookData);

        $this->app->instance(GoogleBooksService::class, $mock);

        $this->actingAs($user)
            ->getJson(route('books.isbn', ['isbn' => '9781234567890']))
            ->assertOk()
            ->assertJson([
                'message' => '書籍情報を取得しました。',
                'data' => $bookData,
            ]);
    }

    /**
     * ISBNが13文字以外なら422を返す
     */
    public function test_isbn_lookup_rejects_invalid_length(): void
    {
        $user = User::factory()->create();

        $mock = Mockery::mock(GoogleBooksService::class);

        $mock->shouldNotReceive('searchByIsbn');

        $this->app->instance(GoogleBooksService::class, $mock);

        $this->actingAs($user)
            ->getJson(route('books.isbn', ['isbn' => '123456789012']))
            ->assertStatus(422)
            ->assertJson([
                'message' => 'ISBNは13文字で入力してください。',
            ]);
    }

    /**
     * 書籍情報を取得できなければ404を返す
     */
    public function test_isbn_lookup_returns_404_when_book_not_found(): void
    {
        $user = User::factory()->create();

        $mock = Mockery::mock(GoogleBooksService::class);

        $mock->shouldReceive('searchByIsbn')
            ->once()
            ->with('9781234567890')
            ->andReturn(null);

        $this->app->instance(GoogleBooksService::class, $mock);

        $this->actingAs($user)
            ->getJson(route('books.isbn', ['isbn' => '9781234567890']))
            ->assertNotFound()
            ->assertJson([
                'message' => '書籍情報を取得できませんでした。手入力で登録してください。',
            ]);
    }
}
