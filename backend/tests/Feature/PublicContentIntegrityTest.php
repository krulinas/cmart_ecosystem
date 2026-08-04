<?php

namespace Tests\Feature;

use App\Models\CarbootEvent;
use App\Models\NewsPost;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * UAT content-integrity checks for public Events and News APIs.
 * Uses only marked fixtures that are deleted in tearDown — never mutates unrelated rows.
 */
class PublicContentIntegrityTest extends TestCase
{
    private const EVENT_MARKER = 'UAT-INTEGRITY-EVENT';

    private const NEWS_MARKER = 'UAT-INTEGRITY-NEWS';

    /** @var list<int> */
    private array $eventIds = [];

    /** @var list<int> */
    private array $newsIds = [];

    /** @var list<int> */
    private array $userIds = [];

    protected function tearDown(): void
    {
        NewsPost::query()->whereIn('id', $this->newsIds)->delete();
        NewsPost::query()->where('title', 'like', self::NEWS_MARKER.'%')->delete();
        CarbootEvent::query()->whereIn('id', $this->eventIds)->get()->each->delete();
        CarbootEvent::query()->where('title', 'like', self::EVENT_MARKER.'%')->get()->each->delete();
        User::query()->whereIn('id', $this->userIds)->delete();

        parent::tearDown();
    }

    public function test_public_events_returns_genuine_available_future_event(): void
    {
        $event = CarbootEvent::query()->create([
            'title' => self::EVENT_MARKER.' Visible',
            'starts_at' => Carbon::now()->addDays(3)->setTime(9, 0),
            'ends_at' => Carbon::now()->addDays(3)->setTime(15, 0),
            'status' => 'Available',
            'description' => 'Recorded description from the database.',
            'site_price' => CarbootEvent::DEFAULT_SITE_PRICE,
        ]);
        $this->eventIds[] = $event->id;

        $response = $this->getJson('/api/events');
        $response->assertOk();

        $matched = collect($response->json())->firstWhere('title', self::EVENT_MARKER.' Visible');
        $this->assertNotNull($matched);
        $this->assertSame('Recorded description from the database.', $matched['description'] ?? null);
        $this->assertSame('Available', $matched['status'] ?? null);
        $this->assertSame($event->id, $matched['id'] ?? null);
    }

    public function test_public_events_excludes_closed_and_ended_events(): void
    {
        $closed = CarbootEvent::query()->create([
            'title' => self::EVENT_MARKER.' Closed',
            'starts_at' => Carbon::now()->addDays(2)->setTime(9, 0),
            'ends_at' => Carbon::now()->addDays(2)->setTime(15, 0),
            'status' => 'Closed',
            'site_price' => CarbootEvent::DEFAULT_SITE_PRICE,
        ]);
        $ended = CarbootEvent::query()->create([
            'title' => self::EVENT_MARKER.' Ended',
            'starts_at' => Carbon::now()->subDays(5)->setTime(9, 0),
            'ends_at' => Carbon::now()->subDays(4)->setTime(15, 0),
            'status' => 'Available',
            'site_price' => CarbootEvent::DEFAULT_SITE_PRICE,
        ]);
        $this->eventIds[] = $closed->id;
        $this->eventIds[] = $ended->id;

        $titles = collect($this->getJson('/api/events')->assertOk()->json())->pluck('title')->all();

        $this->assertNotContains(self::EVENT_MARKER.' Closed', $titles);
        $this->assertNotContains(self::EVENT_MARKER.' Ended', $titles);
    }

    public function test_public_news_returns_genuine_published_post_only(): void
    {
        $author = User::query()->create([
            'name' => 'UAT News Author',
            'email' => 'uat-news-author-'.uniqid('', true).'@example.test',
            'password' => bcrypt('secret'),
            'role' => 'organizer',
            'vendor_status' => 'none',
        ]);
        $this->userIds[] = $author->id;

        $published = NewsPost::query()->create([
            'title' => self::NEWS_MARKER.' Published',
            'excerpt' => 'Genuine excerpt from the database.',
            'body' => 'Genuine body.',
            'category' => 'Announcement',
            'published_at' => now()->subDay(),
            'is_published' => true,
            'author_id' => $author->id,
        ]);
        $draft = NewsPost::query()->create([
            'title' => self::NEWS_MARKER.' Draft',
            'excerpt' => 'Draft excerpt must stay private.',
            'body' => 'Draft body.',
            'category' => 'Announcement',
            'published_at' => now()->subDay(),
            'is_published' => false,
            'author_id' => $author->id,
        ]);
        $this->newsIds[] = $published->id;
        $this->newsIds[] = $draft->id;

        $response = $this->getJson('/api/news');
        $response->assertOk();

        $titles = collect($response->json())->pluck('title')->all();
        $this->assertContains(self::NEWS_MARKER.' Published', $titles);
        $this->assertNotContains(self::NEWS_MARKER.' Draft', $titles);

        $matched = collect($response->json())->firstWhere('title', self::NEWS_MARKER.' Published');
        $this->assertSame('Genuine excerpt from the database.', $matched['excerpt'] ?? null);
        $this->assertSame('Announcement', $matched['category'] ?? null);
    }

    public function test_public_endpoints_do_not_inject_known_demo_seed_titles_when_absent(): void
    {
        // Source-level integrity: if these demo titles are not in DB, the API must not invent them.
        $demoEventTitle = 'CMart Weekly Carboot';
        $demoNewsTitle = 'Digital System Introduced with OIB Developers';

        $eventExists = CarbootEvent::query()->where('title', $demoEventTitle)->exists();
        $newsExists = NewsPost::query()->where('title', $demoNewsTitle)->exists();

        $eventTitles = collect($this->getJson('/api/events')->assertOk()->json())->pluck('title')->all();
        $newsTitles = collect($this->getJson('/api/news')->assertOk()->json())->pluck('title')->all();

        if (! $eventExists) {
            $this->assertNotContains($demoEventTitle, $eventTitles);
        }
        if (! $newsExists) {
            $this->assertNotContains($demoNewsTitle, $newsTitles);
        }

        $this->assertTrue(true);
    }
}
