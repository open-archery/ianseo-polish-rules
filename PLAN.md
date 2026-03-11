# Polish Archery Federation (PZŁucz) Modules — Implementation Plan

## Overview

Build an agentic workflow to expand the ianseo archery system with Polish federation-specific modules. All code lives exclusively in `Modules/Sets/PL/`. Three specialized agents collaborate through a structured loop: **Advisor** (domain knowledge), **Developer** (implementation), **Reviewer** (quality gate). A mandatory **Research Phase** grounds both Advisor and Developer with documented knowledge before any feature work begins.

---

## Target Directory Structure

```
Modules/Sets/PL/
├── .github/agents/
│   ├── advisor.prompt.md          # Advisor agent system prompt
│   ├── developer.prompt.md        # Developer agent system prompt
│   ├── reviewer.prompt.md         # Reviewer agent system prompt
│   └── workflow.md                # Workflow description & execution guide
├── research/
│   ├── pzlucz-rules.md            # Advisor output: PZŁucz rules mapped to ianseo
│   └── ianseo-internals.md        # Developer output: ianseo API & patterns reference
├── sets.php                        # Tournament type registration
├── menu.php                        # Menu entries for PL ruleset
├── Diplomas/                       # (existing) Diploma generation module
├── Rank/                           # (existing) Custom team ranking
└── {future features}/              # New feature subdirectories
```

---

## Step 1 — Initialize a Standalone Git Repository

The current git repo tracks all of `htdocs/`. Create an isolated repo scoped to PL modules only.

**Actions:**

1. Initialize a new git repo in `Modules/Sets/PL/`
2. Create a `.gitignore` for editor/IDE artifacts
3. Commit existing files (Diplomas, Rank, sets.php, menu.php)

**Result:** Version-controlled PL module directory, independent from the main ianseo repo.

---

## Step 2 — Create Agent Prompt Files

Store agent definitions as versioned markdown files so they evolve with the codebase.

### 2.1 — Advisor Agent (`advisor.prompt.md`)

| Property        | Value                                                                                                                         |
| --------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| **Role**        | Domain expert on PZŁucz regulations and ianseo tournament configuration                                                       |
| **Inputs**      | PZŁucz regulations PDF (attached), `research/ianseo-internals.md`, `Modules/Sets/lib.php`, existing reference sets (IT, FITA) |
| **Outputs**     | Structured specifications; updates to `research/pzlucz-rules.md`                                                              |
| **Constraints** | Never writes code. Produces specs only. Maps every regulation to ianseo data model concepts.                                  |

**System prompt essence:**

> You are an expert in Polish archery competition rules (PZŁucz) and the ianseo system's internal data model. You have access to the PZŁucz regulations PDF as your authoritative source. When asked about a competition format, produce a precise technical specification mapping the rules to ianseo's `CreateDivision()`, `CreateClass()`, `CreateEvent()`, `CreateDistanceNew()`, `CreateTargetFace()` functions. Reference constants from `lib.php`. Flag any PZŁucz rule that has no direct ianseo equivalent and would need custom module code. Never write code — only produce specifications.

### 2.2 — Developer Agent (`developer.prompt.md`)

| Property        | Value                                                                                                  |
| --------------- | ------------------------------------------------------------------------------------------------------ |
| **Role**        | PHP developer implementing features in `Modules/Sets/PL/`                                              |
| **Inputs**      | Advisor specs, `research/ianseo-internals.md`, `research/pzlucz-rules.md`, ianseo codebase (read-only) |
| **Outputs**     | Working PHP code; updates to `research/ianseo-internals.md`                                            |
| **Constraints** | MUST NOT modify files outside `Modules/Sets/PL/`. Must follow all conventions below.                   |

**System prompt essence:**

> You are a PHP developer working on the ianseo archery system's Polish federation module (`Modules/Sets/PL/`). You MUST NOT modify files outside this directory. Follow these patterns:
>
> - Bootstrap: `require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php'); CheckTourSession(true);`
> - DB helpers: `safe_r_sql()`, `safe_w_sql()`, `StrSafe_DB()`, `safe_fetch()`, `safe_num_rows()`, `safe_free_result()`
> - Templates: `include('Common/Templates/head.php')` / `tail.php` for UI pages
> - Auto-install tables: `SHOW TABLES LIKE` pattern — no install-script changes
> - Naming: `PL` prefix for classes/tables, `pl_` prefix for functions
> - PDF: extend TCPDF from `Common/tcpdf/tcpdf.php`
> - Organization: features in subdirectories `PL/{FeatureName}/`
> - Menu: register in `menu.php`, guarded by `$_SESSION["TourLocRule"]=='PL'`
> - UI text: all in Polish
> - Always consult `research/ianseo-internals.md` for API details before implementing

### 2.3 — Reviewer Agent (`reviewer.prompt.md`)

| Property        | Value                                                                  |
| --------------- | ---------------------------------------------------------------------- |
| **Role**        | Code reviewer enforcing quality, security, and consistency             |
| **Inputs**      | Diffs or file contents from the Developer agent, both research files   |
| **Outputs**     | Structured review: APPROVE or REQUEST_CHANGES with line-level comments |
| **Constraints** | Read-only. Returns review comments only.                               |

