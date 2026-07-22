import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
  computePreviewAmount,
  resolveEventUnitPrice,
} from '../../src/utils/eventSiteSelection.js';

describe('event-level site pricing preview', () => {
  const sites = [
    { id: 1, label: 'A09', price: '20.00' },
    { id: 2, label: 'A10', price: '20.00' },
    { id: 3, label: 'A11', price: '20.00' },
  ];

  it('uses explicit event unit price for one site', () => {
    assert.equal(computePreviewAmount([sites[0]], 20).toFixed(2), '20.00');
  });

  it('uses explicit event unit price for two sites', () => {
    assert.equal(computePreviewAmount([sites[0], sites[1]], '20.00').toFixed(2), '40.00');
  });

  it('uses explicit event unit price for three sites', () => {
    assert.equal(computePreviewAmount(sites, 20).toFixed(2), '60.00');
  });

  it('does not multiply by event-day count when only site selection changes', () => {
    const twoSites = [sites[0], sites[1]];
    assert.equal(computePreviewAmount(twoSites, 20).toFixed(2), '40.00');
    assert.equal(computePreviewAmount(twoSites, 20).toFixed(2), '40.00');
  });

  it('prefers event site price over mismatched site catalogue price', () => {
    const mismatched = [{ id: 9, label: 'B01', price: '99.00' }];
    assert.equal(resolveEventUnitPrice(mismatched, '20.00'), 20);
    assert.equal(computePreviewAmount(mismatched, '20.00').toFixed(2), '20.00');
  });

  it('formats calculation copy for two sites', () => {
    const unit = resolveEventUnitPrice(sites, '20.00');
    const count = 2;
    const calculation = `RM ${Number(unit).toFixed(2)} × ${count} site${count === 1 ? '' : 's'}`;
    assert.equal(calculation, 'RM 20.00 × 2 sites');
  });
});
