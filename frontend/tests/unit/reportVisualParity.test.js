import assert from 'node:assert/strict';
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';
import { resolveCategoryChartType, shouldUseDoughnutForCategories } from '../../src/utils/chartLifecycle.js';
import {
  buildReportVisualSpec,
  visualSpecContract,
} from '../../src/utils/reportVisualSpec.js';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const fixture = JSON.parse(
  readFileSync(join(root, 'tests/fixtures/reportVisualParitySnapshot.json'), 'utf8'),
);

describe('category chart adaptive rule', () => {
  it('maps 2–5 → doughnut, 6+ → bar, 0–1 → compact', () => {
    assert.equal(resolveCategoryChartType(0), 'compact');
    assert.equal(resolveCategoryChartType(1), 'compact');
    assert.equal(resolveCategoryChartType(2), 'doughnut');
    assert.equal(resolveCategoryChartType(5), 'doughnut');
    assert.equal(resolveCategoryChartType(6), 'bar');
    assert.equal(shouldUseDoughnutForCategories(3), true);
    assert.equal(shouldUseDoughnutForCategories(1), false);
  });
});

describe('report visual spec parity contract (JS)', () => {
  it('uses stacked_bar for revenue and adaptive type for categories', () => {
    const spec = buildReportVisualSpec(fixture, { labelFn: (k) => String(k) });
    const contract = visualSpecContract(spec);

    assert.equal(contract.charts.revenue_collection.type, 'stacked_bar');
    assert.equal(contract.charts.vendor_categories.type, 'doughnut');
    assert.equal(contract.charts.vendor_categories.rows.length, 3);
    assert.deepEqual(
      contract.charts.vendor_categories.rows.map((r) => r.key),
      ['food', 'fashion', 'books'],
    );
    assert.equal(contract.charts.site_utilisation.type, 'doughnut');
    assert.equal(contract.charts.booking_status.type, 'doughnut');
    assert.equal(contract.palette.primary, '#3970E4');
    assert.equal(contract.palette.positive, '#2E9D78');
    assert.equal(contract.includePerformanceAcrossEvents, false);

    // Persist for PHP cross-check
    writeFileSync(
      join(root, 'tests/fixtures/reportVisualParityContract.js.json'),
      `${JSON.stringify(contract, null, 2)}\n`,
      'utf8',
    );
  });

  it('switches category chart type for 6+ and 1-category fixtures', () => {
    const many = {
      sections: {
        vendor_categories: {
          distribution: Array.from({ length: 6 }, (_, i) => ({
            key: `c${i}`,
            label: `Cat ${i}`,
            unique_vendors: i + 1,
          })),
        },
      },
    };
    assert.equal(buildReportVisualSpec(many).charts.vendor_categories.type, 'bar');

    const one = {
      sections: {
        vendor_categories: {
          distribution: [{ key: 'only', label: 'Only', unique_vendors: 4 }],
        },
      },
    };
    assert.equal(buildReportVisualSpec(one).charts.vendor_categories.type, 'compact');
  });
});
