<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ReviewLikeController extends Controller
{
    // レビューいいね登録・解除
    public function toggle(Review $review): RedirectResponse
    {
        $user = auth()->user();

        DB::transaction(function () use ($user, $review) {
            $like = $user->reviewLikes()
                ->where('review_id', $review->id)
                ->lockForUpdate()
                ->first();

            if ($like) {
                $like->delete();
            } else {
                $user->reviewLikes()->create([
                    'review_id' => $review->id,
                ]);
            }
        });

        return back();
    }
}
