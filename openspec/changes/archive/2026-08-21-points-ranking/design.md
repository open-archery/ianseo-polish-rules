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
| `EventClass` | `EcCode`, `EcDivision`, `EcClass`, `EcTeamEvent`, `EcTournament` | maps events to division/class pairs; the scope filter for team events |

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
MP Młodz. / MP Jun. COMBINED(ind,tea,mix, cap 3), CLUB, VOIVODESHIP
OOM / MM Młodz.     COMBINED(ind,tea,mix, cap 2), CLUB, VOIVODESHIP
```

Presets 3 (`Puchar Polski — runda`) covers juniorzy młodsi, juniorzy and seniorzy across all divisions: the point tables are identical and the category dimension (D3) already separates the sections. The mixed report is simply empty when the tournament has no mixed event, so one preset serves all Puchar Polski rounds.

---

### D3 — Everything is scoped to a category

**Decision:** Category = `EnDivision . EnClass` for individuals, `TeEvent` for teams. Bracket lookup, cutoff, sorting and output sections are all per category. Presets additionally declare a division/class **scope** so out-of-scope categories are dropped before any of that.

**Why:** The previous spec had no category dimension at all — "total number of starters" for the cutoff read as tournament-wide, and place 1 in `RU21M` and place 1 in `RU21W` were indistinguishable. LZS also needs the scope filter: it scores only recurve młodzieżowcy/juniorzy/juniorzy młodsi even when seniors shoot the same tournament.

Section ordering reuses `CupRanking`'s division order (`R, C, B`) plus `Classes.ClViewOrder`, and labels come from `DivDescription` / `ClDescription`.

**Scope for team events:** a team event has no `EnDivision`/`EnClass` of its own — it maps to division/class pairs through `EventClass` (ianseo's own qualification team builder filters exactly this way, `Qualification/Fun_Qualification.local.inc.php:1082`). A team event is in scope when at least one of its `EventClass` pairs falls inside the preset scope.

**Attribution in `COMBINED`/`CLUB`/`VOIVODESHIP` follows the athlete's own category:** a team or mixed share lands in the member's `EnDivision . EnClass` section, not in the team event's category — a mixed pair's two members sit in two different sections (e.g. `RU21M` and `RU21W`).

---

### D4 — Team roster source follows the classification's rank source

**Decision:** `source = QUAL` → roster from `TeamComponent`. `source = ELIM` → roster from `TeamFinComponent`.

**Why:** `TeamFinComponent` only holds finals rosters. `Qualification/Fun_Qualification.local.inc.php:461` writes qualification teams into `Teams` + `TeamComponent`. LZS teams are explicitly "składające się z najwyżej sklasyfikowanych zawodników z jednego klubu po rundzie kwalifikacyjnej" — ianseo builds exactly that automatically. Reading `TeamFinComponent` for a `QUAL` classification would silently return no roster.

Team size comes from `Events.EvMaxTeamPerson`; mixed teams are identified by `Events.EvMixedTeam = 1`; the 3-of-4 rule finds the athlete's individual event by its `EvCode` — team and individual events of one category share an `EvCode` in this module's setup scripts (`EvTeamEvent` distinguishes them; see the `I:`/`T:` composite-key convention in `Diplomas/Fun_Diploma.php`). `Events.EvCodeParent` is not used for this: it is never populated for a team↔individual link, either in this module's setup scripts or in FITA core — it chains sub-events within the same kind (e.g. finals brackets), verified against `Common/Rank/Obj_Rank_FinalInd_calc.php` and `Obj_Rank_FinalTeam_calc.php`. (Corrected 2026-08 during implementation — the original text named `EvCodeParent` for this lookup.)

Club id resolves as `IF(EnCountry2 = 0, EnCountry, EnCountry2)`, matching ianseo's own team builder — clubs entered under a second affiliation would otherwise land in the wrong club total. The same rule applies to **individual** points, so an athlete's individual and team points always credit the same club.

`one_team_per_club` (LZS) restricts scoring to `TeSubTeam = 0`.

A tie for the worst qualification place in the 3-of-4 drop is broken deterministically (higher entry id dropped).

**Preset 4 (MM Młodzików) teams are declared, not automatic:** the annex says "trzech zgłoszonych zawodników klubowych" and "klub może zgłosić dowolną liczbę zespołów" — 3-person rosters entered by the operator as ianseo team entries, any number of sub-teams per club, all scoring (`one_team_per_club` off), club teams only (no voivodeship-team problem here, unlike OOM). There is no 4th-athlete rule, so preset 4 does **not** enable `three_of_four`. Rosters still come from `TeamComponent` as for any `QUAL` classification.

---

### D5 — Points belong to athletes; club totals are sums of capped athlete values

**Decision:** Team and mixed points are credited to the **counting members** as even shares (`team_points ÷ counting_member_count`). The `COMBINED` cap applies to those athlete-level values (individual full, team share, mixed share), and the `CLUB` total is the sum of the club's athletes' **post-cap** values — a value dropped by the cap reaches no club or voivodeship total. When the roster is complete and nothing is capped, the shares sum back to the team's full table value, so the club sees the team's 9 points as 9.

**Why:** The annex Zasady sit under "Klasyfikacja klubów i województw" — they define how the club/voivodeship classification is computed. "Każdy zawodnik może uzyskać punkty dwukrotnie/trzykrotnie" means the excess result yields no points at all, so it cannot reach the club either; "w konkurencji zespołowej punktuje tylko 3 zawodników z zespołu" says team points flow through the members. An earlier draft credited the club at full value independently of the cap — that overstates club totals whenever the cap binds (OOM and MM Młodzików, cap 2 over three classifications; presets 1–2 have cap 3 over three classifications, so their cap never binds).

**Arithmetic must be exact:** shares like `22 ÷ 3` must sum back to 22. Credit the team value once and subtract dropped shares; never accumulate floating-point shares.

**Empty-roster fallback:** a scored team with no roster rows still credits its full value to the club (no member attribution is possible), with a warning in the HTML view. Pragmatic divergence from the athlete-sum model, documented.

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

---

### D10 — Cutoff semantics

**Decision:**

- The cutoff applies to **every classification** carrying the flag (ind, tea, mix each), independently, per category — the annex says "ostatni zawodnik/zespół/mikst nie otrzymuje punktów".
- **Starters are competition starters**: counted from qualification ranks (`IndRank`/`TeRank` valid and < 29999), regardless of the classification's rank source. "Liczba startujących" means who started, not who reached the eliminations; this also removes the ambiguity of an athlete who qualified for eliminations and withdrew (`IndRankFinal = 0`).
- The condition is `starters < max(rank_to)` of the classification's bracket table.
- When it fires, **every subject sharing the worst place** is zeroed. The annex's singular "ostatni" does not address ties; zeroing all tied subjects is the documented interpretation (rare in practice — the PL rank engine assigns unique sequential positions below the quarterfinals).

---

### D11 — ELIM rank reading

**Decision:** `ELIM` places are read as `ABS(IndRankFinal)` / `ABS(TeRankFinal)`, and an event with no elimination phase (`Events.EvFinalFirstPhase = 0`) falls back to the qualification rank.

**Why:** ianseo core writes negative final ranks in some flows — `Diplomas/Fun_Diploma.php:152` already wraps in `ABS()` and falls back with `IF(EvFinalFirstPhase=0, IndRank, ABS(IndRankFinal))`. Without the fallback, a category whose field was too small for an elimination round silently vanishes from the athlete tables **and** the club totals under an `ELIM` preset.

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
               (individual events by EnDivision/EnClass,
                team events via their EventClass pairs)

  for each classification c in $preset:
      rows = load subjects for c            (IND | TEAM | MIXED, source QUAL|ELIM)
             ELIM reads ABS(rank); EvFinalFirstPhase = 0 → fall back to QUAL rank
      for each category:
          starters = qualification starters in the category
                     (IndRank/TeRank valid, < 29999 — regardless of c.source)
          for each row:
              points = bracket_lookup(c.brackets, row.place)
          if c.cutoff and starters < max(rank_to):
              zero every row sharing the worst place

      if c.subject is TEAM or MIXED:
          roster = TeamComponent (QUAL) | TeamFinComponent (ELIM)
          if preset.one_team_per_club: keep TeSubTeam = 0 only
          if preset.three_of_four and count(roster) = 4:
              drop the worst qualifier (same-EvCode individual event → Individuals.IndRank;
              tie → higher entry id dropped)
          athlete[m] value += points / count(counting members)
      else:
          athlete value += points

  cap = the preset's COMBINED report cap (no COMBINED → uncapped)
  for each athlete, per category:
      keep the top N positive values (N = 0 → all); dropped values reach no report

  club total = Σ post-cap athlete values, club by IF(EnCountry2=0, EnCountry, EnCountry2)
               + full team value for teams with an empty roster (warning)
               (exact arithmetic: team value once minus dropped shares, no float sums)
  voivodeship total = Σ club totals via PLVoivodeshipMap; empty CoCode = unmapped

  for each report r in $preset['reports']:
      SEPARATE(c)     → rows of c, per category, sorted points desc then place asc
      COMBINED(cs,N)  → per athlete per category (the athlete's own category):
                        post-cap values of cs, sum; total 0 → omitted
      CLUB            → clubs sorted by club total
      VOIVODESHIP     → PLVoivodeshipMap[club.CoCode] grouped, unmapped → "Nieprzypisane"
      skip r entirely if it produced no rows

  return ordered list of rendered reports
```

