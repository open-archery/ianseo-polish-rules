## 0. Close the open gaps

- [ ] 0.1 Read `.github/agents/research/pzlucz-rules.md` §1.4 (category matrix), `openspec/specs/tournament-setup/spec.md`, and this change's `design.md`.
- [ ] 0.2 Get the preset list from the competition owner (gap #1): name, divisions, classes, and which TourTypes each applies to.
- [ ] 0.3 Confirm the two-axis shape (divisions + classes, each optional) covers every preset on that list (gap #2).
- [ ] 0.4 Decide whether presets trim target faces (gap #3).
- [ ] 0.5 Check whether `add-setup-types-16-37` has already built the filter (gap #5); if so, consume it instead of building a second one.

## 1. Preset mechanism in lib.php

- [ ] 1.1 Add the preset table (name → divisions, classes) and a resolver that maps `$subRuleName` to a preset, defaulting to the unfiltered one.
- [ ] 1.2 Apply the filter in `CreateStandardDivisions()`.
- [ ] 1.3 Apply the filter in `CreateStandardClasses()`, keeping the existing division-eligibility matrix on top of it.
- [ ] 1.4 Apply the filter in `InsertStandardEvents()` so only events for surviving division/class pairs are created.
- [ ] 1.5 Unit test: the unfiltered preset produces byte-identical divisions, classes and events to today's `Poland-Full`.
- [ ] 1.6 Unit test: a class-only preset creates just those classes, in eligible divisions only.
- [ ] 1.7 Unit test: a division-only preset creates every eligible class of those divisions and nothing from other divisions.
- [ ] 1.8 Unit test: events follow the surviving categories, individual and team.
- [ ] 1.9 Unit test: an impossible combination (a class not eligible in any named division) creates nothing rather than erroring.

## 2. Wire into the setup scripts

- [ ] 2.1 `Setup_1_PL.php`, `Setup_3_PL.php`, `Setup_6_PL.php`: resolve `$subRuleName` to a preset and pass it into the `lib.php` calls.
- [ ] 2.2 `sets.php`: register the presets from task 0.2 against the TourTypes they apply to.

## 3. Verify

- [ ] 3.1 Create a fresh tournament per preset on the dev install; confirm the category list matches and nothing needs deleting by hand. Never re-run setup on an existing competition.
- [ ] 3.2 Create a `Poland-Full` tournament and diff its categories against one created before this change.
- [ ] 3.3 Run `tools/test.cmd` (or `tools/test.sh`); full suite passes.
- [ ] 3.4 Self-review against the Reviewer agent checklist, then commit.
