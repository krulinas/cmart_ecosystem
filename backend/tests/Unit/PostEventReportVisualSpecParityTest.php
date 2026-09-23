<?php

namespace Tests\Unit;

use App\Support\PostEventReportVisualSpec;
use PHPUnit\Framework\TestCase;

/**
 * JS↔PHP visual-spec contract parity (presentation only).
 */
class PostEventReportVisualSpecParityTest extends TestCase
{
    public function test_contract_matches_js_fixture_expectations(): void
    {
        $fixturePath = dirname(__DIR__, 3).'/frontend/tests/fixtures/reportVisualParitySnapshot.json';
        $this->assertFileExists($fixturePath);

        $snapshot = json_decode((string) file_get_contents($fixturePath), true);
        $this->assertIsArray($snapshot);

        $spec = PostEventReportVisualSpec::fromSnapshot($snapshot);
        $contract = PostEventReportVisualSpec::toContract($spec);

        $this->assertSame('stacked_bar', $contract['charts']['revenue_collection']['type']);
        $this->assertSame('doughnut', $contract['charts']['vendor_categories']['type']);
        $this->assertCount(3, $contract['charts']['vendor_categories']['rows']);
        $this->assertSame(
            ['food', 'fashion', 'books'],
            array_column($contract['charts']['vendor_categories']['rows'], 'key'),
        );
        $this->assertSame('doughnut', $contract['charts']['site_utilisation']['type']);
        $this->assertSame('doughnut', $contract['charts']['booking_status']['type']);
        $this->assertSame('#3970E4', $contract['palette']['primary']);
        $this->assertSame('#2E9D78', $contract['palette']['positive']);
        $this->assertFalse($contract['includePerformanceAcrossEvents']);

        $jsContractPath = dirname(__DIR__, 3).'/frontend/tests/fixtures/reportVisualParityContract.js.json';
        if (is_file($jsContractPath)) {
            $js = json_decode((string) file_get_contents($jsContractPath), true);
            $this->assertIsArray($js);
            $this->assertSame(
                $js['charts']['revenue_collection']['type'],
                $contract['charts']['revenue_collection']['type'],
            );
            $this->assertSame(
                $js['charts']['vendor_categories']['type'],
                $contract['charts']['vendor_categories']['type'],
            );
            $this->assertSame(
                array_column($js['charts']['vendor_categories']['rows'], 'key'),
                array_column($contract['charts']['vendor_categories']['rows'], 'key'),
            );
            $this->assertEquals(
                array_map('floatval', array_column($js['charts']['vendor_categories']['rows'], 'count')),
                array_map('floatval', array_column($contract['charts']['vendor_categories']['rows'], 'count')),
            );
            $this->assertSame(
                array_column($js['charts']['revenue_collection']['rows'], 'color'),
                array_column($contract['charts']['revenue_collection']['rows'], 'color'),
            );
            $this->assertSame(
                array_column($js['charts']['booking_status']['rows'], 'key'),
                array_column($contract['charts']['booking_status']['rows'], 'key'),
            );
            $this->assertSame($js['palette'], $contract['palette']);
        }

        $out = dirname(__DIR__, 3).'/frontend/tests/fixtures/reportVisualParityContract.php.json';
        file_put_contents($out, json_encode($contract, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    public function test_category_chart_type_adaptive_rule(): void
    {
        $this->assertSame('compact', PostEventReportVisualSpec::resolveCategoryChartType(0));
        $this->assertSame('compact', PostEventReportVisualSpec::resolveCategoryChartType(1));
        $this->assertSame('doughnut', PostEventReportVisualSpec::resolveCategoryChartType(2));
        $this->assertSame('doughnut', PostEventReportVisualSpec::resolveCategoryChartType(5));
        $this->assertSame('bar', PostEventReportVisualSpec::resolveCategoryChartType(6));
    }
}