**System prompt essence:**

> You are a code reviewer for the ianseo PL module. Review PHP code against this checklist:
>
> 1. **Security**: SQL injection prevention (`StrSafe_DB()`), XSS (escape output), session checks (`CheckTourSession`)
> 2. **Conventions**: `PL`/`pl_` naming, correct bootstrap path, proper ianseo DB abstraction
> 3. **Scope**: No modifications outside `Modules/Sets/PL/`
> 4. **DB safety**: Auto-install patterns, no hardcoded IDs conflicting with core
> 5. **Completeness**: Menu registration, error handling, edge cases
> 6. **Consistency**: Matches patterns documented in `research/ianseo-internals.md`
>    Return structured review with APPROVE or REQUEST_CHANGES and inline comments per file.

### 2.4 — Workflow Guide (`workflow.md`)

Document the execution flow (see Step 4) so any contributor can follow it.

---

## Step 3 — Research Phase (Before Any Feature Work)

This is a **mandatory prerequisite**. Both Advisor and Developer agents produce reference documents that become persistent context for all future work.

### 3.1 — Advisor Produces `research/pzlucz-rules.md`

**Input:** PZŁucz regulations PDF + `Modules/Sets/lib.php` + reference sets (IT, FITA)

**Required sections:**

| Section                    | Content                                                                                                                           |
| -------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| Competition formats        | All PZŁucz tournament types (outdoor, indoor, field, 3D) — distances, arrow counts, end sizes, session structure                  |
| Divisions                  | Bow types (klasyczny, bloczkowy, goły łuk, instynktowny, etc.) with codes                                                         |
| Age classes                | All categories (kadet młodszy, kadet, junior, junior młodszy, senior, master, etc.) with age boundaries and division eligibility  |
| Scoring rules              | Target faces per division/distance, X-ring counting, tiebreaking procedures                                                       |
| Qualification formats      | Ends, arrows per end, distance sequences per tournament type                                                                      |
| Elimination & finals       | Bracket sizes, set system vs cumulative scoring, shoot-off rules                                                                  |
| Team composition           | Sizes, mixed team rules, selection rules (e.g., best 3 of 4)                                                                      |
| Rankings & classifications | Standings computation, national ranking points, minima                                                                            |
| ianseo mapping             | For each PZŁucz concept: corresponding ianseo tournament type ID, `lib.php` constant/function, or note that custom code is needed |
| Gaps & custom needs        | PZŁucz rules that have NO direct ianseo equivalent                                                                                |

### 3.2 — Developer Produces `research/ianseo-internals.md`

**Input:** ianseo codebase (`Common/`, `Modules/Sets/`, `Modules/config.php`, reference sets IT/FITA)

**Required sections:**

| Section                   | Content                                                                                                                                                                                                                                             |
| ------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Bootstrap & session       | `config.php` initialization, `CheckTourSession()`, available `$_SESSION` variables                                                                                                                                                                  |
| DB abstraction layer      | All `safe_*()` functions with signatures, `StrSafe_DB()`, query patterns, error handling                                                                                                                                                            |
| Sets framework            | How `sets.php` registers types/rules, `$SetType` array structure, how setup scripts are discovered                                                                                                                                                  |
| `lib.php` functions       | Full signatures + parameter semantics for `CreateDivision()`, `CreateClass()`, `CreateSubClass()`, `CreateEvent()`, `CreateEventNew()`, `InsertClassEvent()`, `CreateFinals*()`, `CreateTargetFace()`, `CreateDistanceNew()`, `UpdateTourDetails()` |
| Constants                 | All `TGT_*`, `MATCH_*`, `FINAL_*` constants with meaning                                                                                                                                                                                            |
| Setup scripts             | How `Setup_{N}_{Code}.php` are discovered and executed                                                                                                                                                                                              |
| Menu system               | How `menu.php` is loaded via `glob()`, available menu keys (`COMP`, `PART`, `QUAL`, `ELIM`, `FINI`, `FINT`, `CLUB`, `CAST`, `PRNT`, `HHT`, `MEDI`), submenu creation                                                                                |
| Template system           | `head.php`/`tail.php`, available CSS/JS, page structure                                                                                                                                                                                             |
| PDF generation            | TCPDF integration, existing print page patterns, output buffering                                                                                                                                                                                   |
| AJAX patterns             | Endpoint structure, JSON response conventions                                                                                                                                                                                                       |
| Ranking system            | `Obj_Rank_*` base classes, how to override calculations                                                                                                                                                                                             |
| Auto-install pattern      | `SHOW TABLES LIKE` approach with code examples                                                                                                                                                                                                      |
| Reference implementations | Key patterns from IT and FITA sets                                                                                                                                                                                                                  |

### 3.3 — Cross-Review

After both documents are produced:

- Advisor reads `ianseo-internals.md` to refine the ianseo mapping column in `pzlucz-rules.md`
- Developer reads `pzlucz-rules.md` to annotate `ianseo-internals.md` with PL-specific considerations

Both updated files are committed.

---

