import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    globalSetup: './tests/e2e/global-setup.ts',
    fullyParallel: false,
    timeout: 90_000,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    expect: { timeout: 15_000 },
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:8017',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],
    webServer: process.env.E2E_BASE_URL ? undefined : {
        command: 'php -d extension=pdo_sqlite -d extension=sqlite3 -S 127.0.0.1:8017 -t public tests/e2e/server.php',
        url: 'http://127.0.0.1:8017/robots.txt',
        reuseExistingServer: false,
        timeout: 30_000,
        stdout: 'ignore',
        stderr: 'ignore',
        env: {
            APP_ENV: 'e2e',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: './database/e2e.sqlite',
            QUEUE_CONNECTION: 'sync',
            CACHE_STORE: 'array',
            SESSION_DRIVER: 'database',
            APP_URL: 'http://127.0.0.1:8017',
            MEDICAL_PDF_PASSWORD: 'E2E-Pdf-Encryption-Only!',
            MEDICAL_PDF_ENCRYPTION: 'true',
        },
    },
});
