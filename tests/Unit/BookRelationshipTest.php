<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Favorite;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookRelationshipTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 書籍は登録ユーザーに属する
     */
    public function test_book_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $book->user);
        $this->assertEquals($user->id, $book->user->id);
    }

    /**
     * 書籍には複数のジャンルを紐付けられる
     */
    public function test_book_belongs_to_many_genres(): void
    {
        $book = Book::factory()->create();

        $genreA = Genre::create([
            'name' => '技術書',
        ]);

        $genreB = Genre::create([
            'name' => 'ビジネス',
        ]);

        $book->genres()->attach([
            $genreA->id,
            $genreB->id,
        ]);

        $this->assertCount(2, $book->fresh()->genres);

        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genreA->id,
        ]);

        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genreB->id,
        ]);
    }

    /**
     * ジャンルから紐付く書籍を取得できる
     */
    public function test_genre_belongs_to_many_books(): void
    {
        $genre = Genre::create([
            'name' => '小説',
        ]);

        $bookA = Book::factory()->create();
        $bookB = Book::factory()->create();

        $genre->books()->attach([
            $bookA->id,
            $bookB->id,
        ]);

        $books = $genre->fresh()->books;

        $this->assertCount(2, $books);
        $this->assertTrue($books->contains('id', $bookA->id));
        $this->assertTrue($books->contains('id', $bookB->id));
    }

    /**
     * 書籍には複数のレビューを登録できる
     */
    public function test_book_has_many_reviews(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        Review::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => 'レビュー1',
        ]);

        Review::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => 'レビュー2',
        ]);

        $this->assertCount(2, $book->fresh()->reviews);

        $this->assertEquals(
            [4, 5],
            $book->fresh()->reviews
                ->pluck('rating')
                ->sort()
                ->values()
                ->all()
        );
    }

    /**
     * 書籍には複数ユーザーのお気に入りが紐付く
     */
    public function test_book_has_many_favorites(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $book = Book::factory()->create();

        Favorite::create([
            'user_id' => $userA->id,
            'book_id' => $book->id,
        ]);

        Favorite::create([
            'user_id' => $userB->id,
            'book_id' => $book->id,
        ]);

        $favorites = $book->fresh()->favorites;

        $this->assertCount(2, $favorites);
        $this->assertTrue(
            $favorites->contains('user_id', $userA->id)
        );
        $this->assertTrue(
            $favorites->contains('user_id', $userB->id)
        );
    }

    /**
     * 書籍の出版日は日付型に変換される
     */
    public function test_book_published_date_is_cast_to_date(): void
    {
        $book = Book::factory()->create([
            'published_date' => '2026-01-15',
        ]);

        $this->assertInstanceOf(
            Carbon::class,
            $book->published_date
        );

        $this->assertEquals(
            '2026-01-15',
            $book->published_date->format('Y-m-d')
        );
    }

    /**
     * ジャンルのsyncで古い紐付けが解除される
     */
    public function test_book_genres_can_be_synced(): void
    {
        $book = Book::factory()->create();

        $oldGenre = Genre::create([
            'name' => '旧ジャンル',
        ]);

        $newGenre = Genre::create([
            'name' => '新ジャンル',
        ]);

        $book->genres()->attach($oldGenre->id);

        $book->genres()->sync([$newGenre->id]);

        $this->assertDatabaseMissing('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $oldGenre->id,
        ]);

        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $newGenre->id,
        ]);

        $this->assertCount(1, $book->fresh()->genres);
    }
}
