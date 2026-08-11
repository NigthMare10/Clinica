import { expect, test, type Page } from '@playwright/test';

const cases = [
    {
        kind: 'constancia', name: 'PRODUCTION QA 02', identity: '0000-0000-00002', days: null,
        text: `Por medio de la presente se hace constar que la paciente PRODUCTION QA 02, de 27 años de edad, con número de identidad 0000-0000-00002, se presentó a consulta médica el 11 de agosto de 2026 a las 9:15 a. m., por presentar cefalea, náuseas y sensibilidad a la luz.

Durante la valoración médica se evidenciaron signos compatibles con migraña sin signos de alarma.

De acuerdo con la evaluación clínica realizada, se establece diagnóstico presuntivo de migraña, recomendándose reposo, hidratación y tratamiento sintomático según indicación médica.`,
    },
    {
        kind: 'incapacidad', name: 'PRODUCTION QA 03', identity: '0000-0000-00003', days: 1,
        text: `Por medio de la presente se hace constar que el paciente PRODUCTION QA 03, de 35 años de edad, con número de identidad 0000-0000-00003, fue atendido el 11 de agosto de 2026 a las 14:30, por presentar fiebre, odinofagia y malestar general.

Durante la valoración médica se evidenciaron signos compatibles con faringitis aguda.

Se establece diagnóstico presuntivo de faringitis aguda, recomendándose reposo, hidratación y tratamiento sintomático. Por lo anterior, se extiende incapacidad médica por un (1) día, correspondiente al 11 de agosto de 2026.`,
    },
    {
        kind: 'incapacidad', name: 'PRODUCTION QA 04', identity: '0000-0000-00004', days: 2,
        text: `Por medio de la presente se hace constar que la paciente PRODUCTION QA 04, de 42 años de edad, con número de identidad 0000-0000-00004, acudió para valoración médica el 11 de agosto de 2026 a las 7:45 a. m., por presentar congestión nasal, tos y fatiga.

Durante la evaluación médica se evidenciaron signos compatibles con una infección respiratoria aguda.

Se establece diagnóstico presuntivo de infección respiratoria aguda, recomendándose reposo, líquidos y tratamiento sintomático. Por lo anterior, se extiende incapacidad médica por dos (2) días, correspondientes al 11 y 12 de agosto de 2026.`,
    },
    {
        kind: 'incapacidad', name: 'PRODUCTION QA 05', identity: '0000-0000-00005', days: 3,
        text: `Por medio de la presente se hace constar que el paciente PRODUCTION QA 05, de 30 años de edad, con número de identidad 0000-0000-00005, acudió a consulta médica el día 11 de agosto de 2026 a las 16:20, por presentar fiebre, dolor muscular y debilidad.

Durante la valoración médica se evidenciaron signos compatibles con síndrome viral agudo.

Se establece diagnóstico presuntivo de síndrome viral agudo, recomendándose reposo e hidratación. Por lo anterior, se extiende incapacidad médica por tres (3) días, correspondientes al 11, 12 y 13 de agosto de 2026.`,
    },
] as const;

test.use({ baseURL: 'https://clinicaprivadasanta-ana.com', acceptDownloads: true });

async function downloadCurrent(page: Page, name: string): Promise<string> {
    const body = await page.locator('body').innerText();
    const code = body.match(/CSA-2026-[A-Z0-9]{10}/)?.[0];
    expect(code, `No CSA code found for ${name}`).toBeTruthy();
    const downloadPromise = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Descargar emitido' }).click();
    const download = await downloadPromise;
    await download.saveAs(`test-results/${name.replaceAll(' ', '-').toLowerCase()}-${code}.pdf`);
    console.log(`${name}: ${code}`);
    return code!;
}

