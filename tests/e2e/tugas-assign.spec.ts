import { test, expect } from '@playwright/test';
import { login } from './helpers/login';
import { selectTomOption } from './helpers/tom-select';

const ATASAN = { email: 'atasan@e2e.test', password: 'password' };

test.describe('Tugas - buat & assign', () => {
  test('assign ke diri sendiri (self-assigned casual task)', async ({ page }) => {
    await login(page, ATASAN.email, ATASAN.password);
    await page.goto('/tugas');

    await page.getByRole('button', { name: /Tugas Baru/ }).click();
    await page.locator('textarea').fill('Tugas self-assign e2e');
    await page.getByRole('button', { name: 'Buat Tugas' }).click();

    await expect(page.getByText('Tugas self-assign e2e')).toBeVisible();
  });

  test('assign ke bawahan muncul di scope Tim Saya', async ({ page }) => {
    await login(page, ATASAN.email, ATASAN.password);
    await page.goto('/tugas');

    await page.getByRole('button', { name: /Tugas Baru/ }).click();
    await page.locator('textarea').fill('Tugas buat bawahan e2e');
    await selectTomOption(page, 'Bawahan E2E');
    await page.getByRole('button', { name: 'Buat Tugas' }).click();

    await page.getByRole('button', { name: /Tim Saya/ }).click();
    await expect(page.getByText('Tugas buat bawahan e2e')).toBeVisible();
  });

  test('project baru + assign task ke anggota project', async ({ page }) => {
    await login(page, ATASAN.email, ATASAN.password);
    await page.goto('/tugas');

    await page.getByRole('button', { name: /Project Baru/ }).click();
    await page.getByRole('textbox').first().fill('Project Assign E2E');
    await selectTomOption(page, 'Bawahan E2E');
    await page.getByRole('button', { name: 'Buat Project' }).click();
    await page.waitForURL((url) => url.searchParams.has('activeProjectId'));

    await expect(page.getByRole('button', { name: /Anggota \(2\)/ })).toBeVisible();

    await page.getByRole('button', { name: /Tugas Baru/ }).click();
    await page.locator('textarea').fill('Tugas project e2e');
    await selectTomOption(page, 'Bawahan E2E');
    await page.waitForTimeout(300);
    await page.getByRole('button', { name: 'Buat Tugas' }).click();

    await expect(page.getByText('Tugas project e2e')).toBeVisible({ timeout: 10_000 });
  });
});
