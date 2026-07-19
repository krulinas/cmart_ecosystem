import { strict as assert } from 'node:assert';
import { describe, it } from 'node:test';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  LAYOUT_COPY,
  OCCUPANCY_LABELS,
  SITE_STATUS_LABELS,
  readinessMessage,
} from '../../src/utils/organizerEventLayoutMessages.js';
import { reassignmentErrorMessage } from '../../src/services/organizerSiteReassignmentMessages.js';
import {
  selectionValidationMessage,
  formatOperationalDaysSummary,
} from '../../src/utils/eventSiteSelection.js';

const here = dirname(fileURLToPath(import.meta.url));
const root = join(here, '../..');
const read = (relativePath) => readFileSync(join(root, relativePath), 'utf8');

const MALAY_MARKERS = /Urus Susun Atur|Penerbitan Peta Awam|Status Kesediaan|Belum Bersedia|Tambah Baris|Tersedia|Ditempah|Dinyahaktifkan|Petunjuk masuk awam|Terbitkan Peta Awam|Pilih acara untuk mengurus/;

describe('language scope — Organizer English', () => {
  it('keeps Layout Management messages in English', () => {
    assert.equal(LAYOUT_COPY.navLabel, 'Layout Management');
    assert.equal(LAYOUT_COPY.addRow, 'Add Row');
    assert.equal(LAYOUT_COPY.notPublished, 'Not Published');
    assert.equal(LAYOUT_COPY.publishPublicMap, 'Publish Public Map');
    assert.equal(readinessMessage('NO_ACTIVE_EVENT_DAYS'), 'No active event days are configured.');
    assert.equal(OCCUPANCY_LABELS.available, 'Available');
    assert.equal(OCCUPANCY_LABELS.reserved, 'Booked');
    assert.equal(SITE_STATUS_LABELS.disabled, 'Disabled');
    assert.doesNotMatch(JSON.stringify(LAYOUT_COPY), MALAY_MARKERS);
  });

  it('renders Organizer layout panel and readiness without Malay operational copy', () => {
    const panel = read('src/views/dashboards/organizer/OrganizerEventLayoutPanel.vue');
    const readiness = read('src/components/organizer/layout/EventLayoutReadinessPanel.vue');
    const rowCard = read('src/components/organizer/layout/EventLayoutRowCard.vue');
    const siteCard = read('src/components/organizer/layout/EventLayoutSiteCard.vue');
    const rowForm = read('src/components/organizer/layout/LayoutRowFormModal.vue');
    const siteForm = read('src/components/organizer/layout/LayoutSiteFormModal.vue');
    const generate = read('src/components/organizer/layout/LayoutSiteGenerationModal.vue');

    for (const source of [panel, readiness, rowCard, siteCard, rowForm, siteForm, generate]) {
      assert.doesNotMatch(source, MALAY_MARKERS);
      assert.doesNotMatch(source, /Nama baris|Label tapak|Jenis ruang|Tidak tersedia|Dinyahaktif|Paparan awam|Pratonton/);
    }

    assert.match(panel, /copy\.publicationTitle|copy\.publishPublicMap/);
    assert.match(readiness, /copy\.setupNoticeTitle|copy\.availabilityStatus/);
    assert.match(panel, /VisualParkingLayout|Generate Standard Parking Layout|copy\.generateStandardLayout/);
    assert.match(rowCard, /copy\.siteGrid|copy\.reorderSites|copy\.noSitesInRow/);
    assert.match(siteCard, /copy\.delete|copy\.disableSite/);
    assert.match(rowForm, /Row name|Category|Active|Public display/);
    assert.match(siteForm, /Site label|Space type|Unavailable|Disabled/);
    assert.match(generate, /Label prefix|Count|Preview/);
  });

  it('uses English Organizer navigation and section labels', () => {
    const nav = read('src/config/workspaceNav.js');
    const dashboard = read('src/views/dashboards/AdminDashboard.vue');
    assert.match(nav, /label: 'Layout Management'/);
    assert.match(nav, /Manage category rows and physical sites/);
    assert.match(dashboard, /layout: 'Layout Management'/);
    assert.doesNotMatch(nav, /Urus Susun Atur/);
    assert.doesNotMatch(dashboard, /Urus Susun Atur/);
  });

  it('keeps Organizer reservation UI English', () => {
    const panel = read('src/views/dashboards/organizer/OrganizerItemReservationsPanel.vue');
    assert.match(panel, /Event reservation queue/);
    assert.match(panel, /Confirm charge/);
    assert.match(panel, /Manual expiry/);
    assert.doesNotMatch(panel, /Tempahan|Tapak|Susun Atur|Bayaran balik/);
  });

  it('keeps CMart Management / Super Admin workspace labels English', () => {
    const nav = read('src/config/workspaceNav.js');
    assert.match(nav, /Venue News|Bookings|Feedback|Carboot Events/);
    assert.doesNotMatch(nav, /Urus Susun Atur|Tempahan Item|Peta Awam/);
  });

  it('maps Organizer reassignment errors to English', () => {
    assert.equal(
      reassignmentErrorMessage('CATEGORY_OVERRIDE_REQUIRED'),
      'This selection requires a category exception.',
    );
    assert.doesNotMatch(
      reassignmentErrorMessage('EVENT_LAYOUT_NOT_READY'),
      /Susun atur|Tempahan|Tapak/,
    );
  });
});

describe('language scope — vendor Malay preserved', () => {
  it('retains approved Malay vendor booking and site-selection copy', () => {
    const selector = read('src/components/vendor/EventSiteSelector.vue');
    const category = read('src/components/vendor/VendorBookingCategorySelector.vue');

    assert.match(selector, /Pilih Tapak Fizikal/);
    assert.match(selector, /Langkah 2/);
    assert.match(selector, /Tersedia/);
    assert.match(selector, /Dipilih/);
    assert.match(selector, /Ditempah/);
    assert.match(selector, /Dinyahaktifkan/);
    assert.match(category, /Pilih Kategori Jualan/);
    assert.match(category, /Langkah 1/);

    assert.match(selectionValidationMessage([
      { event_layout_row_id: 1, space_id: 1, position_number: 1 },
      { event_layout_row_id: 2, space_id: 1, position_number: 2 },
    ]), /Pilih tapak|baris/i);
    assert.match(
      formatOperationalDaysSummary([{ operational_date: '2026-08-01' }]),
      /Tapak pilihan akan ditempah/,
    );
  });
});

describe('language scope — public non-vendor English', () => {
  it('renders public event layout guidance in English', () => {
    const section = read('src/components/public/PublicEventLayoutSection.vue');
    assert.match(section, /Visitor Guide|Event Layout Map/);
    assert.doesNotMatch(section, /Panduan Pelawat|Peta Susun Atur Acara|Memuatkan susun atur/);
  });
});
