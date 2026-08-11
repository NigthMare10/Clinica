import { expect, test } from '@playwright/test';
import path from 'node:path';
import { fixture, signIn } from './fixtures';

test('public routes and Spanish verification lookup are live', async ({ page }) => {
    for (const url of ['/', '/especialidades', '/clinica', '/clinicas', '/contacto', '/verificar', '/login']) {
        const response = await page.goto(url);
        expect(response?.status(), url).toBeLessThan(400);
    }
    await page.goto('/verificar');
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex,nofollow');
    await expect(page.getByRole('heading', { name: 'Verifique un documento médico.' })).toBeVisible();
});

test('unknown verification code returns a deterministic not-found result', async ({ page }) => {
    await page.goto('/verificar');
    await page.getByLabel('Código del documento').fill('E2E-FICTICIO-INEXISTENTE');
    await page.getByRole('button', { name: 'Verificar documento' }).click();
    await expect(page.getByRole('heading', { name: 'Documento no encontrado' })).toBeVisible();
});

test('token verification uses /verificar/{token} and supports valid, revoked, replaced and identity-gated fixtures', async ({ page }) => {
    for (const [env, heading] of [['E2E_VALID_TOKEN','Documento auténtico'],['E2E_REVOKED_TOKEN','Documento anulado'],['E2E_REPLACED_TOKEN','Documento reemplazado']] as const) {
        await page.goto(`/verificar/${fixture(env)}`);
        await expect(page.getByRole('heading', { name: heading })).toBeVisible();
        await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex,nofollow');
    }
});

test('identity-gated result protects clinical details and reveals them only after authorization', async ({ page }) => {
    await page.goto(`/verificar/${fixture('E2E_IDENTITY_TOKEN')}`);
    await expect(page.getByText('Protegido por segundo factor')).toBeVisible();
    await expect(page.getByText('Información clínica protegida.')).toBeVisible();
    await page.getByLabel('Últimos 4 dígitos').fill('2345');
    await page.getByRole('button', { name: 'Autorizar detalles' }).click();
    await expect(page.getByText('Paciente Ficticia')).toBeVisible();
    await expect(page.getByText('Seguridad documental')).toBeVisible();
});

test('verification accepts a fictitious issued PDF and rejects a different PDF', async ({ page }) => {
    await page.goto('/verificar');
    await page.getByRole('button', { name: 'PDF Subir archivo' }).click();
    await page.locator('#verification-pdf').setInputFiles(path.resolve(fixture('E2E_ISSUED_PDF_PATH')));
    const validResponse=page.waitForResponse(response=>response.url().endsWith('/verificar/archivo')&&response.request().method()==='POST');
    await page.getByRole('button', { name: /Comparar PDF/ }).click();
    expect((await validResponse).status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Documento auténtico' })).toBeVisible();
    await page.goto('/verificar');
    await page.getByRole('button', { name: 'PDF Subir archivo' }).click();
    await page.locator('#verification-pdf').setInputFiles(path.resolve(fixture('E2E_UNKNOWN_PDF_PATH')));
    const unknownResponse=page.waitForResponse(response=>response.url().endsWith('/verificar/archivo')&&response.request().method()==='POST');
    await page.getByRole('button', { name: /Comparar PDF/ }).click();
    expect((await unknownResponse).status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Documento no encontrado' })).toBeVisible();
});

test('Honduras fallback stays useful when map tiles are unavailable', async ({ page }) => {
    await page.route('https://**.tile.openstreetmap.org/**', route => route.abort());
    await page.goto('/clinicas');
    await expect(page.getByAltText('Mapa geográfico local de Honduras con 18 puntos departamentales')).toBeVisible();
    await expect(page.locator('.network-map-fallback button')).toHaveCount(18);
    await page.locator('.network-map-fallback button').first().click();
    await expect(page.locator('.department-list--cards article.is-selected')).toHaveCount(1);
});

test('invalid token shape is a real 404', async ({ request }) => {
    const response = await request.get('/verificar/token-invalido');
    expect(response.status()).toBe(404);
});
