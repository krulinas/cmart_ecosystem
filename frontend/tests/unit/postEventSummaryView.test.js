import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const viewSource = readFileSync(
  join(root, 'src/components/reports/PostEventSummaryView.vue'),
  'utf8',
);
const visualSpecSource = readFileSync(
  join(root, 'src/utils/reportVisualSpec.js'),
  'utf8',
);

describe('PostEventSummaryView data foundation', () => {
  it('keeps missing financial metrics as unavailable rather than inventing RM 0.00', () => {
    assert.match(viewSource, /reports\.summary\.notRecorded/);
    assert.match(viewSource, /reports\.summary\.notAvailable/);
    assert.equal(viewSource.includes('moneyOrMissing'), true);
    assert.equal(viewSource.includes('formatReportMoney'), true);
  });

  it('avoids raw ISO primary labels and Available cover status', () => {
    assert.equal(viewSource.includes('toLocaleString()'), false);
    assert.equal(viewSource.includes("status: 'Available'"), false);
    assert.equal(viewSource.includes('>Available<'), false);
    assert.equal(viewSource.includes('coverStatus'), true);
    assert.match(viewSource, /reports\.summary\.provisional|coverStatusKey === 'provisional'/);
    assert.match(viewSource, /reports\.summary\.final|coverStatusKey === 'final'/);
  });

  it('does not render survey free-text comments', () => {
    assert.equal(viewSource.includes('qualitative_comments'), false);
    assert.equal(viewSource.includes('comments_and_suggestions'), false);
  });

  it('supports attendance not-recorded and utilisation unavailable messaging', () => {
    assert.equal(viewSource.includes('attendance-not-recorded'), true);
    assert.equal(viewSource.includes('utilisation-unavailable'), true);
    assert.match(viewSource, /siteDayUtilisation|Site-day utilisation/);
  });

  it('supports legacy payment keys without inventing zeros', () => {
    assert.equal(viewSource.includes('expected_booth_fees'), true);
    assert.equal(viewSource.includes('payments.value?.expected'), true);
    assert.equal(viewSource.includes('moneyOrMissing'), true);
  });

  it('aligns Preview visuals with Analytics Hub palette via frozen snapshot only', () => {
    assert.match(viewSource, /buildReportVisualSpec/);
    assert.match(viewSource, /report-analytics-visuals/);
    assert.match(viewSource, /images\/branding\/uum-logo\.png/);
    assert.match(viewSource, /images\/branding\/cmart-logo\.png/);
    assert.match(viewSource, /benchmark-omitted-note/);
    assert.match(visualSpecSource, /includePerformanceAcrossEvents: false/);
    assert.match(visualSpecSource, /ANALYTICS_PALETTE/);
    assert.equal(visualSpecSource.includes('getEventBenchmark'), false);
    assert.equal(viewSource.includes('getEventBenchmark'), false);
  });
});
