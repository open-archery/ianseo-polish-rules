## 1. Pure calculation helpers (TDD)

- [x] 1.1 Write `Live/Fun_LiveQualificationTest.php` covering `pl_live_qual_arrows_shot()` (empty string → 0, fully-padded → 0, fully-shot → full length, mixed shot+padding → shot count only) and `pl_live_qual_is_behind()` (gap exactly one end → not behind, gap one arrow over one end → behind, zero gap → not behind, negative/zero `arrowsPerEnd` guarded); verify the new test file fails (functions don't exist yet).
- [x] 1.2 Implement `Live/Fun_LiveQualification.php` with `pl_live_qual_arrows_shot(string $arrowString): int` and `pl_live_qual_is_behind(int $arrowsShot, int $maxArrows, int $arrowsPerEnd): bool`; verify `tools/test.cmd --filter LiveQualification` (or `tools/test.sh` equivalent) passes.

## 2. Data endpoint

- [x] 2.1 Implement `Live/LiveQualificationData.php`: `CheckTourSession(false)` with explicit return-value check, `checkFullACL(AclQualification, '', AclReadOnly, false)`, validate `$_REQUEST['Session']` and `$_REQUEST['Distance']` inputs before use.
- [x] 2.2 Query `DistanceInformation` for `DiArrows` scoped to the requested `(EnTournament, Session, Distance)`; verified against the live tournament (XVMJK, `ToId=127`, both sessions/distances) via direct query — returned `DiArrows=6` correctly for each of the 4 session×distance combinations.
- [x] 2.3 Query `Qualifications` INNER JOIN `Entries` (mirroring `TargetUpdate_XML.php`'s `EnStatus <= 1` filter) for the requested session, pulling `QuTarget`, `QuLetter`, `EnName`/`EnFirstName`, and the `QuD{n}Score`/`QuD{n}ArrowString` columns for the requested distance; verified against `ToId=127` session 2 — 89 active entries returned, matching the `EnStatus <= 1` count from a standalone count query.
- [x] 2.4 Compute per-row arrows-shot via `pl_live_qual_arrows_shot()`, the session+distance max, and per-row `pl_live_qual_is_behind()` flags; group rows by `QuTarget` then order by `QuLetter` (A, B, C, D) within each target.
- [x] 2.5 Emit JSON (target → ordered list of `{name, score, arrowsShot, isBehind, status, dataGap}` rows) and verify by requesting the endpoint directly; confirmed both via a PHP harness replicating the exact query against `ToId=127` and via a live `curl` against the running Apache/XAMPP instance (`{"error":1}` with no session, as expected — see task 6.2 notes).

## 3. Page shell

- [x] 3.1 Implement `Live/LiveQualification.php`: `CheckTourSession(true)`, `checkFullACL(AclQualification, '', AclReadOnly)`, Polish-language UI labels per project convention.
- [x] 3.2 Render the session `<select>` via `GetSessions('Q')` (same helper `SelectSession.php` uses) and a distance `<select>` listing `1..Tournament.ToNumDist` (matches core's own `Qualification/index.php` distance combo — distance is tournament-wide, not session-scoped; see design.md); verified against `ToId=127` (2 `SesType='Q'` sessions, `ToNumDist=2`) — matches what `GetSessions('Q')` and the `1..ToNumDist` loop would render.
- [x] 3.3 Render an empty table container (no rows) until a session+distance is chosen, matching the "no selection" scenario in `specs/live-qualification-view/spec.md`.
- [x] 3.4 Confirm no HTML in this page contains an editable input, form submission to a write endpoint, or edit link anywhere in the rendered output (read the rendered source; nothing here should resemble `Qualification/index.php`'s score-entry grid).

## 4. Client-side polling

- [x] 4.1 Implement `Live/LiveQualification.js`: on session/distance selection change, fetch `LiveQualificationData.php` and render the grouped-by-target table (target header + one row per athlete, score, arrows shot, and a visual flag class when `isBehind` is true).
- [ ] 4.2 Add the repeating fetch loop (interval matching core's `ReloadTime = 5000` from `Fun_AJAX_CheckTargetUpdate.js`) that re-fetches and re-renders while a session+distance is selected; verify in a browser that the table updates on its own after the interval without a page reload. **Still open**: implemented and code-reviewed; the endpoint itself is confirmed reachable and correct through the live Apache/XAMPP instance (task 2.5), but exercising the actual browser polling loop needs a logged-in session, which requires clicking through the real login/tournament-select flow in a browser — not something to script via curl.
- [ ] 4.3 Verify end-to-end against a real or test tournament: enter/simulate a new arrow result for one entry (e.g. via the existing web scorecard) and confirm the live view reflects the updated score/arrow count within one refresh cycle, and that a target deliberately left behind (fewer arrows entered than its peers by more than one end) shows the lacking-results flag. **Still open** — same reason as 4.2.

## 5. Menu registration

- [x] 5.1 Add the `QUAL` menu entry in `menu.php` (`Podgląd na żywo|.../Live/LiveQualification.php`) inside the existing `$_SESSION["TourLocRule"]=='PL'` guard block; verify the menu item appears for a PL-ruleset tournament and does not appear for a non-PL tournament.

## 6. Final verification

- [x] 6.1 Run the full suite (`tools/test.cmd` / `tools/test.sh`) and confirm all tests pass, including the new `Fun_LiveQualificationTest.php` — 384/384 pass after every change in this file.
- [ ] 6.2 Manually walk through every scenario in `specs/live-qualification-view/spec.md` against a running tournament with the PL ruleset active, confirming each WHEN/THEN holds. **Partially done**: the DB-query-level scenarios (peer-lag flag, round-status exclusion, data-gap marker, arrow-count derivation) are all verified against the live XVMJK tournament (`ToId=127`) via direct query/computation and via a live `curl` smoke test of both endpoints through Apache/XAMPP (graceful `{"error":1}`/crack-page responses, no fatal errors). **Still needs a real logged-in browser pass** for the UI-only scenarios (selection-changes-without-reload, auto-refresh timing, no-edit-affordance-in-a-live-session).

## 7. Round-status and data-gap handling (found during live-data verification)

- [x] 7.1 While verifying task 2.x against the live tournament (`ToId=127`), found 4 entries in session 2 passing `EnStatus <= 1` with `Qualifications.QuIrmType != 0` (DNS) — the original design flagged them as "lacking results" instead of showing why they have no progress. Added a `LEFT JOIN IrmTypes` (same join core's `Qualification/index.php` uses) and excluded any `QuIrmType != 0` row from both the peer-lag pool and the lacking-results flag, surfacing its status (DNF/DNS/DSQ/DQB) in a new Status column instead; verified against the same 4 real entries — none are flagged after the fix.
- [x] 7.2 Also found a real entry with a nonzero score and a zero-length arrow string (no IRM status) — only possible when a distance was scored through a non-arrow-by-arrow path, never from a live phone sync (`Api/ISK-NG/Lib.php` always writes both together). Added a `dataGap` classification, excluded from the peer-lag pool and never flagged; rendered with the existing core `TargetNoComplete` (amber) CSS class, distinct from on-pace/lacking-results/status. Verified against the real entry — `flaggedTargets` dropped from 1 to 0 for that session+distance after the fix.
- [x] 7.3 Added a flagged-target summary (`flaggedTargets`/`totalTargets` in the JSON, rendered above the table) per user request, so staff see at a glance how many targets currently need attention without scanning the whole grid.
- [x] 7.4 Updated `specs/live-qualification-view/spec.md` (two new requirements: round-status exclusion, data-gap marker, flagged-target summary) and `design.md` (Context, Decisions, Risks) to match; re-ran the full suite (384/384 pass) and re-verified both fixes against the live tournament data after each edit.
