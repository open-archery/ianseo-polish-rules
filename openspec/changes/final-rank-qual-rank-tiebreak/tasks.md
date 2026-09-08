## 0. Close the open gaps

- [ ] 0.1 Read `.github/agents/research/pzlucz-rules.md` §2.6.6.2, `openspec/specs/post-elim-ranking/spec.md`, and this change's `design.md`.
- [ ] 0.2 Get a PZŁucz ruling on gap #1 — rank as a proxy for "wynik w kwalifikacjach". If the answer is "score", close this change without implementing.
- [ ] 0.3 Check on the dev install what `IndRank` / `TeRank` actually hold for walkovers, DNS and DSQ (gap #2).
- [ ] 0.4 Decide whether finished tournaments get recalculated (gap #3).

## 1. Individual ranking

- [ ] 1.1 Select the qualification rank in the `calcFromPhase()` query (reuse the existing `Individuals` join if it already provides it).
- [ ] 1.2 Replace `qualScore` with `qualRank` in the collected match data, mapping 0/DNS/DSQ to a sorts-last sentinel.
- [ ] 1.3 Flip that criterion in the `usort` comparator to ascending.
- [ ] 1.4 Update the shared-place comparison to use the same field.
- [ ] 1.5 Update the file's header comment, which currently documents criterion 3 as `IndScore`.

## 2. Team ranking

- [ ] 2.1 Add the `Teams` join and select `TeRank`.
- [ ] 2.2 Apply the same three edits as 1.2-1.4.
- [ ] 2.3 Update the header comment, which documents criterion 3 as `TeScore`.

## 3. Tests

- [ ] 3.1 Equal match and shoot-off averages, different qualification rank → ordered by rank.
- [ ] 3.2 Equal qualification score but different rank (separated by golds/X) → no longer share a place.
- [ ] 3.3 Unranked competitor (rank 0) → placed after every ranked competitor.
- [ ] 3.4 Criterion 1 and 2 precedence unchanged.
- [ ] 3.5 Same four cases for teams.

## 4. Verify

- [ ] 4.1 Shoot a bracket on a fresh test tournament with a deliberate criteria-1-and-2 tie; confirm the placement order. Never re-run this on an existing competition.
- [ ] 4.2 Run `tools/test.cmd` (or `tools/test.sh`); full suite passes.
- [ ] 4.3 Update `openspec/specs/post-elim-ranking/spec.md` section 4 at archive time.
- [ ] 4.4 Self-review against the Reviewer agent checklist, then commit.
