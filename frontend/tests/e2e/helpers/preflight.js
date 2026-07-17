import process from 'node:process';
import {
  env,
  requireOrganizerCredentials,
  requireCmartManagementCredentials,
  requireVendorCredentials,
} from '../config/env.js';

const DEFAULT_E2E_PORT = 5175;
const FETCH_TIMEOUT_MS = 15000;

async function assertHttpReachable(url, label) {
  try {
    const response = await fetch(url, { signal: AbortSignal.timeout(FETCH_TIMEOUT_MS) });
    if (response.status >= 500) {
      return { status: response.status, warning: `${label} responded with HTTP ${response.status} (server is up; check database/migrations if tests fail).` };
    }
    return { status: response.status };
  } catch (error) {
    throw new Error(`${label} not reachable at ${url} (${error.message})`, { cause: error });
  }
}

async function assertAuthLoginReachable() {
  const { email, password } = requireVendorCredentials();
  const loginUrl = `${env.apiBaseUrl}/auth/login`;

  let response;
  try {
    response = await fetch(loginUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email, password }),
      signal: AbortSignal.timeout(FETCH_TIMEOUT_MS),
    });
  } catch (error) {
    throw new Error(
      `Backend auth login not reachable at ${loginUrl} (${error.message}). ` +
        'Start Laravel with: php artisan serve',
      { cause: error },
    );
  }

  if (response.status >= 500) {
    throw new Error(
      `Backend auth login at ${loginUrl} returned HTTP ${response.status}. ` +
        'This usually means MySQL is not running or migrations/seeds are missing. ' +
        'Start XAMPP MySQL, then run: cd backend && php artisan migrate --seed',
    );
  }

  if (response.status === 401 || response.status === 422) {
    throw new Error(
      `Backend auth login at ${loginUrl} rejected E2E vendor credentials (HTTP ${response.status}). ` +
        'Check E2E_VENDOR_EMAIL / E2E_VENDOR_PASSWORD in tests/e2e/.env.e2e match a seeded user.',
    );
  }

  if (!response.ok) {
    return {
      warning: `Backend auth login responded with HTTP ${response.status}; continuing, but login specs may fail.`,
    };
  }

  return { status: response.status };
}

const LOGIN_ONLY_SPECS = new Set([
  'auth.login.spec.js',
  'auth.staff-login.spec.js',
  'auth.manager-login.spec.js',
]);

const PUBLIC_ROUTE_SPECS = new Set([
  'public.public-route-safety.spec.js',
  'public.event-layout.spec.js',
]);

export function requiresBookingData(specFiles = []) {
  if (process.env.E2E_REQUIRES_BOOKING_DATA === 'true') return true;
  if (process.env.E2E_REQUIRES_BOOKING_DATA === 'false') return false;
  if (!specFiles.length) return true;
  return specFiles.some((spec) => {
    const basename = spec.split(/[/\\]/).pop();
    return !LOGIN_ONLY_SPECS.has(basename) && !PUBLIC_ROUTE_SPECS.has(basename);
  });
}

async function assertUpcomingBookableEvents() {
  const url = `${env.apiBaseUrl}/events`;

  let response;
  try {
    response = await fetch(url, {
      headers: { Accept: 'application/json' },
      signal: AbortSignal.timeout(FETCH_TIMEOUT_MS),
    });
  } catch (error) {
    throw new Error(`Public events API not reachable at ${url} (${error.message})`, { cause: error });
  }

  if (!response.ok) {
    throw new Error(`Public events API at ${url} returned HTTP ${response.status}`);
  }

  const events = await response.json();
  const now = Date.now();
  const bookable = (Array.isArray(events) ? events : []).filter((event) => {
    if (event.status === 'Closed') return false;
    if (!event.ends_at) return false;
    return new Date(event.ends_at).getTime() >= now;
  });

  if (bookable.length === 0) {
    throw new Error(
      'E2E preflight failed: no upcoming bookable carboot events found. ' +
        'Run the seeder or create an upcoming event: cd backend && php artisan db:seed',
    );
  }

  return { count: bookable.length, titles: bookable.map((event) => event.title) };
}

function expectedFrontendPort() {
  try {
    const parsed = new URL(env.baseUrl);
    return parsed.port || (parsed.protocol === 'https:' ? '443' : '80');
  } catch {
    return String(DEFAULT_E2E_PORT);
  }
}

export function validateE2EEnvConfig() {
  const issues = [];

  if (!env.baseUrl) {
    issues.push('E2E_BASE_URL is missing. Set it in tests/e2e/.env.e2e.');
  }

  const port = expectedFrontendPort();
  if (port !== String(DEFAULT_E2E_PORT)) {
    issues.push(
      `E2E_BASE_URL uses port ${port}, but the E2E convention is ${DEFAULT_E2E_PORT}. ` +
        'Run `npm run dev:e2e` or update E2E_BASE_URL to match your Vite port.',
    );
  }

  try {
    requireVendorCredentials();
  } catch (error) {
    issues.push(error.message);
  }

  try {
    requireCmartManagementCredentials();
  } catch (error) {
    issues.push(error.message);
  }

  try {
    requireOrganizerCredentials();
  } catch (error) {
    issues.push(error.message);
  }

  if (issues.length) {
    throw new Error(`E2E environment configuration invalid:\n${issues.map((item) => `- ${item}`).join('\n')}`);
  }
}

export async function runE2EPreflight(options = {}) {
  const { requireBookingData = requiresBookingData() } = options;
  validateE2EEnvConfig();

  const failures = [];
  const warnings = [];

  try {
    await assertHttpReachable(`${env.baseUrl}/login`, 'Frontend login page');
  } catch (error) {
    failures.push(
      `${error.message}. Start the E2E Vite server with: npm run dev:e2e`,
    );
  }

  try {
    await assertHttpReachable(`${env.baseUrl}/`, 'Frontend root');
  } catch (error) {
    failures.push(error.message);
  }

  try {
    const apiCheck = await assertHttpReachable(`${env.apiBaseUrl}/spaces`, 'Backend API');
    if (apiCheck.warning) warnings.push(apiCheck.warning);
  } catch (error) {
    failures.push(`${error.message}. Start Laravel with: php artisan serve`);
  }

  try {
    const authCheck = await assertAuthLoginReachable();
    if (authCheck.warning) warnings.push(authCheck.warning);
  } catch (error) {
    failures.push(error.message);
  }

  if (requireBookingData) {
    try {
      const eventsCheck = await assertUpcomingBookableEvents();
      if (eventsCheck.count === 1) {
        warnings.push(`Only one upcoming bookable event found (${eventsCheck.titles[0]}).`);
      }
    } catch (error) {
      failures.push(error.message);
    }
  }

  if (failures.length) {
    throw new Error(`E2E preflight failed:\n${failures.map((item) => `- ${item}`).join('\n')}`);
  }

  return {
    baseUrl: env.baseUrl,
    apiBaseUrl: env.apiBaseUrl,
    port: expectedFrontendPort(),
    requireBookingData,
    warnings,
  };
}
