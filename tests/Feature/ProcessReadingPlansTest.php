<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProcessReadingPlansTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function createPlan(
        string $targetDate,
        ReadingPlanStatus $status = ReadingPlanStatus::InProgress
    ): ReadingPlan {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        return ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => $targetDate,
            'status' => $status,
        ]);
    }

    private function runCommand(): void
    {
        Artisan::call('reading-plans:process');
    }

    public function test_three_days_before_target_date_sends_notification(): void
    {
        Carbon::setTestNow('2026-11-17 20:00:00');

        $plan = $this->createPlan('2026-11-20');

        $this->runCommand();

        $notification = $plan->user->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame(
            'three_days_before',
            $notification->data['timing']
        );
        $this->assertSame(1, $plan->user->notifications()->count());
    }

    public function test_on_target_date_sends_notification(): void
    {
        Carbon::setTestNow('2026-11-20 20:00:00');

        $plan = $this->createPlan('2026-11-20');

        $this->runCommand();

        $notification = $plan->user->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame(
            'on_due_date',
            $notification->data['timing']
        );
        $this->assertSame(1, $plan->user->notifications()->count());
    }

    public function test_plan_expires_after_target_date(): void
    {
        Carbon::setTestNow('2026-11-21 20:00:00');

        $plan = $this->createPlan('2026-11-20');

        $this->runCommand();

        $this->assertSame(
            ReadingPlanStatus::Expired,
            $plan->fresh()->status
        );
    }

    public function test_three_days_after_target_date_sends_notification(): void
    {
        Carbon::setTestNow('2026-11-23 20:00:00');

        $plan = $this->createPlan('2026-11-20');

        $this->runCommand();

        $notification = $plan->user->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame(
            'three_days_after',
            $notification->data['timing']
        );
        $this->assertSame(1, $plan->user->notifications()->count());
    }

    public function test_completed_plan_does_not_receive_notification(): void
    {
        Carbon::setTestNow('2026-11-20 20:00:00');

        $plan = $this->createPlan(
            '2026-11-20',
            ReadingPlanStatus::Completed
        );

        $this->runCommand();

        $this->assertSame(0, $plan->user->notifications()->count());
    }

    public function test_running_command_twice_does_not_duplicate_notification(): void
    {
        Carbon::setTestNow('2026-11-17 20:00:00');

        $plan = $this->createPlan('2026-11-20');

        $this->runCommand();
        $this->runCommand();

        $this->assertSame(1, $plan->user->notifications()->count());

        $this->assertDatabaseCount(
            'reading_plan_reminder_logs',
            1
        );
    }
}
