<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'published_date' => $this->published_date?->format('Y-m-d'),
            'description' => $this->description,
            'image_url' => $this->image_url,

            // ジャンル情報
            'genres' => $this->whenloaded('genres', function () {
                return $this->genres->map(function ($genre) {
                    return [
                        'id' => $genre->id,
                        'name' => $genre->name,
                    ];
                });
            }),

            // 平均評価・レビュー件数
            'reviews_avg_rating' => $this->when(
                $this->reviews_avg_rating !== null,
                fn () => (float) $this->reviews_avg_rating
            ),
            'reviews_count' => $this->whenCounted('reviews'),

            // レビュー一覧（詳細APIでのみ表示）
            'reviews' => ReviewResource::collection(
                $this->whenLoaded('reviews')
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
