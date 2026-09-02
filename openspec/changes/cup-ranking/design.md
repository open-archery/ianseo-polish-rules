## Context

See `proposal.md` — Why. The points-ranking module already computes a full Puchar Polski round: `pl_points_calculate($tourId, PL_POINTS_PRESETS['pp'])` returns two `SEPARATE` reports (`ind`, `mix`) whose rows carry name, licence (`code`), club, category and place, with points already bracketed. The cup layer needs nothing more from that engine than its output — plus the qualification score, which the engine does not read.

Constraint that shapes everything: the four rounds live on four unrelated ianseo installations, and only the final host has a database. The only transport is a file the operator carries, and for rounds shot before this feature exists, a file typed by hand from a PDF.

## Goals / Non-Goals

**Goals:**

- One storage shape for a round, whatever its origin (import or local snapshot), so aggregation has a single code path.
- Aggregation as pure functions over stored rows, unit-testable without a database, matching the existing `PointsRankingCalc.php` split.
- Zero change to the points-ranking engine's behaviour — the cup reads it, never modifies it.

**Non-Goals:**

- Generalising the cup layer to other presets or to a variable round count (4 and `pp` are hardcoded; see proposal Non-goals).
- Syncing rounds over the network between installations.

## Decisions

### D1 — Every round is a stored snapshot, including the local one

The current tournament's result is written into the same table as imported rounds, by a "Zapisz bieżącą rundę" button, instead of being computed live and merged at read time.

Alternative: aggregate imported rounds 1–3 with a live calculation of round 4. Rejected — it doubles the read path (stored rows vs. report structures), and it makes the local round behave differently from every other (no export symmetry, no way to represent a round shot at *this* installation but under a different tournament).

Cost of D1 is one manual step that can go stale, paid for by D5.

### D2 — Three tables, edition-scoped rows

```
PLCupConfig                          PLCupRound
  PlCcTournament   PK                  PlCrEdition        \
  PlCcEdition                          PlCrRound           |
  PlCcRound                            PlCrClassification  | PK
                                       PlCrCategory        |
                                       PlCrIdentity       /
                                       PlCrName
                                       PlCrClubName
                                       PlCrPlace
                                       PlCrPoints
                                       PlCrQualScore
```

`PLCupBarrage` (D6a) is the third table and is edition-scoped like `PLCupRound`.

`PLCupRound` is deliberately **not** keyed by tournament: rounds 1–3 have no tournament here. The edition (a year) is the season boundary, so 2025 and 2026 rows coexist without bleeding. `PLCupConfig` holds only what is per-tournament: which edition and round this tournament is. Diploma wording is not stored at all — it follows from the category and the edition (see the cup-diplomas requirement).

All three tables auto-install with the module's `SHOW TABLES LIKE` pattern (`pl_cup_ensure_tables()`).

### D3 — Identity: licence for individuals, club code for mixed

Individuals are matched across rounds by `Entries.EnCode`; mixed pairs by `Countries.CoCode`, because the regulation allows one mixed pair per club per Puchar Polski round while the pair's members may change. Both are enforced at write time — a missing licence, a missing club code or two pairs of one club aborts the whole snapshot/import, rather than silently producing a row that will never match its counterpart in another round.

Alternative for individuals: name + birth year. Rejected — the licence is authoritative, present in ianseo, and printed on every round's result PDF, so a hand-typed CSV can carry it.

### D4 — Qualification score loaded by the cup layer, not by the engine

A new `pl_cup_load_qual_scores($tourId)` reads `Qualifications.QuScore` keyed by `EnId` (joined through `Entries` for the tournament), and `Teams.TeScore` keyed by club + event, and the snapshot builder joins it onto the report rows. The points-ranking loaders stay untouched, so `points-ranking`'s spec and its tests are unaffected.

Not `Individuals.IndScore`: that column stays at its `-1` sentinel in this ruleset's competitions (every athlete of every tournament on the test installation), so reading it exported `-1` as everyone's qualification score. `QuScore` is the total ianseo maintains per entry over the whole qualification round, and it is what ianseo's own team ranking reads. Negative values are clamped to 0 on the way in, so a sentinel can never look like a score.

Mixed rows lose `TeSubTeam` inside `pl_points_calculate()`, so the team score lookup is keyed by club + event only — safe exactly because D3 rejects two pairs of one club.

### D4a — A stored round keeps placed rows that scored nothing

The tie-break reads the best place and the highest qualification score "w dowolnej rundzie", so a round outside the points brackets still carries data that can decide the cup — dropping those rows would silently rank the wrong archer first. They are stored, and filtered out only at the classification's own level: a competitor whose rounds sum to zero is not listed.

Cost: a bigger stored round (every placed archer, not only the scoring ones). Imported rounds are unaffected — the CSV carries whatever rows the earlier host's PDF lists, and a missing row simply contributes nothing.

### D5 — Staleness by rebuilding, not by hashing

On every view of the cup page, the snapshot builder runs against the live tournament and its output is compared row-for-row against the stored rows of this tournament's round. Differences are counted and reported. No stored checksum, no cache to invalidate: the same function produces both sides, so a change in the builder cannot make an old checksum lie.

The page costs one extra `pl_points_calculate()` per view — the same cost the round page already pays.

### D6 — Tie-break chain declared per cup series, terminator included

The regulation states different chains for different Puchar Polski series, and states none at all for seniors, compound and mixed. The chain is a constant map keyed by division plus class, each entry naming its steps and its terminator:

