# PROJECT RULES
## Dynamic E-commerce System — Official Development Guidelines

### 1) Stability First
- Any new change must not break working features.
- Preserve system stability before adding new functionality.
- Refactors must be careful, incremental, and validated.

### 2) Git Is the Source of Truth
- Starting with V42, the canonical project state lives in the Git repository.
- Do not create a new project folder for every version as the normal workflow.
- Use commits, branches, tags, and releases for history and rollback.
- ZIP packages are optional backups/handoffs, not the development source of truth.

### 3) Multi-language (Arabic / English)
- Every new feature must support Arabic and English.
- Any old untranslated area should be translated when touched.
- Use a unified translation system, not hardcoded UI text.

### 4) Dynamic & Reusable System
- Do not build features specifically for one niche only.
- Keep everything generic and reusable across store types.
- Support dynamic attributes such as color, size, weight, flavor, etc.

### 5) Clean Architecture
- Keep a clear separation between controllers and services.
- Avoid heavy business logic in Blade views.
- Reuse code instead of duplicating logic.

### 6) Performance Matters
- Prefer AJAX / Livewire / live updates where useful.
- Reduce unnecessary full-page reloads.
- Think about speed from the start.

### 7) Growth-first Mindset
- For each feature, ask whether it improves conversion, revenue, operations, reliability, or maintainability.
- Do not add features for appearance only.
- Prioritize measurable business impact.

### 8) Step-by-step Phases
- Work phase by phase.
- Each phase must be stable before moving to the next.
- Do not jump randomly across the roadmap.

### 9) Full Project Awareness
- All work must align with the master plan.
- Avoid isolated features that do not fit the system.
- Every addition should connect to the wider platform.

### 10) Test Before Moving
- Validate each phase before continuing.
- Use demo data when useful.
- Confirm stability before progressing.

### 11) Admin Control
- Any system such as growth, messaging, or automation should support ON/OFF control where operationally appropriate.
- Admin should be able to enable or disable safely.

### 12) Modular System
- Build each feature as a module-oriented unit.
- Make it easy to add, remove, or adjust later.

### 13) Mobile-ready Mindset
- Design features with future API/mobile support in mind.
- Keep long-term mobile expansion possible.

### 14) Documentation Is Part of Done
- Important status and decisions must be stored inside the project docs.
- Update `PROJECT_MASTER_STATUS.md` and `CURRENT_PHASE.md` with meaningful project changes.
- Do not depend on chat history as the only project record.

### 15) Environment & Secrets Policy
- `.env`, private keys, passwords, API secrets, and production credentials must never be committed to Git.
- Keep real values only in local/server environments or approved secret stores such as GitHub Actions Secrets.
- `.env.example` and `.env.production.example` contain names/placeholders only.
- Any credential that has been exposed in public Git history must be rotated.

### 16) Production Change Control
- Production deploys must start from a known Git commit on `main`.
- Create/confirm a recoverable backup before production changes.
- Verify the application after deploy.
- Use rollback when verification fails; database migrations/data changes need their own rollback plan.

### 17) Global Foundation Before Page Closure
- Before the full page-by-page pass, harden shared foundations once: Admin shell, Storefront shell, design tokens/components, forms, feedback states, responsive behavior, bilingual/RTL rules, motion, accessibility and Livewire interaction conventions.
- Fix a shared defect at the shared layer instead of repeating local patches across pages.

### 18) Strict Page Closure / Definition of Done
- Treat every Admin and Customer page as a production closure milestone.
- A page is not CLOSED until every relevant dimension has been reviewed and known issues are resolved: product completeness, UI/UX, information architecture, section/action placement, EN/AR/RTL, responsive/mobile behavior, accessibility, security/permissions, validation, loading/empty/error/success states, performance, help/onboarding, maintainability, automated regression coverage and QAS acceptance.
- Reopen a CLOSED page only for a genuinely new requirement or a newly discovered regression/bug, not deferred polish.

### 19) Livewire 4 Interaction Standard
- Actively use Livewire 4 or an equivalent progressive live pattern to remove unnecessary full-page reloads, scroll jumps and lost UI state.
- Prefer immediate server-confirmed calculations, live search/autocomplete, filters, pagination, inline actions and partial updates while preserving server authority for money, stock, permissions and destructive operations.
- Preserve the user's useful context where possible: scroll position, focus, filters, pagination and open workspace state.

### 20) Customer Experience Is First-Class
- Storefront/Customer pages have the same quality priority as Admin pages because they directly shape merchant customer trust and conversion.
- Existing layouts may be simplified, reorganized, replaced or rebuilt when that materially improves clarity, visual quality, responsiveness, trust or conversion.
- Remove prototype-like, noisy or unnecessary UI even when technically functional.

### 21) Completion Before Expansion
- During this pass, new ideas are recorded but do not interrupt closure unless they are blockers, regressions, security issues, direct dependencies or prevent imminent rework.
- Help and onboarding should explain unfamiliar flows proactively with contextual guidance, examples, useful validation messages and meaningful empty states.

### 22) Buyer-grade Evidence and Honest Scope
- Maintain an inventory of actual pages, roles, variants and business journeys. Each CLOSED page needs exact source/CI/QAS evidence, EN/AR + responsive/keyboard checks, relevant permission and failure-path checks, and no known blocking defect in its active scope.
- Test cross-page money/stock/identity workflows, clean install and upgrade/restore separately from page appearance. Keep implemented, source-verified, QAS-accepted, Production-verified and planned claims distinct.
- Prepare reproducible documentation and repeatable checks for independent developer, tester and AI-assisted review. Do not promise a zero-defect score or describe future SaaS/mobile/social/payment capabilities as complete without proof.

### 23) Fast, Reliable Collaboration
- Work in coherent, reviewable batches; use focused reads and bounded tool output, then verify the latest batch HEAD in CI. Save meaningful decisions, defects and next actions to the repository as work progresses.
- Record source, CI, QAS and Production state separately at each checkpoint. During a long session, give concise progress updates. Before changing chat, provide a self-contained handoff pointing to the current commit and `docs/NEW_CHAT_HANDOFF_2026-09-26.md`.
- A chat handoff or an asynchronous CI run never counts as acceptance; record the actual result before changing a closure status.
