import { expect, test } from '@playwright/test';

test.use({ baseURL: 'https://clinicaprivadasanta-ana.com' });

test('San Pedro Sula document selection displays its clinic address', async ({ page }) => {
    const password = process.env.PRODUCTION_ADMIN_PASSWORD;
    expect(password, 'PRODUCTION_ADMIN_PASSWORD is required').toBeTruthy();
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
    const selector = page.getByLabel('Ubicación que emitirá el documento');
    await selector.selectOption({ label: 'Santa Ana San Pedro Sula · Cortés' });
    await expect(page.locator('.certificate-preview')).toContainText('Plaza Geo Sur, 13 Calle S.O. Barrio Paz Barahona. San Pedro Sula, Honduras');
    await page.screenshot({ path: 'test-results/production-san-pedro-sula-preview.png', fullPage: true });
    expect(failures, failures.join('\n')).toEqual([]);
});
