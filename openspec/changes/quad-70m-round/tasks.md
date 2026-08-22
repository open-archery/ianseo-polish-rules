## 1. Sub-rule registration

- [ ] 1.1 In `sets.php`, change the uniform `foreach` that assigns `array('Poland-Full')` to every allowed type so it still applies to all of them, then append `'Poland-4x70m'` to `$SetType['PL']['rules']['3']` only (types 1 and 6 keep just `Poland-Full`).

## 2. `Setup_3_PL.php` — session doubling

- [ ] 2.1 Add `$isDouble = ($SubRule === 'Poland-4x70m');` near the top of the script (after `$TourType = 3;`).
- [ ] 2.2 Add the local `pl_double_legs($legs, $isDouble)` helper (see design.md Decision 2) that repeats each leg into two consecutively-labelled legs when `$isDouble` is true, and returns `$legs` unchanged otherwise.
- [ ] 2.3 Wrap the distances argument of every `CreateDistanceNew()` call (RM, RW, RU24M, RU24W, RU21M, RU21W, RU18M, RU18W, R50M, R50W, RU15M, RU15W, `C%`, `B%` — 13 calls total) in `pl_double_legs(..., $isDouble)`.
- [ ] 2.4 Change `$tourDetNumDist` from the literal `'2'` to `$isDouble ? '4' : '2'`.
- [ ] 2.5 Change `$DistanceInfoArray` construction to double via `array_merge($DistanceInfoArray, $DistanceInfoArray)` when `$isDouble` is true (see design.md Decision 3). Leave `$tourDetMaxDistScore` at `'360'` unchanged.

## 3. Verification

- [ ] 3.1 Run `grep -n "CreateDistanceNew(" Setup_3_PL.php` and confirm every match's distances argument is wrapped in `pl_double_legs(`.
- [ ] 3.2 Manually create a TourType 3 tournament with sub-rule `Poland-Full` in a running ianseo instance — confirm behaviour is unchanged from before this change (72 arrows, 2 sessions, `tourDetNumDist=2`).
- [ ] 3.3 Manually create a TourType 3 tournament with sub-rule `Poland-4x70m` — confirm:
  - R Senior/U24/U21 (M, W): 4 sessions of 70m
  - R U18/Master (50+): 4 sessions of 60m
  - R Młodzik (U15): sessions in order 40m, 40m, 20m, 20m
  - Compound and Barebow (all categories): 4 sessions of 50m
  - `tourDetNumDist = 4`, `tourDetMaxDistScore = 360`
- [ ] 3.4 Confirm elimination/finals configuration (first-phase cut counts, set/cumulative match format) is identical between the two sub-rules — no regression in `Poland-Full`'s existing elimination behaviour.
- [ ] 3.5 Confirm `Poland-4x70m` does not appear as a selectable sub-rule when creating a TourType 1 or TourType 6 tournament.
- [ ] 3.6 Run `tools/test.cmd` (or `tools/test.sh`) — full PHPUnit suite still passes (no existing test targets `Setup_3_PL.php` directly, but confirms no regression elsewhere).

## 4. Documentation

- [ ] 4.1 If any non-obvious footgun surfaces during implementation (e.g. an ianseo quirk with sub-rule resolution or `CreateDistanceInformation()` behaviour), add an entry to `gotchas.md` in the same commit.
