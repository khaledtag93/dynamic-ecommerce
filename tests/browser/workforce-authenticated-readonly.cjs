'use strict';

/**
 * W-ORG-01b authenticated QAS acceptance (GET-only).
 * No account creation, payroll edits, approvals or destructive mutations.
 *
 * Run only with authorized QAS access, confirmed non-Production target,
 * an explicit disposable-QAS risk acceptance or verified backup/restore,
 * and seven synthetic personas. Never put credentials in Git.
 */
const fs = require('node:fs');
const path = require('node:path');
// Resolve Playwright only after local safety prerequisites pass.

const TARGET = 'https://v42.tag-marketplace.com';
const NAMES = [
  'alpha_manager', 'alpha_lead', 'alpha_worker',
  'beta_manager', 'beta_worker', 'operations_manager', 'finance_manager',
];
const EXPECTED_CODES = {
  alpha_manager: 'QA-ALPHA-MANAGER',
  alpha_lead: 'QA-ALPHA-LEAD',
  alpha_worker: 'QA-ALPHA-WORKER',
  beta_manager: 'QA-BETA-MANAGER',
  beta_worker: 'QA-BETA-WORKER',
  operations_manager: 'QA-OPS-MANAGER',
  finance_manager: 'QA-FINANCE-MANAGER',
};
const VIEWS = [
  { name: 'desktop', width: 1366, height: 800 },
  { name: 'mobile', width: 390, height: 844 },
];
const LOCALES = ['en', 'ar'];
const outputDir = path.resolve(__dirname, '../../storage/logs');

function preflight() {
  const secretFile = process.env.FLOWRA_QA_PERSONAS_FILE;
  if (!secretFile || !path.isAbsolute(secretFile)) {
    throw new Error('BLOCKED: set FLOWRA_QA_PERSONAS_FILE to an absolute, local, private JSON path OUTSIDE the repository.');
  }
  const repo = path.resolve(__dirname, '../..');
  const full = path.resolve(secretFile);
  const rel = path.relative(repo, full);
  if (rel === '' || (!rel.startsWith('..' + path.sep) && rel !== '..' && !path.isAbsolute(rel))) {
    throw new Error('BLOCKED: persona credentials must live outside the Git repository.');
  }
  const cfg = JSON.parse(fs.readFileSync(full, 'utf8'));
  const protectedLane = cfg.backup_verified === true && cfg.isolated_restore_verified === true;
  const disposableLane = cfg.qa_data_policy === 'disposable-qas' &&
    cfg.owner_accepts_qa_data_loss === true &&
    cfg.qas_app_database_confirmed === true;
  if (cfg.environment !== 'qas' ||
      cfg.base_url !== TARGET ||
      cfg.fixture_namespace !== 'QA-WORG01B' ||
      !cfg.personas ||
      !(protectedLane || disposableLane)) {
    throw new Error('BLOCKED: wrong QAS target/namespace or neither a verified restore nor explicit disposable-QAS risk acceptance with confirmed app DB.');
  }
  for (const key of NAMES) {
    const p = cfg.personas[key];
    if (!p || typeof p.email !== 'string' || !p.email.endsWith('@flowra-qa.invalid') ||
        typeof p.password !== 'string' || p.password.length < 10 ||
        p.employee_code !== EXPECTED_CODES[key] ||
        !Number.isSafeInteger(p.profile_id) || p.profile_id <= 0) {
      throw new Error('BLOCKED: missing, invalid or non-synthetic persona: ' + key);
    }
  }
  const ids = NAMES.map(key => cfg.personas[key].profile_id);
  if (new Set(ids).size !== NAMES.length) {
    throw new Error('BLOCKED: repeated employee profile ID across personas.');
  }
  return cfg;
}

function assertionsFor(persona, cfg) {
  const id = k => cfg.personas[k].profile_id;
  const base = '/admin/workforce/';
  const checks = [];
  function add(route, status, include = [], exclude = []) {
    checks.push({ route: base + route, status, include, exclude });
  }
  if (persona === 'alpha_manager') {
    add('employees', 200, ['QA-ALPHA-WORKER','QA-ALPHA-LEAD'], ['QA-BETA-WORKER']);
    add('attendance', 200, ['QA-ALPHA-WORKER'], ['QA-BETA-WORKER']);
    add('employees/' + id('beta_worker') + '/edit', 403);
    add('payroll/compensation', 403);
    add('leave-types', 403);
  } else if (persona === 'alpha_lead') {
    add('employees', 200, ['QA-ALPHA-WORKER'], ['QA-BETA-WORKER']);
    add('employees/' + id('beta_worker') + '/edit', 403);
    add('payroll/compensation', 403);
  } else if (persona === 'beta_manager') {
    add('employees', 200, ['QA-BETA-WORKER'], ['QA-ALPHA-WORKER']);
    add('employees/' + id('alpha_worker') + '/edit', 403);
    add('payroll/compensation', 403);
    add('leave-types', 403);
  } else if (persona === 'alpha_worker' || persona === 'beta_worker') {
    add('time-clock', 200);
    add('my-schedule', 200);
    add('my-leave', 200);
    add('employees', 403);
    add('payroll/compensation', 403);
  } else if (persona === 'operations_manager') {
    add('employees', 200, ['QA-ALPHA-WORKER','QA-BETA-WORKER']);
    add('leave-types', 200);
    add('payroll/compensation', 403);
  } else if (persona === 'finance_manager') {
    add('payroll/compensation', 200, ['QA-ALPHA-WORKER','QA-BETA-WORKER']);
    add('employees', 403);
    add('leave-types', 403);
  }
  return checks;
}

