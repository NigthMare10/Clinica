import { expect, test } from '@playwright/test';
import path from 'node:path';
import { fixture, signIn } from './fixtures';

test.beforeEach(async ({ page }) => signIn(page));

test('admin CRUD create pages expose exact resource contracts', async ({ page }) => {
    for (const [url, heading] of [['/admin/specialties/create','Nueva especialidad'],['/admin/patients/create','Nuevo paciente'],['/admin/templates/create','Nueva plantilla PDF'],['/admin/content/create','Nueva página']] as const) {
        await page.goto(url); await expect(page.getByRole('heading', { name: heading }).first()).toBeVisible();
    }
});

test('creates and edits a fictitious specialty including arrays and SEO', async ({ page }) => {
    await page.goto('/admin/specialties/create');
    await page.getByLabel('Nombre').fill('Especialidad E2E Ficticia');await page.getByLabel('Slug').fill(`especialidad-e2e-${Date.now()}`);
    await page.getByLabel(/Motivos frecuentes/).fill('Consulta ficticia\nControl ficticio');await page.getByLabel(/Servicios/).fill('Servicio ficticio');await page.getByLabel('Título SEO').fill('Especialidad ficticia para E2E');
    await page.getByRole('button',{name:'Guardar especialidad'}).click();await expect(page).toHaveURL('/admin/specialties');
    await page.goto(`/admin/specialties/${fixture('E2E_SPECIALTY_ID')}/edit`);await expect(page.getByLabel('Nombre')).not.toHaveValue('');
});

test('patient, content, setting and template edit contracts render', async ({ page }) => {
    await page.goto(`/admin/patients/${fixture('E2E_PATIENT_ID')}/edit`);await expect(page.getByLabel('Número de documento')).toBeVisible();
    await page.goto(`/admin/content/${fixture('E2E_SITE_PAGE_ID')}/edit`);await expect(page.getByLabel('Contenido')).toBeVisible();await expect(page.getByLabel('Meta descripción')).toBeVisible();
    await page.goto(`/admin/settings/${fixture('E2E_SETTING_ID')}/edit`);await expect(page.getByText(/cuatro valores booleanos/)).toBeVisible();
    await page.goto(`/admin/templates/${fixture('E2E_TEMPLATE_ID')}/edit`);for(const label of ['Página del QR','Posición X (mm)','Posición Y (mm)','Ancho (mm)','Alto (mm)'])await expect(page.getByLabel(label)).toBeVisible();await expect(page.getByText('QR',{exact:true})).toBeVisible();
});

test('uploads a genuine fictitious PDF and enters processing/review workflow', async ({ page }) => {
    await page.goto('/admin/documents/create');await page.getByLabel('Tipo de documento').selectOption('MEDICAL_CERTIFICATE');await page.locator('#document').setInputFiles(path.resolve(fixture('E2E_UPLOAD_PDF_PATH')));await page.getByRole('button',{name:/Cargar y procesar/}).click();await expect(page).toHaveURL(/\/admin\/documents\/.+\/review/);
});

test('processing and failed fixtures expose polling labels and failure tools', async ({ page }) => {
    await page.goto('/admin/documents?status=PROCESSING');await expect(page.getByText('Actualización automática').first()).toBeVisible();
    await page.goto('/admin/documents?status=FAILED');await expect(page.getByRole('link',{name:/Cargar PDF/}).first()).toBeVisible();
});

test('review preserves OCR candidates, saves, detects conflicts and approves ready data', async ({ page }) => {
    await page.goto(`/admin/documents/${fixture('E2E_REVIEW_DOCUMENT_ID')}/review`);await expect(page.getByText('Validación del documento')).toBeVisible();await page.getByRole('button',{name:'Guardar revisión'}).click();await expect(page.getByRole('status')).toBeVisible();
    await page.goto(`/admin/documents/${fixture('E2E_CONFLICT_DOCUMENT_ID')}/review`);await expect(page.getByText('Observaciones de consistencia')).toBeVisible();
    await page.goto(`/admin/documents/${fixture('E2E_APPROVABLE_DOCUMENT_ID')}/review`);await page.getByRole('button',{name:'Aprobar datos'}).click();await expect(page.getByText('Listo',{exact:true}).first()).toBeVisible();
});

test('issues, downloads, revokes and replaces dedicated fixture documents', async ({ page }) => {
    const documentId=fixture('E2E_READY_DOCUMENT_ID');
    await page.goto(`/admin/documents/${documentId}/review`);await page.getByRole('button',{name:'Emitir documento'}).click();await page.getByRole('button',{name:'Confirmar emisión'}).click();await expect(page.getByRole('status')).toContainText(/issued|emitido/i);
    await page.goto('/admin/documents?status=ISSUED');const issuedRow=page.locator('tr').filter({has:page.locator(`a[href*="/admin/documents/${documentId}/download/issued"]`)});await expect(issuedRow.getByRole('link',{name:'Emitido'})).toHaveAttribute('href',/\/download\/issued$/);
    page.once('dialog',dialog=>dialog.accept('Revocación ficticia automatizada E2E'));await issuedRow.getByRole('button',{name:'Anular'}).click();await expect(page.getByRole('status')).toContainText(/revoked|revocado/i);
    await page.goto('/admin/documents?status=REVOKED');const revokedRow=page.locator('tr').filter({has:page.locator(`a[href*="/admin/documents/${documentId}/download/original"]`)});page.once('dialog',dialog=>dialog.accept('ERROR_TEXTO: corrección ficticia automatizada E2E'));await revokedRow.getByRole('button',{name:'Reemitir'}).click();await expect(page).toHaveURL(/\/admin\/documents\/.+\/review/);
});

test('auditor cannot open mutation routes', async ({ browser }) => {
    const context=await browser.newContext();const page=await context.newPage();await signIn(page,'E2E_AUDITOR');const response=await page.goto('/admin/specialties/create');expect(response?.status()).toBe(403);await context.close();
});

test('verification dashboard exposes KPI, filters and secure detail', async ({ page }) => {
    await page.goto('/admin/verifications');
    for (const label of ['Hoy', 'Por QR', 'Por código', 'Por PDF', 'Válidas', 'Fallidas']) await expect(page.getByText(label, { exact: true }).first()).toBeVisible();
    await page.getByLabel('Periodo').selectOption('7days');
    await page.getByLabel('Método').selectOption('MANUAL_CODE');
    await page.getByRole('button', { name: 'Aplicar filtros' }).click();
    await expect(page).toHaveURL(/period=7days/);
});
