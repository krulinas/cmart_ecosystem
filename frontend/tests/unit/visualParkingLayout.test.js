import { strict as assert } from 'node:assert';
import { describe, it } from 'node:test';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  adaptOrganizerRows,
  adaptPublicRows,
  adaptVendorRows,
  buildRenderSegments,
  canOrganizerChangeSiteStatus,
  countSitesByStatus,
  isStandardParkingLayout,
  legendItemsForMode,
  organizerVisualStatus,
  previewStandardParkingLabels,
  shouldInsertAisleBetween,
  statusLabel,
  statusTileClass,
  vendorVisualStatus,
} from '../../src/utils/visualParkingLayout.js';
import {
  VISUAL_PARKING_COPY,
  visualParkingCopy,
} from '../../src/utils/visualParkingLayoutCopy.js';

const here = dirname(fileURLToPath(import.meta.url));
const root = join(here, '../..');
const read = (relativePath) => readFileSync(join(root, relativePath), 'utf8');

function buildStandardRows(overrides = {}) {
  const labels = ['A', 'B', 'C', 'D'];
  return labels.map((label, index) => ({
    id: index + 1,
    label,
    display_order: index + 1,
    category: { label: `Category ${label}` },
    sites: Array.from({ length: 16 }, (_, offset) => ({
      id: index * 16 + offset + 1,
      label: `${label}${String(offset + 1).padStart(2, '0')}`,
      position_number: offset + 1,
      grid_column: offset + 1,
      operational_status: 'active',
      occupancy: 'available',
      locks: {},
      ...overrides.site,
    })),
    ...overrides.row,
  }));
}

describe('visualParkingLayout geometry', () => {
  it('recognizes the standard 4×16 parking layout', () => {
    const rows = buildStandardRows();
    assert.equal(isStandardParkingLayout(rows), true);
    assert.equal(previewStandardParkingLabels().length, 64);
    assert.equal(previewStandardParkingLabels()[0], 'A01');
    assert.equal(previewStandardParkingLabels()[63], 'D16');
  });

  it('inserts the vehicle aisle between rows B and C', () => {
    const rows = adaptOrganizerRows(buildStandardRows());
    const segments = buildRenderSegments(rows);
    assert.equal(shouldInsertAisleBetween({ label: 'B' }, { label: 'C' }), true);
    assert.equal(segments.filter((segment) => segment.type === 'aisle').length, 1);
    const aisleIndex = segments.findIndex((segment) => segment.type === 'aisle');
    assert.equal(segments[aisleIndex - 1].row.label, 'B');
    assert.equal(segments[aisleIndex + 1].row.label, 'C');
    assert.equal(rows.every((row) => row.sites.length === 16), true);
  });
});

describe('visualParkingLayout status mapping', () => {
  it('maps organizer occupancy and operational states', () => {
    assert.equal(organizerVisualStatus({ operational_status: 'active', occupancy: 'available' }), 'available');
    assert.equal(organizerVisualStatus({ operational_status: 'active', occupancy: 'reserved' }), 'reserved');
    assert.equal(organizerVisualStatus({ operational_status: 'active', occupancy: 'confirmed' }), 'confirmed');
    assert.equal(organizerVisualStatus({ operational_status: 'unavailable', occupancy: 'available' }), 'unavailable');
    assert.equal(organizerVisualStatus({ operational_status: 'disabled', occupancy: 'available' }), 'disabled');
  });

  it('maps vendor selection and availability states', () => {
    assert.equal(vendorVisualStatus({ id: 1, availability_status: 'available' }, [1]), 'selected');
    assert.equal(vendorVisualStatus({ id: 2, availability_status: 'available' }, []), 'available');
    assert.equal(
      vendorVisualStatus({ id: 3, availability_status: 'occupied', occupancy_status: 'reserved' }, []),
      'reserved',
    );
    assert.equal(
      vendorVisualStatus({ id: 4, availability_status: 'occupied', occupancy_status: 'confirmed' }, []),
      'confirmed',
    );
  });

  it('respects organizer disable locks for booked sites', () => {
    const booked = { locks: { disable_locked: true }, occupancy: 'confirmed' };
    assert.equal(canOrganizerChangeSiteStatus(booked, 'disabled'), false);
    assert.equal(canOrganizerChangeSiteStatus(booked, 'unavailable'), false);
    assert.equal(canOrganizerChangeSiteStatus(booked, 'active'), true);
  });

  it('applies semantic tile classes', () => {
    assert.equal(statusTileClass('available'), 'vpl-tile--available');
    assert.equal(statusTileClass('selected'), 'vpl-tile--selected');
    assert.equal(statusTileClass('reserved'), 'vpl-tile--reserved');
    assert.equal(statusTileClass('confirmed'), 'vpl-tile--confirmed');
    assert.equal(statusTileClass('unavailable'), 'vpl-tile--unavailable');
    assert.equal(statusTileClass('disabled'), 'vpl-tile--disabled');
  });
});

