# OPS-01 Credential Rotation Evidence — 2026-09-26

## Purpose

This document records the release evidence for historical credential exposure without storing, reproducing, or hashing secret values in the repository.

## Historical exposure inventory

Repository history review confirmed that a tracked root `.env` file existed in the historical V40 snapshot and was later removed by commit `ca9a8fc1` (`security: stop tracking environment secrets`).

The exposed historical file contained populated values for:

- Laravel application encryption key (`APP_KEY`).
- Paymob API credential (`PAYMOB_API_KEY`).
- Paymob HMAC secret (`PAYMOB_HMAC_SECRET`).
- Paymob integration identifier.
- Paymob iframe identifier.

The same historical file did **not** contain populated database, mail, AWS, Redis, or Pusher passwords/secrets.

A recursive path review of the historical snapshot did not identify additional first-party credential files, database dumps, SSH private keys, PEM files, or backup archives requiring a separate rotation track. Dependency/vendor filenames matching generic words such as password, dump, or secret are not treated as credential evidence.

## Required closure actions

| Service / credential | Historical exposure confirmed | Rotation / revocation evidence | Runtime verification | Status |
| --- | --- | --- | --- | --- |
| Laravel `APP_KEY` | Yes | Must verify current QAS and Production keys are not the historical exposed value. If either environment still uses it, perform a controlled key rotation with explicit session/encrypted-data impact review. | Pending | Open |
| Paymob API credential | Yes | Rotate/revoke the historically exposed credential in the Paymob account. Record completion date only. | Pending | Open |
| Paymob HMAC secret | Yes | Rotate/revoke the historically exposed HMAC secret in the Paymob account. Record completion date only. | Pending | Open |
| Paymob integration / iframe identifiers | Present historically | Treat as identifiers rather than secret proof by themselves; verify they belong to the intended active integration after credential rotation. | Pending | Open |
| DB password | No populated historical value found in the tracked `.env` snapshot | No OPS-01 rotation required from this evidence alone. | N/A | Not implicated |
| Mail password | No populated historical value found | No OPS-01 rotation required from this evidence alone. | N/A | Not implicated |
| AWS credentials | No populated historical value found | No OPS-01 rotation required from this evidence alone. | N/A | Not implicated |
| Redis password | No populated historical value found | No OPS-01 rotation required from this evidence alone. | N/A | Not implicated |
| Pusher credentials | No populated historical secret found | No OPS-01 rotation required from this evidence alone. | N/A | Not implicated |

## Evidence handling rules

- Never commit old or new credential values.
- Never paste credential values into chat, logs, screenshots, tickets, or release notes.
- Repository documentation records only service name, completion state, and rotation date.
- A credential is not considered rotated merely because `.env` was deleted from Git.
- OPS-01 closes only after the old Paymob credentials are revoked/rotated, current environments are verified not to use the historical Laravel application key, and the application still passes the relevant QAS smoke tests.

## Current conclusion

OPS-01 remains **open**, but the historical scope is now bounded. The confirmed rotation work is Paymob API/HMAC plus verification of the Laravel application key in QAS and Production. No evidence from the historical tracked `.env` currently requires DB/Mail/AWS/Redis/Pusher credential rotation under this finding.

After OPS-01 closes, continue the recorded P0 sequence with PAY-01 Paymob E2E and OPS-02 isolated database restore rehearsal.
