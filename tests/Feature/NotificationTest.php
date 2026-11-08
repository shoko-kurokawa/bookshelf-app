<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * テスト用のデータベース通知を作成
     */
    private function createNotification(User $user): string
    {
        $id = (string) Str::uuid();

        $user->notifications()->create([
            'id' => $id,
            'type' => ReadingPlanReminder::class,
            'data' => [
                'title' => 'テスト書籍',
                'body' => '読了目標日まであと3日です。',
                'timing' => 'three_days_before',
                'target_date' => '2026-11-20',
                'url' => route('reading-plans.index'),
            ],
        ]);

        return $id;
    }

    public function test_guest_cannot_view_notifications(): void
    {
        $this->get(route('notifications.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_can_view_own_notifications(): void
    {
        $user = User::factory()->create();

        $this->createNotification($user);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('テスト書籍')
            ->assertSee('読了目標日まであと3日です。');
    }

    public function test_user_cannot_view_other_users_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->createNotification($otherUser);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('テスト書籍');
    }

    public function test_user_can_mark_own_notification_as_read(): void
    {
        $user = User::factory()->create();
        $id = $this->createNotification($user);

        $this->actingAs($user)
            ->post(route('notifications.read', $id))
            ->assertRedirect(route('notifications.index'));

        $this->assertNotNull(
            $user->notifications()->findOrFail($id)->read_at
        );
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $id = $this->createNotification($otherUser);

        $this->actingAs($user)
            ->post(route('notifications.read', $id))
            ->assertNotFound();

        $this->assertNull(
            $otherUser->notifications()->findOrFail($id)->read_at
        );
    }

    public function test_guest_cannot_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $id = $this->createNotification($user);

        $this->post(route('notifications.read', $id))
            ->assertRedirect(route('login'));
    }
}
