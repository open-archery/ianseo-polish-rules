## Context

ianseo manages qualification team rosters through the `Teams` + `TeamComponent` tables, normally populated by `Qualification/MakeTeams.php` which auto-groups athletes by country code. This mechanism is destructive: it deletes and recreates all teams on each run.

**Correction (2026-09-08):** it *can* be intercepted from a module — see Decision 6. Both core team builders look for a rule-set override file before running their default body, and ianseo's own PL set ships one. The rest of this document still assumes the backup/restore approach; Decision 6 records the alternative and what it would replace.

PZŁucz rules require operators to manually declare team rosters before qualification starts. Teams may contain 3 or 4 athletes; a 4-athlete team's qualification score is the sum of the best 3 individual scores. The current PL module has no team composition UI and no `Obj_Rank_DivClassTeam_calc.php` override, so both team entry and scoring fall back to ianseo defaults.

## Goals / Non-Goals

**Goals:**
- Let operators create/edit/delete qualification team rosters via a PL UI page
- Protect manually declared rosters from ianseo's core `MakeTeams` overwrite via a PL-owned backup table (`PLTeamDeclaration`)
- Implement best-3-of-4 team score calculation as a rank override
- Support multiple teams per club (TeSubTeam) and pre-qualification roster entry (athletes with QuScore=0)
- Trigger rank recalculation automatically on every roster save/delete
- Update `EvMaxTeamPerson=4` in all three setup scripts

**Non-Goals:**
- Modifying ianseo core files
- Blocking the core "Make Teams" button from executing — but see Decision 6: a rule-set override hook makes intercepting it possible without touching core, so this non-goal is under review
- Mixed team composition
- Field/3D team formats
- Per-end substitution UI (handled by ianseo core `ChangeComponents.php`)

## Decisions

### Decision 1: PLTeamDeclaration as source of truth

**Choice:** Store team declarations in a PL-owned table (`PLTeamDeclaration`) that mirrors what should be in `Teams` + `TeamComponent`. The UI reads/writes this table; a sync function pushes it to ianseo's standard tables.

**Alternatives:**
- Write only to `Teams`/`TeamComponent` directly — simpler, but data is lost whenever `MakeTeams` runs. No recovery path.
- Write only to `PLTeamDeclaration` and never touch `Teams`/`TeamComponent` — ianseo's existing ranking engine and printouts would see no teams. The rank override would need to read from PL tables instead of the standard ones, breaking compatibility with core ranking display pages.

**Rationale:** Dual-write gives both recovery safety and full compatibility with ianseo's standard qualification display, printouts, and final seeding — all of which read from `Teams`/`TeamComponent`.

**Table schema:**
```sql
CREATE TABLE PLTeamDeclaration (
    PltdTournament  INT            NOT NULL,
    PltdEvent       VARCHAR(10)    NOT NULL,   -- event code, e.g. 'RM'
    PltdCoId        INT            NOT NULL,   -- club/country ID (EnCountry)
    PltdSubTeam     INT            NOT NULL DEFAULT 1,
    PltdId          INT            NOT NULL,   -- athlete EnId
    PltdOrder       INT            NOT NULL,   -- position 1..4
    PltdTimestamp   DATETIME       NOT NULL,
    PRIMARY KEY (PltdTournament, PltdEvent, PltdCoId, PltdSubTeam, PltdId)
)
```

Auto-installed via `SHOW TABLES LIKE 'PLTeamDeclaration'` pattern on first page load.

### Decision 2: Sync strategy (PLTeamDeclaration → Teams + TeamComponent)

**Choice:** Full replace per (tournament, event, club, sub-team). On every save or delete, sync deletes the Teams + TeamComponent rows for the affected (event, club, sub-team) and re-inserts from PLTeamDeclaration.

**Rationale:** Simplest correct approach — no delta tracking needed. The affected scope is a single team (one club + sub-team + event), so collateral impact is minimal.