`bracket_lookup()`, the cutoff, the splitting and the cap are pure functions over arrays — they are the unit-test surface and need no database.

## Risks / Trade-offs

**[Risk] Overlapping or incomplete brackets in preset data.** Bracket tables are transcribed from PDF annexes and have already produced two defects: the OOM individual table was misread as `7-10` plus `9-16` (correct: `7-10 → 5`, `11-16 → 4`), and the MP Juniorów mixed table is printed in the annex itself as `6-7` followed by `7-10`, overlapping at place 7 (read as `8-10 → 3`). `PresetsTest.php` asserts no overlaps across every shipped preset, so a bad transcription fails the suite instead of silently awarding whichever bracket matches first.

**[Known limitation] OOM voivodeship teams.** The OOM annex scores "zespoły klubowe / wojewódzkie" (confirmed in the 2026 annex — OOM only; MM Młodzików explicitly allows club teams only). When a team is entered as a voivodeship rather than a club, `Teams.TeCoId` points at a voivodeship entity and the `CLUB` rollup would credit it as if it were a club. Out of scope here: OOM will get its own spec. Until then, run preset 5 only on tournaments whose teams are club teams.

**[Warning] MM Młodzików minimum participation.** The annex voids the classification below 3 clubs from at least 2 voivodeships. The preset declares `min_participation` and the UI shows a warning when unmet; the reports still render.

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
- **Cap reaches club totals** — the annex Zasady are the rules of the club/voivodeship classification; a value dropped by the cap reaches no club or voivodeship total. D5 revised accordingly (2026-08, from the July 2026 annex update).
- **MP Juniorów cap is 3** — annex says "trzykrotnie"; the earlier draft carried 2.
- **Cutoff wording** — applies to zawodnik/zespół/mikst each; "liczba startujących" = competition (qualification) starters; tied worst place → all zeroed (documented interpretation).
- **MM Młodzików teams** — declared 3-person club rosters, any number per club, no 4th-athlete rule → `three_of_four` off for preset 4; club teams only.
