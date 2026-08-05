<?php

namespace Tests\Unit;

use App\Support\CmartReportSnapshotFilter;
use App\Support\ReportDateTimeFormatter;
use PHPUnit\Framework\TestCase;

class CmartReportSnapshotFilterTest extends TestCase
{
    public function test_strips_qualitative_comments_and_pii_keys(): void
    {
        $snapshot = [
            'schema_version' => 1,
            'event' => [
                'title' => 'Demo',
                'status' => 'Available',
                'max_slots' => 40,
            ],
            'sections' => [
                'vendor_survey' => [
                    'respondent_count' => 2,
                    'qualitative_comments' => [
                        ['comment' => 'Great event', 'email' => 'a@b.com'],
                    ],
                    'difficulty_details' => 'secret',
                ],
                'booking_pipeline' => [
                    'total_bookings' => 3,
                    'booking_ids' => [1, 2, 3],
                    'user_id' => 99,
                ],
            ],
            'name' => 'should vanish at root',
        ];

        $filtered = CmartReportSnapshotFilter::forCmart($snapshot);

        $this->assertArrayNotHasKey('qualitative_comments', $filtered['sections']['vendor_survey']);
        $this->assertArrayNotHasKey('difficulty_details', $filtered['sections']['vendor_survey']);
        $this->assertArrayNotHasKey('booking_ids', $filtered['sections']['booking_pipeline']);
        $this->assertArrayNotHasKey('user_id', $filtered['sections']['booking_pipeline']);
        $this->assertArrayNotHasKey('name', $filtered);
        $this->assertArrayNotHasKey('status', $filtered['event']);
        $this->assertArrayNotHasKey('max_slots', $filtered['event']);
        $this->assertTrue($filtered['sections']['vendor_survey']['privacy']['free_text_excluded']);
        $this->assertSame(3, $filtered['sections']['booking_pipeline']['total_bookings']);
    }

    public function test_formats_english_kuala_lumpur_range(): void
    {
        $display = ReportDateTimeFormatter::range(
            '2026-07-25T02:00:00+00:00',
            '2026-07-25T14:00:00+00:00',
        );

        $this->assertNotNull($display);
        $this->assertStringContainsString('July 2026', $display);
        $this->assertStringContainsString('–', $display);
        $this->assertDoesNotMatchRegularExpression('/\d{4}-\d{2}-\d{2}T/', $display);
    }
}
