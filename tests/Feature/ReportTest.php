<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function createReview(
        User $user,
        Book $book,
        int $rating
    ): Review {
        return Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => $rating,
        ]);
    }

    public function test_guest_cannot_view_report(): void
    {
        $this->get(route('reports.index'))
            ->assertRedirect(route('login'));
    }

    public function test_report_is_empty_when_user_has_no_reviews(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('stats', function ($stats) {
                return $stats['summary']['total_reviews'] === 0
                    && $stats['summary']['books_read'] === 0
                    && $stats['summary']['average_rating'] == 0
                    && $stats['rating_distribution']->all() === [0, 0, 0, 0, 0]
                    && $stats['top_rated_books']->isEmpty()
                    && $stats['genre_ratings']->isEmpty();
            });
    }

    public function test_report_summary_counts_reviews_and_unique_books(): void
    {
        $user = User::factory()->create();

        $bookA = Book::factory()->for($user)->create();
        $bookB = Book::factory()->for($user)->create();

        $this->createReview($user, $bookA, 5);
        $this->createReview($user, $bookA, 4);
        $this->createReview($user, $bookB, 3);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('stats', function ($stats) {
                return $stats['summary']['total_reviews'] === 3
                    && $stats['summary']['books_read'] === 2
                    && abs($stats['summary']['average_rating'] - 4.0) < 0.001;
            });
    }

    public function test_report_only_includes_logged_in_users_reviews(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $book = Book::factory()->for($user)->create();

        $this->createReview($user, $book, 5);
        $this->createReview($other, $book, 1);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('stats', function ($stats) {
                return $stats['summary']['total_reviews'] === 1
                    && $stats['summary']['books_read'] === 1
                    && $stats['summary']['average_rating'] == 5
                    && $stats['rating_distribution']->all() === [0, 0, 0, 0, 1];
            });
    }

    public function test_rating_distribution_counts_all_five_ratings(): void
    {
        $user = User::factory()->create();

        foreach ([1, 2, 2, 4, 5] as $rating) {
            $book = Book::factory()->for($user)->create();

            $this->createReview($user, $book, $rating);
        }

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('stats', function ($stats) {
                return $stats['rating_distribution']->all() === [1, 2, 0, 1, 1];
            });
    }

    public function test_top_rated_books_use_highest_rating_per_book(): void
    {
        $user = User::factory()->create();

        $bookA = Book::factory()->for($user)->create();
        $bookB = Book::factory()->for($user)->create();
        $bookC = Book::factory()->for($user)->create();

        $this->createReview($user, $bookA, 4);
        $this->createReview($user, $bookA, 5);
        $this->createReview($user, $bookB, 4);
        $this->createReview($user, $bookC, 3);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('stats', function ($stats) use ($bookA, $bookB) {
                $top = $stats['top_rated_books'];

                return $top->count() === 2
                    && $top[0]['id'] === $bookA->id
                    && $top[0]['rating'] === 5
                    && $top[1]['id'] === $bookB->id
                    && $top[1]['rating'] === 4;
            });
    }

    public function test_top_rated_books_are_limited_to_five(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 7; $i++) {
            $book = Book::factory()->for($user)->create();

            $this->createReview($user, $book, 5);
        }

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('stats', function ($stats) {
                $top = $stats['top_rated_books'];

                return $top->count() === 5
                    && $top->pluck('id')->all() === [1, 2, 3, 4, 5];
            });
    }

    public function test_genre_ratings_count_books_with_multiple_genres(): void
    {
        $user = User::factory()->create();

        $novel = Genre::create(['name' => '小説']);
        $technical = Genre::create(['name' => '技術書']);

        $bookA = Book::factory()->for($user)->create();
        $bookB = Book::factory()->for($user)->create();

        $bookA->genres()->attach([$novel->id, $technical->id]);
        $bookB->genres()->attach($novel->id);

        $this->createReview($user, $bookA, 5);
        $this->createReview($user, $bookB, 3);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('stats', function ($stats) use ($novel, $technical) {
                $genres = $stats['genre_ratings']->keyBy('id');

                return $genres->count() === 2
                    && $genres[$novel->id]['count'] === 2
                    && $genres[$novel->id]['average_rating'] == 4
                    && $genres[$technical->id]['count'] === 1
                    && $genres[$technical->id]['average_rating'] == 5;
            });
    }

    public function test_genre_ratings_are_limited_to_five(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 7; $i++) {
            $genre = Genre::create(['name' => 'ジャンル'.$i]);
            $book = Book::factory()->for($user)->create();

            $book->genres()->attach($genre->id);

            $this->createReview($user, $book, 5);
        }

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('stats', function ($stats) {
                return $stats['genre_ratings']->count() === 5;
            });
    }
}
