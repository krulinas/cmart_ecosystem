import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';
import {
  shouldUseDoughnutForCategories,
  presentNumericRows,
} from '../../src/utils/chartLifecycle.js';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const shell = readFileSync(join(root, 'src/layouts/WorkspaceShell.vue'), 'utf8');
const panel = readFileSync(
  join(root, 'src/views/dashboards/organizer/OrganizerEventAnalyticsPanel.vue'),
  'utf8',
);
const surveyPanel = readFileSync(
  join(root, 'src/components/analytics/SurveyResultsPanel.vue'),
  'utf8',
);
const trendChart = readFileSync(
  join(root, 'src/components/analytics/AnalyticsTrendChart.vue'),
  'utf8',
);
const doughnut = readFileSync(
  join(root, 'src/components/analytics/AnalyticsDoughnutChart.vue'),
  'utf8',
);

describe('centralized BM/EN language toggle', () => {
  it('keeps one desktop toggle in the sidebar drawer slot', () => {
    assert.match(shell, /data-testid="locale-toggle-desktop-slot"/);
    assert.match(shell, /placement="desktop"/);
    assert.equal((shell.match(/placement="desktop"/g) || []).length, 1);
  });

  it('keeps one mobile toggle in the header and hides it on lg+', () => {
    assert.match(shell, /data-testid="locale-toggle-mobile-slot"/);
    assert.match(shell, /placement="mobile"/);
    assert.match(shell, /class="lg:hidden"[^>]*data-testid="locale-toggle-mobile-slot"|data-testid="locale-toggle-mobile-slot"[\s\S]*?lg:hidden/);
    // Mobile section nav must not also host a LanguageToggle.
    assert.equal(shell.includes('<!-- Mobile section nav'), true);
    const mobileNav = shell.slice(
      shell.indexOf('<!-- Mobile section nav'),
      shell.indexOf('<!-- Sidebar'),
    );
    assert.equal(mobileNav.includes('LanguageToggle'), false);
  });
});

describe('organizer analytics chart mappings', () => {
  it('uses doughnut for 2–5 vendor categories and bar otherwise', () => {
    assert.equal(shouldUseDoughnutForCategories(1), false);
    assert.equal(shouldUseDoughnutForCategories(2), true);
    assert.equal(shouldUseDoughnutForCategories(5), true);
    assert.equal(shouldUseDoughnutForCategories(6), false);
    assert.match(panel, /shouldUseDoughnutForCategories/);
    assert.match(panel, /overview-vendor-category-doughnut/);
    assert.match(panel, /overview-vendor-category-bars/);
  });

  it('never maps multi-select survey questions to pie/doughnut', () => {
    const multiSelectBlocks = [
      'chart-product-categories',
      'chart-event-info',
      'chart-item-conditions',
      'chart-unsold-actions',
      'chart-improvements',
      'chart-supporting-impacts',
    ];
    for (const id of multiSelectBlocks) {
      const idx = surveyPanel.indexOf(`test-id="${id}"`);
      assert.ok(idx >= 0, `missing ${id}`);
      const window = surveyPanel.slice(Math.max(0, idx - 280), idx + 80);
      assert.equal(window.includes('chart-type="lollipop-h"'), true, `${id} must stay lollipop`);
      assert.equal(window.includes('doughnut'), false, `${id} must not be doughnut`);
      assert.equal(window.includes('pie'), false, `${id} must not be pie`);
    }
  });

  it('does not draw a one-point trend chart', () => {
    assert.match(trendChart, /validPoints\.value\.length >= 2/);
    assert.match(trendChart, /Never render a meaningless one-point trend/);
  });

  it('treats missing metric values as absent rather than zero', () => {
    const rows = presentNumericRows([
      { label: 'A', count: 3 },
      { label: 'B', count: null },
      { label: 'C', value: undefined },
      { label: 'D', count: 0 },
    ]);
    assert.deepEqual(
      rows.map((r) => ({ label: r.label, count: r.count })),
      [
        { label: 'A', count: 3 },
        { label: 'D', count: 0 },
      ],
    );
  });

  it('destroys Chart.js instances on unmount for doughnut charts', () => {
    assert.match(doughnut, /onBeforeUnmount\(destroyChart\)/);
    assert.match(doughnut, /destroyChartInstance/);
  });

  it('wires the live Python benchmark panel into overview', () => {
    assert.match(panel, /PerformanceAcrossEventsPanel/);
    assert.match(panel, /overview-site-utilisation-doughnut/);
    assert.match(panel, /overview-revenue-stacked/);
  });
});
