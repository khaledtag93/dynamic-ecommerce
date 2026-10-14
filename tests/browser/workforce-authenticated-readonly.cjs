'use strict';

/**
 * W-ORG-01b authenticated QAS acceptance: GET-only by default.
 * Optional --mutation-denials requires explicit QA-only approval and sends
 * deliberately invalid payloads to QA-record targets; 403 is mandatory.
 * No account creation or destructive mutations by default.
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
    // Never infer role scope from page one in a populated QAS environment.
    const searchable = ['employees', 'attendance', 'payroll/compensation'];
    if (status === 200 && searchable.includes(route)) route += '?search=QA-';
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

// Explicitly opt-in to mutation-denial checks; every target is a QA fixture.
// Payloads omit required fields, preventing accidental valid employee updates.
function mutationAssertionsFor(role, cfg) {
  const ids = cfg.personas;
  const leave = cfg.created_row_ids?.employee_leave_requests;
  if (!Array.isArray(leave) || leave.length !== 2 ||
      !leave.every(id => Number.isSafeInteger(id) && id > 0)) {
    throw new Error('BLOCKED: synthetic leave fixture IDs are missing');
  }
  const base = '/admin/workforce/';
  const employeeUpdate = id => ({method:'PUT',route:base+'employees/'+id});
  const leaveApprove = id => ({method:'PATCH',route:base+'leave/'+id+'/approve'});
  const compensationUpdate = id => ({method:'PUT',route:base+'payroll/compensation/'+id});
  switch (role) {
    case 'alpha_manager':
      return [employeeUpdate(ids.beta_worker.profile_id),leaveApprove(leave[0])];
    case 'alpha_lead':
      return [employeeUpdate(ids.beta_worker.profile_id)];
    case 'beta_manager':
      return [employeeUpdate(ids.alpha_worker.profile_id),leaveApprove(leave[0])];
    case 'alpha_worker':
    case 'beta_worker':
      return [leaveApprove(leave[0])];
    case 'operations_manager':
      return [compensationUpdate(ids.alpha_worker.profile_id)];
    case 'finance_manager':
      return [employeeUpdate(ids.alpha_worker.profile_id)];
    default: throw new Error('BLOCKED: unknown mutation persona');
  }
}

async function runMutationDenials(page, role, cfg) {
  const ready = await page.goto(TARGET+'/admin', {waitUntil:'domcontentloaded',timeout:30000});
  if (!ready || ready.status() !== 200 ||
      !await page.locator('meta[name="csrf-token"]').count()) {
    throw new Error('BLOCKED: authenticated admin CSRF token unavailable');
  }
  const cases = [];
  for (const assertion of mutationAssertionsFor(role,cfg)) {
    const outcome = await page.evaluate(async ({route,method}) => {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      if (!token) return {status:0,reason:'csrf-missing'};
      const response = await fetch(route, {
        method, credentials:'same-origin', redirect:'manual',
        headers:{'X-CSRF-TOKEN':token,'Accept':'application/json','Content-Type':'application/json'},
        body:JSON.stringify({})
      });
      return {status:response.status,redirected:response.redirected};
    }, assertion);
    cases.push({role,locale:'en',view:'desktop',check:assertion.method+' '+assertion.route,
      expected:403,actual:outcome.status,pass:outcome.status===403&&!outcome.redirected});
  }
  return cases;
}

async function logIn(page, person) {
  let r;
  try { r = await page.goto(TARGET + '/login', {waitUntil:'domcontentloaded', timeout:15000}); }
  catch { throw new Error('LOGIN_PAGE_NAVIGATION_TIMEOUT'); }
  if (!r || r.status() !== 200) throw new Error('Login form unavailable');
  await page.locator('input[name="email"]').fill(person.email);
  await page.locator('input[name="password"]').fill(person.password);
  try {
    await Promise.all([
      page.waitForURL(u => new URL(u).pathname !== '/login', { timeout:15000 }),
      page.locator('input[name="password"]').locator('xpath=ancestor::form').locator('button[type="submit"]').first().click(),
    ]);
  } catch { throw new Error('LOGIN_SUBMIT_NO_REDIRECT'); }
  if (new URL(page.url()).pathname === '/login') throw new Error('Login did not establish session');
}

async function main() {
  // Validate ALL prerequisites before making any request (including login).
  const cfg = preflight();
  if (process.argv.includes('--preflight')) {
    console.log(JSON.stringify({gate:'PASS',environment:'qas',personas:NAMES.length,mode:'GET-only'}));
    return;
  }
  const mutationOnly = process.argv.includes('--mutation-denials');
  if (mutationOnly && (process.env.FLOWRA_QA_ALLOW_MUTATION_DENIAL_TESTS !== 'YES-QAS-ONLY' ||
      cfg.qa_data_policy !== 'disposable-qas' || cfg.owner_accepts_qa_data_loss !== true ||
      cfg.qas_app_database_confirmed !== true)) {
    throw new Error('BLOCKED: mutation tests require explicit disposable-QAS approval and verified database');
  }
  const {chromium} = require(process.env.FLOWRA_PLAYWRIGHT_MODULE || 'playwright-core');
  fs.mkdirSync(outputDir, {recursive:true});
  const opts = {headless:true,args:['--no-first-run','--no-default-browser-check']};
  if (process.env.FLOWRA_CHROME_PATH) opts.executablePath=process.env.FLOWRA_CHROME_PATH;
  else opts.channel='chrome';

  const roleOption = process.argv.find(arg => arg.startsWith('--only-role='));
  const selectedRole = roleOption ? roleOption.split('=', 2)[1] : null;
  if (selectedRole && !NAMES.includes(selectedRole)) throw new Error('BLOCKED: unknown role filter');
  const selectedRoles = selectedRole ? [selectedRole] : NAMES;
  const localeOption = process.argv.find(arg => arg.startsWith('--only-locale='));
  const selectedLocale = localeOption ? localeOption.split('=', 2)[1] : null;
  if (selectedLocale && !LOCALES.includes(selectedLocale)) throw new Error('BLOCKED: unknown locale');
  const selectedLocales = mutationOnly ? ['en'] : (selectedLocale ? [selectedLocale] : LOCALES);
  const viewOption = process.argv.find(arg => arg.startsWith('--only-view='));
  const selectedView = viewOption ? viewOption.split('=', 2)[1] : null;
  if (selectedView && !VIEWS.some(v => v.name === selectedView)) throw new Error('BLOCKED: unknown viewport');
  const selectedViews = mutationOnly ? [VIEWS[0]] : (selectedView ? VIEWS.filter(v => v.name === selectedView) : VIEWS);
  const browser = await chromium.launch(opts);
  const cases = [];
  try {
    for (const key of selectedRoles) for (const locale of selectedLocales) for (const vp of selectedViews) {
      const ctx = await browser.newContext({viewport:{width:vp.width,height:vp.height}});
      let stage = 'login';
      try {
        const page = await ctx.newPage();
        await logIn(page, cfg.personas[key]);
        if (mutationOnly) {
          stage = 'mutation-denials';
          cases.push(...await runMutationDenials(page, key, cfg));
        } else {
        stage = 'locale';
        await page.goto(TARGET + '/locale/' + locale + '?redirect=%2Fadmin%2Fworkforce%2Fmy-schedule',
          {waitUntil:'domcontentloaded', timeout:30000});
        stage = 'authorization';
        const dir = await page.locator('html').getAttribute('dir');
        cases.push({role:key,locale,view:vp.name,check:'html-direction',
          pass:dir === (locale === 'ar' ? 'rtl' : 'ltr'),actual:dir});
        for (const check of assertionsFor(key, cfg)) {
          try {
            const response = await page.goto(TARGET + check.route,
              {waitUntil:'domcontentloaded',timeout:30000});
            const status = response ? response.status() : 0;
            const finalPath = new URL(page.url()).pathname;
            const redirected = finalPath !== new URL(TARGET + check.route).pathname;
            const loginFormVisible = await page.locator('input[name="password"]').count() > 0;
            const body = ((await page.locator('body').textContent()) || '').slice(0,150000);
            const pass = !redirected && !loginFormVisible && status === check.status &&
              check.include.every(code => body.includes(code)) &&
              check.exclude.every(code => !body.includes(code));
            cases.push({role:key,locale,view:vp.name,check:check.route,
              expected:check.status,actual:status,finalPath,redirected,loginFormVisible,pass});
          } catch (error) {
            cases.push({role:key,locale,view:vp.name,check:check.route,
              pass:false,error:error.name});
          }
        }
        }
      } catch (error) {
        cases.push({role:key,locale,view:vp.name,check:'login-or-locale',
          pass:false,stage,error:error.message.startsWith('LOGIN_') ? error.message : error.name});
      } finally {
        await ctx.close();
      }
    }
  } finally {
    await browser.close();
  }
  const summary = {scope:mutationOnly?'authenticated-qas-mutation-denials':'authenticated-qas-readonly',commit:process.env.FLOWRA_QA_SOURCE_SHA || 'unknown',
    target:TARGET, total:cases.length, passed:cases.filter(c => c.pass).length,
    failed:cases.filter(c => !c.pass).length, cases};
  const suffix = selectedRole ? '-' + [selectedRole, selectedLocale || 'all', selectedView || 'all'].join('-') : '';
  const report = path.join(outputDir,'worg01b-auth-'+(mutationOnly?'mutation-denials':'readonly')+'-results' + suffix + '.json');
  fs.writeFileSync(report, JSON.stringify(summary,null,2));
  console.log(JSON.stringify({target:TARGET,scope:summary.scope,total:summary.total,
    passed:summary.passed,failed:summary.failed,report},null,2));
  if (summary.failed) process.exitCode=1;
}

// Export pure, offline role assertions for regression without connecting to QAS.
module.exports = {NAMES, EXPECTED_CODES, assertionsFor, mutationAssertionsFor};
if (require.main === module) {
  main().catch(error=>{
    // Do not log request objects, cookies, credential file content or secrets.
    console.error(error.message.startsWith('BLOCKED:')?error.message:'ACCEPTANCE_BLOCKED: '+error.name);
    process.exitCode=2;
  });
}
