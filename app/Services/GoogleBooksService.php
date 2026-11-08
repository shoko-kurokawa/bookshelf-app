<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GoogleBooksService
{
    /**
     * ISBNから書籍情報を取得する。
     *
     * @return array<string, mixed>|null
     */
    public function searchByIsbn(string $isbn): ?array
    {
        $params = [
            'q' => 'isbn:' . $isbn,
            'maxResults' => 1,
        ];

        $apiKey = config('services.google_books.api_key');

        if (!empty($apiKey)) {
            $params['key'] = $apiKey;
        }

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->get(
                    rtrim(config('services.google_books.base_url'), '/')
                    . '/volumes',
                    $params
                );

            if (!$response->successful()) {
                Log::warning('Google Books API request failed.', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $volume = $response->json('items.0.volumeInfo');

            if (!is_array($volume)) {
                return null;
            }

            $publishedDate = $volume['publishedDate'] ?? null;

            // Google Books APIは年のみ・年月のみを返すことがある
            if (is_string($publishedDate)) {
                if (preg_match('/^\d{4}$/', $publishedDate)) {
                    $publishedDate .= '-01-01';
                } elseif (preg_match('/^\d{4}-\d{2}$/', $publishedDate)) {
                    $publishedDate .= '-01';
                }
            }

            // 不正な日付はフォームに反映しない
            if (
                !is_string($publishedDate)
                || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $publishedDate)
                || !checkdate(
                    (int) substr($publishedDate, 5, 2),
                    (int) substr($publishedDate, 8, 2),
                    (int) substr($publishedDate, 0, 4)
                )
            ) {
                $publishedDate = null;
            }

            $authors = $volume['authors'] ?? [];

            $imageUrl = data_get(
                $volume,
                'imageLinks.thumbnail'
            );

            if (is_string($imageUrl)) {
                $imageUrl = Str::limit($imageUrl, 255, '');
                $imageUrl = preg_replace(
                    '/^http:/',
                    'https:',
                    $imageUrl
                );
            } else {
                $imageUrl = null;
            }

            $description = $volume['description'] ?? null;

            return [
                'isbn' => $isbn,
                'title' => $volume['title'] ?? null,
                'author' => is_array($authors)
                    ? implode('、', $authors)
                    : null,
                'published_date' => $publishedDate,
                'description' => is_string($description)
                    ? strip_tags($description)
                    : null,
                'image_url' => $imageUrl,
            ];
        } catch (Throwable $e) {
            Log::warning('Google Books API connection error.', [
                'exception' => get_class($e),
            ]);

            return null;
        }
    }
}