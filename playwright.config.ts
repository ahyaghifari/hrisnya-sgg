import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// DB sqlite terpisah dari dev (database/database.sqlite) dan dari test unit (:memory:,
// lihat phpunit.xml) - dipakai server `php artisan serve` yang di-spawn Playwright di bawah.
const testDbPath = path.resolve(__dirname, 'database/testing.sqlite');

export default defineConfig({
  testDir: './tests/e2e',
  globalSetup: './tests/e2e/global-setup.ts',
  fullyParallel: false,
  workers: 1, // semua spec berbagi 1 file sqlite (lihat global-setup.ts) - jangan paralel
  retries: 0,
  timeout: 60_000, // request pertama ke `php artisan serve` yang baru start suka lambat (cold boot)
  reporter: 'list',
  use: {
    baseURL: 'http://127.0.0.1:8787',
    trace: 'retain-on-failure',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
  webServer: {
    command: 'php artisan serve --port=8787',
    url: 'http://127.0.0.1:8787',
    reuseExistingServer: false,
    env: {
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: testDbPath,
      SESSION_DRIVER: 'file',
      CACHE_STORE: 'file',
    },
  },
});
