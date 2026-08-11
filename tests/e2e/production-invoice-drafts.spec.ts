import { expect, test } from '@playwright/test';

const quickText = `Por medio de la presente se hace constar que el paciente INVOICE QA QUICK TEMP, de 33 años de edad, con número de identidad 0000-0000-00991, acudió a consulta médica el día 11 de agosto de 2026 a las 11:15 a. m., por presentar malestar general y cefalea.

Durante la valoración médica se evidenciaron signos compatibles con síndrome viral agudo sin signos de alarma.

Se establece diagnóstico presuntivo de síndrome viral agudo, recomendándose reposo e hidratación adecuada.`;

test.use({ baseURL: 'https://clinicaprivadasanta-ana.com' });

test('manual and quick invoice flows create drafts without NCF issuance', async ({ page }) => {
    const password = process.env.PRODUCTION_ADMIN_PASSWORD;
    expect(password).toBeTruthy();
    const failures: string[] = [];
    page.on('pageerror', error => failures.push(`pageerror: ${error.message}`));
    page.on('response', response => {
        if (response.status() >= 400) failures.push(`${response.status()} ${response.request().method()} ${response.url()}`);
    });

    await page.goto('/login');
    await page.getByLabel('Correo electrónico').fill('cesar@gmail.com');
    await page.getByLabel('Contraseña').fill(password!);
    await page.getByRole('button', { name: /Entrar al panel/ }).click();
    await expect(page).toHaveURL(/\/admin(?:$|\/)/);

    await page.goto('/admin/invoices/create');
    await page.locator('.form-grid .field').filter({ hasText: 'Cliente' }).locator('input').fill('INVOICE QA MANUAL TEMP');
    await page.getByPlaceholder('Descripción manual').fill('Servicio QA temporal');
    await page.getByLabel('Precio').fill('125');
    const manualResponse = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/admin/invoices'));
    await page.getByRole('button', { name: 'Guardar borrador' }).click();
    expect((await manualResponse).status()).toBe(201);
    await expect(page).toHaveURL(/\/admin\/invoices\/[0-9a-f-]+/);
    await page.screenshot({ path: 'test-results/production-manual-invoice-draft.png', fullPage: true });

    await page.goto('/admin/documents/generate/constancia');
    await page.getByLabel(/Pegue aquí/).fill(quickText);
    const analysisResponse = page.waitForResponse(response => response.url().includes('/analyze'));
    await page.getByRole('button', { name: 'ANALIZAR TEXTO' }).click();
    expect((await analysisResponse).status()).toBe(200);
    await page.getByText('Crear borrador de factura al finalizar').click();
    page.once('dialog', dialog => dialog.accept());
    const quickResponse = page.waitForResponse(response => response.request().method() === 'POST' && response.url().includes('/admin/documents/generate/constancia'));
    await page.getByRole('button', { name: 'Emitir documento y crear borrador' }).click();
    expect((await quickResponse).status()).toBeLessThan(400);
    await expect(page.getByText('Documento emitido y borrador de factura creado.')).toBeVisible();
    await page.screenshot({ path: 'test-results/production-quick-invoice-draft.png', fullPage: true });
    expect(failures, failures.join('\n')).toEqual([]);
});
