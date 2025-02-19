<?php

namespace AlwaysOpen\AuditLog\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use AlwaysOpen\AuditLog\EventType;
use AlwaysOpen\AuditLog\Tests\Fakes\Models\IgnoredFieldsPost;
use AlwaysOpen\AuditLog\Tests\Fakes\Models\NonSoftDeletePost;
use AlwaysOpen\AuditLog\Tests\Fakes\Models\Post;
use AlwaysOpen\AuditLog\Tests\Fakes\Models\PostAuditLog;

class PostModelTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function can_get_classname_of_auditlog_model()
    {
        $post = new Post();
        $this->assertEquals(PostAuditLog::class, $post->getAuditLogModelName());
    }

    /** @test */
    public function can_get_instance_of_auditlog_model()
    {
        $post = new Post();
        $this->assertInstanceOf(PostAuditLog::class, $post->getAuditLogModelInstance());
    }

    /** @test */
    public function creating_a_post_triggers_a_revision()
    {
        /** @var Post $post */
        $post = Post::create([
            'title'     => 'Test',
            'posted_at' => '2019-04-05 12:00:00',
        ]);

        /** @var Collection $logs */
        $logs = $post->auditLogs()->where('event_type', EventType::CREATED)->get();
        $this->assertEquals(2, $logs->count());

        $title = $logs->where('field_name', 'title')->first();
        $this->assertEquals('title', $title->field_name);
        $this->assertNull($title->field_value_old);
        $this->assertEquals('Test', $title->field_value_new);

        $posted = $logs->where('field_name', 'posted_at')->first();
        $this->assertEquals('posted_at', $posted->field_name);
        $this->assertNull($posted->field_value_old);
        $this->assertEquals('2019-04-05 12:00:00', $posted->field_value_new);
    }

    /** @test */
    public function updating_a_post_triggers_a_revision()
    {
        /** @var Post $post */
        $post = Post::create([
            'title'     => 'Test',
            'posted_at' => '2019-04-05 12:00:00',
        ]);

        $this->assertEquals(2, $post->auditLogs()->count());

        // Modify the post
        $post->update(['title' => 'My New Title']);
        $this->assertEquals(3, $post->auditLogs()->count());

        $title = $post->auditLogs()->where('event_type', EventType::UPDATED)->first();
        $this->assertEquals('title', $title->field_name);
        $this->assertEquals('Test', $title->field_value_old);
        $this->assertEquals('My New Title', $title->field_value_new);
    }

    /** @test */
    public function deleting_a_post_triggers_a_revision()
    {
        /** @var Post $post */
        $post = Post::create([
            'title'     => 'Test',
            'posted_at' => '2019-04-05 12:00:00',
        ]);

        $this->assertEquals(2, $post->auditLogs()->count());

        $post->delete();

        $this->assertEquals(3, $post->auditLogs()->count());

        $last = $post->auditLogs()->where('event_type', EventType::DELETED)->first();

        $this->assertEquals('deleted_at', $last->field_name);
        $this->assertNull($last->field_value_old);
        $this->assertNotEmpty($last->field_value_new);
    }

    /** @test */
    public function force_deleting_a_post_does_not_trigger_a_revision()
    {
        /** @var Post $post */
        $post = Post::create([
            'title'     => 'Test',
            'posted_at' => '2019-04-05 12:00:00',
        ]);

        $this->assertEquals(2, $post->auditLogs()->count());

        $post->forceDelete();

        $this->assertEquals(2, $post->auditLogs()->count());
    }

    /** @test */
    public function deleting_a_non_soft_deleting_post_does_not_trigger_a_revision()
    {
        /** @var Post $post */
        $post = NonSoftDeletePost::create([
            'title'     => 'Test',
            'posted_at' => '2019-04-05 12:00:00',
        ]);

        $this->assertEquals(2, $post->auditLogs()->count());

        $post->forceDelete();

        $this->assertEquals(2, $post->auditLogs()->count());
    }

    /** @test */
    public function restoring_a_post_triggers_a_revision()
    {
        /** @var Post $post */
        $post = Post::create([
            'title'     => 'Test',
            'posted_at' => '2019-04-05 12:00:00',
        ]);

        $this->assertEquals(2, $post->auditLogs()->count());

        $post->delete();

        $this->assertEquals(3, $post->auditLogs()->count());

        $post->restore();

        $this->assertEquals(5, $post->auditLogs()->count());

        $last = $post->auditLogs()->where('event_type', EventType::RESTORED)->first();
        $this->assertEquals('deleted_at', $last->field_name);
        $this->assertNull($last->field_value_new);
    }

    /** @test */
    public function fields_can_be_ignored()
    {
        /** @var Post $post */
        $post = IgnoredFieldsPost::create([
            'title'     => 'Test',
            'posted_at' => '2019-04-05 12:00:00',
        ]);

        $this->assertEquals(1, $post->auditLogs()->count());

        $post->update(['posted_at' => now()]);

        $this->assertEquals(1, $post->auditLogs()->count());
    }

    /** @test */
    public function asOf_gets_correct_value()
    {
        $latestTitle = 'first';
        $nextTitles = [
            'second',
            'third',
            'fourth',
        ];

        /** @var Post $post */
        $post = Post::create([
            'title'     => $latestTitle,
            'posted_at' => '2019-04-05 12:00:00',
        ]);

        // Set all current entries to 5 days in the past
        $date = now()->subDays(5);
        $postAuditLogs = PostAuditLog::all();
        $postAuditLogs->each(function (PostAuditLog $auditLog) use ($date) {
            $auditLog->occurred_at = $date;
            $auditLog->save();
        });


        foreach ($nextTitles as $title) {
            $post->title = $title;
            $post->save();
        }

        $auditLogEvents = PostAuditLog::where('subject_id', $post->getKey())
            ->where('field_name', 'title')
            ->orderBy('occurred_at', 'asc')
            ->limit(4);

        $auditLogEvents->each(function (PostAuditLog $auditLog) use (&$date) {
            $date->addDay();
            $auditLog->occurred_at = $date;
            $auditLog->save();
        });

        $auditLogEvents->each(function (PostAuditLog $auditLog) use ($post) {
            $asOfInstance = $post->asOf($auditLog->occurred_at);
            $this->assertEquals($auditLog->field_value_new, $asOfInstance->{$auditLog->field_name});
        });
    }
}
