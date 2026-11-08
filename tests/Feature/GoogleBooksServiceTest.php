<?php

namespace Tests\Feature;

use App\Services\GoogleBooksService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleBooksServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google_books.base_url' => 'https://www.googleapis.com/books/v1',
            'services.google_books.api_key' => null,
        ]);

        // テスト中に実際の外部APIへ通信しない
        Http::preventStrayRequests();
    }

    private function fakeBook(array $volumeInfo): void
    {
        Http::fake([
            '*' => Http::response([
                'items' => [
                    ['volumeInfo' => $volumeInfo],
                ],
            ], 200),
        ]);
    }

    private function search(string $isbn = '9781234567890'): ?array
    {
        return app(GoogleBooksService::class)->searchByIsbn($isbn);
    }

    public function test_can_fetch_book_information_by_isbn(): void
    {
        $this->fakeBook([
            'title' => 'Laravel入門',
            'authors' => ['山田太郎', '鈴木花子'],
            'publishedDate' => '2026-05-15',
            'description' => 'Laravelの解説書',
            'imageLinks' => [
                'thumbnail' => 'https://example.com/book.jpg',
            ],
        ]);

        $result = $this->search();

        $this->assertSame([
            'isbn' => '9781234567890',
            'title' => 'Laravel入門',
            'author' => '山田太郎、鈴木花子',
            'published_date' => '2026-05-15',
            'description' => 'Laravelの解説書',
            'image_url' => 'https://example.com/book.jpg',
        ], $result);

        Http::assertSent(function (Request $request) {
            return $request->url() ===
                'https://www.googleapis.com/books/v1/volumes?q=isbn%3A9781234567890&maxResults=1'
                && $request->hasHeader('Accept', 'application/json');
        });
    }

    public function test_year_only_date_is_converted_to_full_date(): void
    {
        $this->fakeBook([
            'title' => 'テスト書籍',
            'publishedDate' => '2020',
        ]);

        $result = $this->search();

        $this->assertSame('2020-01-01', $result['published_date']);
    }

    public function test_year_and_month_date_is_converted_to_full_date(): void
    {
        $this->fakeBook([
            'title' => 'テスト書籍',
            'publishedDate' => '2020-07',
        ]);

        $result = $this->search();

        $this->assertSame('2020-07-01', $result['published_date']);
    }

    public function test_invalid_date_returns_null(): void
    {
        $this->fakeBook([
            'title' => 'テスト書籍',
            'publishedDate' => '2026-02-30',
        ]);

        $result = $this->search();

        $this->assertNull($result['published_date']);
    }

    public function test_missing_authors_returns_empty_string(): void
    {
        $this->fakeBook([
            'title' => '著者情報なし',
        ]);

        $result = $this->search();

        $this->assertSame('', $result['author']);
    }

    public function test_description_html_tags_are_removed(): void
    {
        $this->fakeBook([
            'title' => 'テスト書籍',
            'description' => '<p>Laravelの<strong>入門書</strong></p>',
        ]);

        $result = $this->search();

        $this->assertSame(
            'Laravelの入門書',
            $result['description']
        );
    }

    public function test_http_image_url_is_converted_to_https(): void
    {
        $this->fakeBook([
            'title' => 'テスト書籍',
            'imageLinks' => [
                'thumbnail' => 'http://example.com/book.jpg',
            ],
        ]);

        $result = $this->search();

        $this->assertSame(
            'https://example.com/book.jpg',
            $result['image_url']
        );
    }

    public function test_long_image_url_is_truncated_to_255_characters(): void
    {
        $longUrl = 'https://example.com/'.str_repeat('a', 300);

        $this->fakeBook([
            'title' => 'テスト書籍',
            'imageLinks' => [
                'thumbnail' => $longUrl,
            ],
        ]);

        $result = $this->search();

        $this->assertSame(
            255,
            mb_strlen($result['image_url'])
        );
    }

    public function test_returns_null_when_book_is_not_found(): void
    {
        Http::fake([
            '*' => Http::response(['items' => []], 200),
        ]);

        $this->assertNull($this->search());
    }

    public function test_returns_null_when_api_returns_error(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => 'Internal Server Error',
            ], 500),
        ]);

        $this->assertNull($this->search());
    }

    public function test_returns_null_when_connection_fails(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection failed');
        });

        $this->assertNull($this->search());
    }

    public function test_api_key_is_included_when_configured(): void
    {
        config([
            'services.google_books.api_key' => 'test-api-key',
        ]);

        $this->fakeBook([
            'title' => 'テスト書籍',
        ]);

        $this->search();

        Http::assertSent(function (Request $request) {
            return $request['key'] === 'test-api-key'
                && $request['q'] === 'isbn:9781234567890'
                && (int) $request['maxResults'] === 1;
        });
    }
}
