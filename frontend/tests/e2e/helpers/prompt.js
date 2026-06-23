import { until } from 'selenium-webdriver';

export async function withPromptAnswer(driver, answer, action) {
  await driver.executeScript(
    `window.__e2ePromptAnswer = arguments[0];
     window.prompt = function() { return window.__e2ePromptAnswer; };`,
    answer,
  );

  await action();

  try {
    const alert = await driver.wait(until.alertIsPresent(), 2000);
    await alert.sendKeys(answer);
    await alert.accept();
  } catch {
    // Prompt was handled by the page stub.
  }
}
