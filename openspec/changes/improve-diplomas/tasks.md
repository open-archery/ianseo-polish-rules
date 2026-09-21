## 1. Athlete name order

- [ ] 1.1 In `Fun_Diploma.php`, swap `CONCAT(Entries.EnFirstName, ' ', Entries.EnName)` to
      `CONCAT(Entries.EnName, ' ', Entries.EnFirstName)` in `pl_diploma_get_ind_qual_results()`,
      `pl_diploma_get_ind_final_results()`, `pl_diploma_get_team_qual_results()`, and
      `pl_diploma_get_team_final_results()`; verify by asserting the swapped SQL fragment in each
      function's existing `FakeDb`-backed test in `DiplomaTest.php`.
- [ ] 1.2 Apply the same swap in `pl_diploma_get_all_athletes()` and `pl_diploma_get_athlete()`;
      verify `testGetAllAthletesWithoutSearchOmitsLikeFilter`/`testGetAthleteReturnsDetailsWhenFound`
      still pass against a `FakeDb` row shaped `{EnFirstName: 'Nowak', EnName: 'Jan'}` and assert
      the mapped `EnFullName` is `'Jan Nowak'`.
- [ ] 1.3 Add `gotchas.md` entry documenting that ianseo's `EnFirstName` column holds the surname
      and `EnName` holds the given name, in the same commit as 1.1/1.2.

## 2. Date/location line and config-exists gating

- [ ] 2.1 In `PLDiplomaPdf.php`, change `printDiploma()`'s bottom line to build from whichever of
      `$location`/`$dates` are non-empty, printing nothing when both are blank and no separator
      when only one is set; verify with a new `DiplomaTest.php` (or a small PDF-independent unit
      around the string-building logic, extracted into a `Fun_Diploma.php` helper if needed for
      testability without invoking TCPDF) covering all three cases.
- [ ] 2.2 In `DiplomaSetup.php`, add a `ConfigExists` boolean to `pl_diploma_get_config()`'s
      returned array, `true` only when a `PLDiplomaConfig` row was found; verify with a new
      `DiplomaSetupTest.php` case asserting `ConfigExists` is `false` for
      `testGetConfigReturnsDefaultsWhenNotConfigured` and `true` for
      `testGetConfigReturnsStoredValues`.
- [ ] 2.3 In `DiplomaConfig.php`, gate the session pre-fill block on `!$config['ConfigExists']`
      instead of `empty($config['Dates'])`/`empty($config['Location'])`; verify manually (see
      task 6.1) that saving blank Dates/Location keeps them blank across a reload.

## 3. Custom diploma bugs

- [ ] 3.1 In `PrnCustomDipl.php`, fix the category-text lookup to read
      `$eventTexts[$eventCode]['customText']` instead of the whole array; verify with a new test
      exercising the lookup logic (extract the "resolve category text for a composite event code"
      block into a testable `Fun_Diploma.php` function per task 4.2 rather than testing the print
      script directly) confirming a configured `customText` string is returned, not an array.
- [ ] 3.2 In `PrnCustomDipl.php`, compute `titleText` the same way `PrnIndividualDipl.php`/
      `PrnTeamDipl.php` already do (`pl_diploma_extract_year($config['Dates'])` +
      `pl_diploma_build_title()`) whenever `$config['TitlesEnabled']` is set, deriving `isTeam`/
      `isMixed` from the event-type prefix built in task 9 (design.md Decision 9); verify with a
      `DiplomaTest.php` case asserting the built title string is non-empty when titles are enabled
      and the event has title text configured, and empty when titles are disabled.

## 4. Category/division phrase composition

- [ ] 4.1 In `DiplomaSetup.php`, add `pl_diploma_get_category_defaults($rawEventCode)` returning
      `['name' => ..., 'bowPhrase' => ...]` per design.md's tables (gendered table for individual/
      team, genderless table keyed by age code for mixed `X`-suffixed codes, `U10`-prefix bow-phrase
      override); verify with a `DiplomaSetupTest.php` (or `DiplomaTest.php`, matching where
      `testGetTitleDefaults` already lives) data provider covering: Senior M/W, U21 M/W, U10 M/W,
      Masters (e.g. `50W`), mixed Senior (`RX`), and mixed U21 (`RU21X`).
