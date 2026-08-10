import { expect, test } from '@playwright/test';
import { assertNoPageOverflow } from './fixtures';

const viewports=[[1920,1080],[1440,900],[1366,768],[1024,768],[768,1024],[430,932],[390,844],[360,800]] as const;

for(const [width,height] of viewports){
    test(`public and admin-entry pages fit ${width}x${height}`,async({page})=>{
        await page.setViewportSize({width,height});
        for(const url of ['/','/especialidades','/verificar','/login']){await page.goto(url);await assertNoPageOverflow(page);}
        await page.goto('/verificar');await page.screenshot({path:`tests/screenshots/verify-${width}x${height}.png`,fullPage:true});
    });
}

test('mobile navigation remains usable at the narrowest viewport',async({page})=>{await page.setViewportSize({width:360,height:800});await page.goto('/');await page.getByRole('button',{name:'Abrir menú'}).click();await expect(page.getByRole('navigation',{name:'Navegación principal'})).toBeVisible();await assertNoPageOverflow(page);});
