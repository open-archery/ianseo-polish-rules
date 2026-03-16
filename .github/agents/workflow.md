# PZŁucz Module Workflow Guide

This document describes how to develop new features for the ianseo Polish Archery Federation
module using the OpenSpec framework and the three agent roles. Read this before starting any
feature work.

---

## Agents at a Glance

| Agent         | Prompt file                          | Role                                             | Writes                                                         |
| ------------- | ------------------------------------ | ------------------------------------------------ | -------------------------------------------------------------- |
| **Advisor**   | `.github/agents/advisor.prompt.md`   | PZŁucz rules expert — business requirements only | `openspec/specs/{feature}/spec.md`, `research/pzlucz-rules.md` |
| **Developer** | `.github/agents/developer.prompt.md` | ianseo domain expert + PHP implementer           | `openspec/changes/{name}/design.md`, code in `PL/`             |
| **Reviewer**  | `.github/agents/reviewer.prompt.md`  | Quality & security gate                          | Review documents only                                          |

---

## Prerequisites

Before running any feature cycle, the research phase must be complete:

- [ ] `.github/agents/research/pzlucz-rules.md` exists and is up to date
- [ ] `.github/agents/research/ianseo-internals.md` exists and is up to date

If either file is missing, run the **Research Phase** first (see below).

---

## Research Phase (one-time, then maintained)

### Produce `ianseo-internals.md` (Developer agent role)

As the Developer agent, read: `Common/Fun_DB.inc.php`, `Common/Fun_Modules.php`,
`Common/config.inc.php`, `Modules/Sets/IT/sets.php`, `Modules/Sets/FITA/sets.php`,
any existing `PL/` files.

Produce `.github/agents/research/ianseo-internals.md` covering:

- Bootstrap and session API
- DB helper functions and conventions
- Module registration (`sets.php`, `menu.php` patterns)
- Event/Division/Class creation functions
- Ranking factory hook points
- AJAX and PDF patterns

### Produce `pzlucz-rules.md` (Advisor agent role)

As the Advisor agent, read `regulamin-lucznictwa.md` and `ianseo-internals.md`.

Produce `.github/agents/research/pzlucz-rules.md` covering:

- Competition formats (1440, Single-distance, Indoor)
- Divisions (R, C, B, T, L)
- Age classes and eligibility
- Scoring and tiebreaking rules
- Elimination and post-elimination placement rules

Commit both: `git commit -m "docs: research phase complete"`

---

## Feature Development Cycle

```
Feature Request
      │
      ▼
 STEP 0 ── openspec-explore (optional thinking/scoping)
      │
      ▼
 STEP A ── Advisor writes openspec/specs/{feature}/spec.md
      │
      ▼
 STEP B ── openspec-propose creates change + Developer writes design.md
      │
      ▼
 STEP C ── openspec-apply-change implements tasks
      │
      ▼
 STEP D ── Reviewer reviews
      │
   ┌──┴──┐
APPROVE  REQUEST_CHANGES
   │          │
   ▼          └──► back to STEP C (or B if design is wrong)
 openspec-archive-change
      │
      ▼
  git commit
```

---

### STEP 0 — Explore (optional)

Use `/openspec-explore` (or the `openspec-explore` skill) to think through the feature before
writing a spec. This is a thinking mode — no implementation happens here.

Good entry points:

- "Let me think through what PZŁucz requires for [format]"
- "I want to understand the ianseo hook points for [feature]"

---

### STEP A — Advisor: Write the Feature Spec

Acting as the **Advisor agent** (load `.github/agents/advisor.prompt.md` as context):

1. Read `.github/agents/research/regulamin-lucznictwa.md` and `pzlucz-rules.md`
2. Run the three-pass reasoning process (Pass 0 feasibility → Pass 1 regulation analysis → Pass 2 gap detection)
3. If fully covered by ianseo config: produce a Feasibility Report and stop
4. Otherwise: write the spec to `openspec/specs/{feature-name}/spec.md`
5. If `pzlucz-rules.md` needs updating, update and commit: `git commit -m "docs: update pzlucz-rules for {feature}"`

