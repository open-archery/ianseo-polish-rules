# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Polish Archery Federation (PZŁucz) ruleset module** for the [ianseo](https://www.ianseo.net/) tournament management system. All code lives exclusively in `Modules/Sets/PL/` — the ianseo core is read-only.

## Commands

There are no build or compile steps. This is a pure PHP project loaded directly by ianseo.

**Dependency install:**

```bash
composer install
```

**Automated tests (PHPUnit):**

One-time setup — download the pinned PHPUnit phar (not via Composer; the module has no autoload/deps to justify it):

```bash
# macOS / Linux / ianseo-docker container
mkdir -p tools
curl -fsSL -o tools/phpunit.phar https://phar.phpunit.de/phpunit-11.5.phar
```

```powershell
# Windows
New-Item -ItemType Directory -Force tools
Invoke-WebRequest https://phar.phpunit.de/phpunit-11.5.phar -OutFile tools\phpunit.phar
```

Run the suite:

```bash
# macOS / Linux / ianseo-docker container (requires php on PATH)
tools/test.sh
tools/test.sh --filter ClubName   # focused run for TDD
```

```
:: Windows (uses the ianseo-bundled PHP if none is on PATH)
tools\test.cmd
tools\test.cmd --filter ClubName
```

Test files live next to the code they test (e.g. `Lookup/ClubNameTest.php` tests `Lookup/Fun_ClubName.php`) — PHPUnit discovers any `*Test.php` file in the module tree; the class name must match the filename. Shared test infrastructure lives in `tests/`: `tests/bootstrap.php` shims ianseo's global DB functions (`safe_r_sql`, `safe_w_sql`, `StrSafe_DB`, etc. — see `tests/Support/FakeDb.php`) since module files only call them, never define them. Stub SQL results with `FakeDb::on($regexPattern, $rows)`; assert writes happened with `FakeDb::executed($regexPattern)`; simulate a write failure with `FakeDb::throwOn($regexPattern, $message)` (needed for transaction-rollback tests). For code that calls `Modules/Sets/lib.php`-style core builders (`CreateDivision`, `CreateClass`, `InsertClassEvent`) instead of `safe_*` directly, `tests/Support/CallLog.php` records the call args — assert with `CallLog::calls($fnName)` / `CallLog::callsMatching($fnName, $predicate)`. Pure functions (e.g. `Lookup/Fun_ClubName.php`) need no stubbing at all. Manual tournament testing in ianseo with the PL ruleset active is still the way to verify UI/integration behavior end-to-end.

## OpenSpec Workflow

This project uses the [OpenSpec](https://openspec.dev) spec-driven workflow. Config is in `openspec/config.yaml`.

Key commands (Claude Code skills):

- `/openspec-explore` — thinking/scoping mode before writing a spec
- `/openspec-propose` — create a change with proposal + design + tasks in one step
- `/openspec-apply-change` — implement tasks from a change
- `/openspec-archive-change` — archive a completed change

Full workflow documented in `.github/agents/workflow.md`.

## Key Reference Files

Research files (read before implementing):

- `.github/agents/research/ianseo-internals.md` — ianseo API reference: DB layer, session handling, module registration, AJAX patterns, ranking, auto-install patterns
- `.github/agents/research/pzlucz-rules.md` — PZŁucz competition rules mapped to ianseo concepts
- `.github/agents/research/regulamin-lucznictwa.md` — Full Polish archery regulations (source of truth)

Implemented feature specs live in `openspec/specs/`:

| Capability                         | Spec                                       | Design                              |
| ---------------------------------- | ------------------------------------------ | ----------------------------------- |
| Tournament setup (1440/70m/indoor) | `openspec/specs/tournament-setup/spec.md`  | `design.md`                         |
| Post-elimination ranking           | `openspec/specs/post-elim-ranking/spec.md` | `design.md`                         |
| Sportzona athlete lookup           | `openspec/specs/sportzona-lookup/spec.md`  | `design.md`                         |
| Bib list batch import              | `openspec/specs/bib-import/spec.md`        | `design.md`                         |
| Diplomas module                    | —                                          | `openspec/specs/diplomas/design.md` |

## Architecture

### Module Entry Points

| File             | Role                                                                                             |
| ---------------- | ------------------------------------------------------------------------------------------------ |
| `sets.php`       | Registers tournament types (1=1440 FITA, 3=70m, 6=18m indoor) and the "Poland-Full" sub-rule     |
| `menu.php`       | Registers menu items; guarded by `$_SESSION["TourLocRule"]=='PL'`                                |
| `lib.php`        | Shared helpers: `CreateStandardDivisions()`, `CreateStandardClasses()`, `InsertStandardEvents()` |
| `Setup_1_PL.php` | FITA 1440 round setup (4 distances, 12 ends, 360 max, no eliminations)                           |
| `Setup_3_PL.php` | 70m outdoor setup (2 sessions, eliminations except U15)                                          |
| `Setup_6_PL.php` | 18m indoor setup (2 sessions × 10 ends × 3 arrows; U12 shoots at 15m)                            |

### Features

- **`Diplomas/`** — PDF diploma generation via TCPDF; config UI, setup, business logic in `Fun_Diploma.php`, PDF renderer in `PLDiplomaPdf.php`
- **`Rank/`** — Custom ranking objects for individual and team finals (`Obj_Rank_FinalInd_calc.php`, `Obj_Rank_FinalTeam_calc.php`)
- **`Import/`** — Athlete import by licence number (`BibImport.php` UI, `Fun_BibImport.php` logic)
- **`Lookup/`** — Sportzona API proxy for club/athlete lookup (`SportzonaProxy.php`, `Fun_ClubName.php`)

### Conventions

- **Scope:** All changes stay within `Modules/Sets/PL/`. Never modify ianseo core.
- **DB tables:** Prefixed with `PL`; created on first use via `SHOW TABLES LIKE` auto-install pattern.
- **Functions:** Prefixed with `pl_`.
- **UI language:** All user-facing text in Polish; code comments in English.
- **Line endings:** LF only (enforced by `.gitattributes` and `.vscode/settings.json`).
- **PHP version:** 8.2+ (matches the ianseo-bundled runtime and PHPUnit 11's minimum).

## Agent Roles

Three specialized roles guide feature development (prompts in `.github/agents/`):

- **Advisor** (`advisor.prompt.md`) — PZŁucz domain expert; writes `openspec/specs/{feature}/spec.md`
- **Developer** (`developer.prompt.md`) — ianseo PHP implementer; writes `openspec/changes/{name}/design.md` and code
- **Reviewer** (`reviewer.prompt.md`) — code quality gate; reviews before archiving
