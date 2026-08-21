## Context

PZŁucz competitions convert competition places into points. Some publish those points as standalone classifications; others aggregate them per athlete and roll them up through clubs to voivodeships. The feature lives entirely within `Modules/Sets/PL/` and reads existing ianseo tables without modifying any core file.

ianseo tables consumed (read-only):

| Table | Columns used | Notes |
|---|---|---|
| `Individuals` | `IndId`, `IndEvent`, `IndTournament`, `IndRank`, `IndRankFinal` | `IndRank` = qualification place, `IndRankFinal` = elimination place |
| `Teams` | `TeCoId`, `TeSubTeam`, `TeEvent`, `TeTournament`, `TeRank`, `TeRankFinal` | no roster columns |
| `TeamComponent` | `TcCoId`, `TcTournament`, `TcEvent`, `TcFinEvent`, `TcId`, `TcOrder` | **qualification** roster, written by `Qualification/Fun_Qualification.local.inc.php` |
| `TeamFinComponent` | `TfcCoId`, `TfcSubTeam`, `TfcEvent`, `TfcTournament`, `TfcId` | **finals/elimination** roster |
| `Entries` | `EnId`, `EnCode`, `EnName`, `EnFirstName`, `EnDivision`, `EnClass`, `EnCountry`, `EnCountry2` | `EnCode` = licence number |
| `Countries` | `CoId`, `CoCode`, `CoName`, `CoTournament` | ianseo's club table; **there is no `Companies` table** |
| `Events` | `EvCode`, `EvTeamEvent`, `EvMixedTeam`, `EvMaxTeamPerson`, `EvCodeParent` | team size and mixed flag come from here |
| `Divisions`, `Classes` | `DivDescription`, `ClDescription`, `ClViewOrder` | section labels |

## Goals / Non-Goals

**Goals**

- Preset selection per tournament from read-only presets
- Points calculation per category, per classification, with cutoff and 3-of-4 rules
- Report composition: standalone classifications, combined athlete totals with a cap, club and voivodeship rollups
- Single PDF containing the preset's declared reports, plus club/voivodeship diplomas
- Operator UI mapping clubs to voivodeships, persisted across tournaments

**Non-Goals**

- Cross-tournament season aggregation (separate future feature; see D8)
- Editable presets via UI
- Any change to ianseo core

## Decisions

### D1 — Presets are PHP constants, not seeded DB tables

**Decision:** Define all presets as PHP arrays in `Presets.php`. Read them directly. No `PLPointsPreset` / `PLPointsEventConfig` / `PLPointsTable` tables, no seeder.

**Why:** The earlier design justified DB tables with "the bracket lookup is cleanest as a SQL range query", but the calculation loop is PHP anyway and each bracket table is under 20 rows — the lookup is a `foreach`. Seeding also introduced a latent bug with no stated fix: seeded rows are never re-seeded, so a point-value change in a future annex would never reach an installation that already ran the seeder. Constants remove three tables, the DDL check, the seeder, the migration question, and let the whole engine be unit-tested with no `FakeDb` stubbing.

**Shape:**

```php
const PL_POINTS_PRESETS = [
    'pp' => [
        'name'   => 'Puchar Polski — runda',
        'scope'  => ['divisions' => [], 'classes' => []],   // empty = all
        'classifications' => [
            'ind' => ['subject' => 'IND',   'source' => 'ELIM', 'cutoff' => false,
                      'brackets' => [[1,1,25],[2,2,21],[3,3,18],[4,4,15],[5,5,13],
                                     [6,6,12],[7,7,11],[8,8,10],[9,16,5],[17,32,1]]],
            'mix' => ['subject' => 'MIXED', 'source' => 'ELIM', 'cutoff' => false,
                      'brackets' => [/* … same, no 17-32 row … */]],
        ],
        'reports' => [
            ['kind' => 'SEPARATE', 'classification' => 'ind', 'label' => 'Klasyfikacja indywidualna'],
            ['kind' => 'SEPARATE', 'classification' => 'mix', 'label' => 'Klasyfikacja mikstów'],
        ],
    ],
    // …
];
```

