import assert from 'node:assert/strict';
import { afterEach, describe, it } from 'node:test';
import {
  buildPostEventPdfFilename,
  downloadAuthorizedPdf,
  looksLikePdfBytes,
  parseContentDispositionFilename,
} from '../../src/utils/reportPdfDownload.js';
import { buildReportVisualSpec } from '../../src/utils/reportVisualSpec.js';
import { ANALYTICS_PALETTE } from '../../src/utils/analyticsChartPalette.js';

describe('report PDF download helpers', () => {
  const originalFetch = globalThis.fetch;
  const originalDocument = globalThis.document;
  const originalUrl = globalThis.URL;
  const originalRaf = globalThis.requestAnimationFrame;
  const originalLocalStorage = globalThis.localStorage;

  afterEach(() => {
    globalThis.fetch = originalFetch;
    globalThis.document = originalDocument;
    globalThis.URL = originalUrl;
    globalThis.requestAnimationFrame = originalRaf;
    globalThis.localStorage = originalLocalStorage;
  });

  it('parses Content-Disposition filenames', () => {
    assert.equal(
      parseContentDispositionFilename('attachment; filename="organizer-post-event-report-demo-v1.pdf"'),
      'organizer-post-event-report-demo-v1.pdf',
    );
    assert.equal(
      parseContentDispositionFilename("attachment; filename*=UTF-8''organizer-post-event-report-caf%C3%A9-v2.pdf"),
      'organizer-post-event-report-café-v2.pdf',
    );
  });

  it('builds readable fallback filenames', () => {
    assert.equal(
      buildPostEventPdfFilename({ audience: 'organizer', eventSlug: 'Spring Market!', version: 3 }),
      'organizer-post-event-report-spring-market-v3.pdf',
    );
  });

  it('detects PDF magic bytes and rejects non-PDF', () => {
    assert.equal(looksLikePdfBytes(new Uint8Array([0x25, 0x50, 0x44, 0x46, 0x2d])), true);
    assert.equal(looksLikePdfBytes(new Uint8Array([0x7b, 0x22, 0x6d, 0x73])), false);
  });

  it('triggers an anchor download and does not revoke before click', async () => {
    const pdfBytes = new Uint8Array([0x25, 0x50, 0x44, 0x46, 0x2d, 0x31, 0x2e, 0x34]);
    let revokeCount = 0;
    let clicked = false;
    let revokedBeforeClick = false;
    const clicks = [];

    globalThis.localStorage = { getItem: () => 'test-token' };
    globalThis.URL = {
      createObjectURL: () => 'blob:mock-pdf',
      revokeObjectURL: () => {
        revokeCount += 1;
        if (!clicked) revokedBeforeClick = true;
      },
    };

    const anchor = {
      href: '',
      download: '',
      rel: '',
      style: {},
      click() {
        clicked = true;
        clicks.push({ href: this.href, download: this.download });
      },
      remove() {},
    };

    globalThis.document = {
      createElement: () => anchor,
      body: { appendChild() {} },
    };
    globalThis.requestAnimationFrame = (cb) => {
      setTimeout(cb, 0);
      return 1;
    };

    globalThis.fetch = async () => ({
      ok: true,
      headers: {
        get: (name) => {
          if (name === 'content-type') return 'application/pdf';
          if (name === 'content-disposition') {
            return 'attachment; filename="organizer-post-event-report-demo-v1.pdf"';
          }
          return null;
        },
      },
      arrayBuffer: async () => pdfBytes.buffer,
    });

    const result = await downloadAuthorizedPdf('https://example.test/pdf', {
      fallbackFilename: 'fallback.pdf',
    });

    assert.equal(clicked, true);
    assert.equal(clicks[0].download, 'organizer-post-event-report-demo-v1.pdf');
    assert.equal(revokedBeforeClick, false);
    assert.equal(result.filename, 'organizer-post-event-report-demo-v1.pdf');

    await new Promise((resolve) => setTimeout(resolve, 900));
    assert.ok(revokeCount >= 1);
  });

  it('refuses to save JSON error bodies as PDF', async () => {
    globalThis.localStorage = { getItem: () => null };
    globalThis.fetch = async () => ({
      ok: false,
      status: 500,
      headers: {
        get: (name) => (name === 'content-type' ? 'application/json' : null),
      },
      json: async () => ({ message: 'Unable to generate the Post-Event PDF.' }),
      text: async () => '{"message":"Unable to generate the Post-Event PDF."}',
    });

    await assert.rejects(
      () => downloadAuthorizedPdf('https://example.test/pdf'),
      /Unable to generate the Post-Event PDF/,
    );
  });
});

describe('report visual spec from snapshot', () => {
  it('maps Analytics Hub compositions without inventing zeros or live benchmarks', () => {
    const spec = buildReportVisualSpec({
      sections: {
        event_performance: {
          sites_sold: 4,
          available_sites: 6,
          site_utilisation_percent: 40,
        },
        booking_pipeline: {
          by_approval_status: { Approved: 3, Pending_Organizer: 1 },
        },
        payments: {
          collected_revenue: 100,
          outstanding_invoice_balance: 20,
          invoice_count: 2,
        },
        vendor_categories: {
          distribution: [
            { label: 'Food', unique_vendors: 2 },
            { label: 'Fashion', unique_vendors: null },
          ],
        },
        feedback: {
          response_count: 2,
          rating_distribution: { 5: 2, 4: 0, 3: 0, 2: 0, 1: 0 },
        },
      },
    });

    assert.equal(spec.includePerformanceAcrossEvents, false);
    assert.match(spec.performanceAcrossEventsNote, /omitted/i);
    assert.equal(spec.charts.site_utilisation.rows.length, 2);
    assert.equal(spec.charts.booking_status.rows.find((r) => r.key === 'Approved').count, 3);
    assert.equal(spec.charts.revenue_collection.rows[0].color, '#2E9D78');
    assert.equal(spec.charts.revenue_collection.type, 'stacked_bar');
    assert.equal(spec.charts.vendor_categories.rows.length, 1);
    assert.equal(spec.charts.feedback_ratings.rows.length, 1);
    assert.equal(spec.palette.primary, ANALYTICS_PALETTE.primary);
    assert.equal(ANALYTICS_PALETTE.primary, '#3970E4');
  });
});
