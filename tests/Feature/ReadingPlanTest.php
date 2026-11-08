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
}