**Sync sequence (per affected team):**
1. `DELETE FROM TeamComponent WHERE TcTournament=? AND TcEvent=? AND TcCoId=? AND TcSubTeam=? AND TcFinEvent=0`
2. `DELETE FROM Teams WHERE TeTournament=? AND TeEvent=? AND TeCoId=? AND TeSubTeam=? AND TeFinEvent=0`
3. If PLTeamDeclaration still has rows for this team: `INSERT INTO Teams (...)` then `INSERT INTO TeamComponent (...)` for each athlete
4. Trigger `Obj_RankFactory::create('DivClassTeam', ...)->calculate()`

### Decision 3: Best-3-of-4 rank override approach

**Choice:** `PL/Rank/Obj_Rank_DivClassTeam_calc.php` overrides `calculate()` to:
1. For each team, query `TeamComponent JOIN Qualifications` to get all athletes' scores
2. Rank athletes within each team: `ORDER BY QuScore DESC, QuGold DESC, QuXnine DESC`
3. Take the top 3 (handles both 3- and 4-athlete teams correctly)
4. `UPDATE Teams SET TeScore=SUM(top3), TeGold=SUM(top3gold), TeXnine=SUM(top3x)`
5. Rank teams: `ORDER BY TeScore DESC, TeGold DESC, TeXnine DESC`

**Alternatives:**
- Override via sub-query with MySQL user variable rank — works but fragile across MySQL versions. Prefer PHP-side top-3 selection.
- Separate "recalc score" pass + existing core "rank" pass — requires calling core's calculate() which could overwrite scores. Single override is cleaner.

**Pattern reference:** `Modules/Sets/NO/Rank/Obj_Rank_DivClassTeam_calc.php` uses a similar TeamComponent+Qualifications join for tuple tiebreaking. PL's override goes further by recalculating TeScore before ranking.

### Decision 4: AJAX page structure

**Choice:** Two-file pattern matching existing PL module pages:
- `Teams/ManualTeams.php` — full-page UI with jQuery, event/club selector, athlete list
- `Teams/ManualTeams-data.php` — AJAX endpoints (action param: `save`, `delete`, `restore`, `list`)

**Rationale:** Consistent with `Import/BibImport.php` + `Fun_BibImport.php` pattern already in the module. Simple, no framework dependency.

### Decision 5: EvMaxTeamPerson update in setup scripts

**Choice:** Set `EvMaxTeamPerson=4` for all team events in Setup_1_PL, Setup_3_PL, Setup_6_PL.

