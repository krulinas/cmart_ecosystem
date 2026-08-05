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

describe('PostEventSummaryView data foundation', () => {
  it('does not coerce missing metrics with ?? 0 in display paths', () => {
    assert.equal(viewSource.includes('?? 0'), false);
    assert.equal(viewSource.includes('Not recorded'), true);
    assert.equal(viewSource.includes('Not available for this event'), true);
  });

  it('avoids raw ISO primary labels and Available cover status', () => {
    assert.equal(viewSource.includes('toLocaleString()'), false);
    assert.equal(viewSource.includes('Asia/Kuala_Lumpur'), true);
    assert.equal(viewSource.includes("status: 'Available'"), false);
    assert.equal(viewSource.includes('>Available<'), false);
    assert.equal(viewSource.includes('coverStatus'), true);
    assert.equal(viewSource.includes('Provisional'), true);
    assert.equal(viewSource.includes('Final'), true);
  });

  it('does not render survey free-text comments', () => {
    assert.equal(viewSource.includes('qualitative_comments'), false);
    assert.equal(viewSource.includes('comments_and_suggestions'), false);
  });

  it('supports attendance not-recorded and utilisation unavailable messaging', () => {
    assert.equal(viewSource.includes('Attendance verification was not recorded for this event.'), true);
    assert.equal(viewSource.includes('utilisation-unavailable'), true);
    assert.equal(viewSource.includes('Site-day utilisation'), true);
  });

  it('supports legacy payment keys without inventing zeros', () => {
    assert.equal(viewSource.includes('expected_booth_fees'), true);
    assert.equal(viewSource.includes('payments.value?.expected'), true);
    assert.equal(viewSource.includes('moneyDisplay'), true);
  });
});
