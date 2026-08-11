import { expect, test } from '@playwright/test';

const productionOnly = process.env.E2E_BASE_URL?.includes('clinicaprivadasanta-ana.com') ? test : test.skip;

productionOnly('production clinics renders without browser or network failures', async ({ page }) => {
    const browserErrors: string[] = [];
    const failedRequests: string[] = [];
    const failedResponses: string[] = [];

    page.on('console', message => {
        if (message.type() === 'error' || message.type() === 'warning') {
            browserErrors.push(`${message.type()}: ${message.text()}`);
        }
    });
    page.on('pageerror', error => browserErrors.push(`pageerror: ${error.message}`));
    page.on('requestfailed', request => failedRequests.push(`${request.url()}: ${request.failure()?.errorText ?? 'failed'}`));
    page.on('response', response => {
        if (response.status() >= 400) failedResponses.push(`${response.status()} ${response.url()}`);
    });

    const response = await page.goto('/clinicas');
    expect(response?.status()).toBe(200);
    await page.waitForTimeout(1_000);
    expect(failedResponses, failedResponses.join('\n')).toEqual([]);
    expect(failedRequests, failedRequests.join('\n')).toEqual([]);
    expect(browserErrors, browserErrors.join('\n')).toEqual([]);
    await expect(page.getByRole('heading', { name: 'Atención más cerca de ti.' })).toBeVisible();
    await expect(page.getByAltText('Mapa geográfico local de Honduras con 18 puntos departamentales')).toBeVisible();
    await expect(page.locator('.network-map-fallback button')).toHaveCount(18);
    await page.getByPlaceholder('Buscar departamento').fill('Cortés');
    await expect(page.locator('.department-list--cards article')).toHaveCount(1);
    await page.getByPlaceholder('Buscar departamento').clear();
    await page.locator('.network-map-fallback button').first().click();
    await expect(page.locator('.department-list--cards article.is-selected')).toHaveCount(1);
    await expect(page.locator('footer')).toBeVisible();
    await page.screenshot({ path: 'test-results/production-clinicas.png', fullPage: true });

});

productionOnly('production public and administrative routes remain functional', async ({ page }) => {
    const password = process.env.PRODUCTION_ADMIN_PASSWORD;
    expect(password, 'PRODUCTION_ADMIN_PASSWORD is required').toBeTruthy();
    const failures: string[] = [];
    page.on('console', message => {
        if (message.type() === 'error' || message.type() === 'warning') failures.push(`console.${message.type()}: ${message.text()}`);
    });
    page.on('pageerror', error => failures.push(`pageerror: ${error.message}`));
    page.on('requestfailed', request => failures.push(`requestfailed: ${request.method()} ${request.url()} ${request.failure()?.errorText}`));
    page.on('response', response => {
        if (response.status() >= 400) failures.push(`${response.status()} ${response.request().method()} ${response.url()}`);
    });

    for (const route of ['/', '/especialidades', '/clinica', '/clinicas', '/contacto', '/verificar', '/login']) {
        const response = await page.goto(route);
        expect(response?.status(), route).toBe(200);
        await expect(page.locator('body')).not.toBeEmpty();
    }

    await page.getByLabel('Correo electrónico').fill('cesar@gmail.com');
    await page.getByLabel('Contraseña').fill(password!);
    await page.getByRole('button', { name: /Entrar al panel/ }).click();
    await expect(page).toHaveURL(/\/admin(?:$|\/)/);
    for (const [route, heading] of [
        ['/admin', 'Centro de operaciones'],
        ['/admin/documents', 'Documentos médicos'],
        ['/admin/patients', 'Pacientes'],
        ['/admin/verifications', 'Historial de verificaciones'],
        ['/admin/settings/signature', 'Firma y sello institucional'],
        ['/admin/fiscal-authorizations', 'CAI, RTN y rangos NCF'],
    ]) {
        const response = await page.goto(route);
        expect(response?.status(), route).toBe(200);
        await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
    }
    await page.goto('/admin/settings/signature');
    if (await page.getByText('Activo combinado en uso').count() === 0) {
        const response = page.waitForResponse(item => item.request().method() === 'POST' && item.url().includes('/admin/settings/signature/import-combined'));
        await page.getByRole('button', { name: 'IMPORTAR FIRMA + SELLO DESDE DOCS' }).click();
        expect((await response).status()).toBeLessThan(400);
    }
    await expect(page.getByText('Activo combinado en uso')).toBeVisible();
    await page.screenshot({ path: 'test-results/production-admin-signature.png', fullPage: true });
    await page.goto('/admin/verifications');
    const verificationRows = page.locator('tbody');
    await expect(verificationRows.getByText('Enlace QR', { exact: true }).first()).toBeVisible();
    await expect(verificationRows.getByText('Código', { exact: true }).first()).toBeVisible();
    await expect(verificationRows.getByText('PDF', { exact: true }).first()).toBeVisible();
    await page.screenshot({ path: 'test-results/production-verification-logs.png', fullPage: true });
    expect(failures, failures.join('\n')).toEqual([]);
});
