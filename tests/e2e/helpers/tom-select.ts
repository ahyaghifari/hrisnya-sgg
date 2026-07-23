import { Page } from '@playwright/test';

/**
 * Klik kontrol TomSelect (input asli disembunyikan/positioned absolute lewat plugin,
 * tidak clickable langsung - lihat komponen `.ts-control` yang dibungkusnya), ketik buat
 * filter, klik opsi dari dropdown. Sama untuk single & multi select.
 */
export async function selectTomOption(page: Page, optionText: string, controlIndex = 0): Promise<void> {
  await page.locator('.ts-control').nth(controlIndex).click();
  await page.waitForTimeout(150);
  await page.keyboard.type(optionText);
  await page.waitForTimeout(300);
  await page.locator('.ts-dropdown .option', { hasText: optionText }).click();
  await page.keyboard.press('Escape');
}
