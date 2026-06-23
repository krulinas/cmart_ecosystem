import { By, until } from 'selenium-webdriver';

export async function waitForTestId(driver, testId, timeoutMs = 15000) {
  const locator = By.css(`[data-testid="${testId}"]`);
  return driver.wait(until.elementLocated(locator), timeoutMs, `Timed out waiting for [data-testid="${testId}"]`);
}

export async function waitForTestIdHidden(driver, testId, timeoutMs = 15000) {
  const locator = By.css(`[data-testid="${testId}"]`);
  await driver.wait(async () => {
    const elements = await driver.findElements(locator);
    if (!elements.length) return true;

    try {
      return !(await elements[0].isDisplayed());
    } catch (error) {
      if (error.name === 'StaleElementReferenceError') return true;
      throw error;
    }
  }, timeoutMs, `Timed out waiting for [data-testid="${testId}"] to be hidden`);
}

export async function waitForAppReady(driver, rootTestId = 'vendor-dashboard-root', timeoutMs = 15000) {
  const element = await waitForTestId(driver, rootTestId, timeoutMs);
  await driver.wait(until.elementIsVisible(element), timeoutMs, `[data-testid="${rootTestId}"] is not visible`);
  return element;
}

export async function waitForUrlContains(driver, fragment, timeoutMs = 15000) {
  return driver.wait(
    async () => {
      const currentUrl = await driver.getCurrentUrl();
      return currentUrl.includes(fragment);
    },
    timeoutMs,
    `Timed out waiting for URL to contain "${fragment}"`,
  );
}
