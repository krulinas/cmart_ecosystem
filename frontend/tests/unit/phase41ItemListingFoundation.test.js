import { strict as assert } from 'node:assert';
import { describe, it } from 'node:test';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const root = join(here, '../..');

const itemForm = readFileSync(
  join(root, 'src/components/VendorItemFormModal.vue'),
  'utf8',
);
const eventPanel = readFileSync(
  join(root, 'src/views/dashboards/staff/StaffEventsPanel.vue'),
  'utf8',
);
const marketplaceCard = readFileSync(
  join(root, 'src/components/public/MarketplaceItemCard.vue'),
  'utf8',
);
const marketplaceDetails = readFileSync(
  join(root, 'src/components/MarketplaceItemDetailsModal.vue'),
  'utf8',
);

describe('Phase 4.1 item listing foundation', () => {
  it('loads canonical categories and submits vendor_category_id', () => {
    assert.match(itemForm, /fetchVendorCategories\(api\)/);
    assert.match(itemForm, /v-model="form\.vendor_category_id"/);
    assert.match(itemForm, /fd\.append\('vendor_category_id', form\.vendor_category_id\)/);
    assert.match(itemForm, /vendor_category_id: Number\(form\.vendor_category_id\)/);
    assert.doesNotMatch(itemForm, /fd\.append\('category', form\.category\)/);
    assert.doesNotMatch(itemForm, /category: form\.category/);
  });

  it('preselects an existing canonical item category', () => {
    assert.match(
      itemForm,
      /item\?\.vendor_category_id != null[\s\S]*String\(item\.vendor_category_id\)/,
    );
  });

  it('keeps server validation visible for canonical category fields', () => {
    assert.match(itemForm, /errors\.vendor_category_id \|\| errors\.category/);
    assert.match(itemForm, /categoryLoadError/);
  });

  it('sends nullable decimal fee configuration through the event form', () => {
    assert.match(eventPanel, /v-model="form\.item_reservation_service_fee"/);
    assert.match(eventPanel, /step="0\.01"/);
    assert.match(
      eventPanel,
      /item_reservation_service_fee: form\.item_reservation_service_fee === ''[\s\S]*\? null/,
    );
    assert.match(
      eventPanel,
      /fd\.append\('item_reservation_service_fee', form\.item_reservation_service_fee\)/,
    );
  });

  it('does not introduce a reservation call to action', () => {
    assert.doesNotMatch(marketplaceCard, />\s*Reserve(?:\s+now)?\s*</i);
    assert.doesNotMatch(marketplaceDetails, />\s*Reserve(?:\s+now)?\s*</i);
    assert.doesNotMatch(itemForm, /\/reservations/);
    assert.doesNotMatch(eventPanel, /\/reservations/);
  });
});
