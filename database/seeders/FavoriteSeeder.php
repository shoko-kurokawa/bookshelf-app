<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Favorite;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    public function run(): void
    {
        $emails = [
            'yamada@example.com',
            'suzuki@example.com',
            'tanaka@example.com',
            'sato@example.com',
            'takahashi@example.com',
        ];

        $books = Book::whereIn('isbn', [
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
        ])->orderBy('isbn')->get();

        foreach ($emails as $index => $email) {
            $user = User::where('email', $email)->firstOrFail();

            $bookIds = [];

            for ($i = 0; $i < 4; $i++) {
                $bookIds[] = $books[($index * 2 + $i) % $books->count()]->id;
            }

            $user->favoriteBooks()->syncWithoutDetaching($bookIds);
        }
    }
}