## Step 4 — Feature Development Workflow (Repeatable Loop)

For every new feature, follow this cycle:

```
┌──────────────────────────────────────────────────────────────┐
│  FEATURE REQUEST (human or backlog item)                     │
└──────────────┬───────────────────────────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────────────────────────┐
│  STEP A: ADVISOR AGENT                                       │
│  Reads: PZŁucz PDF + research/pzlucz-rules.md +             │
│         research/ianseo-internals.md                         │
│  Produces:                                                   │
│   · Feature specification (tournament config, data model     │
│     mapping, UI requirements, file list to create/modify)    │
│   · Updates to research/pzlucz-rules.md if new rules found  │
└──────────────┬───────────────────────────────────────────────┘
               │ spec document
               ▼
┌──────────────────────────────────────────────────────────────┐
│  STEP B: DEVELOPER AGENT                                     │
│  Reads: spec + research/ianseo-internals.md +                │
│         research/pzlucz-rules.md + ianseo codebase           │
│  Produces:                                                   │
│   · Working PHP code in PL/{FeatureName}/                    │
│   · Menu entries in menu.php                                 │
│   · Updates to research/ianseo-internals.md if new           │
│     patterns/APIs discovered during implementation           │
└──────────────┬───────────────────────────────────────────────┘
               │ code changes (diff)
               ▼
┌──────────────────────────────────────────────────────────────┐
│  STEP C: REVIEWER AGENT                                      │
│  Reads: diff + research/*.md                                 │
│  Returns: APPROVE or REQUEST_CHANGES with inline comments    │
└──────────────┬───────────────────────────────────────────────┘
               │
        ┌──────┴──────┐
        │             │
   APPROVED    REQUEST_CHANGES
        │             │
        ▼             └──► back to STEP B with review comments
   git add + commit         (loop until approved)
   push to remote
```

---

## Step 5 — Research Maintenance Policy

The research files are living documents:

| Trigger                                                  | Action                                                    |
| -------------------------------------------------------- | --------------------------------------------------------- |
| PZŁucz publishes new regulations                         | Advisor updates `pzlucz-rules.md` with new PDF            |
| ianseo upgrades to a new version                         | Developer updates `ianseo-internals.md` from new codebase |
| Feature implementation reveals undocumented API behavior | Developer appends finding to `ianseo-internals.md`        |
| New competition format requested that wasn't mapped      | Advisor adds section to `pzlucz-rules.md`                 |

Each update is committed with a descriptive message (e.g., `docs: update pzlucz-rules for 2027 regulations`).

---

## Step 6 — Practical Execution in VS Code

Since you're using VS Code with Copilot, each agent maps to a **Copilot Chat session**:

| Action        | How                                                                                                                                     |
| ------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| Run Advisor   | Open chat → set system context from `advisor.prompt.md` → attach PZŁucz PDF + research files → describe the feature or ask for research |
| Run Developer | Open chat → set system context from `developer.prompt.md` → provide spec + research files → implement                                   |
| Run Reviewer  | Open chat → set system context from `reviewer.prompt.md` → paste diff/files → get review                                                |
| Commit        | Terminal: `git add -A; git commit -m "feat: {description}"` in `PL/` repo                                                               |

---

## Step 7 — Suggested Feature Roadmap

After research is complete, tackle features in this order:

| Priority | Feature                       | Directory       | Description                                                                                 |
| -------- | ----------------------------- | --------------- | ------------------------------------------------------------------------------------------- |
| **0**    | Research phase                | `research/`     | Produce both `.md` files — **blocking prerequisite**                                        |
| **1**    | Full tournament setup scripts | `PL/Setup/`     | `Setup_1_PL.php`, `Setup_3_PL.php`, `Setup_6_PL.php` — Polish divisions, classes, distances |
| **2**    | Extended `sets.php`           | `PL/`           | More tournament types, proper sub-rules for Polish formats                                  |
| **3**    | Polish field/3D support       | `PL/Setup/`     | Setup scripts for field archery under PZŁucz rules                                          |
| **4**    | Custom printouts              | `PL/Printouts/` | Result sheets, start lists with Polish formatting                                           |
| **5**    | License/classification check  | `PL/License/`   | Validate athlete classifications per PZŁucz regulations                                     |

---

## Summary of Implementation Steps

| Step    | What                                      | Who                            | Output                                 |
| ------- | ----------------------------------------- | ------------------------------ | -------------------------------------- |
| **1**   | Init standalone git repo in `PL/`         | Human                          | Git repo with existing files committed |
| **2**   | Create `.github/agents/*.prompt.md` files | Human + Copilot                | 4 versioned prompt files               |
| **3.1** | Research: PZŁucz rules mapping            | Advisor agent + PZŁucz PDF     | `research/pzlucz-rules.md`             |
| **3.2** | Research: ianseo internals                | Developer agent + codebase     | `research/ianseo-internals.md`         |
| **3.3** | Cross-review research files               | Both agents                    | Refined research files                 |
| **4**   | Feature loop (repeatable)                 | Advisor → Developer → Reviewer | Working code, committed                |
| **5**   | Maintain research files                   | Agents, on triggers            | Updated docs                           |
