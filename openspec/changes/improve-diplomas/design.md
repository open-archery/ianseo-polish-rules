## Context

See proposal.md - Why/What Changes for motivation. This design covers five independent fixes
inside the already-implemented `Diplomas/` module (entry points reached via the existing
"Dyplomy" menu.php link — no new menu/module registration needed). Two are pure bug fixes with an
obvious one-line correction (name order, custom-diploma "Array"/missing-titles); the other three
touch shared plumbing: the diploma config auto-install pattern (`pl_diploma_ensure_tables()`'s
`SHOW COLUMNS ... LIKE` / `ALTER TABLE` pattern), the `PLDiplomaConfig`/`PLDiplomaEventText`
tables, and `PLDiplomaPdf::printDiploma()`'s category-line rendering, which is shared by all three
print entry points (`PrnIndividualDipl.php`, `PrnTeamDipl.php`, `PrnCustomDipl.php`).

## Goals / Non-Goals

**Goals:**
- One shared function resolves and composes the category line, called identically by all three
  print entry points — no duplicated override/fallback/compose logic.
- The composed category line renders correctly even for a tournament whose admin has never
  opened the diploma config page (falls back to computed defaults, same as `EvEventName` did
  before this change).
- Schema changes ride the existing auto-migration pattern; no manual DB steps.

**Non-Goals:**
- No bow-type defaults for divisions T (traditional) or L (longbow) — `lib.php`'s tournament
  setup never creates R/C/B-only... i.e. never creates T/L event codes, so there is nothing to
  default.
- No change to `EvEventName` generation in `lib.php`, to Sportzona/BibImport name handling, or to
  any other module.
- No new menu entry or module registration.

## Decisions

### 1. Centralize category-line resolution
New function `pl_diploma_resolve_category_line($rawEventCode, $evType, $eventTextRow)` in
`Fun_Diploma.php`, called by all three `Prn*.php` files instead of each reimplementing the
override/fallback/compose sequence. `$evType` is `'I'`/`'T'`/`'M'` (already computed or trivially
derivable at each call site); `$eventTextRow` is `$eventTexts[$compositeKey] ?? array()`.

### 2. Move the "w kategorii " prefix out of `printDiploma()`
Today `PLDiplomaPdf::printDiploma()` hardcodes `'w kategorii ' . $classText`. The override-text
branch needs that exact prefix; the new composed branch needs `'w konkurencji ...'` instead — two
different fixed prefixes depending on which branch fired. The caller already knows which branch
fired, so `pl_diploma_resolve_category_line()` returns the **complete** line (including whichever
prefix applies), the `printDiploma()` parameter is renamed `$classText` → `$categoryLine`, and the
method prints it verbatim. Alternative considered: keep the prefix inside `printDiploma()` and
pass only the variable tail — rejected, it would need a prefix-selector parameter for no benefit.

### 3. Default lookup mirrors `pl_diploma_get_title_defaults()`
New function `pl_diploma_get_category_defaults($rawEventCode)` in `DiplomaSetup.php`, structured
like the existing title-defaults function: split on trailing `X` (mixed) the same way, so mixed
event codes naturally resolve through a separate, genderless table keyed by age code. Rejected
alternative: one lookup by division letter for bow phrase and a separate one by class code for
name, with mixed/U10 handled as branches inside the composition function — rejected because it
scatters special-casing that the existing title-defaults function already proved works better as
a single per-raw-code lookup.

`{name}` — gendered table (used for individual **and** team; both carry an M/W-suffixed raw code):

| Class | name | Class | name |
|---|---|---|---|
| M | mężczyzn | W | kobiet |
| U24M | Młodzieżowców | U24W | Młodzieżówek |
| U21M | Juniorów | U21W | Juniorek |
| U18M | Juniorów młodszych | U18W | Juniorek młodszych |
| U15M | Młodzików | U15W | Młodziczek |
| U12M | chłopców | U12W | dziewcząt |
| U10M | chłopców | U10W | dziewcząt |
| {NN}M (Masters, NN=40/50/60/70/80) | mężczyzn U{NN} | {NN}W | kobiet U{NN} |

`{name}` — genderless table, mixed events only (raw code ends in `X`; no U12/U10 entries — no
mixed U12/U10 event codes exist in `lib.php`, and none are expected):

| Age code | name |
|---|---|
| *(Senior, empty age code)* | *(empty — omitted from the composed line)* |
| U24 | Młodzieżowców |
| U21 | Juniorów |
| U18 | Juniorów młodszych |
| U15 | Młodzików |

`{bowPhrase}` — by division letter (first character of the raw code), with a class-code override
applied first regardless of division:

| Match | bowPhrase |
|---|---|
| Class code starts with `U10` (any division) | łuków popularnych |
| Division `R` | łuków klasycznych |
| Division `C` | łuków bloczkowych |
| Division `B` | łuków barebow |

### 4. Composition rule
```
typeWord = 'indywidualnej ' | 'zespołowej ' | 'mikstów'   (evType I / T / M)
name     = '' when evType == 'M' and the resolved name is empty (Senior mixed); otherwise as resolved
line     = customText override present?
             yes → 'w kategorii ' . customText
             no  → 'w konkurencji ' . typeWord . name . (name != '' ? ', ' : '') ...
```
Concretely: `w konkurencji {typeWord}{name}, w kategorii {bowPhrase}`, and when `name` is empty
(mixed Senior only) the line collapses to `w konkurencji mikstów, w kategorii {bowPhrase}` — no
double space, no dangling comma before an empty name.

