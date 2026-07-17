import { strict as assert } from 'node:assert';
import { describe, it } from 'node:test';
import { categoryConflictMessage } from '../../src/services/vendorCategoriesApi.js';

describe('vendorCategoriesApi messages', () => {
  it('maps CATEGORY_REQUIRED to BM', () => {
    assert.equal(
      categoryConflictMessage('CATEGORY_REQUIRED'),
      'Sila pilih kategori jualan terlebih dahulu.',
    );
  });

  it('maps SITE_CATEGORY_INCOMPATIBLE to BM', () => {
    assert.equal(
      categoryConflictMessage('SITE_CATEGORY_INCOMPATIBLE'),
      'Tapak yang dipilih tidak sepadan dengan kategori jualan anda.',
    );
  });

  it('maps LAYOUT_CHANGED to BM', () => {
    assert.equal(
      categoryConflictMessage('LAYOUT_CHANGED'),
      'Susun atur acara telah berubah. Sila semak dan pilih tapak semula.',
    );
  });

  it('falls back when code unknown', () => {
    assert.equal(categoryConflictMessage('OTHER', 'Fallback'), 'Fallback');
  });
});
