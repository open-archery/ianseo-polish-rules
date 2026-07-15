## Context

Several PZŁucz competitions require a points-based ranking that converts competition positions into points, then rolls them up through athletes → clubs → voivodeships. This calculation is currently done manually off-system. The feature lives entirely within `Modules/Sets/PL/` and reads from existing ianseo tables without modifying any core files.

Key ianseo tables consumed (read-only):
- `Individuals` — `IndRank` (qual rank), `IndRankFinal` (final rank), `IndId`, `IndEvent`
- `Teams` — `TeRank`, `TeRankFinal`, `TeCoId`, `TeSubTeam`, `TeEvent`
- `TeamFinComponent` — team roster: `TfcId` (= `EnId`), `TfcCoId`, `TfcSubTeam`, `TfcEvent`, `TfcTournament`
- `Entries` — `EnId`, `EnName`, `EnFirstName`, `EnCountry` (club CoId)
- `Companies` — `CoId`, `CoName`, `CoCode`

## Goals / Non-Goals

**Goals:**
- Per-tournament preset selection from 7 read-only competition presets
- Athlete points calculation (individual + team + mixed, with cutoff and max-events cap)
- Club and voivodeship aggregation where preset enables it
- PDF report (individual ranking → club ranking → voivodeship ranking)
- Operator UI to map clubs to voivodeships

**Non-Goals:**
- Cross-tournament season aggregation
- Editable presets via UI
- Any modification to ianseo core files

## Decisions

### D1 — Presets as seeded DB data, not pure PHP arrays

**Decision:** Define presets as PHP constant arrays in `Presets.php`; seed them into `PLPointsPreset` / `PLPointsEventConfig` / `PLPointsTable` on first page access using the `SHOW TABLES LIKE` auto-install pattern. Read all preset data from DB at runtime.

**Why over pure PHP arrays:** The rank→points bracket lookup (`rank_from ≤ R ≤ rank_to`) is cleanest as a SQL range query. Keeping data in DB also makes the calculation engine independent of PHP constants at query time.

**Why read-only:** The regulation tables are fixed per competition type. Making them editable introduces data-integrity risk with no operator benefit.

---

### D2 — Per-tournament config via `PLPointsTournamentConfig` table

**Decision:** Store the selected preset ID per tournament in a dedicated `PLPointsTournamentConfig (PltcTournament INT, PltcPresetId INT)` table. Auto-installed on first access.

**Why over `ModulesParameters`:** `getModuleParameter()` is read-only in the public ianseo API; there is no corresponding `setModuleParameter()` helper. Using a PL-owned table keeps write access explicit and conventional.

---

### D3 — Team roster from `TeamFinComponent`; 3-of-4 via `IndRank` join

**Decision:** Read team composition from `TeamFinComponent JOIN Entries JOIN Individuals`. For the 3-of-4 rule, sort roster members by `IndRank` ASC (best qualifier first) within the same individual event category (same EvCode prefix stripped of team suffix), drop the member with the highest `IndRank` (worst qualifier) if roster count = 4.

**Why IndRank not IndScore:** The regulation text says "najniższy wynik w kwalifikacjach" (lowest qualification result) but uses rank for tiebreaking elsewhere; using `IndRank` (lower = better) is unambiguous and consistent with other PL ranking logic.

**Assumption for preset #6 (Między. Młodzicy):** Teams are always 3 members; the drop-worst-of-4 branch will never trigger but is safe to run.

---

### D4 — Fractional points stored as DECIMAL(8,4)

**Decision:** Store computed athlete-level points as `DECIMAL(8,4)` in the result set (in-memory array, not persisted to DB). Display rounded to 2 decimal places in HTML and PDF.

**Why not persist:** Points ranking is a derived view over existing ianseo data. Recalculating on demand avoids stale-cache issues when results change. Calculation is fast (single tournament, O(n athletes)).

---

### D5 — Voivodeship mapping is global, not per-tournament

**Decision:** `PLVoivodeshipMap (PlvmCoId INT PK, PlvmVoivodeship VARCHAR(64))` maps by `CoId` (ianseo company record). Mappings apply across all tournaments.

**Why:** Clubs don't change voivodeship. Per-tournament mapping would create redundant re-entry work.

---

### D6 — Single calculation class, no caching

**Decision:** `PLPointsCalculator` in `PointsRankingCalc.php` computes everything on page load / PDF request. No result table, no caching.

**Trade-off:** Re-runs on every request. Acceptable because the data set is small (hundreds of athletes per tournament) and computation is straightforward SQL + PHP loops.

## File Structure

```
PointsRanking/
  PointsRanking.php          ← UI: preset selector + HTML ranking preview
  PointsRankingPdf.php       ← PDF generation (extends TCPDF)
  PointsRankingCalc.php      ← PLPointsCalculator class
  Fun_PointsRanking.php      ← DB helpers: auto-install, preset load, config read/write
  Presets.php                ← PHP constant arrays for all 7 presets
  VoivodeshipMap.php         ← UI: club → voivodeship mapping operator page
```