### 5. Print-time fallback to computed defaults (diverges from title-field behavior)
`titlePrefix`/`titleText` intentionally do **not** fall back to computed defaults at print time —
only the config UI pre-fills the input box with a suggestion; an unconfigured event prints no
title, which is correct because titles are opt-in (`PlDcTitlesEnabled`). The category line is
different: it is mandatory and visible on every diploma, and before this change it always had
*something* (`EvEventName`). So `pl_diploma_resolve_category_line()` falls back to
`pl_diploma_get_category_defaults()` whenever the saved `categoryName`/`bowPhrase` is empty,
rather than printing an empty name/phrase for a tournament whose admin hasn't opened the config
page yet. This is a deliberate divergence from the title-field pattern — call it out in a comment
at the resolution function so a future maintainer doesn't "fix" it into matching title behavior.

### 6. New `PLDiplomaEventText` columns
`PlDeCategoryName VARCHAR(100) NOT NULL DEFAULT ''`, `PlDeBowPhrase VARCHAR(100) NOT NULL DEFAULT
''` — added via the existing `SHOW COLUMNS ... LIKE` / `ALTER TABLE` upgrade branch in
`pl_diploma_ensure_tables()`, same pattern already used for `PlDeTitlePrefix`/`PlDeTitleText`.
`pl_diploma_save_event_text()`'s signature and its "all fields empty → delete row" check extend
from 3 to 5 fields. `DiplomaConfig.php`'s per-event table gains two more inputs ("Nazwa
kategorii", "Rodzaj łuku"), pre-filled with `pl_diploma_get_category_defaults()`'s output exactly
like `displayPrefix`/`displayTitleText` are today.

### 7. Default place range changes from 1-3 to 1-8
`pl_diploma_get_config()`'s `$defaults['PlaceTo']` changes from `3` to `8` (matching §1.7.2/
§1.7.4), and `PLDiplomaConfig`'s `PlDcPlaceTo` column default changes from `3` to `8` in
`pl_diploma_ensure_tables()`'s `CREATE TABLE` for new installs (cosmetic — the column default is
never actually relied on at runtime, since `pl_diploma_save_config()` always inserts an explicit
value; only the PHP-level default in `pl_diploma_get_config()` is user-visible). No migration for
existing `PLDiplomaConfig` rows — a tournament that already has a saved range keeps it, exactly
like Decision 8's `ConfigExists` gating already ensures for Dates/Location.

### 8. `pl_diploma_get_config()` gains a `ConfigExists` flag
Additive key in the returned array (existing callers reading `CompetitionName`/`Dates`/etc. are
unaffected). `DiplomaConfig.php` gates its session pre-fill block on `!$config['ConfigExists']`
instead of `empty($config['Dates'])`/`empty($config['Location'])`, so a deliberately blank saved
value is never overwritten by the tournament's own session date/location on the next page load.

### 9. `PrnCustomDipl.php` derives event type from the composite code it already has
The athlete picker already sends `eventCode` in the same `I:`/`T:`/`M:` composite form used
everywhere else in the module (`Diplomas.php`'s `customEventCode` select uses the same `$code`
keys as the main event list). `PrnCustomDipl.php` reads the single-character prefix the same way
`pl_diploma_raw_event_code()` already strips it, defaulting to `'I'` when no `eventCode` is
selected (manual athlete entry) — no new request parameter needed.

## Risks / Trade-offs

- **[Risk]** The composed line is noticeably longer than the old `EvEventName`/custom text
  (e.g. "w konkurencji zespołowej Juniorów młodszych, w kategorii łuków barebow" vs. the old
  single-line "Łuk barebow - Junior młodszy"). `printDiploma()` currently renders this row with a
  single `Cell()` at 18pt, which does not wrap — the longest real combinations would visibly
  overflow or get clipped. → **Mitigation**: drop this line to 14pt and switch it from `Cell()` to
  `MultiCell()` so it wraps onto a second line instead of clipping; verify against the longest
  combination during implementation.
- **[Risk]** Missing one of the six `CONCAT(EnFirstName, ' ', EnName)` sites when fixing the name
  order leaves one diploma batch inconsistent with the others. → **Mitigation**: tasks.md
  enumerates all six call sites explicitly (`Fun_Diploma.php`'s four result-fetching functions
  plus `pl_diploma_get_all_athletes()` and `pl_diploma_get_athlete()`); no shared helper exists to
  centralize this since each site builds raw SQL, so exhaustiveness is the only guard.
- **[Risk]** Widening the per-event settings table in `DiplomaConfig.php` from 5 to 7 columns
  makes an already-dense table busier. → **Mitigation**: none needed beyond the existing
  horizontal layout; consistent with how the title columns were added previously.

## Migration Plan

No manual steps. The two new `PLDiplomaEventText` columns are created lazily on first page load
after deployment, via the same `SHOW COLUMNS ... LIKE` check already used for
`PlDeTitlePrefix`/`PlDeTitleText` — existing rows get `''` for both, which resolves to the
computed defaults at print time per Decision 5. Rollback is a plain code revert; leftover unused
columns are harmless and consistent with how this module already accumulates auto-migrated schema
across releases.
