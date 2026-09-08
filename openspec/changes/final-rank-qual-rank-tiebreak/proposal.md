## Why

The third tiebreaker for archers eliminated in the same phase (§2.6.6.2) is implemented literally as the qualification **score** — `Qualifications.QuScore` for individuals, `Teams.TeScore` for teams. Two archers with the same match average, the same shoot-off average and the same qualification score therefore share a place. ianseo's own PL rule set uses the qualification **rank** (`IndRank` / `TeRank`) for the same criterion, which separates them: rank already has core's golds and X/9 tiebreaks folded into it. Shared places at 5-8 or 9-16 are awkward for diplomas, points ranking and cup scoring, all of which then have to decide what to do with a tie.

## What Changes

- `Rank/Obj_Rank_FinalInd_calc.php`: third sort criterion becomes `IndRank` ascending (lower rank wins) instead of `QuScore` descending. The shared-place rule keeps applying, but now only when the ranks are also equal — which for a completed qualification round means never.
- `Rank/Obj_Rank_FinalTeam_calc.php`: same substitution, `TeRank` for `TeScore`.
- Unranked entries (no qualification round shot, `IndRank` 0 or DNS/DSQ codes) sort last rather than first — a rank of 0 must not beat rank 1.
- Spec section 4 (Tiebreaking Detail) updated to say rank, with the reasoning recorded.

## Capabilities

### New Capabilities

_(none)_

### Modified Capabilities

- `post-elim-ranking`: criterion 3 of the same-phase tiebreak changes from qualification score to qualification rank, for both individual and team events.

## Open gaps

1. **The regulation says score, not rank.** §2.6.6.2 reads "Wynik w kwalifikacjach". Using rank adds golds and X/9 as a de-facto fourth criterion ahead of "share position". Needs a PZŁucz ruling that this is acceptable, or the change should be dropped.
2. Behaviour for athletes with no qualification result (walkover into finals, DNS) is asserted above but not confirmed against a real competition.
3. Whether existing tournaments should be recalculated after the change, or left with the placements they were awarded.

## Non-goals

- Changing criteria 1 and 2 (match average, shoot-off average).
- Changing the no-bronze-match shared 3rd place rule.
- Changing how core ianseo computes `IndRank` / `TeRank` themselves.

## Impact

- **Modified files:** `Rank/Obj_Rank_FinalInd_calc.php`, `Rank/Obj_Rank_FinalTeam_calc.php`, their tests
- **DB:** no schema change; `IndRank` and `TeRank` are already selected by neighbouring queries
- **Spec produced by:** Advisor agent → delta at `openspec/changes/final-rank-qual-rank-tiebreak/specs/post-elim-ranking/spec.md`
- **Design produced by:** Developer agent → `openspec/changes/final-rank-qual-rank-tiebreak/design.md`
