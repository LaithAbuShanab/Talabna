<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Category;
use App\Models\User;
use App\Services\AdminActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AdminActivityLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_an_action_with_an_actor_and_subject(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $category = Category::factory()->create();
        $logger = new AdminActivityLogger;

        $log = $logger->log(
            actor: $admin,
            action: 'category.updated',
            subject: $category,
            description: 'Updated a category.',
            metadata: ['is_active' => false],
        );

        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('category.updated', $log->action);
        $this->assertSame(Category::class, $log->subject_type);
        $this->assertSame($category->id, $log->subject_id);
        $this->assertSame(['is_active' => false], $log->metadata);
    }

    public function test_actor_and_subject_are_optional(): void
    {
        $logger = new AdminActivityLogger;

        $log = $logger->log(actor: null, action: 'system.event');

        $this->assertNull($log->user_id);
        $this->assertNull($log->subject_type);
        $this->assertNull($log->metadata);
    }

    public function test_log_rows_are_append_only(): void
    {
        $logger = new AdminActivityLogger;
        $log = $logger->log(actor: null, action: 'system.event');

        $this->expectException(LogicException::class);

        $log->update(['action' => 'changed']);
    }
}