---

### D2 — Classifications and reports are separate concepts

**Decision:** A **classification** says where points come from (subject kind, rank source, bracket table, cutoff flag). A **report** says what gets rendered. Four report kinds: `SEPARATE`, `COMBINED(cap N)`, `CLUB`, `VOIVODESHIP`.

**Why:** Puchar Polski needs two classifications that are never summed; LZS and OOM need classifications that are summed per athlete and then per club. The previous design hardcoded the second shape and tried to fake the first with `max_events = 1`, which yields "best of individual-or-mixed" in one table rather than two independent tables. Splitting the concepts makes both a data question, not a code branch.

**Mapping of the four competitions:**

```
PP Jun / Jun. Mł.   SEPARATE(ind)
PP Bloczki / BB     SEPARATE(ind), SEPARATE(mix)
LZS                 COMBINED(ind,tea, cap 0), SEPARATE(tea), CLUB, VOIVODESHIP
MP Młodz. / OOM     COMBINED(ind,tea,mix, cap 3|2), CLUB, VOIVODESHIP
```

Presets 3 (`Puchar Polski — runda`) covers juniorzy młodsi, juniorzy and seniorzy across all divisions: the point tables are identical and the category dimension (D3) already separates the sections. The mixed report is simply empty when the tournament has no mixed event, so one preset serves all Puchar Polski rounds.

---

### D3 — Everything is scoped to a category

**Decision:** Category = `EnDivision . EnClass` for individuals, `TeEvent` for teams. Bracket lookup, cutoff, sorting and output sections are all per category. Presets additionally declare a division/class **scope** so out-of-scope categories are dropped before any of that.

**Why:** The previous spec had no category dimension at all — "total number of starters" for the cutoff read as tournament-wide, and place 1 in `RU21M` and place 1 in `RU21W` were indistinguishable. LZS also needs the scope filter: it scores only recurve młodzieżowcy/juniorzy/juniorzy młodsi even when seniors shoot the same tournament.

Section ordering reuses `CupRanking`'s division order (`R, C, B`) plus `Classes.ClViewOrder`, and labels come from `DivDescription` / `ClDescription`.

---

### D4 — Team roster source follows the classification's rank source

**Decision:** `source = QUAL` → roster from `TeamComponent`. `source = ELIM` → roster from `TeamFinComponent`.

**Why:** `TeamFinComponent` only holds finals rosters. `Qualification/Fun_Qualification.local.inc.php:461` writes qualification teams into `Teams` + `TeamComponent`. LZS teams are explicitly "składające się z najwyżej sklasyfikowanych zawodników z jednego klubu po rundzie kwalifikacyjnej" — ianseo builds exactly that automatically. Reading `TeamFinComponent` for a `QUAL` classification would silently return no roster.

Team size comes from `Events.EvMaxTeamPerson`; mixed teams are identified by `Events.EvMixedTeam = 1`; the 3-of-4 rule finds the athlete's individual event via `Events.EvCodeParent` rather than stripping a suffix off `EvCode` by hand.

Club id resolves as `IF(EnCountry2 = 0, EnCountry, EnCountry2)`, matching ianseo's own team builder — clubs entered under a second affiliation would otherwise land in the wrong club total.

`one_team_per_club` (LZS) restricts scoring to `TeSubTeam = 0`.

---

### D5 — Team points are credited twice

**Decision:** Team and mixed points go to the club at **full value**, and to each counting member split evenly.

**Why:** The two consumers want different things. The club ranking wants the team's 9 points as 9. The athlete-level `COMBINED` table wants a per-athlete figure so the `Suma` column means something, and the user has confirmed the halved/thirded figure is the wanted display. Crediting the club directly rather than re-summing member shares also keeps the club total exact when a roster is missing or a member is dropped by the 3-of-4 rule.

---

### D6 — Voivodeship map keyed by `CoCode`, in a PL-owned table

**Decision:** `PLVoivodeshipMap (PlvmCoCode VARCHAR(10) PK, PlvmVoivodeship VARCHAR(64))`.

