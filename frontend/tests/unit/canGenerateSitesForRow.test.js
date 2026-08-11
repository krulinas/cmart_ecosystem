import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { canGenerateSitesForRow } from '../../src/utils/organizerEventLayoutHelpers.js';

describe('canGenerateSitesForRow', () => {
  it('allows generation when a row has fewer than 16 sites', () => {
    assert.equal(canGenerateSitesForRow({ sites: Array.from({ length: 15 }, (_, i) => ({ id: i + 1 })) }), true);
    assert.equal(canGenerateSitesForRow({ sites: [] }), true);
    assert.equal(canGenerateSitesForRow({}), true);
  });

  it('blocks generation when a row already has a complete 16-site set', () => {
    assert.equal(canGenerateSitesForRow({ sites: Array.from({ length: 16 }, (_, i) => ({ id: i + 1 })) }), false);
    assert.equal(canGenerateSitesForRow({ sites: Array.from({ length: 20 }, (_, i) => ({ id: i + 1 })) }), false);
  });
});
