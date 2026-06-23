import { until } from 'selenium-webdriver';
import { waitForTestId } from './wait.js';

export async function waitForVisible(driver, testId, timeoutMs = 15000) {
  const element = await waitForTestId(driver, testId, timeoutMs);
  await driver.wait(until.elementIsVisible(element), timeoutMs, `[data-testid="${testId}"] is not visible`);
  return element;
}

export async function safeClick(driver, testId, timeoutMs = 15000) {
  const element = await waitForVisible(driver, testId, timeoutMs);
  await driver.executeScript('arguments[0].scrollIntoView({block: "center"});', element);
  await driver.wait(until.elementIsEnabled(element), timeoutMs, `[data-testid="${testId}"] is not enabled`);
  await element.click();
  return element;
}

export function uniqueTestMarker(baseText) {
  return `${baseText} ${Date.now()}`;
}