**Rationale:** This parameter governs the maximum size of per-end finals roster in `ChangeComponents.php`. Setting it to 4 allows the finals UI to accommodate 4-person rosters for substitution. No impact on 3-athlete teams (operators simply don't add a 4th).

### Decision 6: The core team builders have a rule-set override hook

**Status:** open — this was found after the rest of the design was written, while comparing our module against ianseo's official PL set. It is not yet decided whether to use it.

**Finding:** both core builders look for a rule-set file before running their default body, and skip that body entirely when they find one:

| Core function | Looked-up path | Location |
| --- | --- | --- |
| `MakeTeams()` | `Modules/Sets/{ToLocRule}/Functions/MakeTeams%s.php` | `Qualification/Fun_Qualification.local.inc.php:363` |
| `MakeTeamsAbs()` | `Modules/Sets/{ToLocRule}/Functions/MakeTeamsAbs%s.php` | `Qualification/Fun_Qualification.local.inc.php:1044` |

`%s` is tried as `-{ToType}-{ToSubRule}-{ToYear}`, `-{ToType}-{ToSubRule}`, `-{ToType}`, `-{ToSubRule}-{ToYear}`, `-{ToSubRule}`, `-{ToYear}`, then empty — first match wins. ianseo's official PL set ships `Functions/MakeTeamsAbs.php` (~25KB), so the hook is in active use upstream.

**Why it matters here:** the backup/restore mitigation exists because rosters can be regenerated from many places, not just the "Make Teams" button — `Partecipants/Fun_Partecipants.local.inc.php:277` and `Participants/lib.php:376` (editing a participant), `Qualification/UpdateArrow.php` (score entry), `Modules/Barcodes/GetScoreBarCode.php`, `HHT/Import.php`, `Api/ISK-NG/Lib.php`, `Final/Team/*` and the two buttons. An override file intercepts **all** of them in one place, so rosters would never be destroyed and "Przywróć skład" would become a recovery tool rather than routine operating procedure.

**What the override must handle if adopted:**

- The file is `require_once`'d *inside* the function, so it runs in that function's scope (`$Societa`, `$Div`/`$Category`, `$ToId`, `$ToType`, `$ToSubRule`, `$ToYear`, `$Errore`, `$events4abs`) and must set `$Errore` — the function returns it. `require_once` also means the body executes only on the **first** call within a request; later calls in the same request are silently no-ops.
- Skipping the default body also skips `DropTmpTeams()` and the `runJack("QRRankUpdate", …)` notifications, which the override has to reproduce if anything downstream depends on them.
- It replaces team building for **every** event of a PL tournament, including events with no manual roster — those still need the core auto-grouping behaviour, so the override cannot simply do nothing.
- Naming it `MakeTeams-{ToSubRule}.php` scopes it to one sub-rule, which is a way to limit the blast radius.

**Alternatives:** keep the backup/restore approach as designed (safe, already specified, but the operator must notice and click); adopt the override (rosters never destroyed, but the module takes on responsibility for team building in every code path); or both — override plus the restore action as a safety net.

## Files to Create / Modify

| File | Action | Purpose |
|------|--------|---------|
| `Teams/ManualTeams.php` | CREATE | Main UI page |
| `Teams/ManualTeams-data.php` | CREATE | AJAX endpoints (save/delete/restore/list) |
| `Rank/Obj_Rank_DivClassTeam_calc.php` | CREATE | Best-3-of-4 rank override |
| `menu.php` | MODIFY | Add "Składy drużyn" under QUAL |
| `Setup_1_PL.php` | MODIFY | EvMaxTeamPerson → 4 |
| `Setup_3_PL.php` | MODIFY | EvMaxTeamPerson → 4 |
| `Setup_6_PL.php` | MODIFY | EvMaxTeamPerson → 4 |

## Risks / Trade-offs

**[Risk] MakeTeams destroys TeamComponent data** → Mitigation: "Przywróć skład" action in ManualTeams-data.php re-syncs from PLTeamDeclaration. Documented in operator instructions. Note the trigger surface is wider than the button: editing a participant, entering scores, barcode and HHT import, and the ISK API all call `MakeTeams()` / `MakeTeamsAbs()`, so an operator can lose a roster without knowingly regenerating anything. Decision 6 describes an override hook that would prevent the destruction rather than recover from it.

**[Risk] Rank calc runs on teams with all-zero scores (pre-qual)** → Mitigation: Rank calc skips teams where `TeScore=0` (consistent with core behaviour — `WHERE TeScore<>0`). Teams appear unranked until qualification scores exist. This is expected.

**[Risk] Athlete selected on two different sub-teams of the same club** → Mitigation: Validation in ManualTeams-data.php checks that a given `PltdId` does not appear in another sub-team for the same (tournament, event, club).

**[Risk] EvMaxTeamPerson=4 change requires tournament reset to take effect** → ianseo setup scripts run only at tournament creation. Existing tournaments are unaffected until reset. New PL tournaments will have the correct value.

**[Trade-off] Full-replace sync on save** → On a busy system, a quick succession of saves could trigger multiple rank calc runs. Acceptable for tournament management (not a high-frequency operation).

## Migration Plan

No data migration required. `PLTeamDeclaration` is auto-installed on first page load. Existing tournaments using the PL ruleset are unaffected until an operator visits the new page.

## Open Questions

None — all decisions resolved during exploration.
