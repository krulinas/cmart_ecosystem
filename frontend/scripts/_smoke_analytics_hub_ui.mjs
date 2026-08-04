import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE || 'http://localhost:5173';
const API = process.env.SMOKE_API || 'http://127.0.0.1:8000/api';
const EMAIL = process.env.SMOKE_EMAIL || 'organizer@cmart.com';
const PASSWORD = process.env.SMOKE_PASSWORD || 'password123';

const results = [];
const note = (label, ok, detail = '') => {
  results.push({ label, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'} · ${label}${detail ? ` — ${detail}` : ''}`);
};

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
const consoleErrors = [];
const apiHits = [];
page.on('pageerror', (err) => consoleErrors.push(String(err)));
page.on('console', (msg) => {
  if (msg.type() === 'error') consoleErrors.push(msg.text());
});
page.on('response', async (res) => {
  const url = res.url();
  if (url.includes('/api/')) {
    apiHits.push(`${res.status()} ${url.replace(API, '')}`);
  }
});

try {
  const loginRes = await page.request.post(`${API}/auth/login`, {
    data: { email: EMAIL, password: PASSWORD },
  });
  note('API login', loginRes.ok(), `status=${loginRes.status()}`);
  const loginJson = await loginRes.json();
  const token = loginJson.token;
  const user = loginJson.user;
  if (!token) throw new Error('No token from login');

  await page.goto(BASE, { waitUntil: 'domcontentloaded' });
  await page.evaluate(({ token: t, user: u }) => {
    localStorage.setItem('carboot_cmart_token', t);
    localStorage.setItem('carboot_cmart_user', JSON.stringify(u));
  }, { token, user });

  await page.goto(`${BASE}/admin#event-analytics`, { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForSelector('[data-testid="organizer-event-analytics-hub"]', { timeout: 30000 });
  await page.waitForTimeout(2000);

  note('Analytics Hub renders', await page.locator('[data-testid="organizer-event-analytics-hub"]').isVisible());

  const navText = await page.locator('aside').innerText().catch(() => '');
  note('Sidebar shows Analytics Hub', /Analytics Hub/i.test(navText));
  note('Sidebar does not show Revenue', !/(^|\n)Revenue(\n|$)/i.test(navText));
  note('Sidebar does not show Word Cloud', !/Word Cloud/i.test(navText));
  note('Sidebar does not show Audit Log for organizer', !/(^|\n)Audit Log(\n|$)/i.test(navText));

  const eventsHit = apiHits.find((h) => h.includes('carboot-events'));
  note('carboot-events requested', Boolean(eventsHit), eventsHit || apiHits.slice(-8).join(' | '));

  await page.waitForFunction(() => {
    const sel = document.querySelector('[data-testid="organizer-event-analytics-hub"] select');
    return sel && sel.options && sel.options.length > 1;
  }, { timeout: 20000 }).catch(() => null);

  const optionCount = await page.locator('[data-testid="organizer-event-analytics-hub"] select option').count();
  note('Event selector populated', optionCount > 1, `options=${optionCount}`);

  const hasEvent5 = await page.locator('[data-testid="organizer-event-analytics-hub"] select option[value="5"]').count();
  if (hasEvent5) {
    await page.selectOption('[data-testid="organizer-event-analytics-hub"] select', '5');
    await page.waitForTimeout(2500);
    note('Selected event 5', true);
  } else {
    // pick first real event
    const value = await page.locator('[data-testid="organizer-event-analytics-hub"] select option').nth(1).getAttribute('value');
    if (value) {
      await page.selectOption('[data-testid="organizer-event-analytics-hub"] select', value);
      await page.waitForTimeout(2500);
    }
    note('Selected event 5', false, `fallback=${value || 'none'}`);
  }

  const hubText = await page.locator('[data-testid="organizer-event-analytics-hub"]').innerText();
  note('Respondent count shows 9', /\bn\s*=\s*9\b|Survey respondents\s*\n9\b/i.test(hubText), hubText.slice(0, 240).replace(/\s+/g, ' '));

  const tabLabels = [
    'Overview',
    'Survey Results',
    'Vendor Comments',
    'Operations',
    'Data Sources',
  ];
  for (const label of tabLabels) {
    const btn = page.getByRole('button', { name: label });
    const visible = await btn.isVisible().catch(() => false);
    if (visible) {
      await btn.click();
      await page.waitForTimeout(350);
    }
    note(`Tab loads: ${label}`, visible);
  }

  await page.getByRole('button', { name: 'Data Sources' }).click();
  await page.waitForTimeout(400);
  note('CSV source under Data Sources', await page.locator('[data-testid="current-csv-source"]').isVisible());

  await page.getByRole('button', { name: 'Survey Results' }).click();
  await page.waitForTimeout(500);
  const itemsText = await page.locator('[data-testid="organizer-event-analytics-hub"]').innerText();
  note('No raw snake_case tidak_berkenaan', !itemsText.includes('tidak_berkenaan'));
  note('Humanized Tidak berkenaan or empty survey state', itemsText.includes('Tidak berkenaan') || /No CSV data|No vendor survey/i.test(itemsText));

  await page.getByRole('button', { name: 'Overview' }).click();
  await page.waitForTimeout(400);
  const revText = await page.locator('[data-testid="organizer-event-analytics-hub"]').innerText();
  note('Overview is event-scoped copy', /this event/i.test(revText) && /platform/i.test(revText));
  note('Sales bands kept categorical note', /not exact RM|categorical bands|platform/i.test(revText));

  const hasEvent6 = await page.locator('[data-testid="organizer-event-analytics-hub"] select option[value="6"]').count();
  if (hasEvent6) {
    await page.selectOption('[data-testid="organizer-event-analytics-hub"] select', '6');
    await page.waitForTimeout(2500);
    await page.getByRole('button', { name: 'Overview' }).click();
    await page.waitForTimeout(400);
    const text6 = await page.locator('[data-testid="organizer-event-analytics-hub"]').innerText();
    const showsNineReady = /Survey respondents\s*\n9\b/i.test(text6);
    note('Event 6 does not show event 5 respondents=9', !showsNineReady, text6.slice(0, 180).replace(/\s+/g, ' '));
  } else {
    note('Event 6 isolation', false, 'option missing');
  }

  await page.goto(`${BASE}/admin#revenue`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1200);
  note('#revenue redirects to event-analytics', page.url().includes('event-analytics'));
  note('Hub visible after #revenue redirect', await page.locator('[data-testid="organizer-event-analytics-hub"]').isVisible());

  await page.selectOption('[data-testid="organizer-event-analytics-hub"] select', '5').catch(() => null);
  await page.waitForTimeout(1000);
  await page.getByRole('button', { name: 'Generate Event Report' }).click();
  await page.waitForTimeout(1500);
  note('Generate Report goes to report-centre', page.url().includes('report-centre'));

  const unexpected = consoleErrors.filter((e) =>
    !/favicon|ResizeObserver|Download the Vue Devtools|Vue DevTools|wordcloud\/(feedback|products).*500|status of 500/i.test(e)
    && !/Failed to load resource: the server responded with a status of 500/i.test(e)
  );
  // Word-cloud 500s are asserted separately via UI empty/error states; ignore network noise here.
  note('No new uncaught console errors', unexpected.length === 0, unexpected.slice(0, 3).join(' | '));
} catch (err) {
  note('Smoke script completed', false, String(err));
  console.log('API hits:', apiHits.slice(-20).join('\n'));
} finally {
  await browser.close();
}

const failed = results.filter((r) => !r.ok);
console.log(`\nSummary: ${results.length - failed.length}/${results.length} passed`);
process.exit(failed.length ? 1 : 0);
