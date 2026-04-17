# PROJECT RULES
## Dynamic E-commerce System — Official Development Guidelines

### 1) Stability First
- Any new change must not break working features.
- Preserve system stability before adding new functionality.
- Refactors must be careful, incremental, and validated.

### 2) Full Delivery as ZIP
- Deliver work as a full project ZIP.
- Do not rely on copy/paste.
- Goal: avoid missing files, wrong placement, and merge mistakes.

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
- For each feature, ask whether it improves conversion or revenue.
- Do not add features for appearance only.
- Prioritize business impact.

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
- Any system such as growth, messaging, or automation should support ON/OFF control.
- Admin should be able to enable or disable safely.

### 12) Modular System
- Build each feature as a module-oriented unit.
- Make it easy to add, remove, or adjust later.

### 13) Mobile-ready Mindset
- Design features with future API/mobile support in mind.
- Keep long-term mobile expansion possible.

### 14) No Memory Dependency
- Do not depend on chat memory.
- Important ideas and status must be stored inside the project docs.
- The master plan is the main reference.

### 15) Environment Policy
- During active development ZIP deliveries, keep the existing `.env` file.
- During public/release packaging, replace `.env` with `.env.example` after a final security pass.
