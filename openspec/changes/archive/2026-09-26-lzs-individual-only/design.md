## Context

`PointsRanking/Presets.php`'s `lzs` entry currently declares a `tea` (`TEAM`) classification alongside `ind`, sharing one bracket table, with `one_team_per_club => true`. It feeds a `COMBINED(ind, tea, cap 0)` report ("Podsumowanie punktacji"), a `SEPARATE(tea)` report ("Klasyfikacja drużynowa"), and the `CLUB`/`VOIVODESHIP` rollups (which sum post-cap athlete values across whatever classifications are declared). The team roster is read from `TeamComponent` — ianseo's own auto-built qualification team (top-ranked club athletes after the qualification round), per D4 of the archived `points-ranking` design.

That design rested on an annex reading that assumed LZS has team events the same way MMP/MPJ/OOM do. Confirmed with the domain owner during exploration: LZS competitions have no team event at all. `PointsRankingCalc.php` reads every preset flag defensively (`$preset['classifications']`, `!empty($preset['one_team_per_club'])`, etc.), so dropping keys entirely — rather than zeroing them out — is safe and leaves no dead configuration behind.

See proposal.md for the full rationale; this document covers only the mechanics of the fix.

## Goals / Non-Goals

**Goals:**
- `lzs` preset scores individuals only: no team classification, no team roster read, no team report.
- `lzs`'s `CLUB`/`VOIVODESHIP` totals become the sum of individual points only (a natural consequence of removing the `tea` classification, not a separate code path).
- Preset-6 sections of `openspec/specs/points-ranking/spec.md` and its cross-references to LZS-as-team-example are corrected.

**Non-Goals:**
- No change to `mmp`, `mpj`, `mmm`, `oom` — their team classifications are real and untouched.
- No change to the Cup module (`Cup.php`/`CupCalc.php`, Puchar Polski only) — confirmed during exploration it already never touches team data (the `pp` preset has no `tea` classification, and `CupCalc.php` hardcodes `['ind', 'mix']`).
- No DB schema or migration work: presets are read-only PHP constants (D1 of the original design); this is a pure code + spec edit.

## Decisions

**D1 — Delete the `tea` key and its reports from the `lzs` preset, don't zero it out.**
`Presets.php`'s `lzs['classifications']` drops the `tea` entry entirely (only `ind` remains). `lzs['reports']` drops the `SEPARATE(tea)` entry and the `COMBINED(ind, tea, cap 0)` entry is replaced with `SEPARATE(ind)`.

*Why `SEPARATE(ind)` and not `COMBINED(ind, cap 0)`:* `COMBINED` exists to sum an athlete's points across *multiple* listed classifications into a `Suma` column. With only `ind` left, a `COMBINED` report would render a single classification column plus a `Suma` column that always equals it — a redundant column with no other consumer. `SEPARATE(ind)` is what preset 3 (Puchar Polski) already uses for its individual-only table, so this keeps the same report kind for the same shape of data instead of inventing a one-classification "combined" report.

*Why set `one_team_per_club` to `false` rather than delete the key:* every read site uses `!empty($preset[...])`, so either form behaves identically at runtime. Every sibling preset (`mmp`/`mpj`/`mmm`/`oom`) explicitly lists all fields including `one_team_per_club => false`; keeping that shape on `lzs` too avoids making it the one preset with an asymmetric array.

**D2 — `CLUB`/`VOIVODESHIP` need no code change.**
`pl_points_compute_club_totals()` already sums whatever classifications a preset declares; with no `COMBINED` report declared, the existing "no COMBINED report → totals are uncapped, computed over every declared classification" path (already specified in the Club ranking requirement) applies unchanged. Removing `tea` from `classifications` is sufficient — the club rollup naturally becomes individual-only.

**D3 — Spec edits reassign LZS-as-example scenarios to a preset that still has the behavior being illustrated.**
Two requirements outside "Preset definitions" used LZS as their worked example of generic team-classification behavior (`Rank source per classification` → qualification-only competition; `Team points assignment` → qualification team roster). Both are re-pointed at `mmm` (Międzywojewódzkie Mistrzostwa Młodzików), which also scores its team classification with source `QUAL`, so the requirement keeps a live example instead of citing a preset that no longer has the behavior. The `Combined athlete total and max-events cap` → "Unlimited cap" scenario is genericized (no preset named) since `lzs` was the only preset with cap `0` and it no longer has a `COMBINED` report at all.

## Risks / Trade-offs

- **Any tournament with `lzs` already selected** will silently stop showing/scoring the team leg the next time its ranking page or PDF is generated — no data migration, but any previously-printed team diplomas or PDFs for LZS are now based on rules the module no longer models. Not a code risk; a real-world "already happened" risk to flag to the operator, not a task here.
- **Test fixtures**: `Fun_PointsRankingTest.php:399-437` is an end-to-end fixture keyed to `\PL_POINTS_PRESETS['lzs']` that asserts a team roster of 3 scoring 9/3/3/3. Once `tea` is removed, this test must be rewritten as individual-only rather than deleted outright, since it's the module's only end-to-end coverage of the LZS preset shape.

## Migration Plan

No migration. Deploy the updated `Presets.php`; the change takes effect on the next points-ranking calculation for any tournament with `lzs` active, per the existing "preset values change with the code" behavior.
