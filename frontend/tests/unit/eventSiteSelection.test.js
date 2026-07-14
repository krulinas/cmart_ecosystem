import { strict as assert } from 'node:assert';
import { describe, it } from 'node:test';
import {
  arePositionsContiguous,
  canSelectSite,
  computePreviewAmount,
  formatOperationalDaysSummary,
  getSelectedSites,
  groupSitesByRow,
  pruneInvalidSelections,
  selectionValidationMessage,
  toggleSiteSelection,
} from '../../src/utils/eventSiteSelection.js';

const sampleSites = [
  {
    id: 1,
    label: 'A01',
    row_label: 'A',
    position_number: 1,
    space_id: 10,
    space_name: 'Standard',
    price: '30.00',
    availability_status: 'available',
    is_selectable: true,
  },
  {
    id: 2,
    label: 'A02',
    row_label: 'A',
    position_number: 2,
    space_id: 10,
    space_name: 'Standard',
    price: '30.00',
    availability_status: 'available',
    is_selectable: true,
  },
  {
    id: 3,
    label: 'A03',
    row_label: 'A',
    position_number: 3,
    space_id: 10,
    space_name: 'Standard',
    price: '30.00',
    availability_status: 'occupied',
    is_selectable: false,
  },
  {
    id: 4,
    label: 'B01',
    row_label: 'B',
    position_number: 1,
    space_id: 20,
    space_name: 'Large',
    price: '50.00',
    availability_status: 'available',
    is_selectable: true,
  },
];

describe('eventSiteSelection helpers', () => {
  it('groups sites by row with ordered positions', () => {
    const rows = groupSitesByRow(sampleSites);
    assert.equal(rows.length, 2);
    assert.equal(rows[0].rowLabel, 'A');
    assert.deepEqual(rows[0].sites.map((site) => site.label), ['A01', 'A02', 'A03']);
  });

  it('selects first available site and rejects occupied site', () => {
    const first = toggleSiteSelection(sampleSites[0], [], sampleSites);
    assert.deepEqual(first.selectedIds, [1]);

    const occupied = toggleSiteSelection(sampleSites[2], [], sampleSites);
    assert.deepEqual(occupied.selectedIds, []);
    assert.match(occupied.blockedMessage || '', /adjacent|same row/i);
  });

  it('allows adjacent same-row selection and rejects gaps', () => {
    const first = toggleSiteSelection(sampleSites[0], [], sampleSites);
    const second = toggleSiteSelection(sampleSites[1], first.selectedIds, sampleSites);
    assert.deepEqual(second.selectedIds, [1, 2]);

    const gap = toggleSiteSelection(sampleSites[2], second.selectedIds, sampleSites);
    assert.deepEqual(gap.selectedIds, [1, 2]);
  });

  it('rejects different-row and different-space selections', () => {
    const first = toggleSiteSelection(sampleSites[0], [], sampleSites);
    const otherRow = toggleSiteSelection(sampleSites[3], first.selectedIds, sampleSites);
    assert.deepEqual(otherRow.selectedIds, [1]);
    assert.ok(otherRow.blockedMessage);
  });

  it('blocks middle deselection that would split the range', () => {
    const rowSites = [
      sampleSites[0],
      sampleSites[1],
      {
        ...sampleSites[2],
        id: 5,
        availability_status: 'available',
        is_selectable: true,
      },
    ];

    const first = toggleSiteSelection(rowSites[0], [], rowSites);
    const second = toggleSiteSelection(rowSites[1], first.selectedIds, rowSites);
    const allThree = toggleSiteSelection(rowSites[2], second.selectedIds, rowSites);
    const blocked = toggleSiteSelection(rowSites[1], allThree.selectedIds, rowSites);

    assert.deepEqual(blocked.selectedIds, allThree.selectedIds);
    assert.match(blocked.blockedMessage || '', /edge|clear/i);
  });

  it('allows edge deselection', () => {
    const first = toggleSiteSelection(sampleSites[0], [], sampleSites);
    const both = toggleSiteSelection(sampleSites[1], first.selectedIds, sampleSites);
    const edge = toggleSiteSelection(sampleSites[1], both.selectedIds, sampleSites);
    assert.deepEqual(edge.selectedIds, [1]);
  });

  it('computes preview amount without multiplying by day count', () => {
    const selected = getSelectedSites(sampleSites, [1, 2]);
    assert.equal(computePreviewAmount(selected), 60);
  });

  it('validates contiguous same-row same-space selections', () => {
    const selected = getSelectedSites(sampleSites, [1, 2]);
    assert.equal(selectionValidationMessage(selected), null);

    const mixedRow = getSelectedSites(sampleSites, [1, 4]);
    assert.match(selectionValidationMessage(mixedRow), /same row/i);
  });

  it('prunes invalid selections after conflict refresh', () => {
    const updatedSites = sampleSites.map((site) =>
      site.id === 2
        ? { ...site, availability_status: 'occupied', is_selectable: false }
        : site,
    );
    const pruned = pruneInvalidSelections([1, 2], updatedSites);
    assert.deepEqual(pruned, [1]);
  });

  it('formats operational day summaries', () => {
    const single = formatOperationalDaysSummary([{ operational_date: '2026-08-01' }]);
    assert.match(single, /2026-08-01/);

    const multi = formatOperationalDaysSummary([
      { operational_date: '2026-08-01' },
      { operational_date: '2026-08-02' },
    ]);
    assert.match(multi, /all active event days/i);
  });

  it('reports non-contiguous positions', () => {
    assert.equal(arePositionsContiguous([sampleSites[0], sampleSites[2]]), false);
  });

  it('canSelectSite respects availability', () => {
    assert.equal(canSelectSite(sampleSites[2], []), false);
    assert.equal(canSelectSite(sampleSites[0], []), true);
  });
});