```php
// division . class group          steps                        terminator
'R' U21M,U21W  => ['place','qual'],                             'SHARED'
'R' U18M,U18W  => ['place','qual'],                             'BARRAGE'
'B' *          => ['qual'],                                     'BARRAGE'
'R' M,W        => [],                                           'SHARED'
'C' *          => [],                                           'SHARED'
mixed *        => [],                                           'SHARED'
default        => [],                                           'SHARED'
```

Deliberately **not** collapsed to "division letter, barebow skips the place step". That generalisation reads the barebow rule onto seniors, compound and mixed, where the annex is silent — inventing a tie-break the federation has not written. Silent series get an empty chain: equal sums share a rank, marked as a shared place. If PZŁucz confirms one common interpretation, this map is the single edit point.

`SHARED` and `BARRAGE` differ only in the mark the renderers print and in whether D6a's recording control is offered.

### D6a — Baraż outcome stored as a per-row order, applied as the last comparator step

The shoot-off is judged on the line, so the system only records its result: a third table, `PLCupBarrage` (`PlCbEdition`, `PlCbClassification`, `PlCbCategory`, `PlCbIdentity` PK, `PlCbOrder`), holds one small integer per row of a tied group, 1 being the winner. The comparator's final step is `manual order asc`, so a recorded outcome breaks the tie; rows without a recorded position stay tied and keep the "baraż" mark.

Offered only for categories whose D6 terminator is `BARRAGE`, and only once the edition's round 4 is stored — a baraż settles the cup, not a standing after round 2. Gating on "round 4 present" rather than "this tournament is round 4" also keeps the classification honest when it is opened at another host after the season ends.

Keyed by identity rather than by "tied group", because a group has no stable id — a correction to any round can change who is in it. That also makes the outcome survive a re-import (it is stored outside `PLCupRound`, which is wiped per round) and become inert on its own once the tie disappears, since the step is only reached by rows that are still equal on everything else.

Alternative: store an explicit final rank per row. Rejected — it would have to be rewritten whenever any round changes, and it lets the operator contradict the regulation order.

Rows ranked by a recorded outcome are flagged `barrage_resolved` and both renderers print that, so the PDF explains why two equal totals rank differently.

### D7 — CSV: semicolon, UTF-8 BOM, header row

Columns: `Klasyfikacja;Kategoria;Identyfikator;Nazwa;Klub;Miejsce;Punkty;Kwalifikacje`. Semicolon plus BOM is what Polish Excel opens without an import wizard, and rounds 1–3 will be typed in Excel. Reading accepts both `,` and `.` as decimal separator; writing emits plain integers (every `pp` bracket value is an integer).

Alternative: JSON. Rejected — not hand-editable by the operators who must transcribe old rounds from a PDF.

### D8 — Category codes validated against configured events, not entered categories

Import validates `Kategoria` against the tournament's configured `Classes`/`EventClass` codes rather than against `pl_points_load_categories()`, which only returns categories that have entries. A category shot in round 1 but empty in round 4 must still import. Section labels come from `pl_ranking_div_labels()`, falling back to the raw code when the tournament has no such class configured.

### D9 — Files

Create:

| File | Role |
|---|---|
| `PointsRanking/Fun_Cup.php` | tables, config access, qual-score loader, snapshot builder, CSV read/write |
| `PointsRanking/CupCalc.php` | pure: aggregation, tie-break comparator, ranking, snapshot diff |
| `PointsRanking/Cup.php` | UI — edition/round/diploma-name config, snapshot button, import/export, HTML classification, baraż outcome form |
| `PointsRanking/PrnCupRanking.php` | cup PDF (TCPDF, same style as `PointsRankingPdf.php`) |
| `PointsRanking/PrnCupDipl.php` | cup diplomas via `PLDiplomaPdf` + `pl_diploma_get_config()` |
| `PointsRanking/Fun_CupTest.php`, `PointsRanking/CupCalcTest.php` | PHPUnit, `FakeDb` for the loaders, pure tests for the calc |

Modify:

- `menu.php` — one entry under `PRNT`: `'Puchar Polski - klasyfikacja|' . $CFG->ROOT_DIR . 'Modules/Sets/PL/PointsRanking/Cup.php'`. The menu entry is always rendered for the PL ruleset; the page itself enforces the `pp` gate (menu.php has no tournament preset context and should not query for one).

ianseo hook points are the module's usual ones: `config.php` bootstrap + `CheckTourSession(true)`, `Common/Templates/head.php`/`tail.php` for the page, `safe_*` helpers for all SQL, TCPDF via the existing `PLDiplomaPdf` class.

## Risks / Trade-offs

- **Stale snapshot silently drives the classification** → the page compares and warns on every view (D5); the classification is still rendered so a stale round is visible rather than missing.
- **Round 1–3 CSV typed from a PDF has transcription errors** → import is atomic and validates category codes and identities, so a bad file changes nothing; the export path removes the typing entirely once every host runs this module.
- **Category codes differ if an earlier round was not run on the PL module** → import reports the offending line and code; the operator edits the CSV. Codes are deterministic in `lib.php`, so this only affects rounds run elsewhere.
- **An athlete's licence changes mid-season** → their two rows never merge. Not handled in code; the operator edits the CSV. The regulation treats the licence as the athlete's identity.
- **Mixed identity assumes one pair per club per round** → enforced by rejection (D3), so a violating tournament fails loudly instead of merging two pairs.
- **Individual diplomas for the whole cup reuse the round's dates and location** → accepted: the diploma is handed out on the last day of the final round, which is what that configuration holds.

## Migration Plan

No migration. Tables auto-install on first visit to the cup page; nothing else in the module reads them. Rollback is deleting the new files and the menu line — the three `PL` tables are orphaned but harmless, and the points-ranking module is untouched.
