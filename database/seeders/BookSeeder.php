<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        // 山田太郎を登録者にする
        $user = User::where('email', 'yamada@example.com')->firstOrFail();

        $books = [
            ['吾輩は猫である', '夏目漱石', '9784101010014', '1905-01-01', ['小説']],
            ['人を動かす', 'D・カーネギー', '9784422100524', '1936-10-01', ['ビジネス', '自己啓発']],
            ['リーダブルコード', 'Dustin Boswell', '9784873115658', '2012-06-23', ['技術書']],
            ['7つの習慣', 'スティーブン・R・コヴィー', '9784863940246', '2013-08-30', ['ビジネス', '自己啓発']],
            ['坊っちゃん', '夏目漱石', '9784101010021', '1906-04-01', ['小説']],
            ['サピエンス全史', 'ユヴァル・ノア・ハラリ', '9784309226712', '2016-09-08', ['歴史', '科学']],
            ['Clean Code', 'Robert C. Martin', '9784048930598', '2017-12-18', ['技術書']],
            ['嫌われる勇気', '岸見一郎・古賀史健', '9784478025819', '2013-12-13', ['自己啓発']],
            ['火花', '又吉直樹', '9784163902302', '2015-03-11', ['小説']],
            ['FACTFULNESS', 'ハンス・ロスリング', '9784822289607', '2019-01-11', ['ビジネス', '科学']],
            ['コンテナ物語', 'マルク・レビンソン', '9784822251468', '2007-01-18', ['ビジネス', '歴史']],
        ];

        foreach ($books as $index => $data) {
            [$title, $author, $isbn, $date, $genreNames] = $data;

            $book = Book::firstOrCreate(
                ['isbn' => $isbn],
                [
                    'user_id' => $user->id,
                    'title' => $title,
                    'author' => $author,
                    'published_date' => $date,
                    'description' => "『{$title}』の紹介文です。{$author}による書籍です。",
                    'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text=' . ($index + 1),
                ]
            );

            $genreIds = Genre::whereIn('name', $genreNames)
                ->pluck('id')
                ->all();

            $book->genres()->sync($genreIds);
        }
    }
}