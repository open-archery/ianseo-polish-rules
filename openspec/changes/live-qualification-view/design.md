## Context

See `proposal.md` - Why for motivation. Relevant existing ianseo core precedent (read, don't modify):

- `Qualification/CheckTargetUpdate.php` + `Fun_AJAX_CheckTargetUpdate.js` + `TargetUpdate_XML.php`: core's own read-only, polling, `AclReadOnly`-gated live status page. It polls every 5s (`ReloadTime = 5000` in the JS) and returns a 3-color per-target status via a hand-typed cutoff time, session-only (no distance axis), no athlete/score/arrow data. This change is a sibling page for the PL module with a richer payload and a different, self-adjusting flag rule (peer-lag, not a manual cutoff) - it does not modify or replace the core page.
- `Qualifications` table already carries everything needed per distance `D1..D8`: `QuD{n}Score`, `QuD{n}Hits`, and `QuD{n}ArrowString` - a fixed-width (`ends*arrowsPerEnd`), space-padded-on-the-right string, one character per arrow, written by whatever wrote the score (phones via the ISK-NG sync API, or the web scorecard UI - this change reads either path identically and never writes). Target grouping comes from `QuTarget` (physical target number) + `QuLetter` (A/B/C/D, the wave assigned by this module's own ABC/ACD target-assignment feature). `DistanceInformation.DiArrows` gives arrows-per-end for a given `(DiSession, DiDistance)`. `Entries.EnStatus <= 1` is the existing "active entry" filter, reused verbatim from `TargetUpdate_XML.php`.
- `Qualifications.QuIrmType` (joined against the small system-wide `IrmTypes` lookup table - `0`=normal, `5`=DNF, `10`=DNS, `15`=DSQ, `20`=DQB - the same join core's own `Qualification/index.php` uses) records a round-status independent of `EnStatus`: an entry can be a fully active, valid entry (`EnStatus <= 1`) and still be DNS/DNF/DSQ/DQB for this specific round. **Discovered by querying the live tournament (XVMJK, `ToId=127`) during verification**: session 2 had 4 such entries passing the `EnStatus <= 1` filter with `QuIrmType` set - the original design didn't account for this and would have flagged them as "lacking results" (0 arrows vs. peers' 36) instead of showing why they have no progress.
- A nonzero `QuD{n}Score` with a zero-length `QuD{n}ArrowString` and `QuIrmType = 0` is a second real pattern found in the same tournament's session 1 (and one entry in session 2): the distance was scored through some non-arrow-by-arrow path (bulk "set all hits" mode in `Qualification/index.php`, or a manual/imported total) rather than a live phone sync, which always writes the arrow string alongside the score (`Api/ISK-NG/Lib.php`'s `UPDATE Qualifications SET QuD{n}Score=..., QuD{n}ArrowString='{arrowString}', ...`). Naively this reads as "0 arrows shot" and would false-flag a fully-scored distance as stalled.

## Goals / Non-Goals

**Goals:**
- Reuse the read-only ACL guard and polling shape core already validated, applied to a new PL-owned page and endpoint.
- Compute everything from data already written by existing score-entry paths; zero new writes, zero new tables.
- Keep the peer-lag threshold computation a pure, unit-testable function independent of the DB query that feeds it.

**Non-Goals:**
- Not a kiosk/TV auto-cycling display (per explore-mode decision: admin-dashboard style, dropdowns stay visible for re-filtering).
- Not a replacement for or modification of `Qualification/CheckTargetUpdate.php`.
- No websocket/SSE push channel - plain interval polling matches core's own approach and this module's existing AJAX patterns (`Lookup/SportzonaProxy.php`).

## Decisions

**Integration/hook point:** New menu entry under the existing `QUAL` (Qualification) menu key in `menu.php`, guarded the same way every other PL entry is (`$on && $_SESSION["TourLocRule"]=='PL'`):
```php
$ret['QUAL'][] = 'Podgląd na żywo|' . $CFG->ROOT_DIR . 'Modules/Sets/PL/Live/LiveQualification.php';
```

**Files to create** (all under `Modules/Sets/PL/Live/`, new directory):
- `LiveQualification.php` - page shell. `CheckTourSession(true)`, `checkFullACL(AclQualification, '', AclReadOnly)`. Renders session/distance `<select>` controls and an empty table container. Includes the polling JS. No `<form>` action beyond the selection controls themselves - selection changes trigger a re-fetch, not a submit/reload.

  Sessions come from `GetSessions('Q')` (same as `SelectSession.php`). The distance list is a plain `1..ToNumDist` loop (`Tournament.ToNumDist`), **not** scoped per-session through `DistanceInformation` - this matches core's own `Qualification/index.php` distance combo exactly, which builds the same way and does not filter by session either (a distance index is tournament-wide; which `QuD{n}` columns exist for a given entry is independent of which session shot them). Discovered while implementing task 3.2, which originally assumed a `DistanceInformation`-scoped list; corrected to follow the established core convention instead of inventing a new one. `DistanceInformation` is still queried, but only in `LiveQualificationData.php`, to look up `DiArrows` (arrows-per-end) for the peer-lag threshold - that lookup is unaffected by this correction.
- `LiveQualificationData.php` - AJAX/JSON endpoint. `CheckTourSession(false)` with an explicit check of the return value (see gotchas.md - `CheckTourSession(false)` does not stop execution on its own), then `checkFullACL(AclQualification, '', AclReadOnly, false)`, mirroring `TargetUpdate_XML.php`'s pattern for an AJAX context. Runs one query joining `Qualifications` + `Entries` + `IrmTypes` (`LEFT JOIN ... ON IrmId = QuIrmType`) filtered to the requested `EnTournament`, `QuSession`, `EnStatus <= 1`; looks up `DiArrows` for the requested session+distance from `DistanceInformation`; computes arrows-shot per row via `pl_live_qual_arrows_shot()`. Per row, classifies it before computing the flag:
  - `QuIrmType != 0` → `status` = the `IrmType` text (DNF/DNS/DSQ/DQB), `isBehind` forced `false`, excluded from the max-arrows pool.
  - `QuIrmType = 0` and `arrowsShot = 0` and `score > 0` → `dataGap = true`, `isBehind` forced `false`, excluded from the max-arrows pool.
  - otherwise → normal: counted in the max-arrows pool, `isBehind` computed via `pl_live_qual_is_behind()`.

  Groups rows by `QuTarget`+`QuLetter` order, counts targets with any `isBehind` row (`flaggedTargets`) against the total target count (`totalTargets`), and emits all of it as JSON.
- `Fun_LiveQualification.php` - pure helper functions, no DB access:
  - `pl_live_qual_arrows_shot(string $arrowString): int` - trims trailing padding, returns shot count.
  - `pl_live_qual_is_behind(int $arrowsShot, int $maxArrows, int $arrowsPerEnd): bool` - the peer-lag rule from the spec.
- `Fun_LiveQualificationTest.php` - PHPUnit coverage for the two pure functions (edge cases: empty string, fully shot, exactly-one-end gap boundary).
- `LiveQualification.js` - client polling loop: on selection change or timer tick, fetch `LiveQualificationData.php` with the current session/distance, replace the table body and the summary line (`flaggedTargets / totalTargets`). Modeled on `Fun_AJAX_CheckTargetUpdate.js`'s `setTimeout`-chained loop but consuming JSON instead of the XML core uses, since this endpoint's payload (nested target -> rows) is more naturally JSON and nothing here needs to match core's XML contract. Row highlighting reuses core's own existing CSS classes from `Common/Styles/Blue_screen.css` (`table.Tabella td.TargetOk/.TargetNoComplete/.TargetKo`, the same three states `CheckTargetUpdate`'s own JS already applies) rather than introducing new styles: green for on-pace, amber (`TargetNoComplete`) for a `dataGap` row, red (`TargetKo`) for lacking-results, and the plain `Center` class (no color) for a DNS/DNF/DSQ/DQB row, whose Status column cell carries the explanation instead.

**Response format:** JSON, not XML. Core's `TargetUpdate_XML.php` uses XML because it's part of an older core AJAX convention; this is a new PL-only endpoint with a richer, nested shape (target -> array of athlete rows), and this module already uses JSON for its other AJAX proxy (`Lookup/SportzonaProxy.php`). No reason to match core's XML convention when nothing consumes it but our own new JS.

**Poll interval:** reuse core's `5000`ms constant as the starting value - it's already the tournament-proven cadence for "is a new result in yet" on the same underlying table.

**Flag computation lives in a pure function**, not inline in the query, so it has a unit test independent of `FakeDb` - consistent with how `Lookup/Fun_ClubName.php` is structured in this codebase.

## Risks / Trade-offs

- **[Risk] Peer-lag threshold assumes a single dominant round pace.** If two very different classes/distances share one session (unusual but not impossible in this ruleset), the "max" reference could come from a class shooting a different `DiArrows`. → Mitigation: the max and the `DiArrows` divisor are both computed within the same requested `(QuSession, DiDistance)` scope already, and `DiArrows` is looked up per that exact pair, not assumed constant - this only becomes a problem if `DistanceInformation` itself has inconsistent `DiArrows` across targets in one session+distance, which would be a pre-existing tournament-setup data issue, not something this view can or should paper over.
- **[Risk] Polling load.** Every open live-view tab re-queries `Qualifications` every 5s. → Mitigation: scoped to one session+distance per request (indexed by `QuSession`, filtered further in PHP/SQL by distance columns already present), same order of query cost as core's own `CheckTargetUpdate` polling; acceptable for the realistic number of simultaneously open admin tabs at a single tournament.
- **[Risk] Peer-lag pool exclusions rely on data patterns, not just explicit flags.** The `dataGap` classification (`QuIrmType = 0`, `arrowsShot = 0`, `score > 0`) is inferred from column values rather than a dedicated flag ianseo sets - if some future scoring path writes a nonzero score with an empty arrow string for a reason unrelated to "scored via a different mechanism", this view would mislabel it. → Mitigation: verified against the live tournament's actual data (two independent real cases, both consistent with the inferred meaning) rather than assumed from schema alone; the ISK-NG sync path (`Api/ISK-NG/Lib.php`) always writes `ArrowString` and `Score` together, so a live phone sync can never produce this combination itself.
- **[Trade-off] No bye/empty-slot placeholder rows.** A target letter (A/B/C/D) with no entry assigned simply doesn't appear, rather than rendering an explicit "empty" row. Keeps the query and the spec simple; can be added later if staff find the gap confusing in practice.

## Migration Plan

Purely additive: new directory, new menu entry, no schema change, no touched core file, no touched existing PL file besides the `menu.php` addition. Nothing to migrate or roll back beyond removing the menu line and the `Live/` directory if reverted.
