import { after, afterEach, before, beforeEach } from 'mocha';
import { logout } from './helpers/auth.js';
import { quitDriver } from './helpers/driver.js';
import { runE2EPreflight } from './helpers/preflight.js';
import { clearBrowserSession } from './helpers/session.js';

let activeDriver = null;

export async function setActiveDriver(driver) {
  if (activeDriver && activeDriver !== driver) {
    await quitDriver(activeDriver);
  }
  activeDriver = driver;
}

before(async function () {
  this.timeout(30000);
  await runE2EPreflight();
});

beforeEach(async function () {
  this.timeout(45000);
  if (!activeDriver) return;

  try {
    await clearBrowserSession(activeDriver);
  } catch {
    // Each spec should start from a clean browser session when reusing one driver.
  }
});

afterEach(async function () {
  this.timeout(20000);
  if (!activeDriver) return;

  try {
    await Promise.race([
      logout(activeDriver),
      new Promise((_, reject) => {
        setTimeout(() => reject(new Error('logout timed out')), 18000);
      }),
    ]);
  } catch {
    try {
      await clearBrowserSession(activeDriver);
    } catch {
      // Best-effort session reset between specs to avoid cross-role token bleed.
    }
  }
});

after(async function () {
  await quitDriver(activeDriver);
  activeDriver = null;
});
