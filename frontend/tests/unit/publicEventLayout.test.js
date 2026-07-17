import { strict as assert } from 'node:assert';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';
import {
  filterPublicLayoutRows,
  normalizePublicLayout,
  publicLayoutFilterAnnouncement,
} from '../../src/utils/publicEventLayout.js';

const here = dirname(fileURLToPath(import.meta.url));
const root = join(here, '../..');

const payload = {
  layout_available: true,
  historical: false,
  entrance_note: 'Pintu utama',
  event: { id: 10, name: 'Carboot Weekend', status: 'Available' },
  categories: [
    { id: 2, slug: 'food-beverages', label: 'Food & Beverages', display_order: 2 },
    { id: 1, slug: 'pre-loved-thrift', label: 'Pre-loved / Thrift', display_order: 1 },
  ],
  rows: [
    {
      id: 12,
      label: 'Row B',
      display_order: 2,
      category: { id: 2, slug: 'food-beverages', label: 'Food & Beverages' },
      sites: [
        { id: 102, label: 'B02', display_order: 2, position_number: 2, occupancy: 'confirmed' },
        { id: 101, label: 'B01', display_order: 1, position_number: 1, booking_id: 99 },
      ],
      locks: { structure_locked: true },
    },
    {
      id: 11,
      label: 'Row A',
      display_order: 1,
      category: { id: 1, slug: 'pre-loved-thrift', label: 'Pre-loved / Thrift' },
      sites: [{ id: 100, label: 'A01', display_order: 1, position_number: 1 }],
    },
    {
      id: 13,
      label: 'Row C',
      display_order: 3,
      category: { id: 2, slug: 'food-beverages', label: 'Food & Beverages' },
      sites: [{ id: 103, label: 'C01', display_order: 1, position_number: 1 }],
    },
  ],
  booking: { id: 99 },
  override_reason: 'private',
};

describe('Phase 3.10 public event layout helpers', () => {
  it('normalizes deterministic API rows and sites using an allowlist', () => {
    const layout = normalizePublicLayout(payload);

    assert.deepEqual(layout.categories.map((category) => category.label), [
      'Pre-loved / Thrift',
      'Food & Beverages',
    ]);
    assert.deepEqual(layout.rows.map((row) => row.label), ['Row A', 'Row B', 'Row C']);
    assert.deepEqual(layout.rows[1].sites.map((site) => site.label), ['B01', 'B02']);
    assert.equal('booking' in layout, false);
    assert.equal('override_reason' in layout, false);
    assert.equal('locks' in layout.rows[1], false);
    assert.equal('booking_id' in layout.rows[1].sites[0], false);
    assert.equal('occupancy' in layout.rows[1].sites[1], false);
  });

  it('filters all matching rows for an API-provided category', () => {
    const layout = normalizePublicLayout(payload);
    const foodRows = filterPublicLayoutRows(layout.rows, 2);

    assert.deepEqual(foodRows.map((row) => row.label), ['Row B', 'Row C']);
    assert.equal(filterPublicLayoutRows(layout.rows, 'all').length, 3);
    assert.match(publicLayoutFilterAnnouncement('Food & Beverages', 2), /2 baris/);
  });
});

describe('Phase 3.10 public event layout UI wiring', () => {
  const component = readFileSync(
    join(root, 'src/components/public/PublicEventLayoutSection.vue'),
    'utf8',
  );

  it('integrates once into the shared public event detail modal', () => {
    const modal = readFileSync(join(root, 'src/components/EventDetailsModal.vue'), 'utf8');
    assert.match(modal, /PublicEventLayoutSection/);
    assert.match(modal, /:event-id="event\.id"/);

    const api = readFileSync(join(root, 'src/services/publicEventLayoutApi.js'), 'utf8');
    assert.match(api, /\/events\/\$\{eventId\}\/layout/);
  });

  it('contains loading, published, unavailable, empty, category-empty, error and retry states', () => {
    for (const testId of [
      'public-layout-loading',
      'public-layout-map',
      'public-layout-unavailable',
      'public-layout-empty',
      'public-layout-category-empty',
      'public-layout-error',
      'public-layout-retry',
    ]) {
      assert.match(component, new RegExp(testId));
    }

    for (const message of [
      'Memuatkan susun atur acara…',
      'Susun atur acara belum diterbitkan.',
      'Tiada susun atur awam tersedia buat masa ini.',
      'Tiada baris tersedia untuk kategori ini.',
      'Susun atur acara tidak dapat dimuatkan.',
      'Cuba Lagi',
    ]) {
      assert.match(component, new RegExp(message.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
    }
  });

  it('uses API categories, selected semantics, live announcements and non-interactive site markers', () => {
    assert.match(component, /layout\.value\?\.categories/);
    assert.match(component, /Semua Kategori/);
    assert.match(component, /:aria-pressed=/);
    assert.match(component, /aria-live="polite"/);
    assert.match(component, /focus-visible:ring-2/);
    assert.match(component, /<li[\s\S]*data-testid="public-layout-site"/);
    assert.doesNotMatch(component, /data-testid="public-layout-site"[\s\S]{0,120}@click/);
  });

  it('does not render booking authority, occupancy state or Organizer controls', () => {
    assert.doesNotMatch(component, /Book now|Tempah Sekarang|RM \{\{|occupancy_status|allocation_status|override_reason|active_lock/);
    assert.doesNotMatch(component, /event-site-clear-selection|organizer-|site-assignment/);
  });

  it('provides responsive category wrapping and practical mobile site grids', () => {
    assert.match(component, /flex flex-wrap gap-2/);
    assert.match(component, /grid-cols-2/);
    assert.match(component, /min-\[420px\]:grid-cols-3/);
    assert.match(component, /sm:grid-cols-4/);
    assert.match(component, /min-h-11/);
    assert.match(component, /min-w-0/);
  });
});
