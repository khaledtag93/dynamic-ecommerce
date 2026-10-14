'use strict';

const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {NAMES, EXPECTED_CODES, assertionsFor, mutationAssertionsFor} =
  require('./workforce-authenticated-readonly.cjs');

const fixture = {
  personas: Object.fromEntries(NAMES.map((name, i) => [name, {
    profile_id: 101 + i, employee_code: EXPECTED_CODES[name],
  }])),
  created_row_ids: {employee_leave_requests: [201, 202]},
};

test('seven unique QA personas match Workforce matrix', () => {
  assert.equal(NAMES.length, 7);
  assert.equal(new Set(NAMES).size, 7);
  assert.equal(new Set(Object.values(EXPECTED_CODES)).size, 7);
  assert.ok(Object.values(EXPECTED_CODES).every(code => code.startsWith('QA-')));
});

test('read-only role matrix includes permissions, cross-team and payroll segregation', () => {
  for (const name of NAMES) {
    const checks = assertionsFor(name, fixture);
    assert.ok(checks.length >= 3, name);
    assert.ok(checks.every(c => c.route.startsWith('/admin/workforce/')));
    assert.ok(checks.some(c => c.status === 403), name);
  }
  const alpha = assertionsFor('alpha_manager', fixture);
  assert.ok(alpha.some(c => c.route.endsWith('/employees/105/edit') && c.status === 403));
  assert.ok(alpha.some(c => c.route.startsWith('/admin/workforce/employees?search=QA-')));
  const finance = assertionsFor('finance_manager', fixture);
  assert.ok(finance.some(c => c.route.includes('payroll/compensation?search=QA-')));
  assert.ok(finance.some(c => c.route.endsWith('/employees') && c.status === 403));
});

test('200 list checks use deterministic QA search instead of first page', () => {
  for (const name of NAMES) {
    for (const c of assertionsFor(name, fixture)) {
      if (c.status === 200 && /\/(employees|attendance|compensation)(\?|$)/.test(c.route)) {
        assert.ok(c.route.endsWith('?search=QA-'), c.route);
      }
    }
  }
});

test('mutation-denial matrix targets exclusively QA-created records', () => {
  const profileIds = new Set(NAMES.map(name => fixture.personas[name].profile_id));
  const leaveIds = new Set(fixture.created_row_ids.employee_leave_requests);
  for (const name of NAMES) {
    const specs = mutationAssertionsFor(name, fixture);
    assert.ok(specs.length > 0, name);
    for (const spec of specs) {
      assert.match(spec.route, /^\/admin\/workforce\//);
      assert.ok(['PUT', 'PATCH'].includes(spec.method), spec.method);
      const targetId = Number(spec.route.match(/\/(\d+)(?:\/approve)?$/)?.[1]);
      assert.ok(profileIds.has(targetId) || leaveIds.has(targetId), spec.route);
    }
  }
  assert.ok(mutationAssertionsFor('alpha_manager',fixture)
    .some(s=>s.route==='/admin/workforce/leave/201/approve'));
});

test('mutation-denial fixtures reject missing or malformed QA leave IDs', () => {
  assert.throws(() => mutationAssertionsFor('beta_manager', {
    ...fixture, created_row_ids:{employee_leave_requests:[201]},
  }), /BLOCKED/);
});

test('PHP fixture runner uses correct Laravel root and guarded cleanup dry-run', () => {
  const root = path.resolve(__dirname,'../..');
  const php = fs.readFileSync(path.join(root,'ops/qa/worg01b_personas.php'),'utf8');
  assert.match(php, /\$root = dirname\(__DIR__, 2\);/);
  assert.doesNotMatch(php,/\$root = dirname\(__DIR__, 3\);/);
  assert.match(php, /'--cleanup-dry-run'/);
  assert.match(php, /qaCleanupImpact\(\$ids, \$expectedDb\)/);
  assert.match(php, /information_schema\.KEY_COLUMN_USAGE/);
  assert.match(php, /cleanup row count reconciliation failed/);
  const ignore = fs.readFileSync(path.join(root,'.gitignore'),'utf8');
  assert.match(ignore,/\/storage\/app\/private\//);
});

test('browser preflight fails closed without private manifest and never launches Chrome', () => {
  const {spawnSync} = require('node:child_process');
  const script = path.join(__dirname,'workforce-authenticated-readonly.cjs');
  const result = spawnSync(process.execPath,[script,'--preflight'],{
    encoding:'utf8', timeout:5000,
    env:{...process.env,FLOWRA_QA_PERSONAS_FILE:''},
  });
  assert.equal(result.status,2);
  assert.match(result.stderr,/BLOCKED:/);
});

test('mutation-denial mode requires explicit QAS consent before browser launch', () => {
  const os = require('node:os');
  const {spawnSync} = require('node:child_process');
  const temp = fs.mkdtempSync(path.join(os.tmpdir(),'flowra-qa-contract-'));
  const manifest = path.join(temp,'synthetic.json');
  const cfg = {
    environment:'qas',
    base_url:'https://v42.tag-marketplace.com',
    fixture_namespace:'QA-WORG01B',
    qa_data_policy:'disposable-qas',
    owner_accepts_qa_data_loss:true,
    qas_app_database_confirmed:true,
    personas:Object.fromEntries(NAMES.map((name,i) => [name,{
      profile_id:101+i,employee_code:EXPECTED_CODES[name],
      email:name+'.worg01b@flowra-qa.invalid',password:'synthetic-test-only-not-secret'
    }])),
    created_row_ids:{employee_leave_requests:[201,202]}
  };
  try {
    fs.writeFileSync(manifest,JSON.stringify(cfg),{mode:0o600});
    const result = spawnSync(process.execPath,[
      path.join(__dirname,'workforce-authenticated-readonly.cjs'),'--mutation-denials'
    ],{encoding:'utf8',timeout:5000,env:{
      ...process.env,FLOWRA_QA_PERSONAS_FILE:manifest,FLOWRA_QA_ALLOW_MUTATION_DENIAL_TESTS:''
    }});
    assert.equal(result.status,2);
    assert.match(result.stderr,/BLOCKED: mutation tests require explicit/);
  } finally {
    fs.rmSync(temp,{recursive:true,force:true});
  }
});

test('manifest escrow is restricted and survives unresolved commit/recovery states', () => {
  const root=path.resolve(__dirname,'../..');
  const php=fs.readFileSync(path.join(root,'ops/qa/worg01b_personas.php'),'utf8');
  assert.match(php,/\$pendingManifestPath = \$manifestPath\.'\.pending';/);
  assert.match(php,/file_put_contents\(\$pendingManifestPath, \$encoded, LOCK_EX\)/);
  assert.match(php,/chmod\(\$pendingManifestPath, 0600\)/);
  assert.match(php,/rename\(\$pendingManifestPath, \$manifestPath\)/);
  assert.match(php,/is_file\(\$pendingManifestPath\)/);
  assert.match(php,/if \(! unlink\(\$manifestPath\)\)/);
});
