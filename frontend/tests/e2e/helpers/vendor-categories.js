import { By } from 'selenium-webdriver';
import { waitForTestId } from './wait.js';

export async function selectBookingCategory(driver, label) {
  await waitForTestId(driver, 'vendor-booking-category-selector');
  const options = await driver.findElements(
    By.css('[data-testid="vendor-category-option"]'),
  );

  for (const option of options) {
    const text = (await option.getText()).trim();
    if (text === label || text.startsWith(`${label}\n`)) {
      await driver.executeScript('arguments[0].click();', option);
      return;
    }
  }

  throw new Error(`Booking category option "${label}" was not found.`);
}
