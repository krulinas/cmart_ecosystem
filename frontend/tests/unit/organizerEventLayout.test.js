import { strict as assert } from 'node:assert';
import { describe, it } from 'node:test';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import {
  buildRowReorderPayload,
  buildSiteReorderPayload,
  countActiveSites,
  previewGeneratedLabels,
  siteStateKey,
  sortRowsByDisplayOrder,
} from '../../src/utils/organizerEventLayoutHelpers.js';
import {
  LAYOUT_COPY,
  LAYOUT_ERROR_MESSAGES,
  READINESS_BLOCKER_MESSAGES,
  layoutErrorMessage,
  readinessMessage,
} from '../../src/utils/organizerEventLayoutMessages.js';

const here = dirname(fileURLToPath(import.meta.url));
const root = join(here, '../..');

describe('organizerEventLayoutHelpers', () => {
  it('previews padded labels matching backend generation', () => {
    assert.deepEqual(
      previewGeneratedLabels({
        labelPrefix: 'a',
        count: 5,
        startNumber: 1,
        numberPadding: 2,
      }),
      ['A01', 'A02', 'A03', 'A04', 'A05'],
    );
  });

  it('rejects empty prefix and caps count at 100', () => {
    assert.deepEqual(previewGeneratedLabels({ labelPrefix: '', count: 3 }), []);
    assert.equal(
      previewGeneratedLabels({ labelPrefix: 'B', count: 150 }).length,
      100,
    );
  });

  it('builds transactional row reorder payload', () => {
    const rows = [
      { id: 3, display_order: 1 },
      { id: 1, display_order: 2 },
      { id: 2, display_order: 3 },
    ];
    const payload = buildRowReorderPayload(rows, 0, 2);
    assert.deepEqual(payload.rows.map((row) => row.id), [1, 2, 3]);
    assert.deepEqual(payload.rows.map((row) => row.display_order), [1, 2, 3]);
  });

  it('builds site reorder payload and counts active sites', () => {
    const sites = [
      { id: 10, display_order: 1 },
      { id: 11, display_order: 2 },
    ];
    const payload = buildSiteReorderPayload(sites, 1, 0);
    assert.deepEqual(payload.sites.map((site) => site.id), [11, 10]);

    const rows = [
      {
        sites: [
          { operational_status: 'active' },
          { operational_status: 'disabled' },
          { operational_status: 'active' },
        ],
      },
    ];
    assert.equal(countActiveSites(rows), 2);
    assert.equal(siteStateKey({ operational_status: 'active', occupancy: 'reserved' }), 'reserved');
    assert.equal(
      siteStateKey({ operational_status: 'active', occupancy: 'available', locks: { structure_locked: true } }),
      'structurally_locked',
    );
  });

  it('sorts rows by display_order then id', () => {
    const sorted = sortRowsByDisplayOrder([
      { id: 2, display_order: 1 },
      { id: 1, display_order: 1 },
      { id: 3, display_order: 0 },
    ]);
    assert.deepEqual(sorted.map((row) => row.id), [3, 1, 2]);
  });
});

describe('organizerEventLayoutMessages', () => {
  it('maps readiness blockers and conflict codes to English operational copy', () => {
    assert.equal(readinessMessage('NO_ACTIVE_LAYOUT_ROWS'), READINESS_BLOCKER_MESSAGES.NO_ACTIVE_LAYOUT_ROWS);
    assert.equal(
      layoutErrorMessage({ response: { data: { error: 'ROW_NOT_EMPTY' } } }),
      LAYOUT_ERROR_MESSAGES.ROW_NOT_EMPTY,
    );
    assert.equal(
      layoutErrorMessage({ response: { data: { error: 'SITE_HAS_ALLOCATION_HISTORY' } } }),
      LAYOUT_ERROR_MESSAGES.SITE_HAS_ALLOCATION_HISTORY,
    );
    assert.equal(LAYOUT_COPY.pageTitle, 'Site Layout');
    assert.equal(LAYOUT_COPY.navLabel, 'Layout Management');
    assert.equal(LAYOUT_COPY.operationalNotReady, 'Not Ready for Booking');
    assert.equal(LAYOUT_COPY.publicationTitle, 'Public Map Publication');
    assert.match(LAYOUT_COPY.pageTitle, /Site Layout/);
    assert.doesNotMatch(LAYOUT_COPY.navLabel, /Urus|Susun|Tapak|Tempahan/);
  });

  it('falls back when error code is unknown', () => {
    assert.equal(
      layoutErrorMessage({ response: { data: {} } }),
      LAYOUT_COPY.fallbackError,
    );
  });
});

