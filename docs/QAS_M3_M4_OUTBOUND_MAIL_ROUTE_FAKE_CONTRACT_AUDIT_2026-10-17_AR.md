# Flowra — QAS M3/M4 Outbound Mail Route Source-only Contract Audit (2026-10-17)

## Baseline and environment gate
- [Exact baseline checkpoint 0ccdc2d82b13c4c2c28364df0c807355a1d3042e](https://github.com/khaledtag93/dynamic-ecommerce/commit/0ccdc2d82b13c4c2c28364df0c807355a1d3042e). [Baseline full CI #39052017455](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39052017455): independently verified **completed/success on that exact SHA, 1,013 tests / 19,644 assertions**.
- Authorized authenticated QAS phpMyAdmin browser SQL UI **NOT AVAILABLE** through available tools. **SELECT DATABASE() has NOT RUN**; its identity gate is UNKNOWN. No other SQL was attempted. Do not query anything else until an explicitly authorized owner/operator returns the exact string \`u637857322_v42_rehearsal\` from \`SELECT DATABASE() AS connected_database;\` alone. No SSH/DB PHP alternatives or tool safety bypass.
- Last verified QAS deployed **de180de6** with **MAIL_MAILER=array** (prior observation), not this source-only branch. Production untouched.

## Audited source leaves and outbound risk
| Flow | Verified source route | Tested scope | Residual unknown |
| --- | --- | --- | --- |
| Password Reset | \`User\` inherits stock Laravel \`ResetPassword\` notification, no named mailer; existing [fake baseline](QAS_RESET_ONLY_MAILER_FAKE_CONTRACTS_2026-10-17_AR.md) | 2 prior green tests with Notification/Mail fakes | Real QAS inbox delivery, synthetic allowlist and prospective named mailer |
| Order email | \`OrderNotificationService::sendEmail\` calls \`Mail::to()->send(new OrderUpdateMail)\` on **default** mailer | New fake direct leaf test + missing-recipient test | Effective live notification flags, UI event pipeline and pending jobs |
| Admin escalation | \`NotificationAutomationService::notifyAdminEmail\` uses \`Mail::raw\` with rule recipient, else global From fallback | New direct leaf call mocked to a local in-memory recipient/subject double; **no scanner/rule execution, retries or transport** | Active QAS rules/admin fallback/targets, broader notification fallback safety |
| Growth email | \`SmtpEmailGrowthChannel::send\` calls \`Mail::to()->send(new GrowthCampaignMail)\` on **default** mailer | New fake driver test of synthetic address and absent-recipient skip | Active Growth real-email flag, queued jobs/delivery backlog |

## Four narrowly scoped regression cases
- \`tests/Feature/OutboundMailRouteIsolationContractTest.php\` adds **four** tests using \`Mail::fake()\` and \`Notification::fake()\`. All addresses and order data are synthetic (\`@example.test\`) and exist only in CI. No application code, configuration, permissions, policies, secrets or live database are modified.
- Order positive path: intercept and inspect recipient, mailable type, \`mailer === null\`, count and no queued mailable. Order empty-recipient path: no outgoing fake records.
- Admin escalation: Laravel 13 \`MailFake::raw()\` is **a no-op**, so \`Mail::assertSent()\` would be a false proof. A Mockery interception of **only** \`Mail::raw\` verifies callback-selected synthetic recipient and generated subject, while \`mailer()\` is forbidden on the mock. Do not assert actual email delivery, live rule selection, or admin-recipient policy. The direct leaf is invoked, **not** \`executeForDispatch\`, retry, scanner or queue.
- Growth: fake captures \`GrowthCampaignMail\`, ensures \`mailer === null\` and zero queueing; a missing recipient returns \`skipped\` without additional fake mail.
- All new tests are designed for isolated GitHub Actions MySQL testing environment only; never QAS/Production. Run full Hardening CI and require **completed/success at exact test SHA** before declaring GREEN. A test double mismatch is not automatically an app bug.

## Conclusion / strict boundaries
- Existing Order, Admin and Growth direct email paths use the **global** mailer, so global SMTP would impact much more than password reset; **do not enable it**.
- \`qas_reset_capture\` remains **DESIGN ONLY**, NOT IMPLEMENTED. Tests do not prove future sandbox routing/allowlist, queued/scheduled isolation, Mailtrap forwarding OFF, or any runtime safe send. No real SMTP/email/SMS/WhatsApp, checkout, scanners, retries, queues, QAS/Production writes.
- **M3 BLOCKED, M4 PARTIAL/BLOCKED, C1-A Issue #2 BLOCKED, QAS acceptance Issues #3/#4/#5/#6 OPEN, Production NO-GO**.
- Next dependency: only authorized standalone phpMyAdmin identity SELECT evidence; otherwise further source-only case-by-case audit or design, never a provider activation from test success.