**`menu.php` addition:**
```php
$_menu[] = ['Klasyfikacja punktowa',    'PL/PointsRanking/PointsRanking.php'];
$_menu[] = ['Mapa województw',          'PL/PointsRanking/VoivodeshipMap.php'];
```

## DB Schema

```sql
-- Auto-installed, seeded with preset data
CREATE TABLE PLPointsPreset (
  PlppId         INT AUTO_INCREMENT PRIMARY KEY,
  PlppName       VARCHAR(100) NOT NULL,
  PlppMaxEvents  TINYINT NOT NULL DEFAULT 2,   -- 0 = unlimited
  PlppCutoff     TINYINT NOT NULL DEFAULT 1,
  PlppClubRank   TINYINT NOT NULL DEFAULT 1,
  PlppVoivRank   TINYINT NOT NULL DEFAULT 1
);

CREATE TABLE PLPointsEventConfig (
  PlpecPresetId    INT NOT NULL,
  PlpecEventType   ENUM('IND','TEAM','MIXED') NOT NULL,
  PlpecEnabled     TINYINT NOT NULL DEFAULT 1,
  PlpecSource      ENUM('QUAL','FINAL') NOT NULL DEFAULT 'FINAL',
  PlpecThreeOfFour TINYINT NOT NULL DEFAULT 0,
  PRIMARY KEY (PlpecPresetId, PlpecEventType)
);

CREATE TABLE PLPointsTable (
  PlptPresetId  INT NOT NULL,
  PlptEventType ENUM('IND','TEAM','MIXED') NOT NULL,
  PlptRankFrom  SMALLINT NOT NULL,
  PlptRankTo    SMALLINT NOT NULL,
  PlptPoints    DECIMAL(8,2) NOT NULL,
  PRIMARY KEY (PlptPresetId, PlptEventType, PlptRankFrom)
);

-- Per-tournament config
CREATE TABLE PLPointsTournamentConfig (
  PltcTournament INT NOT NULL PRIMARY KEY,
  PltcPresetId   INT NOT NULL
);

-- Global club → voivodeship mapping
CREATE TABLE PLVoivodeshipMap (
  PlvmCoId        INT NOT NULL PRIMARY KEY,
  PlvmVoivodeship VARCHAR(64) NOT NULL
);
```

## Calculation Engine

```
PLPointsCalculator::calculate($tournamentId, $presetId):
  1. Load preset + event configs + brackets from DB
  2. For IND (if enabled):
       a. Query Individuals for all athletes with valid rank (source per config)
       b. For each athlete: lookup bracket → assign raw IND points
       c. Apply cutoff if enabled: zero out athlete at rank = total_starters
             when total_starters < max(PlptRankTo) for IND
  3. For TEAM (if enabled):
       a. Query Teams for all teams with valid rank
       b. For each team: lookup bracket → team_points
       c. Apply cutoff (same logic, independent of IND)
       d. Query TeamFinComponent to get roster
       e. If three_of_four and count = 4: drop member with highest IndRank
       f. points_per_member = team_points / count_counting_members
       g. Accumulate into athlete points map by EnId
  4. For MIXED (if enabled):
       a. Same as TEAM but always 2 members, divide by 2
  5. Apply max_events cap per athlete:
       collect [IND_pts, TEAM_pts, MIXED_pts], sort desc,
       if max_events > 0: keep top max_events, sum
  6. Build athlete ranking (sort by total desc, assign shared ranks on ties)
  7. If club_rank: group by CoId, sum totals
  8. If voiv_rank: join PLVoivodeshipMap, group by voivodeship, sum club totals
  Return: [athletes[], clubs[], voivodeships[]]
```

## Risks / Trade-offs

**[Risk] TeamFinComponent not populated for all team events**
→ Mitigation: if roster is empty for a team, skip point splitting for that team and show a warning in the HTML view. Do not fail silently.

**[Risk] IndRank = 0 for athletes who didn't qualify**
→ Mitigation: treat IndRank = 0 and IndRankFinal = 0 as "no result" → 0 points (same as outside all brackets).

**[Risk] Multiple teams from same club — wrong roster join**
→ Mitigation: always join TeamFinComponent on (TfcCoId, TfcSubTeam, TfcEvent) — the triple uniquely identifies a team roster. Never join on club alone.

**[Risk] Preset seeding runs on every page load if SHOW TABLES check is expensive**
→ Mitigation: seed only when PLPointsPreset table doesn't exist (one DDL check per request at most); subsequent requests skip seeding entirely.

## Migration Plan

1. No existing data to migrate — all tables are net-new.
2. Auto-install creates and seeds tables on first page access.
3. Rollback: drop `PLPointsPreset`, `PLPointsEventConfig`, `PLPointsTable`, `PLPointsTournamentConfig`, `PLVoivodeshipMap`; remove `PointsRanking/` directory and menu entries.

## Open Questions

- **Cutoff for preset #6 (Między. Młodzicy):** Confirmed YES by user during explore session.
- **Tiebreaker in individual ranking when total points are equal:** No tiebreaker specified in regulations. Current design assigns shared rank with no secondary sort. Confirm if secondary sort (e.g. individual event points) is needed.
- **Mixed table bracket "7-10" in Młodzieżowe MP:** Confirmed as 8-10 during explore session.
