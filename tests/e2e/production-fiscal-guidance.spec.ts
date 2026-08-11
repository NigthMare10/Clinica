import { expect, test } from '@playwright/test';

test.use({ baseURL: 'https://clinicaprivadasanta-ana.com' });

test('draft invoices use the central HN-08 fiscal authorization', async ({ page }) => {
    const password = process.env.PRODUCTION_ADMIN_PASSWORD;
    expect(password).toBeTruthy();
    const failures: string[] = [];
    page.on('pageerror', error => failures.push(`pageerror: ${error.message}`));
    page.on('response', response => {
        if (response.status() >= 500) failures.push(`${response.status()} ${response.url()}`);
    });

    await page.goto('/login');
    await page.getByLabel('Correo electrónico').fill('cesar@gmail.com');
    await page.getByLabel('Contraseña').fill(password!);
    await page.getByRole('button', { name: /Entrar al panel/ }).click();
    await expect(page).toHaveURL(/\/admin(?:$|\/)/);
    await page.goto('/admin/invoices/019ff2d6-fdf6-720a-8cbc-1782acd1dd0c');
    await expect(page.getByRole('button', { name: 'Confirmar emisión' })).toBeEnabled();
    await page.getByRole('button', { name: 'Confirmar emisión' }).click();
    await expect(page.getByRole('heading', { name: 'Confirmar emisión' })).toBeVisible();
    await expect(page.locator('.modal-card select option').filter({ hasText: '008-001-01- · próximo 134099' })).toHaveCount(1);
    await page.getByRole('button', { name: 'Cancelar' }).click();
    await page.screenshot({ path: 'test-results/production-central-fiscal-authorization.png', fullPage: true });
    expect(failures, failures.join('\n')).toEqual([]);
});