- [ ] 4.2 In `Fun_Diploma.php`, add `pl_diploma_resolve_category_line($rawEventCode, $evType,
      $eventTextRow)` implementing design.md Decisions 2/4/5: `customText` override wins outright
      (`'w kategorii ' . customText`); otherwise resolve `name`/`bowPhrase` from
      `$eventTextRow['categoryName']`/`['bowPhrase']` falling back to
      `pl_diploma_get_category_defaults()` when empty, then compose
      `'w konkurencji ' . typeWord . name . (name !== '' ? ', ' : '') . 'w kategorii ' . bowPhrase`
      with `typeWord` from `$evType` (`I`→`'indywidualnej '`, `T`→`'zespołowej '`,
      `M`→`'mikstów'` + trailing space only when `name !== ''`); verify with a `DiplomaTest.php`
      data provider covering every scenario in `specs/diplomas/spec.md`'s "Category line" and
      "U10 class bow-type override" scenarios, plus the override-wins case.
- [ ] 4.3 In `DiplomaSetup.php`'s `pl_diploma_ensure_tables()`, add `PlDeCategoryName VARCHAR(100)
      NOT NULL DEFAULT ''` and `PlDeBowPhrase VARCHAR(100) NOT NULL DEFAULT ''` to
      `PLDiplomaEventText`'s `CREATE TABLE`, plus the matching `SHOW COLUMNS ... LIKE` / `ALTER
      TABLE` upgrade branch; verify with a `DiplomaSetupTest.php` case mirroring
      `testEnsureTablesAddsTitlesEnabledColumnWhenUpgrading` for both new columns.
- [ ] 4.4 Extend `pl_diploma_get_event_texts()`'s and `pl_diploma_save_event_text()`'s signatures
      to read/write `categoryName`/`bowPhrase` alongside `customText`/`titlePrefix`/`titleText`,
      including in the "all fields empty → delete row" check; verify with
      `DiplomaSetupTest.php` cases mirroring the existing `testSaveEventText*` tests, extended to
      the two new fields.
- [ ] 4.5 In `PLDiplomaPdf.php`, rename `printDiploma()`'s `$classText` parameter to
      `$categoryLine`, print it verbatim (no automatic `'w kategorii '` prefix), and switch that
      line from `Cell()` to `MultiCell()` at a reduced font size (match the 14pt used by
      neighboring lines) so long composed lines wrap instead of clipping; verify manually (task
      6.2) against the longest real combination ("w konkurencji zespołowej Juniorów młodszych, w
      kategorii łuków barebow").
- [ ] 4.6 In `PrnIndividualDipl.php`, `PrnTeamDipl.php`, and `PrnCustomDipl.php`, replace the
      existing per-event `classText`-override lookup with a single call to
      `pl_diploma_resolve_category_line()` (passing `'I'`/`'T'`/computed `$evType`/`'M'`
      respectively) and pass its result as `printDiploma()`'s new `$categoryLine` argument;
      verify by re-running the full `Diplomas/` PHPUnit suite (`tools/test.sh --filter Diploma`)
      and confirming no test references the old `$classText`/override-array shape.
- [ ] 4.7 In `DiplomaConfig.php`, add "Nazwa kategorii" and "Rodzaj łuku" inputs to the per-event
      table, pre-filled with `pl_diploma_get_category_defaults()`'s output the same way
      `displayPrefix`/`displayTitleText` are today, and wire their POST handling into
      `pl_diploma_save_event_text()`; verify manually (task 6.1) that saving populates
      `PlDeCategoryName`/`PlDeBowPhrase` and reopening the page shows the saved values.

## 5. Default place range

- [ ] 5.1 In `DiplomaSetup.php`, change `pl_diploma_get_config()`'s `$defaults['PlaceTo']` from
      `3` to `8`, and `PLDiplomaConfig`'s `CREATE TABLE` `PlDcPlaceTo` column default from `3` to
      `8`; verify by updating `testGetConfigReturnsDefaultsWhenNotConfigured` to assert
      `PlaceTo === 8`.

## 6. Manual verification

- [ ] 6.1 In a running ianseo instance with a `TourLocRule='PL'` tournament: open
      `DiplomaConfig.php` on a fresh tournament (confirm 1-8 range and session date/location
      pre-filled), save with Dates/Location cleared, reload, and confirm both stay blank; save
      per-event category name/bow-phrase overrides and confirm they persist.
- [ ] 6.2 Generate one individual, one team, and one mixed-team diploma batch (covering a Senior
      class, a non-Senior age class, and a U10 class if the tournament has one) plus one custom
      diploma for an event with a saved `customText` override; visually confirm: given-name-first
      athlete names, no "Array" text, correct titles on the custom diploma when enabled, the
      composed category line reads correctly and wraps rather than clips, and the blank-date/
      location diploma omits that line entirely.
