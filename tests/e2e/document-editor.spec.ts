import { expect, test } from '@playwright/test';
import { assertNoPageOverflow, fixture, signIn } from './fixtures';

test.beforeEach(async ({ page }) => signIn(page));

test('issued document editor exposes its protected PDF, complete text and temporary preview', async ({ page }) => {
    await page.goto(`/admin/documents/${fixture('E2E_VALID_DOCUMENT_ID')}/edit`);
    await expect(page.getByRole('heading', { name: 'Editar constancia médica' })).toBeVisible();
    await expect(page.locator('iframe[title="PDF vigente"]')).toHaveAttribute('src', /\/admin\/documents\/.*\/preview/);
    const text = page.getByLabel('Texto completo');
    await expect(text).toHaveValue(/Por medio de la presente/);
    await text.fill('Por medio de la presente se hace constar que la paciente ficticia recibió atención actualizada.');
    await page.getByRole('button', { name: 'Actualizar vista previa' }).click();
    await expect(page.getByText('VISTA PREVIA - AÚN NO GUARDADO')).toBeVisible();
});

test('document editor remains usable on a 390px viewport', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`/admin/documents/${fixture('E2E_VALID_DOCUMENT_ID')}/edit`);
    await expect(page.getByRole('button', { name: 'Editar' })).toBeVisible();
    await expect(page.getByLabel('Texto completo')).toBeVisible();
    await page.getByRole('button', { name: 'Vista previa', exact: true }).click();
    await expect(page.locator('iframe[title="PDF vigente"]')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Guardar cambios y regenerar' })).toBeVisible();
    await assertNoPageOverflow(page);
});
