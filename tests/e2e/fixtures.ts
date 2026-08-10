import { expect, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
import path from 'node:path';

let generated: Record<string, string> | null = null;

export function fixture(name: string): string {
    if (!generated) generated = JSON.parse(readFileSync(path.resolve('storage/framework/testing/e2e-fixtures.json'), 'utf8'));
    const value = process.env[name] || generated?.[name];
    if (!value) throw new Error(`${name} is required. Provision fictitious E2E data outside production and provide its identifier.`);
    return value;
}

export async function signIn(page: Page, prefix = 'E2E_ADMIN') {
    await page.goto('/login');
    await page.getByLabel('Correo electrónico').fill(fixture(`${prefix}_EMAIL`));
    await page.getByLabel('Contraseña').fill(fixture(`${prefix}_PASSWORD`));
    await page.getByRole('button', { name: /Entrar al panel/ }).click();
    await expect(page).toHaveURL(/\/admin/);
}

export async function assertNoPageOverflow(page: Page) {
    const widths = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }));
    expect(widths.scroll, `horizontal overflow: ${JSON.stringify(widths)}`).toBeLessThanOrEqual(widths.client + 1);
}
