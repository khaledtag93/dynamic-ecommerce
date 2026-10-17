# C1 — Registration Concurrent Integrity / Same-Email Race (2026-10-17)

## Baseline
- Starting checkpoint: [cb5b8b6034a945dc41df4a1645f671da62ffc483](https://github.com/khaledtag93/dynamic-ecommerce/commit/cb5b8b6034a945dc41df4a1645f671da62ffc483).
- Baseline full CI [#39042089781](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39042089781) **completed/success** on exact baseline SHA: 1,010 tests / 19,609 assertions.
- One intervening documentation-only roadmap commit [540151e2](https://github.com/khaledtag93/dynamic-ecommerce/commit/540151e217b8e6e688beb286ba64d29de953c9a2) concerning merchant data migration was preserved and is out of scope.
- **Scope: SOURCE ONLY**. GitHub CI uses isolated MySQL database \`dynamic_test\`; no QAS or Production writes. No external email/SMS/WhatsApp, OTP/OAuth, configuration, permissions, credentials, secrets or policies changed.

## Precise race and reproducibility

### Observed source contract
\`app/Http/Controllers/Auth/RegisterController.php\` uses Laravel UI \`RegistersUsers\`. Its \`validator()\` includes \`unique:users\` for \`email\`; \`create()\` previously called \`User::create\` without handling a database unique conflict. The \`users.email\` column has an independent MySQL unique index. These statements are different protections: application validation is susceptible to a time-of-check-to-time-of-use race, while the DB unique index prevents two rows.

### Authentic two-worker HTTP regression
Files:
- [tests/Feature/C1RegistrationConcurrentIntegrityTest.php](../tests/Feature/C1RegistrationConcurrentIntegrityTest.php)
- [tests/Support/C1RegistrationRaceWorker.php](../tests/Support/C1RegistrationRaceWorker.php)

Within the isolated CI test:
1. Assert \`APP_ENV=testing\`, the selected MySQL DB name \`dynamic_test\`, and a unique index for \`users.email\`; fail closed otherwise.
2. Start two **separate PHP processes**. Each boots its own Laravel HTTP Kernel, DB connection, guest session and encrypted cookie. Each GETs \`/register\` and then submits a POST with its own session cookie and CSRF token. Both use the *same* newly-generated synthetic email.
3. Both requests are held in their \`User::creating\` listener only **after** their unique-email validation passed; release both simultaneously just before the database INSERT. This proves a real collision rather than sequential duplicate form submission. It is independent in-process HTTP handling on each worker; **not** two real browser windows or a public HTTP socket.
4. Verify different guest session IDs, exactly ONE committed database user, and the losing request's HTTP response. Test-only created user and coordination files are removed even on failure.
5. Registration messages use test-only array mail backend, never a real recipient. APP_DEBUG is false in CI; response is screened for raw SQL strings.

### RED evidence — actual defect
- [Test-only RED commit 400ff428](https://github.com/khaledtag93/dynamic-ecommerce/commit/400ff4286260552edbe4120daa61c2f4944537f1); [CI #39042824372](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39042824372) **completed/failure**: **1,010 passed, 1 new failed (19,623 assertions)**.
- Both workers' callbacks confirmed they passed validation before INSERT. Different stored guest session IDs. Results: worker A **HTTP 500** (no email errors), worker B **HTTP 302**, exactly one row. The losing HTTP response did **not** disclose raw SQL when APP_DEBUG=false. This is a reproducible unhandled concurrent duplicate-email exception, separate from optional IP rate-limit or enumeration policy decisions.
- Not a violation of database uniqueness. It is a user-visible, unexpected HTTP 500 on a valid race, needing deterministic field-validation handling.

### Minimal source fix
- [Fix commit ea672d58](https://github.com/khaledtag93/dynamic-ecommerce/commit/ea672d5888fb3eb5a7d9b4232f555100f3119e78).
- **Only** changed \`app/Http/Controllers/Auth/RegisterController.php\`: within \`create()\`, catch Laravel \`Illuminate\\Database\\UniqueConstraintViolationException\`; check that the attempted email now exists, then throw standard \`Illuminate\\Validation\\ValidationException\` on \`email\`. If no matching email exists, rethrow the original unique-constraint exception. Preserve unique DB index, validator, registration email UX, guest-cart handoff, and auth session rotation.
- No new hard limits, response-enumeration behavior, permissions or identity policy; catching an already-proven race is a bounded non-policy bug fix.

### GREEN evidence
- [Full CI #39043137013](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39043137013) on exact fix SHA \`ea672d5888fb3eb5a7d9b4232f555100f3119e78\`: **completed/success**, **1,011 tests / 19,626 assertions**.
- The same \`C1RegistrationConcurrentIntegrityTest\` is PASS. Its assertions require exactly one created account, one successful signup (302) and one graceful email-validation redirect (302), separate guest IDs, and no SQL content leaked in responses.
- This closes the **source-verified defect**, not end-to-end live/browser acceptance, alternative DB vendors, load testing, or registration risk policies.

## Closure and open gates
| Area | Disposition |
| --- | --- |
| Same-email database uniqueness under forced simultaneous POST | **IMPLEMENTED / SOURCE PASS** |
| Losing concurrent signup receives normal validation instead of 500 | **FIXED / SOURCE PASS** |
| Duplicate-email wording / account enumeration | **POLICY OPEN** (no new requirement invented) |
| IP/account signup throttling and bot-abuse thresholds | **POLICY OPEN** |
| Real-browser QAS signup, cookie transport, session persistence, and production backend | **ACCEPTANCE OPEN** |
| C1-A preflight Issue #2 | **BLOCKED** |
| QAS acceptance Issues #3/#4/#5/#6 | **OPEN** |
| Phase 1 Production | **NO-GO** |

Do not silently close broader C1 account identity, OTP/OAuth, all-device revocation or QAS gates based on this isolated regression. No outward communication or QAS/Production write was performed.

## Next independent slice
Do a source-only review of registration/email normalization and authorization/ownership across related account flows, or document decisions for DEC-REG-ENUM / DEC-REG-RATE without implementing a policy. Require a separate red regression for any alleged new bug and full exact-SHA CI before checkpoint closure.

## Documentation checkpoint status
This document records authoritative **fix-SHA** CI, not an assumed result on the later documentation-only checkpoint. Independently verify that last commit's GitHub Actions run is \`completed/success\` on exact head before declaring it GREEN.
