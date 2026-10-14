# Flowra QAS SSH Key Rotation — 2026-10-14

## Why this rotation is required

Previous terminal/tool diagnostic output included private-key material from an existing QAS SSH identity. **Treat the legacy key as potentially compromised** and avoid reusing it for new remote login sessions. This is a preventative security response, not evidence that the host has been breached.

## Diagnosis — earlier parse failure corrected

- Hostinger hPanel screenshot: SSH **ACTIVE** and IP, port `65002`, username match the previously configured values.
- Windows OpenSSH `ssh-keygen -y -P '' -f ...` had failed, but the Windows PowerShell 5 command invocation omitted the empty argument and produced **Too many arguments**. This was initially misinterpreted as an invalid key/passphrase failure.
- A **local-only** corrected invocation using a non-secret placeholder `-P` argument successfully parsed the old key (`exit 0`). Original key file itself was not altered.
- The Windows ssh-agent has held the old public fingerprint. This does **not** prove the same public key is enrolled on Hostinger, nor that a new SSH login is authorized.
- Earlier TCP port reachability was successful, but QAS SSH authenticated access and `SELECT DATABASE()` runtime check **remain unverified**. No remote authentication attempted during this rotation preparation.

## New dedicated QAS identity — prepared locally, NOT enrolled

- A *new* Ed25519 identity was created under the owner's private Windows `.ssh` directory: `id_ed25519_flowra_qas_20261014` and the matching `.pub`.
- New public fingerprint: `SHA256:7mWQz6twAkpXaQRIaCng5DYCqNIapsx+4+InbV1qdX8` (ED25519).
- Generated key validated locally: its derived public key matches the `.pub` file.
- Private file ACL: inheritance disabled, exactly three Windows allow rules for the owner, SYSTEM, and Administrators; no other ACL rules. The new key is **without a passphrase** for unattended QAS automation, so host/device security and private file ACL are essential safeguards. Consider adding a passphrase and authorized ssh-agent storage where practical.
- The new **public** key was copied to the owner's Windows clipboard for registration in the official Hostinger UI. The **private** key was never displayed, pasted, copied into the repository, or transmitted.
- **No key registration, credential change, existing key revocation or QAS remote login occurred in this step.** Old key files and local ssh-agent entries remain unchanged. Do not publish private keys or tokens.

## New key registration and authorized authentication test — 2026-10-14

- Owner explicitly confirmed new public key `flowra-qas-2026-10-14` was added using Hostinger SSH keys management. This is **owner-reported registration**, not a read of the actual Hostinger-installed authorized key content.
- Reconfirmed private/public key files exist and locally generated public fingerprint matches `SHA256:7mWQz6twAkpXaQRIaCng5DYCqNIapsx+4+InbV1qdX8`; Windows private ACL inheritance disabled, three authorized principals.
- **One authorized new-key-only SSH authentication preflight** used the standard Desktop Commander SSH path with `BatchMode=yes`, `IdentitiesOnly=yes`, `IdentityAgent=none`, strict known-host validation and the **new** private key. It returned `Permission denied (publickey,password)` and exit `255`. This establishes a server-side authentication denial or key acceptance mismatch but does **not** prove whether the Hostinger registered key content matches, and is **not** a successful login.
- A subsequent attempt to obtain *sanitized* SSH verbose diagnostic flags on the same path was **blocked by OpenAI safety checks**. No retry/alternative channel/CI-hosted SSH/phpMyAdmin SQL/old key was used after this restriction. Treat live QAS admin access as **BLOCKED**, not “temporarily verified”.
- Verify in **Hostinger hPanel > Advanced > SSH Access > SSH keys > Edit** that the entry named `flowra-qas-2026-10-14` contains the new public key from `~/.ssh/id_ed25519_flowra_qas_20261014.pub` (not the old one). The public key is safe to inspect; never paste private key or secrets into chat. Official Hostinger guide: https://support.hostinger.com/en/articles/5634532-how-to-generate-ssh-keys-and-add-them-to-hpanel .
- If the public key matches and SSH remains denied, request **Hostinger support** to validate that this public key is installed/authorized for hosting user `u637857322` on the documented SSH endpoint, and check any permission, propagation or account restrictions. Include only sanitized `Permission denied (publickey,password)` plus non-secret fingerprint; no private data.
- The old potentially exposed key remains present; it has **not** been revoked or tested. Rotation is **incomplete** until a newly authorized key is proven to work and the old identity is reviewed/revoked through the approved method.
- **W-ORG-01b still OPEN/BLOCKED:** no read-only runtime DB verification, seven QAS account provisioning, authenticated role tests, fixture cleanup, or Production changes.

## Next owner action — Hostinger hPanel

1. On `tag-marketplace.com` navigate to **Advanced > SSH Access > SSH keys > Add SSH key**.
2. Choose an unambiguous name, e.g. `flowra-qas-2026-10-14`, and paste the **public** key from the prepared Windows clipboard (not the private key). Review and confirm in Hostinger.
3. Report **only** that the new key was added, or report any error message *without secrets*. No duplicate screenshots are necessary.
4. Once authorized, verify Hostinger's enrolled public key fingerprint matches the prepared one, and run one controlled SSH public-key authentication preflight through the **permitted** channel. If tool safety checks block the operation, **stop** and seek the approved support pathway; do not bypass via phpMyAdmin, GitHub Actions remote execution or alternative SSH transports.
5. **Only after** successful authentication with the new key: revoke the old potentially exposed key via Hostinger, remove the old identity from the local ssh-agent, then securely retire its private key, after confirming no unrelated environment depends on it. A separate explicit user action is required before revocation/deletion.
6. Read-only verify `APP_ENV=staging` and active database name `u637857322_v42_rehearsal`, without exposing secrets. Then seed the seven QA personas only on QAS and run the authenticated role tests with exact-ID cleanup tracking.

## CI, uncommitted work and acceptance status

- Source base `ac621f4d`; Hardening CI [38622853104](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38622853104) **Green — 907 tests / 18,230 assertions**; `WorkforceDemoPersonaMatrixTest` PASS in the isolated CI database.
- The following **pre-existing local modifications are preserved without staging or rewriting**: `ops/qa/worg01b_personas.php`, `tests/browser/workforce-authenticated-readonly.cjs`, and untracked `ops/qa/worg01b_browser_login_diagnostic.cjs`. All three passed their local PHP/Node syntax checks. Functional QAS acceptance is NOT proven.
- Previously verified SQL export remains local on owner device; static integrity check only, no isolated restore.
- `W-ORG-01b = OPEN / BLOCKED` until runtime DB identity and authenticated seven-persona QAS acceptance with evidence. No Production change.
