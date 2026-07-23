import { execSync } from 'node:child_process';
import { closeSync, existsSync, openSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * Reset + seed DB sqlite khusus e2e sebelum suite Playwright jalan (sekali, bukan per test file).
 * Pola sama seperti env vars di webServer (playwright.config.ts) - harus sinkron.
 */
export default function globalSetup() {
  const testDbPath = path.resolve(__dirname, '../../database/testing.sqlite');

  // Laravel/SQLite butuh file-nya sudah ada duluan, tidak dibuat otomatis.
  if (!existsSync(testDbPath)) {
    closeSync(openSync(testDbPath, 'w'));
  }

  execSync('php artisan migrate:fresh --seed --seeder=PlaywrightDemoSeeder --force', {
    cwd: path.resolve(__dirname, '../..'),
    stdio: 'inherit',
    env: {
      ...process.env,
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: testDbPath,
    },
  });
}