describe('organizer layout UI wiring', () => {
  it('exposes Layout Management nav for carboot operations only', () => {
    const nav = readFileSync(join(root, 'src/config/workspaceNav.js'), 'utf8');
    assert.match(nav, /hash: 'layout'/);
    assert.match(nav, /Layout Management/);
    assert.doesNotMatch(nav, /Urus Susun Atur/);
    assert.match(nav, /CARBOOT_OPERATIONS/);

    const theme = readFileSync(join(root, 'src/config/managementWorkspaceTheme.js'), 'utf8');
    assert.match(theme, /'layout'/);

    const dashboard = readFileSync(join(root, 'src/views/dashboards/AdminDashboard.vue'), 'utf8');
    assert.match(dashboard, /OrganizerEventLayoutPanel/);
    assert.match(dashboard, /activeSection === 'layout'/);
    assert.match(dashboard, /Layout Management/);

    const eventsPanel = readFileSync(join(root, 'src/views/dashboards/staff/StaffEventsPanel.vue'), 'utf8');
    assert.match(eventsPanel, /Layout Management/);
    assert.match(eventsPanel, /manage-layout-button/);
  });

  it('panel and components include required English operational labels and lock copy', () => {
    const panel = readFileSync(
      join(root, 'src/views/dashboards/organizer/OrganizerEventLayoutPanel.vue'),
      'utf8',
    );
    assert.match(panel, /data-testid="organizer-event-layout-panel"/);
    assert.match(panel, /layout-add-row-button/);
    assert.match(panel, /layout-empty-state/);
    assert.match(panel, /layout-error-state/);
    assert.match(panel, /copy\.publicationTitle/);
    assert.doesNotMatch(panel, /Penerbitan Peta Awam|Terbitkan Peta Awam|Tersedia|Ditempah/);

    const readiness = readFileSync(
      join(root, 'src/components/organizer/layout/EventLayoutReadinessPanel.vue'),
      'utf8',
    );
    assert.match(readiness, /operational-readiness-badge/);
    assert.match(readiness, /readiness-blocker-list/);
    assert.match(readiness, /copy\.setupNoticeTitle|copy\.availabilityStatus/);
    assert.match(panel, /VisualParkingLayout|copy\.generateStandardLayout/);
    assert.doesNotMatch(readiness, /Status Kesediaan|Operasi tempahan/);

    const rowCard = readFileSync(
      join(root, 'src/components/organizer/layout/EventLayoutRowCard.vue'),
      'utf8',
    );
    assert.match(rowCard, /layout-site-grid/);
    assert.match(rowCard, /renameLockedHint/);
    assert.doesNotMatch(rowCard, /Grid Tapak|Susun Semula Tapak|Tiada tapak/);

    const generateModal = readFileSync(
      join(root, 'src/components/organizer/layout/LayoutSiteGenerationModal.vue'),
      'utf8',
    );
    assert.match(generateModal, /layout-generate-preview/);
    assert.match(generateModal, /generateAtomicHint/);
  });

  it('API module targets Phase 3.5 organizer layout endpoints', () => {
    const api = readFileSync(join(root, 'src/services/organizerEventLayoutApi.js'), 'utf8');
    assert.match(api, /\/organizer\/vendor-categories/);
    assert.match(api, /\/organizer\/events\/\$\{eventId\}\/layout/);
    assert.match(api, /layout\/rows\/\$\{rowId\}\/sites\/generate/);
    assert.match(api, /layout\/standard-template/);
    assert.match(api, /generateStandardParkingLayout/);
    assert.match(api, /layout\/sites\/\$\{siteId\}/);
    assert.match(api, /\/spaces/);
  });
});
