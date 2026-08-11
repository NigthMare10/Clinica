import { expect, test } from '@playwright/test';

const text = `Por medio de la presente se hace constar que el paciente PRODUCTION QA 06, de 34 años de edad, con número de identidad 0000-0000-00006, acudió a consulta médica el día 11 de agosto de 2026 a las 10:30 a. m., por presentar malestar general y cefalea.

Durante la valoración médica se evidenciaron signos compatibles con síndrome viral agudo sin signos de alarma.

Se establece diagnóstico presuntivo de síndrome viral agudo, recomendándose reposo e hidratación adecuada.`;

test.use({ baseURL: 'https://clinicaprivadasanta-ana.com', acceptDownloads: true });

test('San Pedro Sula issued document uses its address and combined mark position', async ({ page }) => {
    const password = process.env.PRODUCTION_ADMIN_PASSWORD;
    expect(password).toBeTruthy();
    const failures: string[] = [];
    page.on('pageerror', error => failures.push(`pageerror: ${error.message}`));
    page.on('response', response => {
        if (response.status() >= 500) failures.push(`${response.status()} ${response.request().method()} ${response.url()}`);
    });

    await page.goto('/login');
    await page.getByLabel('Correo electrónico').fill('cesar@gmail.com');
    await page.getByLabel('Contraseña').fill(password!);
    await page.getByRole('button', { name: /Entrar al panel/ }).click();
    await expect(page).toHaveURL(/\/admin(?:$|\/)/);
    await page.goto('/admin/documents/generate/constancia');
    await page.getByLabel('Ubicación que emitirá el documento').selectOption({ label: 'Santa Ana San Pedro Sula · Cortés' });
    await page.getByLabel(/Pegue aquí/).fill(text);
    const analysis = page.waitForResponse(response => response.url().includes('/analyze'));
    await page.getByRole('button', { name: 'ANALIZAR TEXTO' }).click();
    expect((await analysis).status()).toBe(200);
    await expect(page.locator('.certificate-preview')).toContainText('Plaza Geo Sur, 13 Calle S.O. Barrio Paz Barahona. San Pedro Sula, Honduras');
    page.once('dialog', dialog => dialog.accept());
    const issue = page.waitForResponse(response => response.request().method() === 'POST' && response.url().includes('/admin/documents/generate/constancia'));
    await page.getByRole('button', { name: 'Firmar y emitir documento' }).click();
    expect((await issue).status()).toBeLessThan(400);
    await expect(page.getByText('Documento firmado y emitido.')).toBeVisible();
    const code = (await page.locator('body').innerText()).match(/CSA-2026-[A-Z0-9]{10}/)?.[0];
    expect(code).toBeTruthy();
    const downloadPromise = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Descargar emitido' }).click();
    await (await downloadPromise).saveAs(`test-results/production-qa-06-${code}.pdf`);
    expect(failures, failures.join('\n')).toEqual([]);
});
