import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import { createDriver } from '../helpers/driver.js';
import {
  assertGuestSessionClean,
  assertNotRedirectedToLogin,
  assertNoProtectedDashboardVisible,
  assertProtectedRoutesStillBlocked,
  assertPublicApiReturnsOk,
  assertPublicDetailModalOpen,
  assertPublicPageVisible,
  assertPublicRouteAccessible,
  capturePublicRouteFailureDiagnostics,
  clickFirstVisibleTestId,
  closePublicDetailModal,
  ensureGuestSession,
  PUBLIC_API_ENDPOINTS,
  visitPublicRoute,
  waitForOptionalPublicCards,
  waitForPublicContentLoaded,
} from '../helpers/public-routes.js';
import { setActiveDriver } from '../setup.js';

describe('Public route safety and no over-locking', function () {
  this.timeout(180000);

  let driver;

  before(async function () {
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  beforeEach(async function () {
    await ensureGuestSession(driver);
    await assertGuestSessionClean(driver);
  });

  it('8A-1 Guest can access landing page without login', async function () {
    try {
      await assertPublicRouteAccessible(driver, '/', {
        rootTestId: 'public-landing-root',
        label: 'landing page',
        requiredText: ['Carboot@CMart', 'Upcoming Carboot Events'],
      });

      for (const probe of PUBLIC_API_ENDPOINTS) {
        await assertPublicApiReturnsOk(driver, probe.endpoint, { label: probe.label });
      }
    } catch (error) {
      const diagnostics = await capturePublicRouteFailureDiagnostics(driver, 't8a-landing-failed', {
        flow: '8A-1',
      });
      error.message = `${error.message} Diagnostics: ${JSON.stringify(diagnostics)}.`;
      throw error;
    }
  });

  it('8A-2 Guest can access public events calendar without login', async function () {
    try {
      await assertPublicRouteAccessible(driver, '/calendar', {
        rootTestId: 'public-calendar-root',
        label: 'public calendar',
        requiredText: ['CMart Carboot Schedule', 'DISCOVER EVENTS'],
      });

      await waitForPublicContentLoaded(driver, { loadingGoneText: 'Loading events…' });
    } catch (error) {
      const diagnostics = await capturePublicRouteFailureDiagnostics(driver, 't8a-calendar-failed', {
        flow: '8A-2',
      });
      error.message = `${error.message} Diagnostics: ${JSON.stringify(diagnostics)}.`;
      throw error;
    }
  });

  it('8A-3 Guest can access public news section without login', async function () {
    try {
      await visitPublicRoute(driver, '/#news');
      await assertNotRedirectedToLogin(driver, { label: 'landing news section' });
      await assertPublicPageVisible(driver, {
        rootTestId: 'public-news-root',
        requiredText: ['News & Updates'],
      });
      await assertNoProtectedDashboardVisible(driver);

      await assertPublicApiReturnsOk(driver, '/news', { label: 'Public news list' });
    } catch (error) {
      const diagnostics = await capturePublicRouteFailureDiagnostics(driver, 't8a-news-failed', {
        flow: '8A-3',
      });
      error.message = `${error.message} Diagnostics: ${JSON.stringify(diagnostics)}.`;
      throw error;
    }
  });

  it('8A-4 Guest can access Carboot Reuse Preview marketplace without login', async function () {
    try {
      await assertPublicRouteAccessible(driver, '/marketplace', {
        rootTestId: 'marketplace-preview-root',
        label: 'marketplace preview',
        requiredText: ['Carboot Reuse Preview', 'Preview only: no online checkout'],
      });

      await assertPublicPageVisible(driver, {
        rootTestId: 'marketplace-preview-notice',
        requiredText: ['Preview only: no online checkout'],
      });

      await assertPublicApiReturnsOk(driver, '/marketplace/items', { label: 'Public marketplace items' });
    } catch (error) {
      const diagnostics = await capturePublicRouteFailureDiagnostics(driver, 't8a-marketplace-failed', {
        flow: '8A-4',
      });
      error.message = `${error.message} Diagnostics: ${JSON.stringify(diagnostics)}.`;
      throw error;
    }
  });

  it('8A-5 Guest can open public detail modals when seeded content exists', async function () {
    const opened = {
      event: false,
      news: false,
      marketplaceItem: false,
    };

    try {
      await visitPublicRoute(driver, '/');
      await assertPublicPageVisible(driver, { rootTestId: 'public-landing-root' });
      await waitForOptionalPublicCards(driver, {
        cardTestId: 'public-event-card',
        emptyStateText: 'No upcoming events scheduled right now',
      });

      if (await clickFirstVisibleTestId(driver, 'public-event-card')) {
        await assertPublicDetailModalOpen(driver);
        opened.event = true;
        await closePublicDetailModal(driver);
      }

      await visitPublicRoute(driver, '/#news');
      await assertPublicPageVisible(driver, { rootTestId: 'public-news-root' });
      await waitForOptionalPublicCards(driver, {
        cardTestId: 'public-news-card',
        emptyStateText: 'No news posts yet',
      });

      if (await clickFirstVisibleTestId(driver, 'public-news-card')) {
        await assertPublicDetailModalOpen(driver);
        opened.news = true;
        await closePublicDetailModal(driver);
      }

      await visitPublicRoute(driver, '/marketplace');
      await assertPublicPageVisible(driver, { rootTestId: 'marketplace-preview-root' });
      await waitForOptionalPublicCards(driver, {
        cardTestId: 'public-item-card',
        emptyStateText: 'No preview items available yet',
      });

      const itemCards = await driver.findElements(By.css('[data-testid="public-item-card"]'));
      if (itemCards.length > 0) {
        const firstCard = itemCards[0];
        const viewDetailsButton = await firstCard.findElement(By.css('button'));
        await driver.executeScript('arguments[0].scrollIntoView({block: "center"});', viewDetailsButton);
        await driver.executeScript('arguments[0].click();', viewDetailsButton);

        await assertPublicDetailModalOpen(driver);
        const bodyText = await driver.findElement(By.css('body')).getText();
        assert.ok(
          bodyText.includes('Preview only') || bodyText.includes('In-person only'),
          'Marketplace item detail must communicate preview-only / in-person purchase.',
        );
        opened.marketplaceItem = true;
        await closePublicDetailModal(driver);
      }

      if (!opened.event && !opened.news && !opened.marketplaceItem) {
        this.skip(
          'No public event, news, or marketplace item cards available to open detail modals. ' +
            'Seed events/news/marketplace items for full 8A-5 coverage.',
        );
      }

      assert.ok(
        opened.event || opened.news || opened.marketplaceItem,
        'At least one public detail modal must open when seeded content exists.',
      );
    } catch (error) {
      const diagnostics = await capturePublicRouteFailureDiagnostics(driver, 't8a-details-failed', {
        flow: '8A-5',
        opened,
      });
      error.message = `${error.message} Diagnostics: ${JSON.stringify(diagnostics)}.`;
      throw error;
    }
  });

  it('8A-6 Protected dashboards remain blocked for guests', async function () {
    try {
      await assertProtectedRoutesStillBlocked(driver);
    } catch (error) {
      const diagnostics = await capturePublicRouteFailureDiagnostics(driver, 't8a-protected-control-failed', {
        flow: '8A-6',
      });
      error.message = `${error.message} Diagnostics: ${JSON.stringify(diagnostics)}.`;
      throw error;
    }
  });
});
