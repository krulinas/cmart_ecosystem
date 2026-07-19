import { strict as assert } from 'node:assert';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import {
  prepareAvailabilityRows,
  pruneInvalidSelections,
  selectionValidationMessage,
} from '../../src/utils/eventSiteSelection.js';

const registration = readFileSync(
  new URL('../../src/views/auth/Registration.vue', import.meta.url),
  'utf8',
);
const selector = readFileSync(
  new URL('../../src/components/vendor/EventSiteSelector.vue', import.meta.url),
  'utf8',
);
const categorySelector = readFileSync(
  new URL('../../src/components/vendor/VendorBookingCategorySelector.vue', import.meta.url),
  'utf8',
);

const row = {
  id: 22,
  label: 'Row B',
  description: 'Food vendors',
  display_order: 2,
  category: { id: 2, label: 'Food & Beverages' },
  sites: [
    {
      id: 101,
      event_layout_row_id: 22,
      label: 'B01',
      position_number: 1,
      space_id: 5,
      space_name: 'Standard',
      price: '30.00',
      availability_status: 'available',
      is_selectable: true,
    },
    {
      id: 102,
      event_layout_row_id: 22,
      label: 'B02',
      position_number: 2,
      space_id: 5,
      space_name: 'Standard',
      price: '30.00',
      availability_status: 'available',
      is_selectable: true,
    },
  ],
};

describe('Phase 3.9 vendor category-first site selection', () => {
  it('renders API-backed category selection and visible profile suggestion', () => {
    assert.match(registration, /fetchVendorCategories\(api\)/);
    assert.match(registration, /VendorBookingCategorySelector/);
    assert.doesNotMatch(registration, /CATEGORY_OPTIONS/);
    assert.match(categorySelector, /Pilih Kategori Jualan/);
    assert.match(categorySelector, /Cadangan daripada profil anda/);
    assert.match(categorySelector, /role="radiogroup"/);
    assert.match(categorySelector, /role="radio"/);
  });

  it('requests availability with the canonical category and retains real rows', () => {
    assert.match(registration, /vendor_category_id: bookingForm\.vendor_category_id/);
    assert.match(registration, /availabilityRows\.value = Array\.isArray\(data\.rows\)/);
    assert.match(registration, /:rows="availabilityRows"/);
    assert.match(selector, /prepareAvailabilityRows\(props\.rows\)/);
    assert.doesNotMatch(selector, /groupSitesByRow\(props\.sites\)/);
  });

  it('builds row cards and selection summary from backend row IDs', () => {
    const rows = prepareAvailabilityRows([row]);
    assert.equal(rows[0].rowId, 22);
    assert.equal(rows[0].category.label, 'Food & Beverages');
    assert.equal(rows[0].availableSiteCount, 2);

    for (const text of [
      'Kategori Jualan',
      'Baris',
      'Tapak Dipilih',
      'Bilangan Tapak',
      'Hari Acara',
      'Harga Setiap Tapak',
      'Jumlah:',
    ]) {
      assert.match(selector, new RegExp(text));
    }
  });

  it('preserves backend-aligned same-row, contiguous, and same-space rules', () => {
    assert.equal(selectionValidationMessage(row.sites), null);
    assert.match(
      selectionValidationMessage([
        row.sites[0],
        { ...row.sites[1], event_layout_row_id: 23 },
      ]),
      /baris yang sama/i,
    );
    assert.match(
      selectionValidationMessage([
        row.sites[0],
        { ...row.sites[1], space_id: 6 },
      ]),
      /jenis ruang yang sama/i,
    );
    assert.match(
      selectionValidationMessage([
        row.sites[0],
        { ...row.sites[1], position_number: 3 },
      ]),
      /bersebelahan/i,
    );
  });

  it('prunes only stale selections and never auto-resubmits', () => {
    const refreshed = row.sites.map((site) =>
      site.id === 102
        ? { ...site, availability_status: 'occupied', occupancy_status: 'reserved', is_selectable: false }
        : site,
    );
    assert.deepEqual(pruneInvalidSelections([101, 102], refreshed), [101]);
    assert.match(registration, /removedStaleSiteLabels/);
    assert.match(registration, /Pilihan tapak telah dikemas kini/);
    assert.doesNotMatch(registration, /refreshAvailabilityAfterConflict[\s\S]*submitBooking\(\)/);
  });

  it('submits only server-authoritative booking inputs', () => {
    const payload = registration.match(/api\.post\('\/bookings', \{([\s\S]*?)\}\)/)?.[1] || '';
    assert.match(payload, /event_id/);
    assert.match(payload, /event_site_ids/);
    assert.match(payload, /vendor_category_id/);
    assert.match(payload, /product_details/);
    assert.doesNotMatch(payload, /\bquantity\b|\bamount\b|\btotal\b|\bspace_id\b/);
  });

  it('includes keyboard, ARIA, live status, and responsive grid affordances', () => {
    const visual = readFileSync(
      new URL('../../src/components/layout/VisualParkingLayout.vue', import.meta.url),
      'utf8',
    );
    assert.match(selector, /VisualParkingLayout/);
    assert.match(selector, /mode="vendor"/);
    assert.match(visual, /aria-pressed/);
    assert.match(visual, /aria-disabled/);
    assert.match(visual, /focus-visible|box-shadow: 0 0 0 3px/);
    assert.match(visual, /minmax\(3\.25rem, 1fr\)/);
    assert.match(registration, /aria-live="polite"/);
  });
});
