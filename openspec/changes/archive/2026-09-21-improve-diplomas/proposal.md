## Why

The diploma module (§1.7 Nagrody: diplomas for places 1-8) has four bugs found in real use, plus
two refinements: names print surname-first, the date/place line can't be saved blank despite the
form accepting it, the custom diploma prints the literal word "Array" instead of the category
text, that diploma silently drops championship titles even when enabled, the "w kategorii {name}"
line needs restructuring to read correctly across individual/team/mixed events, and the default
place range (1-3) undersells what §1.7 actually asks for.

## What Changes

- Swap `EnFirstName`/`EnName` concatenation order everywhere a diploma builds a full name —
  ianseo's `EnFirstName` holds the surname and `EnName` the given name (opposite of what the
  names suggest), so today's output is surname-first.
- Let "Data"/"Miejsce" be saved blank: `printDiploma()` omits the date/location line when both
  are empty and drops the separator when only one is set. `DiplomaConfig.php` stops re-injecting
  the session's tournament date/location on every reload — it pre-fills only on a tournament's
  first-ever visit, never once a config row (even blank) exists.
- Fix `PrnCustomDipl.php` reading the whole `$eventTexts[$eventCode]` array, not its
  `['customText']` key, as the category text — the literal cause of "Array" on the PDF.
- Fix `PrnCustomDipl.php` never passing a title phrase to `printDiploma()`, so championship
  titles never appear on the custom diploma even when `PlDcTitlesEnabled` is on.
- Replace "w kategorii {classText}" with one template for individual, team, and mixed diplomas:
  `w konkurencji {typeWord}{name}, w kategorii {bowPhrase}` (full default tables in design.md).
  The existing per-event "Tekst na dyplomie" override still fully replaces this line when set.
- Change the default place range for new tournaments from 1-3 to 1-8, matching §1.7.2/§1.7.4;
  admins can still configure any range.

## Capabilities

### Modified Capabilities

- `diplomas`: name field order, conditional date/location line, custom-diploma category-text and
  title bugs, the category/division phrase composition rule, and the default place range.

## Non-goals

- Place range stays fully admin-configurable; only the out-of-the-box default changes.
- No change to medals/cups or title eligibility thresholds (§1.6.5/§1.6.6).
- No new UI beyond editable defaults, following the existing `titlePrefix`/`titleText` pattern.

## Impact

- `Diplomas/Fun_Diploma.php` — name order in 6 functions.
- `Diplomas/PLDiplomaPdf.php`, `PrnCustomDipl.php`, `PrnIndividualDipl.php`, `PrnTeamDipl.php` —
  date/location, category-line, and title-building fixes.
- `Diplomas/DiplomaSetup.php`, `DiplomaConfig.php` — default-lookup function(s), new
  config/event-text columns, session pre-fill gating, default place range.
- Advisor: no new spec content (wording is operator preference). Developer owns design.md and
  all code.
