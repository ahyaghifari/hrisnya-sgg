import { test, expect, Page } from '@playwright/test';
import { login } from './helpers/login';
import { selectTomOption } from './helpers/tom-select';

const ATASAN = { email: 'atasan@e2e.test', password: 'password' };
const BAWAHAN = { email: 'bawahan@e2e.test', password: 'password' };
const REQUIREMENT = 'Tugas review flow e2e';

function taskCard(page: Page, requirement: string) {
  return page.locator('[wire\\:click*="openTaskDetail"]', { hasText: requirement });
}

test.describe.serial('Tugas - ubah status lalu review & rating', () => {
  test('atasan assign task ke bawahan', async ({ page }) => {
    await login(page, ATASAN.email, ATASAN.password);
    await page.goto('/tugas');

    await page.getByRole('button', { name: /Tugas Baru/ }).click();
    await page.locator('textarea').fill(REQUIREMENT);
    await selectTomOption(page, 'Bawahan E2E');
    await page.getByRole('button', { name: 'Buat Tugas' }).click();

    await page.getByRole('button', { name: /Tim Saya/ }).click();
    await expect(taskCard(page, REQUIREMENT)).toBeVisible();
  });

  test('bawahan geser status sampai Ditinjau, opsi Selesai tidak tersedia', async ({ page }) => {
    await login(page, BAWAHAN.email, BAWAHAN.password);
    await page.goto('/tugas');

    const select = taskCard(page, REQUIREMENT).locator('select');

    await select.selectOption({ label: 'Sedang Dalam Proses' });
    await page.waitForTimeout(500);
    await select.selectOption({ label: 'Ditinjau' });
    await page.waitForTimeout(500);

    const options = await select.locator('option').allTextContents();
    expect(options).not.toContain('Selesai');
  });

  test('atasan review & kasih rating, task jadi Selesai', async ({ page }) => {
    await login(page, ATASAN.email, ATASAN.password);
    await page.goto('/tugas');
    await page.getByRole('button', { name: /Tim Saya/ }).click();

    await taskCard(page, REQUIREMENT).click();
    await expect(page.getByRole('heading', { name: 'Review & Nilai' })).toBeVisible();

    await page.locator('label').filter({ has: page.locator('input[value="5"]') }).click();
    await page.getByRole('textbox', { name: 'Komentar (opsional)' }).fill('Bagus, sesuai target');
    await page.getByRole('button', { name: 'Kirim Review' }).click();

    await expect(page.getByRole('heading', { name: 'Review', exact: true })).toBeVisible();
    await expect(page.getByText('Bagus, sesuai target')).toBeVisible();
  });
});