describe('visualParkingLayout adapters and language scope', () => {
  it('keeps organizer and public English while vendor uses Malay', () => {
    assert.equal(visualParkingCopy('organizer').available, 'Available');
    assert.equal(visualParkingCopy('organizer').aisle, 'Vehicle Aisle');
    assert.equal(visualParkingCopy('organizer').exit, 'Exit');
    assert.equal(visualParkingCopy('organizer').entrance, 'Entrance');
    assert.equal(visualParkingCopy('vendor').available, 'Tersedia');
    assert.equal(visualParkingCopy('vendor').selected, 'Dipilih');
    assert.equal(visualParkingCopy('vendor').reserved, 'Ditempah');
    assert.equal(visualParkingCopy('vendor').unavailable, 'Tidak Tersedia');
    assert.equal(visualParkingCopy('vendor').disabled, 'Dinyahaktifkan');
    assert.equal(visualParkingCopy('vendor').aisle, 'Laluan Kenderaan');
    assert.equal(visualParkingCopy('vendor').exit, 'Keluar');
    assert.equal(visualParkingCopy('vendor').entrance, 'Masuk');
    assert.equal(visualParkingCopy('public').title, 'Event Layout Map');
    assert.equal(statusLabel('organizer', 'available'), 'Available');
    assert.equal(statusLabel('vendor', 'available'), 'Tersedia');
    assert.doesNotMatch(JSON.stringify(VISUAL_PARKING_COPY.organizer), /Tersedia|Ditempah|Laluan Kenderaan/);
    assert.doesNotMatch(JSON.stringify(VISUAL_PARKING_COPY.public), /Tersedia|Ditempah|Laluan Kenderaan/);
  });

  it('adapts organizer, vendor, and public payloads without leaking authority', () => {
    const organizer = adaptOrganizerRows(buildStandardRows({
      site: { occupancy: 'reserved', locks: { disable_locked: true } },
    }), { focusedSiteId: 1 });
    assert.equal(organizer[0].sites[0].status, 'reserved');
    assert.equal(organizer[0].sites[0].focused, true);
    assert.equal(organizer[0].sites[0].locks.disable_locked, true);

    const vendor = adaptVendorRows([{
      id: 10,
      label: 'A',
      display_order: 1,
      category: { label: 'Food' },
      sites: [
        {
          id: 1,
          label: 'A01',
          position_number: 1,
          availability_status: 'available',
          is_selectable: true,
          price: '30.00',
          space_name: 'Standard',
        },
        {
          id: 2,
          label: 'A02',
          position_number: 2,
          availability_status: 'unavailable',
          is_selectable: false,
          price: '30.00',
        },
      ],
    }], [1]);
    assert.equal(vendor[0].sites[0].status, 'selected');
    assert.equal(vendor[0].sites[1].status, 'unavailable');
    assert.equal(vendor[0].sites[1].interactive, false);

    const publicRows = adaptPublicRows([{
      id: 1,
      label: 'A',
      display_order: 1,
      category: { label: 'Food' },
      sites: [{ id: 1, label: 'A01', position_number: 1, space: { name: 'Standard' } }],
    }]);
    assert.equal(publicRows[0].sites[0].status, 'public');
    assert.equal(publicRows[0].sites[0].interactive, false);
    assert.equal(publicRows[0].sites[0].price, null);

    const counts = countSitesByStatus(organizer);
    assert.equal(counts.total, 64);
    assert.equal(counts.reserved, 64);
    assert.equal(legendItemsForMode('organizer').some((item) => item.key === 'selected'), false);
  });
});

describe('VisualParkingLayout.vue contract', () => {
  it('renders orientation, aisle, rectangular tiles, and explicit modes', () => {
    const source = read('src/components/layout/VisualParkingLayout.vue');
    assert.match(source, /mode="organizer"|data-mode|validator/);
    assert.match(source, /visual-parking-exit/);
    assert.match(source, /visual-parking-entrance/);
    assert.match(source, /visual-parking-aisle/);
    assert.match(source, /vpl__tile/);
    assert.match(source, /\.vpl__tile \{[^}]*border-radius: 0\.5rem/);
    assert.doesNotMatch(source, /\.vpl__tile \{[^}]*border-radius:\s*9999px/);
    assert.match(source, /organizer.*vendor.*public|validator/);
  });
});