**Why not `CoId`:** `Countries` has PK `CoId` but unique key `(CoTournament, CoCode)` — every tournament gets its own rows, so the same club has a different `CoId` in every tournament. A `CoId`-keyed map would need re-entry every event. `CoCode` is stable.

**Why not ianseo's native `Countries.CoParent1`:** it exists, and `Partecipants/ChangeNationsNames.php` already provides an operator UI, but it is per-tournament data and therefore has the same re-entry problem. A PL-owned table accumulates the mapping once. Feeding that accumulated data into `CoParent1` automatically is a possible future convenience, explicitly out of scope here.

---

### D7 — Diplomas reuse the Diplomas module

**Decision:** `PrnPointsRankingDipl.php` calls `pl_diploma_ensure_tables()` / `pl_diploma_get_config()` and drives `PLDiplomaPdf::printDiploma()` directly, exactly as `Diplomas/PrnTeamDipl.php` does. Club and voivodeship names go into the recipient slot with an empty club line; the place range comes from the diploma configuration (`PlaceFrom`, `PlaceTo`).

**Why:** The renderer, the per-tournament header/footer text and the place-range configuration already exist and are already how PL diplomas look. No new configuration surface, ~60 lines of glue.

---

### D8 — Forward compatibility with season aggregation, without building it

**Decision:** Every athlete-level output row carries `Entries.EnCode`. Nothing else is done for cross-tournament work.

**Why:** Puchar Polski's klasyfikacja generalna sums round results across the season; that will be a separate feature layered on this one. Licence number is the only stable cross-tournament athlete key in ianseo. Carrying it costs one column and removes the need to reshape this module later.

---

### D9 — No caching, computed on demand

**Decision:** Calculate on page load / PDF request. No result table.

**Trade-off:** Recomputed every request. Acceptable — a few hundred athletes, plain SQL and PHP loops — and it avoids stale results when scores are corrected.

## File Structure

```
PointsRanking/
  PointsRanking.php          ← UI: preset selector, HTML preview, PDF + diploma buttons
  PrnPointsRanking.php       ← PDF: renders the preset's declared reports
  PrnPointsRankingDipl.php   ← PDF: club / voivodeship diplomas via PLDiplomaPdf
  Fun_PointsRanking.php      ← data layer: loaders, auto-install, tournament config, voiv. map
  PointsRankingCalc.php      ← pure computation: brackets, cutoff, splitting, reports
  Presets.php                ← PHP constant preset definitions
  VoivodeshipMap.php         ← UI: club → voivodeship mapping
  PointsRankingCalcTest.php  ← PHPUnit, no DB stubbing needed
  PresetsTest.php            ← bracket integrity over every shipped preset
```

`Fun_PointsRanking.php` also hosts the shared `pl_ranking_div_labels($tourId)` and division ordering, moved out of `CupRanking/Fun_CupRanking.php` (which currently duplicates them with `CombinedRanking/`).

**`menu.php` addition:**

```php
$ret['PRNT'][] = 'Klasyfikacja punktowa|' . $CFG->ROOT_DIR . 'Modules/Sets/PL/PointsRanking/PointsRanking.php';
$ret['PRNT'][] = 'Mapa województw|'       . $CFG->ROOT_DIR . 'Modules/Sets/PL/PointsRanking/VoivodeshipMap.php';
```

## DB Schema

```sql
CREATE TABLE PLPointsTournamentConfig (
  PltcTournament INT NOT NULL PRIMARY KEY,
  PltcPresetKey  VARCHAR(32) NOT NULL      -- key into PL_POINTS_PRESETS
);

CREATE TABLE PLVoivodeshipMap (
  PlvmCoCode      VARCHAR(10) NOT NULL PRIMARY KEY,
  PlvmVoivodeship VARCHAR(64) NOT NULL
);
```

Both auto-installed via the `SHOW TABLES LIKE` pattern used elsewhere in the module.

## Calculation Engine