---

### STEP B — Propose and Design

#### B1 — Create the change

```bash
openspec new change "{feature-name}"
```

Or use `/openspec-propose` to create the change and all artifacts in one step:

```
/openspec-propose
```

This creates `openspec/changes/{name}/` with `proposal.md`, `design.md`, and `tasks.md`.

#### B2 — Developer writes design.md

Acting as the **Developer agent** (load `.github/agents/developer.prompt.md` as context):

1. Read `openspec/specs/{feature-name}/spec.md` fully
2. Read `.github/agents/research/ianseo-internals.md`
3. Find the closest reference implementation in `Modules/Sets/IT/` or `Modules/Sets/FITA/`
4. Fill in `openspec/changes/{name}/design.md` with:
   - ianseo tournament type and hook point
   - Division/class/event mapping
   - Custom code plan for every `⚠ CUSTOM NEEDED` item
   - Full list of files to create
   - Menu entries needed
5. Fill in `openspec/changes/{name}/tasks.md` with atomic implementation tasks

---

### STEP C — Implement

```
/openspec-apply-change
```

Or equivalently use the `openspec-apply-change` skill. This reads the tasks from
`openspec/changes/{name}/tasks.md` and implements them one by one, marking each complete.

During implementation, the Developer agent must:

- Stay within `Modules/Sets/PL/` — never modify ianseo core
- Follow all conventions from `.github/agents/developer.prompt.md`
- Update `.github/agents/research/ianseo-internals.md` if new ianseo behaviour is discovered

---

### STEP D — Review

Acting as the **Reviewer agent** (load `.github/agents/reviewer.prompt.md` as context):

1. Read `openspec/specs/{feature-name}/spec.md` and `openspec/changes/{name}/design.md`
2. Read `.github/agents/research/ianseo-internals.md`
3. Review all new/modified PHP files against the full checklist
4. Output: **APPROVE** or **REQUEST_CHANGES** with inline findings

On **APPROVE** → archive and commit:

```bash
# Archive the change
/openspec-archive-change

# Commit the implementation
git add -A
git commit -m "feat: {short description}"
```

On **REQUEST_CHANGES** → go back to STEP C (or STEP B if the design needs revision).

---

## Research Maintenance

Update research files when these events occur:

| Event                                                | Action                                                            | Role      |
| ---------------------------------------------------- | ----------------------------------------------------------------- | --------- |
| PZŁucz publishes new regulations                     | Re-run research phase with new PDF                                | Advisor   |
| ianseo core is upgraded                              | Re-read affected `Common/` files and update `ianseo-internals.md` | Developer |
| Implementation reveals undocumented ianseo behaviour | Append finding to `ianseo-internals.md`                           | Developer |
| New competition format not yet in `pzlucz-rules.md`  | Add the relevant section                                          | Advisor   |

Commit messages:

```
docs: update ianseo-internals — discovered {topic}
docs: update pzlucz-rules for {year} regulations
```

---

## Commit Message Conventions

- `feat:` — new feature
- `fix:` — bug fix
- `docs:` — research or documentation update
- `refactor:` — code restructure without behaviour change
- `chore:` — maintenance (gitignore, config, etc.)

---

## Feature Backlog

| Priority | Feature                      | Status      | Spec                                       |
| -------- | ---------------------------- | ----------- | ------------------------------------------ |
| ✅       | Tournament setup scripts     | Done        | `openspec/specs/tournament-setup/spec.md`  |
| ✅       | Post-elimination ranking     | Done        | `openspec/specs/post-elim-ranking/spec.md` |
| ✅       | Sportzona athlete lookup     | Done        | `openspec/specs/sportzona-lookup/spec.md`  |
| ✅       | Bib list batch import        | Done        | `openspec/specs/bib-import/spec.md`        |
| ✅       | Diplomas module              | Done        | `openspec/specs/diplomas/design.md`        |
| —        | Field/3D support             | Not started | —                                          |
| —        | Licence/classification check | Not started | —                                          |
