'use strict';

// QAS-only authentication diagnostic. Never prints credentials/cookie values.
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require(process.env.FLOWRA_PLAYWRIGHT_MODULE || 'playwright-core');
const target = 'https://v42.tag-marketplace.com';
const file = process.env.FLOWRA_QA_PERSONAS_FILE;
if (!file || !path.isAbsolute(file)) throw new Error('BLOCKED: private fixture file not supplied');
const cfg = JSON.parse(fs.readFileSync(file, 'utf8'));
if (cfg.environment !== 'qas' || cfg.base_url !== target ||
    cfg.fixture_namespace !== 'QA-WORG01B' ||
    cfg.qa_data_policy !== 'disposable-qas' || cfg.qas_app_database_confirmed !== true) {
  throw new Error('BLOCKED: QAS fixture identity mismatch');
}
const p = cfg.personas.alpha_manager;
if (!p || !p.email.endsWith('@flowra-qa.invalid')) throw new Error('BLOCKED: synthetic login missing');
(async()=>{
  const browser = await chromium.launch({headless:true, channel:'chrome'});
  try {
    const ctx = await browser.newContext({viewport:{width:1366,height:800}});
    const page = await ctx.newPage();
    let requestSeen = 0;
    let responseStatus = null;
    let redirectPath = null;
    page.on('response', res => {
      const u = new URL(res.url());
      if (u.origin === target && u.pathname === '/login' && res.request().method() === 'POST') {
        requestSeen++;
        responseStatus = res.status();
        const location = res.headers()['location'];
        if(location) {
          try {redirectPath = new URL(location,target).pathname;} catch {}
        }
      }
    });
    const get = await page.goto(target+'/login', {waitUntil:'domcontentloaded',timeout:20000});
    await page.locator('input[name="email"]').fill(p.email);
    await page.locator('input[name="password"]').fill(p.password);
    await page.locator('input[name="password"]').locator('xpath=ancestor::form').locator('button[type="submit"]').first().click();
    await page.waitForTimeout(2500);
    const names = (await ctx.cookies()).map(c=>c.name);
    const result = {
      qas: true, loginPageGetStatus:get?.status()||0,
      loginPostObserved:requestSeen > 0,
      loginPostStatus:responseStatus,
      loginRedirectPath:redirectPath,
      finalPath:new URL(page.url()).pathname,
      loginFormStillPresent:(await page.locator('input[name="password"]').count())>0,
      hasSessionCookie:names.some(n=>n.includes('session')),
      errorMarkerPresent:(await page.locator('.alert-danger, .invalid-feedback, [role="alert"]').count())>0
    };
    console.log(JSON.stringify(result,null,2));
    await ctx.close();
  } finally {await browser.close();}
})().catch(err=>{console.error('LOGIN_DIAGNOSTIC_FAILED='+err.name);process.exitCode=2;});
