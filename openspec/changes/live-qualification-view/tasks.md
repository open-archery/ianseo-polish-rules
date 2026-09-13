## 1. Pure calculation helpers (TDD)

- [x] 1.1 Write `Live/Fun_LiveQualificationTest.php` covering `pl_live_qual_arrows_shot()` (empty string → 0, fully-padded → 0, fully-shot → full length, mixed shot+padding → shot count only) and `pl_live_qual_is_behind()` (gap exactly one end → not behind, gap one arrow over one end → behind, zero gap → not behind, negative/zero `arrowsPerEnd` guarded); verify the new test file fails (functions don't exist yet).
- [x] 1.2 Implement `Live/Fun_LiveQualification.php` with `pl_live_qual_arrows_shot(string $arrowString): int` and `pl_live_qual_is_behind(int $arrowsShot, int $maxArrows, int $arrowsPerEnd): bool`; verify `tools/test.cmd --filter LiveQualification` (or `tools/test.sh` equivalent) passes.

## 2. Data endpoint

- [x] 2.1 Implement `Live/LiveQualificationData.php`: `CheckTourSession(false)` with explicit return-value check, `checkFullACL(AclQualification, '', AclReadOnly, false)`, validate `$_REQUEST['Session']` and `$_REQUEST['Distance']` inputs before use.
- [ ] 2.2 Query `DistanceInformation` for `DiArrows` scoped to the requested `(EnTournament, Session, Distance)`; verify via a manual query/print that it returns the expected arrows-per-end for both an indoor (18m) and outdoor (70m) test tournament.
- [ ] 2.3 Query `Qualifications` INNER JOIN `Entries` (mirroring `TargetUpdate_XML.php`'s `EnStatus <= 1` filter) for the requested session, pulling `QuTarget`, `QuLetter`, `EnName`/`EnFirstName`, and the `QuD{n}Score`/`QuD{n}ArrowString` columns for the requested distance; verify the query returns the same active-entry set `Qualification/CheckTargetUpdate.php` would show for that session.
- [x] 2.4 Compute per-row arrows-shot via `pl_live_qual_arrows_shot()`, the session+distance max, and per-row `pl_live_qual_is_behind()` flags; group rows by `QuTarget` then order by `QuLetter` (A, B, C, D) within each target.
- [ ] 2.5 Emit JSON (target → ordered list of `{name, score, arrowsShot, isBehind}` rows) and verify by requesting the endpoint directly (browser or curl against a test tournament) with a known session/distance.

## 3. Page shell

- [x] 3.1 Implement `Live/LiveQualification.php`: `CheckTourSession(true)`, `checkFullACL(AclQualification, '', AclReadOnly)`, Polish-language UI labels per project convention.
- [ ] 3.2 Render the session `<select>` via `GetSessions('Q')` (same helper `SelectSession.php` uses) and a distance `<select>` listing `1..Tournament.ToNumDist` (matches core's own `Qualification/index.php` distance combo — distance is tournament-wide, not session-scoped; see design.md); verify both selectors list the correct sessions/distances for a manually created test tournament.
- [x] 3.3 Render an empty table container (no rows) until a session+distance is chosen, matching the "no selection" scenario in `specs/live-qualification-view/spec.md`.
- [x] 3.4 Confirm no HTML in this page contains an editable input, form submission to a write endpoint, or edit link anywhere in the rendered output (read the rendered source; nothing here should resemble `Qualification/index.php`'s score-entry grid).

## 4. Client-side polling

- [x] 4.1 Implement `Live/LiveQualification.js`: on session/distance selection change, fetch `LiveQualificationData.php` and render the grouped-by-target table (target header + one row per athlete, score, arrows shot, and a visual flag class when `isBehind` is true).
- [ ] 4.2 Add the repeating fetch loop (interval matching core's `ReloadTime = 5000` from `Fun_AJAX_CheckTargetUpdate.js`) that re-fetches and re-renders while a session+distance is selected; verify in a browser that the table updates on its own after the interval without a page reload.
- [ ] 4.3 Verify end-to-end against a real or test tournament: enter/simulate a new arrow result for one entry (e.g. via the existing web scorecard) and confirm the live view reflects the updated score/arrow count within one refresh cycle, and that a target deliberately left behind (fewer arrows entered than its peers by more than one end) shows the lacking-results flag.

## 5. Menu registration

- [x] 5.1 Add the `QUAL` menu entry in `menu.php` (`Podgląd na żywo|.../Live/LiveQualification.php`) inside the existing `$_SESSION["TourLocRule"]=='PL'` guard block; verify the menu item appears for a PL-ruleset tournament and does not appear for a non-PL tournament.

## 6. Final verification

- [x] 6.1 Run the full suite (`tools/test.cmd` / `tools/test.sh`) and confirm all tests pass, including the new `Fun_LiveQualificationTest.php`.
- [ ] 6.2 Manually walk through every scenario in `specs/live-qualification-view/spec.md` against a running tournament with the PL ruleset active, confirming each WHEN/THEN holds.
