# PZŁucz Module Workflow Guide

This document describes how to use the three agents to develop new features for the
ianseo Polish Archery Federation module. Read this before starting any feature work.

---

## Agents at a Glance

| Agent | Prompt file | Role | Writes |
|-------|-------------|------|--------|
| **Advisor** | `.agents/advisor.prompt.md` | PZŁucz rules expert + ianseo mapping | Specs, `research/pzlucz-rules.md` |
| **Developer** | `.agents/developer.prompt.md` | PHP implementer | Code in `PL/`, `research/ianseo-internals.md` |
| **Reviewer** | `.agents/reviewer.prompt.md` | Quality & security gate | Review documents only |

---

## Prerequisites

Before running any feature cycle, the research phase must be complete:

- [ ] `research/pzlucz-rules.md` exists and is up to date
- [ ] `research/ianseo-internals.md` exists and is up to date

If either file is missing, run the **Research Phase** below first.

---

## Research Phase (one-time, then maintained)

### Produce `research/ianseo-internals.md` (Developer agent)

1. Open a new Copilot Chat
2. Load `.agents/developer.prompt.md` as the system prompt
3. Provide as context: `Modules/Sets/lib.php`, `Common/Fun_DB.inc.php`, `Common/Fun_Modules.php`,
   `Common/config.inc.php`, `Modules/Sets/IT/sets.php`, `Modules/Sets/FITA/sets.php`,
   any existing `PL/` files
4. Say: _"Produce `research/ianseo-internals.md` covering all sections defined in PLAN.md §3.2."_
5. Save the output to `research/ianseo-internals.md` and commit

### Produce `research/pzlucz-rules.md` (Advisor agent)

1. Open a new Copilot Chat
2. Load `.agents/advisor.prompt.md` as the system prompt
3. **Attach the PZŁucz regulations PDF**
4. Provide as context: `research/ianseo-internals.md`, `Modules/Sets/lib.php`
5. Say: _"Produce `research/pzlucz-rules.md` covering all sections defined in PLAN.md §3.1."_
6. Save the output to `research/pzlucz-rules.md` and commit

### Cross-review

- Re-run Advisor with the new `ianseo-internals.md` to refine the "ianseo mapping" column
- Commit updated files: `git commit -m "docs: research phase complete"`

---

## Feature Development Cycle

Repeat this cycle for every new feature.

```
Feature Request
      │
      ▼
 STEP A ─── ADVISOR generates spec
      │
      ▼
 STEP B ─── DEVELOPER implements
      │
      ▼
 STEP C ─── REVIEWER reviews
      │
   ┌──┴──┐
APPROVE  REQUEST_CHANGES
   │          │
   ▼          └──► back to STEP B
  commit
```

---

### STEP A — Advisor: Generate Feature Spec

1. Open a new Copilot Chat
2. Load `.agents/advisor.prompt.md` as the system prompt
3. Provide as context:
   - **PZŁucz regulations PDF** (attach)
   - `research/pzlucz-rules.md`
   - `research/ianseo-internals.md`
   - `Modules/Sets/lib.php`
4. Describe the feature:
   _"We need to support [competition format]. Please produce a feature specification."_
5. Save the output as `{FeatureName}/spec.md`
6. If the Advisor updated `pzlucz-rules.md`, save and commit it:
   `git commit -m "docs: update pzlucz-rules for {feature}"`

---

### STEP B — Developer: Implement

1. Open a new Copilot Chat
2. Load `.agents/developer.prompt.md` as the system prompt
3. Provide as context:
   - `{FeatureName}/spec.md`
   - `research/ianseo-internals.md`
   - `research/pzlucz-rules.md`
   - The closest reference set (e.g., `Modules/Sets/IT/` or `Modules/Sets/FITA/`)
4. Say: _"Implement the feature described in spec.md."_
5. Apply the generated files to `Modules/Sets/PL/{FeatureName}/`
6. If `ianseo-internals.md` needs updating (new API behaviour found), do that too
7. **Do not commit yet** — send to reviewer first

---

### STEP C — Reviewer: Review

1. Open a new Copilot Chat
2. Load `.agents/reviewer.prompt.md` as the system prompt
3. Provide as context:
   - All new/modified files (paste content or diff)
   - `{FeatureName}/spec.md`
   - `research/ianseo-internals.md`
4. Say: _"Please review this implementation."_
5. Read the review output:
   - **APPROVE** → proceed to commit
   - **REQUEST_CHANGES** → go back to STEP B with the review document as additional context

---

### Commit (after approval)

```powershell
cd c:\Ianseo\ianseo\htdocs\Modules\Sets\PL
git add -A
git commit -m "feat: {short description of feature}"
```

Commit message conventions:
- `feat:` — new feature
- `fix:` — bug fix
- `docs:` — research or documentation update
- `refactor:` — code restructure without behaviour change
- `chore:` — maintenance (gitignore, config, etc.)

---

## Research Maintenance

Update research files when these events occur:

| Event | Action | Agent |
|-------|--------|-------|
| PZŁucz publishes new regulations | Re-run research phase §3.1 with new PDF | Advisor |
| ianseo core is upgraded | Re-read affected `Common/` files and update `ianseo-internals.md` | Developer |
| Implementation reveals undocumented ianseo behaviour | Append finding to `ianseo-internals.md` | Developer |
| New competition format not yet in `pzlucz-rules.md` | Ask Advisor to add the relevant section | Advisor |

Commit message for research updates:
```
docs: update ianseo-internals — discovered {topic}
docs: update pzlucz-rules for {year} regulations
```

---

## Feature Backlog

See `PLAN.md` §7 for the suggested implementation order.

Current status:

| Priority | Feature | Status |
|----------|---------|--------|
| 0 | Research phase | ☐ Not started |
| 1 | Tournament setup scripts (`Setup/`) | ☐ Not started |
| 2 | Extended `sets.php` | ☐ Not started |
| 3 | Field/3D support | ☐ Not started |
| 4 | Custom printouts | ☐ Not started |
| 5 | License/classification check | ☐ Not started |
