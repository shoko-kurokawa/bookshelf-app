<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ログインユーザーはジャンル一覧を表示できる
     */
    public function test_authenticated_user_can_view_genre_index(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('genres.index'));

        $response->assertOk();
    }

    /**
     * ログインユーザーはジャンル登録画面を表示できる
     */
    public function test_authenticated_user_can_view_genre_create_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('genres.create'));

        $response->assertOk();
    }

    /**
     * ログインユーザーはジャンルを登録できる
     */
    public function test_authenticated_user_can_create_genre(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('genres.store'), [
                'name' => 'プログラミング',
            ]);

        $response->assertRedirect(route('genres.index'));

        $this->assertDatabaseHas('genres', [
            'name' => 'プログラミング',
        ]);
    }

    /**
     * ジャンル名が未入力の場合は登録できない
     */
    public function test_genre_name_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('genres.store'), [
                'name' => '',
            ]);

        $response->assertSessionHasErrors('name');

        $this->assertDatabaseCount('genres', 0);
    }

    /**
     * 同じ名前のジャンルを重複登録できない
     */
    public function test_duplicate_genre_name_is_rejected(): void
    {
        $user = User::factory()->create();

        Genre::create([
            'name' => '小説',
        ]);

        $response = $this->actingAs($user)
            ->post(route('genres.store'), [
                'name' => '小説',
            ]);

        $response->assertSessionHasErrors('name');

        $this->assertDatabaseCount('genres', 1);
    }

    /**
     * ジャンル詳細画面を表示できる
     */
    public function test_authenticated_user_can_view_genre_detail(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => '科学',
        ]);

        $response = $this->actingAs($user)
            ->get(route('genres.show', $genre));

        $response->assertOk();
    }

    /**
     * ジャンル詳細には紐づく書籍が表示される
     */
    public function test_genre_detail_shows_related_books(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => '歴史',
        ]);

        $book = Book::factory()->create([
            'title' => '歴史テスト書籍',
        ]);

        $book->genres()->attach($genre->id);

        $response = $this->actingAs($user)
            ->get(route('genres.show', $genre));

        $response->assertOk();
        $response->assertSee('歴史テスト書籍');
    }

    /**
     * ジャンル編集画面を表示できる
     */
    public function test_authenticated_user_can_view_genre_edit_page(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => '旅行',
        ]);

        $response = $this->actingAs($user)
            ->get(route('genres.edit', $genre));

        $response->assertOk();
    }

    /**
     * ジャンル名を更新できる
     */
    public function test_authenticated_user_can_update_genre(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => '旧ジャンル名',
        ]);

        $response = $this->actingAs($user)
            ->put(route('genres.update', $genre), [
                'name' => '新ジャンル名',
            ]);

        $response->assertRedirect(route('genres.index'));

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '新ジャンル名',
        ]);
    }

    /**
     * ジャンル更新時は自分自身の名前を使用できる
     */
    public function test_genre_can_be_updated_with_same_name(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => 'ビジネス',
        ]);

        $response = $this->actingAs($user)
            ->put(route('genres.update', $genre), [
                'name' => 'ビジネス',
            ]);

        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => 'ビジネス',
        ]);
    }

    /**
     * ジャンル更新時に他のジャンル名と重複できない
     */
    public function test_genre_update_rejects_duplicate_name(): void
    {
        $user = User::factory()->create();

        Genre::create([
            'name' => '小説',
        ]);

        $genre = Genre::create([
            'name' => '科学',
        ]);

        $response = $this->actingAs($user)
            ->put(route('genres.update', $genre), [
                'name' => '小説',
            ]);

        $response->assertSessionHasErrors('name');

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '科学',
        ]);
    }

    /**
     * 書籍が紐づいていないジャンルは削除できる
     */
    public function test_genre_without_books_can_be_deleted(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => '削除対象ジャンル',
        ]);

        $response = $this->actingAs($user)
            ->delete(route('genres.destroy', $genre));

        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('genres', [
            'id' => $genre->id,
        ]);
    }

    /**
     * 書籍が紐づいているジャンルは削除できない
     */
    public function test_genre_with_books_cannot_be_deleted(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => '削除不可ジャンル',
        ]);

        $book = Book::factory()->create();

        $book->genres()->attach($genre->id);

        $response = $this->actingAs($user)
            ->delete(route('genres.destroy', $genre));

        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '削除不可ジャンル',
        ]);
    }

    /**
     * 未ログインユーザーはジャンル管理画面を利用できない
     */
    public function test_guest_cannot_access_genre_management(): void
    {
        $genre = Genre::create([
            'name' => '認証テスト',
        ]);

        $this->get(route('genres.index'))
            ->assertRedirect(route('login'));

        $this->get(route('genres.create'))
            ->assertRedirect(route('login'));

        $this->get(route('genres.show', $genre))
            ->assertRedirect(route('login'));

        $this->get(route('genres.edit', $genre))
            ->assertRedirect(route('login'));

        $this->post(route('genres.store'), [
            'name' => '未ログイン登録',
        ])->assertRedirect(route('login'));

        $this->put(route('genres.update', $genre), [
            'name' => '未ログイン更新',
        ])->assertRedirect(route('login'));

        $this->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '認証テスト',
        ]);

        $this->assertDatabaseCount('genres', 1);
    }
}