async function downloadExisting(page: Page, name: string): Promise<string | null> {
    await page.goto('/admin/documents');
    await page.getByLabel('Buscar documentos').fill('PRODUCTION');
    await page.waitForTimeout(900);
    const row = page.locator('tbody tr').filter({ hasText: name }).first();
    if (await row.count() === 0) return null;
    const code = (await row.locator('.code-wrap').innerText()).trim();
    expect(code).toMatch(/^CSA-2026-[A-Z0-9]{10}$/);
    const downloadPromise = page.waitForEvent('download');
    await row.getByRole('link', { name: 'Emitido', exact: true }).click();
    const download = await downloadPromise;
    await download.saveAs(`test-results/${name.replaceAll(' ', '-').toLowerCase()}-${code}.pdf`);
    console.log(`${name}: ${code}`);
    return code;
}

test('five persistent production QA documents issue and download', async ({ page }) => {
    const password = process.env.PRODUCTION_ADMIN_PASSWORD;
    expect(password, 'PRODUCTION_ADMIN_PASSWORD is required').toBeTruthy();
    const failures: string[] = [];
    page.on('console', message => {
        if (message.type() === 'error' || message.type() === 'warning') failures.push(`console.${message.type()}: ${message.text()}`);
    });
    page.on('pageerror', error => failures.push(`pageerror: ${error.message}`));
    page.on('requestfailed', request => {
        if (!request.url().includes('/download/')) failures.push(`requestfailed: ${request.method()} ${request.url()} ${request.failure()?.errorText}`);
    });
    page.on('response', response => {
        if (response.status() >= 400) failures.push(`${response.status()} ${response.request().method()} ${response.url()}`);
    });

    await page.goto('/login');
    await page.getByLabel('Correo electrónico').fill('cesar@gmail.com');
    await page.getByLabel('Contraseña').fill(password!);
    await page.getByRole('button', { name: /Entrar al panel/ }).click();
    await expect(page).toHaveURL(/\/admin(?:$|\/)/);

    expect(await downloadExisting(page, 'PRODUCTION QA 01'), 'PRODUCTION QA 01 was not persisted').toBeTruthy();

    for (const item of cases) {
        if (await downloadExisting(page, item.name)) {
            continue;
        }

        await page.goto(`/admin/documents/generate/${item.kind}`);
        await page.getByLabel(/Pegue aquí/).fill(item.text);
        const analysisResponse = page.waitForResponse(response => response.url().includes('/analyze'));
        await page.getByRole('button', { name: 'ANALIZAR TEXTO' }).click();
        expect((await analysisResponse).status()).toBe(200);
        await expect(page.locator('.extraction-fields label').filter({ hasText: 'Paciente' }).locator('input')).toHaveValue(item.name);
        await expect(page.locator('.extraction-fields label').filter({ hasText: 'Identidad' }).locator('input')).toHaveValue(item.identity.replaceAll('-', ''));
        await expect(page.locator('.extraction-fields label').filter({ hasText: 'Consulta' }).locator('input')).toHaveValue('2026-08-11');
        await expect(page.locator('.extraction-fields label').filter({ hasText: 'Diagnóstico' }).locator('textarea')).not.toHaveValue('');
        if (item.days) {
            await expect(page.locator('.extraction-fields label').filter({ hasText: 'Días' }).locator('input')).toHaveValue(String(item.days));
            await expect(page.locator('.extraction-fields label').filter({ hasText: 'Desde' }).locator('input')).toHaveValue('2026-08-11');
            await expect(page.locator('.extraction-fields label').filter({ hasText: 'Hasta' }).locator('input')).toHaveValue(`2026-08-${10 + item.days}`);
        }
        await page.screenshot({ path: `test-results/${item.name.replaceAll(' ', '-').toLowerCase()}-analyzed.png`, fullPage: true });

        page.once('dialog', dialog => dialog.accept());
        const issueResponse = page.waitForResponse(response => response.request().method() === 'POST' && response.url().includes(`/admin/documents/generate/${item.kind}`));
        await page.getByRole('button', { name: 'Firmar y emitir documento' }).click();
        expect((await issueResponse).status()).toBeLessThan(400);
        await expect(page.getByText('Documento firmado y emitido.')).toBeVisible();
        await downloadCurrent(page, item.name);
    }

    await page.screenshot({ path: 'test-results/production-document-list.png', fullPage: true });
    expect(failures, failures.join('\n')).toEqual([]);
});
