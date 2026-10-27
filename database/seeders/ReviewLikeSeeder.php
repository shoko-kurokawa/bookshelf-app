<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::whereIn('email', [
            'yamada@example.com',
            'suzuki@example.com',
            'tanaka@example.com',
            'sato@example.com',
            'takahashi@example.com',
        ])->get();

        $reviews = Review::with('user')
            ->whereHas('book', function ($query) {
                $query->whereIn('isbn', [
                    '9784101010014',
                    '9784422100524',
                    '9784873115658',
                    '9784863940246',
                    '9784101010021',
                    '9784309226712',
                    '9784048930598',
                    '9784478025819',
                    '9784163902302',
                    '9784822289607',
                    '9784822251468',
                ]);
            })
            ->orderBy('id')
            ->get();

        foreach ($reviews as $index => $review) {
            // 0, 1, 2, 3件を順番に割り当てる
            $likeCount = $index % 4;

            $userIds = $users
                ->where('id', '!=', $review->user_id)
                ->take($likeCount)
                ->pluck('id')
                ->all();

            $review->likedByUsers()->syncWithoutDetaching($userIds);
        }
    }
}
