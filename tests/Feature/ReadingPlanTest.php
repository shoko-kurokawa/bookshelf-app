<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReadingPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-09 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function createPlan(
        User $user,
        Book $book,
        ReadingPlanStatus $status = ReadingPlanStatus::InProgress,
        string $targetDate = '2026-11-20'
    ): ReadingPlan {
        return ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => $targetDate,
            'status' => $status,
        ]);
    }

    public function test_user_cannot_edit_other_users_plan(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $book = Book::factory()->for($owner)->create();
        $plan = $this->createPlan($owner, $book);

        $this->actingAs($other)
            ->get(route('reading-plans.edit', $plan))
            ->assertForbidden();
    }

    public function test_user_cannot_update_other_users_plan(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $book = Book::factory()->for($owner)->create();
        $plan = $this->createPlan($owner, $book);

        $this->actingAs($other)
            ->put(route('reading-plans.update', $plan), [
                'target_date' => '2026-11-25',
            ])
            ->assertForbidden();

        $this->assertSame(
            '2026-11-20',
            $plan->fresh()->target_date->format('Y-m-d')
        );
    }

    public function test_user_cannot_delete_other_users_plan(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $book = Book::factory()->for($owner)->create();
        $plan = $this->createPlan($owner, $book);

        $this->actingAs($other)
            ->delete(route('reading-plans.destroy', $plan))
            ->assertForbidden();

        $this->assertDatabaseHas('reading_plans', [
            'id' => $plan->id,
        ]);
    }

    public function test_user_cannot_complete_other_users_plan(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $book = Book::factory()->for($owner)->create();
        $plan = $this->createPlan($owner, $book);

        $this->actingAs($other)
            ->patch(route('reading-plans.complete', $plan))
            ->assertForbidden();

        $this->assertSame(
            ReadingPlanStatus::InProgress,
            $plan->fresh()->status
        );
    }

    public function test_completed_plan_cannot_be_edited(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $plan = $this->createPlan(
            $user,
            $book,
            ReadingPlanStatus::Completed
        );

        $this->actingAs($user)
            ->get(route('reading-plans.edit', $plan))
            ->assertForbidden();
    }

    public function test_expired_plan_returns_to_in_progress_when_date_changes(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $plan = $this->createPlan(
            $user,
            $book,
            ReadingPlanStatus::Expired,
            '2026-11-05'
        );

        $this->actingAs($user)
            ->put(route('reading-plans.update', $plan), [
                'target_date' => '2026-11-25',
            ])
            ->assertRedirect(route('reading-plans.index'));

        $this->assertSame(
            ReadingPlanStatus::InProgress,
            $plan->fresh()->status
        );

        $this->assertSame(
            '2026-11-25',
            $plan->fresh()->target_date->format('Y-m-d')
        );
    }

    public function test_duplicate_in_progress_plan_cannot_be_created(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->createPlan($user, $book);

        $this->actingAs($user)
            ->post(route('reading-plans.store'), [
                'book_id' => $book->id,
                'target_date' => '2026-11-25',
            ])
            ->assertSessionHasErrors(['book_id']);

        $this->assertDatabaseCount('reading_plans', 1);
    }

    public function test_new_plan_can_be_created_after_completion(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->createPlan(
            $user,
            $book,
            ReadingPlanStatus::Completed
        );

        $this->actingAs($user)
            ->post(route('reading-plans.store'), [
                'book_id' => $book->id,
                'target_date' => '2026-11-25',
            ])
            ->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseCount('reading_plans', 2);
    }

    public function test_past_target_date_cannot_be_registered(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('reading-plans.store'), [
                'book_id' => $book->id,
                'target_date' => '2026-11-08',
            ])
            ->assertSessionHasErrors(['target_date']);

        $this->assertDatabaseCount('reading_plans', 0);
    }

    public function test_index_shows_only_own_reading_plans(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $bookA = Book::factory()->for($user)->create();
        $bookB = Book::factory()->for($other)->create();

        $ownPlan = $this->createPlan($user, $bookA);
        $otherPlan = $this->createPlan($other, $bookB);

        $this->actingAs($user)
            ->get(route('reading-plans.index'))
            ->assertOk()
            ->assertViewHas('readingPlans', function ($plans) use ($ownPlan, $otherPlan) {
                return $plans->total() === 1
                    && $plans->pluck('id')->contains($ownPlan->id)
                    && ! $plans->pluck('id')->contains($otherPlan->id);
            });
    }

    public function test_index_can_filter_by_status(): void
    {
        $user = User::factory()->create();

        $bookA = Book::factory()->for($user)->create();
        $bookB = Book::factory()->for($user)->create();

        $inProgress = $this->createPlan($user, $bookA);
        $completed = $this->createPlan(
            $user,
            $bookB,
            ReadingPlanStatus::Completed
        );

        $this->actingAs($user)
            ->get(route('reading-plans.index', [
                'status' => ReadingPlanStatus::Completed->value,
            ]))
            ->assertOk()
            ->assertViewHas(
                'currentStatus',
                ReadingPlanStatus::Completed->value
            )
            ->assertViewHas('readingPlans', function ($plans) use ($inProgress, $completed) {
                return $plans->total() === 1
                    && $plans->first()->id === $completed->id
                    && ! $plans->pluck('id')->contains($inProgress->id);
            });
    }

    public function test_invalid_status_filter_shows_all_plans(): void
    {
        $user = User::factory()->create();

        $bookA = Book::factory()->for($user)->create();
        $bookB = Book::factory()->for($user)->create();

        $this->createPlan($user, $bookA);
        $this->createPlan(
            $user,
            $bookB,
            ReadingPlanStatus::Completed
        );

        $this->actingAs($user)
            ->get(route('reading-plans.index', [
                'status' => 'invalid-status',
            ]))
            ->assertOk()
            ->assertViewHas('currentStatus', '')
            ->assertViewHas('readingPlans', function ($plans) {
                return $plans->total() === 2;
            });
    }

    public function test_index_paginates_ten_plans_per_page(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 12; $i++) {
            $book = Book::factory()->for($user)->create();
            $this->createPlan($user, $book);
        }

        $this->actingAs($user)
            ->get(route('reading-plans.index'))
            ->assertOk()
            ->assertViewHas('readingPlans', function ($plans) {
                return $plans->total() === 12
                    && $plans->count() === 10
                    && $plans->lastPage() === 2;
            });

        $this->actingAs($user)
            ->get(route('reading-plans.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('readingPlans', function ($plans) {
                return $plans->total() === 12
                    && $plans->count() === 2
                    && $plans->currentPage() === 2;
            });
    }

    public function test_create_page_includes_books_owned_by_other_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $otherBook = Book::factory()->for($other)->create();

        $this->actingAs($user)
            ->get(route('reading-plans.create'))
            ->assertOk()
            ->assertViewHas('books', function ($books) use ($otherBook) {
                return $books->pluck('id')->contains($otherBook->id);
            });
    }

    public function test_user_can_create_reading_plan(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('reading-plans.store'), [
                'book_id' => $book->id,
                'target_date' => '2026-11-25',
            ])
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reading_plans', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => '2026-11-25 00:00:00',
            'status' => ReadingPlanStatus::InProgress->value,
        ]);
    }

    public function test_user_can_view_edit_page_for_own_plan(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $plan = $this->createPlan($user, $book);

        $this->actingAs($user)
            ->get(route('reading-plans.edit', $plan))
            ->assertOk()
            ->assertViewHas('readingPlan', function ($viewPlan) use ($plan) {
                return $viewPlan->id === $plan->id
                    && $viewPlan->relationLoaded('book');
            });
    }

    public function test_user_can_update_in_progress_plan(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $plan = $this->createPlan($user, $book);

        $this->actingAs($user)
            ->put(route('reading-plans.update', $plan), [
                'target_date' => '2026-11-28',
            ])
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success');

        $this->assertSame(
            ReadingPlanStatus::InProgress,
            $plan->fresh()->status
        );

        $this->assertSame(
            '2026-11-28',
            $plan->fresh()->target_date->format('Y-m-d')
        );
    }

    public function test_user_can_complete_in_progress_plan(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $plan = $this->createPlan($user, $book);

        $this->actingAs($user)
            ->patch(route('reading-plans.complete', $plan))
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success');

        $plan->refresh();

        $this->assertSame(
            ReadingPlanStatus::Completed,
            $plan->status
        );

        $this->assertNotNull($plan->completed_at);
    }

    public function test_user_can_delete_own_reading_plan(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $plan = $this->createPlan($user, $book);

        $this->actingAs($user)
            ->delete(route('reading-plans.destroy', $plan))
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('reading_plans', [
            'id' => $plan->id,
        ]);
    }

    /**
     * 目標日が未入力の場合は更新できない
     */
    public function test_update_requires_target_date(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $plan = $this->createPlan($user, $book);

        $this->actingAs($user)
            ->put(route('reading-plans.update', $plan), [])
            ->assertSessionHasErrors([
                'target_date' => '目標日を入力してください。',
            ]);
    }

    /**
     * 目標日が不正な形式の場合は更新できない
     */
    public function test_update_rejects_invalid_date_format(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $plan = $this->createPlan($user, $book);

        $this->actingAs($user)
            ->put(route('reading-plans.update', $plan), [
                'target_date' => '2026/11/25',
            ])
            ->assertSessionHasErrors([
                'target_date' => '目標日は正しい日付で入力してください。',
            ]);
    }

    /**
     * 過去の日付には更新できない
     */
    public function test_update_rejects_past_target_date(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $plan = $this->createPlan($user, $book);

        $this->actingAs($user)
            ->put(route('reading-plans.update', $plan), [
                'target_date' => '2026-11-08',
            ])
            ->assertSessionHasErrors([
                'target_date' => '目標日は本日以降の日付を指定してください。',
            ]);
    }

    /**
     * 目標日が今日の場合は更新できる
     */
    public function test_update_accepts_today_as_target_date(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $plan = $this->createPlan($user, $book);

        $this->actingAs($user)
            ->put(route('reading-plans.update', $plan), [
                'target_date' => '2026-11-09',
            ])
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success');

        $this->assertSame(
            '2026-11-09',
            $plan->fresh()->target_date->format('Y-m-d')
        );
    }

    /**
     * 読了済みの計画は更新できない
     */
    public function test_completed_plan_cannot_be_updated(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $plan = $this->createPlan(
            $user,
            $book,
            ReadingPlanStatus::Completed
        );

        $this->actingAs($user)
            ->put(route('reading-plans.update', $plan), [
                'target_date' => '2026-11-25',
            ])
            ->assertSessionHasErrors([
                'target_date' => '読了済みの計画は編集できません。',
            ]);

        $this->assertSame(
            ReadingPlanStatus::Completed,
            $plan->fresh()->status
        );
    }

    /**
     * 期限切れ計画の再開時、同じ書籍の読書中計画があれば拒否する
     */
    public function test_expired_plan_cannot_restart_when_active_plan_exists(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $expiredPlan = $this->createPlan(
            $user,
            $book,
            ReadingPlanStatus::Expired,
            '2026-11-01'
        );

        $activePlan = $this->createPlan(
            $user,
            $book,
            ReadingPlanStatus::InProgress,
            '2026-11-20'
        );

        $this->actingAs($user)
            ->put(route('reading-plans.update', $expiredPlan), [
                'target_date' => '2026-11-25',
            ])
            ->assertSessionHasErrors([
                'target_date' => 'この書籍には、すでに読書中の計画があります。',
            ]);

        $this->assertSame(
            ReadingPlanStatus::Expired,
            $expiredPlan->fresh()->status
        );

        $this->assertSame(
            ReadingPlanStatus::InProgress,
            $activePlan->fresh()->status
        );
    }
}
