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

export function e2eT6APayPassMarker() {
  return `E2E-T6A-PAYPASS-${Date.now()}`;
}

/** @deprecated Use e2eT6APayPassMarker */
export function e2eT6PayPassMarker() {
  return e2eT6APayPassMarker();
}

export function e2eT6BWithdrawnMarker() {
  return `E2E-T6B-WITHDRAWN-${Date.now()}`;
}

export function e2eT6BRejectedMarker() {
  return `E2E-T6B-REJECTED-${Date.now()}`;
}

export function e2eT6CNotPendingMarker() {
  return `E2E-T6C-NOT-PENDING-${Date.now()}`;
}

export function e2eT6DDoubleVerifyMarker() {
  return `E2E-T6D-DOUBLE-VERIFY-${Date.now()}`;
}

export function e2eT7AStaffGuardMarker() {
  return `E2E-T7A-STAFF-GUARD-${Date.now()}`;
}

export function e2eT7BManagerApproveMarker() {
  return `E2E-T7B-MANAGER-APPROVE-${Date.now()}`;
}

export function e2eT7BManagerRejectMarker() {
  return `E2E-T7B-MANAGER-REJECT-${Date.now()}`;
}

export function e2eT7CVendorBOwnershipMarker() {
  return `E2E-T7C-VENDOR-B-OWNERSHIP-${Date.now()}`;
}

export function e2eT7DGuestProtectionMarker() {
  return `E2E-T7D-GUEST-PROTECTION-${Date.now()}`;
}