```
pl_points_calculate($tourId, $preset):

  categories = events in the tournament, filtered by $preset['scope']

  for each classification c in $preset:
      rows = load subjects for c            (IND | TEAM | MIXED, source QUAL|ELIM)
      for each category:
          starters = count(rows with place < 29999)
          for each row:
              points = bracket_lookup(c.brackets, row.place)
          if c.cutoff and starters < max(rank_to):
              points[last placed row] = 0

      if c.subject is TEAM or MIXED:
          roster = TeamComponent (QUAL) | TeamFinComponent (ELIM)
          if preset.one_team_per_club: keep TeSubTeam = 0 only
          if preset.three_of_four and count(roster) = 4:
              drop the worst qualifier (Events.EvCodeParent → Individuals.IndRank)
          credit club       += points            (full value)
          credit athlete[m] += points / count(counting members)
      else:
          credit club       += points
          credit athlete    += points

  for each report r in $preset['reports']:
      SEPARATE(c)     → rows of c, per category, sorted points desc then place asc
      COMBINED(cs,N)  → per athlete per category: values of cs,
                        keep top N (N = 0 → all), sum
      CLUB            → clubs sorted by credited club total
      VOIVODESHIP     → PLVoivodeshipMap[club.CoCode] grouped, unmapped → "Nieprzypisane"
      skip r entirely if it produced no rows

  return ordered list of rendered reports
```

`bracket_lookup()`, the cutoff, the splitting and the cap are pure functions over arrays — they are the unit-test surface and need no database.

## Risks / Trade-offs

**[Risk] Overlapping or incomplete brackets in preset data.** Bracket tables are transcribed from PDF annexes and have already produced two defects: the OOM individual table was misread as `7-10` plus `9-16` (correct: `7-10 → 5`, `11-16 → 4`), and the MP Juniorów mixed table is printed in the annex itself as `6-7` followed by `7-10`, overlapping at place 7 (read as `8-10 → 3`). `PresetsTest.php` asserts no overlaps across every shipped preset, so a bad transcription fails the suite instead of silently awarding whichever bracket matches first.

**[Known limitation] OOM voivodeship teams.** The OOM annex scores "zespoły klubowe / wojewódzkie". When a team is entered as a voivodeship rather than a club, `Teams.TeCoId` points at a voivodeship entity and the `CLUB` rollup would credit it as if it were a club. Out of scope here: OOM will get its own spec. Until then, run preset 5 only on tournaments whose teams are club teams.

**[Risk] Roster table empty for a scored team.** Club credit still lands (D5); the member split is skipped and the HTML view names the team in a warning. Never fails silently.

**[Risk] `IndRank = 0` for an athlete with no result.** Treated as "no result" → 0 points and omitted from the table, same as a place outside all brackets.

**[Risk] Multiple teams of one club in the same category.** Roster joins always use the full `(club, sub-team, event)` triple, never club alone.

**[Risk] Club identity via `EnCountry2`.** Matching ianseo's own rule is correct but means the club shown in the points table can differ from `EnCountry` shown elsewhere in ianseo. Documented, not worked around.

## Migration Plan

1. No existing data to migrate — both tables are net-new.
2. Auto-install creates them on first page access.
3. Rollback: drop `PLPointsTournamentConfig` and `PLVoivodeshipMap`, remove `PointsRanking/`, remove the two menu entries.

## Open Questions

*(none blocking implementation)*

Resolved:

- **Preset 4 brackets** — supplied from the annex. The three classifications end at different places (`5-9`/`10-20`, `5-6`, `5-8`) because the fields differ in size. "Każdy zawodnik może uzyskać punkty tylko dwukrotnie" is the `COMBINED` cap of 2.
- **OOM voivodeship teams** — deferred; OOM gets its own spec. See Risks.
- **Presets 1, 2 and 5 brackets** — supplied from the 2026 annexes (Uchwały 24/11/2026, 6/02/2026, 6/07/2026). Note this reassigns the values the earlier draft carried: the table previously labelled Młodzieżowe MP is in fact **MP Juniorów**, and Młodzieżowe MP uses a separate table topping out at 25. OOM no longer shares a table with MP Juniorów.
- **Puchar Polski mixed, places 17-32** — intentionally absent; at most 16 pairs take part.
- **LZS individual source** — `QUAL`: the competition shoots qualifications only, no elimination round.
