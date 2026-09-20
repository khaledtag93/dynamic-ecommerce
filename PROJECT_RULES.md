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
