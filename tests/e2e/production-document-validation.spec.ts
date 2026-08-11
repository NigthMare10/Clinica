import path from 'node:path';
import { expect, test } from '@playwright/test';

const documents = [
    ['CSA-2026-3SPH1MTBOS', 'production-qa-01-CSA-2026-3SPH1MTBOS.pdf'],
    ['CSA-2026-LBTVD4U86H', 'production-qa-02-CSA-2026-LBTVD4U86H.pdf'],
    ['CSA-2026-0IGY9FO5QT', 'production-qa-03-CSA-2026-0IGY9FO5QT.pdf'],
    ['CSA-2026-3IIUXTMHTK', 'production-qa-04-CSA-2026-3IIUXTMHTK.pdf'],
    ['CSA-2026-RX9PEAEGWK', 'production-qa-05-CSA-2026-RX9PEAEGWK.pdf'],
] as const;

test.use({ baseURL: 'https://clinicaprivadasanta-ana.com' });

test('production QR, code and PDF validations are authentic', async ({ page }) => {
    const password = process.env.PRODUCTION_ADMIN_PASSWORD;
    expect(password, 'PRODUCTION_ADMIN_PASSWORD is required').toBeTruthy();
    const failures: string[] = [];
    page.on('console', message => {
        if (message.type() === 'error' || message.type() === 'warning') failures.push(`console.${message.type()}: ${message.text()}`);
    });
    page.on('pageerror', error => failures.push(`pageerror: ${error.message}`));
    page.on('requestfailed', request => {
        if (!request.url().includes('/download/')) failures.push(`requestfailed: ${request.method()} ${request.url()} ${request.failure()?.errorText}`);
    });
    page.on('response', response => {
        if (response.status() >= 400) failures.push(`${response.status()} ${response.request().method()} ${response.url()}`);
    });

    await page.goto('/login');
    await page.getByLabel('Correo electrónico').fill('cesar@gmail.com');
    await page.getByLabel('Contraseña').fill(password!);
    await page.getByRole('button', { name: /Entrar al panel/ }).click();
    await expect(page).toHaveURL(/\/admin(?:$|\/)/);

    const downloaded: string[] = [];
    for (const [code, filename] of documents.slice(0, 2)) {
        await page.goto('/admin/documents');
        await page.getByLabel('Buscar documentos').fill(code);
        await page.waitForTimeout(900);
        const row = page.locator('tbody tr').filter({ hasText: code }).first();
        const downloadPromise = page.waitForEvent('download');
        await row.getByRole('link', { name: 'Emitido', exact: true }).click();
        const download = await downloadPromise;
        const target = path.join(process.env.TEMP!, 'opencode', filename);
        await download.saveAs(target);
        downloaded.push(target);
    }

    for (const filename of downloaded) {
        await page.goto('/verificar');
        await page.getByRole('button', { name: 'PDF Subir archivo' }).click();
        await page.locator('#verification-pdf').setInputFiles(filename);
        await page.getByRole('button', { name: /Comparar PDF/ }).click();
        await expect(page.getByRole('heading', { name: 'Documento auténtico' })).toBeVisible();
    }

    for (const [code] of documents) {
        await page.goto('/verificar');
        await page.getByLabel('Código del documento').fill(code);
        await page.getByRole('button', { name: /Verificar documento/ }).click();
        await expect(page.getByRole('heading', { name: 'Documento auténtico' })).toBeVisible();
    }

    for (const url of [process.env.PRODUCTION_QR_URL_01, process.env.PRODUCTION_QR_URL_02]) {
        expect(url).toMatch(/^https:\/\/clinicaprivadasanta-ana\.com\/verificar\//);
        await page.goto(url!);
        await expect(page.getByRole('heading', { name: 'Documento auténtico' })).toBeVisible();
    }

    await page.screenshot({ path: 'test-results/production-verification-success.png', fullPage: true });
    expect(failures, failures.join('\n')).toEqual([]);
});
