import { By } from 'selenium-webdriver';
import { env, requireStaffCredentials, requireVendorCredentials } from '../config/env.js';
import { waitForTestId, waitForUrlContains } from './wait.js';

async function fillLoginField(driver, testId, value) {
  const input = await waitForTestId(driver, testId);
  await driver.executeScript(
    `const field = arguments[0];
     field.value = arguments[1];
     field.dispatchEvent(new Event('input', { bubbles: true }));
     field.dispatchEvent(new Event('change', { bubbles: true }));`,
    input,
    value,
  );
}

async function submitLoginForm(driver) {
  await driver.executeScript(`
    const form = document.querySelector('[data-testid="login-email"]')?.closest('form');
    if (!form) throw new Error('Login form not found.');
    form.requestSubmit();
  `);
}

export async function logout(driver) {
  await driver.executeScript(`
    localStorage.clear();
    sessionStorage.clear();
  `);
  await driver.manage().deleteAllCookies();
  await driver.get(`${env.baseUrl}/login`);
  await driver.navigate().refresh();
  await waitForTestId(driver, 'login-email', 20000);
}

export async function loginAsVendor(driver) {
  const { email, password } = requireVendorCredentials();

  await driver.get(`${env.baseUrl}/login`);

  await fillLoginField(driver, 'login-email', email);
  await fillLoginField(driver, 'login-password', password);
  await submitLoginForm(driver);

  try {
    await waitForUrlContains(driver, '/dashboard', 20000);
  } catch (error) {
    const currentUrl = await driver.getCurrentUrl();
    const toasts = await driver.findElements(By.css('.Vue-Toastification__toast-body'));
    const toastMessages = [];

    for (const toast of toasts) {
      toastMessages.push((await toast.getText()).trim());
    }

    throw new Error(
      `Vendor login did not reach the dashboard. Current URL: ${currentUrl}. ` +
        `Toast messages: ${toastMessages.filter(Boolean).join(' | ') || 'none'}.`,
      { cause: error },
    );
  }

  await waitForTestId(driver, 'vendor-dashboard-root');
}

export async function loginAsStaff(driver) {
  const { email, password } = requireStaffCredentials();

  await driver.get(`${env.baseUrl}/login`);

  await fillLoginField(driver, 'login-email', email);
  await fillLoginField(driver, 'login-password', password);
  await submitLoginForm(driver);

  try {
    await waitForUrlContains(driver, '/admin', 20000);
  } catch (error) {
    const currentUrl = await driver.getCurrentUrl();
    const toasts = await driver.findElements(By.css('.Vue-Toastification__toast-body'));
    const toastMessages = [];

    for (const toast of toasts) {
      toastMessages.push((await toast.getText()).trim());
    }

    throw new Error(
      `Staff login did not reach the admin workspace. Current URL: ${currentUrl}. ` +
        `Toast messages: ${toastMessages.filter(Boolean).join(' | ') || 'none'}.`,
      { cause: error },
    );
  }

  await waitForTestId(driver, 'staff-dashboard-root');
}
