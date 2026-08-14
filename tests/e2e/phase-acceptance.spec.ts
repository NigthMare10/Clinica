import { expect, test } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { signIn } from './fixtures';

const artifacts = path.resolve('artifacts/playwright');
const incapacity = `Por medio de la presente se hace constar que la paciente Paciente Prueba Automatizada, de 21 años de edad, con número de identidad 0801200599999, acudió a consulta médica el día 9 de agosto de 2026 a las 10:00 a. m., por presentar diarrea frecuente, fiebre y dolor abdominal.

Durante la valoración médica se evidenciaron manifestaciones compatibles con un proceso gastrointestinal agudo que limita temporalmente sus actividades habituales.

Se establece diagnóstico presuntivo de gastroenteritis aguda de probable origen infeccioso, recomendándose reposo, hidratación abundante y tratamiento sintomático según indicación médica.

Por lo anterior, se extiende incapacidad médica por dos (2) días, correspondientes al 9 y 10 de agosto de 2026.`;

test.beforeAll(() => mkdirSync(artifacts, { recursive: true }));

test('captures public photographic pages and geographic fallback', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByAltText('Doctora conversando con una paciente durante una consulta')).toBeVisible();
    await page.screenshot({ path: path.join(artifacts, 'home-photography.png'), fullPage: true });
    await page.route('https://**.tile.openstreetmap.org/**', route => route.abort());
    await page.goto('/clinicas');
    await expect(page.locator('.network-map-fallback button')).toHaveCount(18);
    await page.screenshot({ path: path.join(artifacts, 'honduras-fallback.png'), fullPage: true });
    await page.goto('/verificar');
    await page.screenshot({ path: path.join(artifacts, 'verification-lookup.png'), fullPage: true });
});

test('analyzes text, previews a constancia and issues an encrypted one-page incapacity', async ({ page }) => {
    await signIn(page);
    await page.goto('/admin/documents/generate/constancia');
    await expect(page.getByRole('heading', { name: 'Nueva constancia' }).first()).toBeVisible();
    await page.getByLabel(/Pegue aquí el contenido/).fill(incapacity);
    await page.getByRole('button', { name: 'ANALIZAR TEXTO' }).click();
    await expect(page.getByText(/% detectado/)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Vista previa' }).last()).toBeEnabled();
    await page.screenshot({ path: path.join(artifacts, 'text-analysis.png'), fullPage: true });
    await page.getByRole('button', { name: 'Vista previa' }).last().click();
    await expect(page.locator('.certificate-preview')).toBeVisible();

    await page.goto('/admin/documents/generate/incapacidad');
    await expect(page.getByRole('heading', { name: 'Nueva incapacidad' }).first()).toBeVisible();
    await page.getByLabel(/Pegue aquí el contenido/).fill(incapacity);
    await page.getByRole('button', { name: 'ANALIZAR TEXTO' }).click();
    await expect(page.getByRole('button', { name: 'Firmar y emitir' })).toBeEnabled();
    const issueResponse = page.waitForResponse(response => response.request().method() === 'POST'
        && new URL(response.url()).pathname === '/admin/documents/generate/incapacidad', { timeout: 30_000 });
    page.once('dialog', dialog => dialog.accept());
    await page.getByRole('button', { name: 'Firmar y emitir' }).click();
    const response = await issueResponse;
    expect(response.status()).toBe(302);
    expect(await response.headerValue('location')).toMatch(/\/admin\/documents\/.+\/review/);
    await expect(page).toHaveURL(/\/admin\/documents\/.+\/review/, { timeout: 30_000 });
    await expect(page.getByText('Emitido', { exact: true }).first()).toBeVisible();
    await page.screenshot({ path: path.join(artifacts, 'issued-incapacity.png'), fullPage: true });
});

test('captures admin dashboard and verification logs', async ({ page }) => {
    await signIn(page);
    await page.goto('/admin');
    await page.screenshot({ path: path.join(artifacts, 'admin-dashboard.png'), fullPage: true });
    await page.goto('/admin/verifications');
    await expect(page.getByRole('heading', { name: 'Historial de verificaciones' })).toBeVisible();
    await page.screenshot({ path: path.join(artifacts, 'verification-logs.png'), fullPage: true });
});