async function logIn(page, person) {
  const r = await page.goto(TARGET + '/login', {waitUntil:'domcontentloaded', timeout:30000});
  if (!r || r.status() !== 200) throw new Error('Login form unavailable');
  await page.locator('input[name="email"]').fill(person.email);
  await page.locator('input[name="password"]').fill(person.password);
  await Promise.all([
    page.waitForURL(u => new URL(u).pathname !== '/login', { timeout:20000 }),
    page.locator('form[action] button[type="submit"]').first().click(),
  ]);
  if (new URL(page.url()).pathname === '/login') throw new Error('Login did not establish session');
}

async function main() {
  // Validate ALL prerequisites before making any request (including login).
  const cfg = preflight();
  if (process.argv.includes('--preflight')) {
    console.log(JSON.stringify({gate:'PASS',environment:'qas',personas:NAMES.length,mode:'GET-only'}));
    return;
  }
  const {chromium} = require(process.env.FLOWRA_PLAYWRIGHT_MODULE || 'playwright-core');
  fs.mkdirSync(outputDir, {recursive:true});
  const opts = {headless:true,args:['--no-first-run','--no-default-browser-check']};
  if (process.env.FLOWRA_CHROME_PATH) opts.executablePath=process.env.FLOWRA_CHROME_PATH;
  else opts.channel='chrome';

  const browser = await chromium.launch(opts);
  const cases = [];
  try {
    for (const key of NAMES) for (const locale of LOCALES) for (const vp of VIEWS) {
      const ctx = await browser.newContext({viewport:{width:vp.width,height:vp.height}});
      try {
        const page = await ctx.newPage();
        await logIn(page, cfg.personas[key]);
        await page.goto(TARGET + '/locale/' + locale + '?redirect=%2Fadmin%2Fworkforce%2Fmy-schedule',
          {waitUntil:'domcontentloaded', timeout:30000});
        const dir = await page.locator('html').getAttribute('dir');
        cases.push({role:key,locale,view:vp.name,check:'html-direction',
          pass:dir === (locale === 'ar' ? 'rtl' : 'ltr'),actual:dir});
        for (const check of assertionsFor(key, cfg)) {
          try {
            const response = await page.goto(TARGET + check.route,
              {waitUntil:'domcontentloaded',timeout:30000});
            const status = response ? response.status() : 0;
            const body = ((await page.locator('body').textContent()) || '').slice(0,150000);
            const pass = status === check.status &&
              check.include.every(code => body.includes(code)) &&
              check.exclude.every(code => !body.includes(code));
            cases.push({role:key,locale,view:vp.name,check:check.route,
              expected:check.status,actual:status,pass});
          } catch (error) {
            cases.push({role:key,locale,view:vp.name,check:check.route,
              pass:false,error:error.name});
          }
        }
      } catch (error) {
        cases.push({role:key,locale,view:vp.name,check:'login-or-locale',
          pass:false,error:error.name});
      } finally {
        await ctx.close();
      }
    }
  } finally {
    await browser.close();
  }
  const summary = {scope:'authenticated-qas-readonly',commit:process.env.FLOWRA_QA_SOURCE_SHA || 'unknown',
    target:TARGET, total:cases.length, passed:cases.filter(c => c.pass).length,
    failed:cases.filter(c => !c.pass).length, cases};
  const report = path.join(outputDir,'worg01b-auth-readonly-results.json');
  fs.writeFileSync(report, JSON.stringify(summary,null,2));
  console.log(JSON.stringify({target:TARGET,scope:summary.scope,total:summary.total,
    passed:summary.passed,failed:summary.failed,report},null,2));
  if (summary.failed) process.exitCode=1;
}

main().catch(error=>{
  // Do not log request objects, cookies, credential file content or secrets.
  console.error(error.message.startsWith('BLOCKED:')?error.message:'ACCEPTANCE_BLOCKED: '+error.name);
  process.exitCode=2;
});
