# Requirements — PZŁucz Tournament Setup Scripts

## Purpose

When an organiser selects the **Poland (PZŁucz)** competition style and creates a new tournament in ianseo, the system must automatically configure the correct bow types, age categories, distances, and target faces according to the PZŁucz Archery Regulations. This document defines exactly what each setup script must configure.

Three competition formats are required as the first priority:

| Script           | Competition format      | Type                   |
| ---------------- | ----------------------- | ---------------------- |
| `Setup_1_PL.php` | 1440 Round              | Outdoor, 4 distances   |
| `Setup_3_PL.php` | Single-distance Round   | Outdoor, 1–2 distances |
| `Setup_6_PL.php` | Indoor 18m / Indoor 15m | Indoor                 |

---

## 1. 1440 Round (`Setup_1_PL.php`)

### Format Description

The 1440 Round is the classic outdoor competition shot over one day. Each archer shoots **36 arrows at each of 4 decreasing distances** — starting from the farthest target and finishing at the closest. The maximum possible score is 1440 points. Archers in different age categories shoot different sets of distances; younger archers shoot shorter distances.

Used at the Polish Championships and regional competitions.

### Bow Types (Divisions)

| Polish name   | Code | Description                                      |
| ------------- | ---- | ------------------------------------------------ |
| Łuk klasyczny | R    | Recurve — sight, stabilisers, clicker allowed    |
| Łuk bloczkowy | C    | Compound — release aid, magnifying scope allowed |
| Łuk barebow   | B    | Barebow — no sight, no stabilisers               |

**Age eligibility per bow type:**

- Łuk klasyczny (R): all age categories
- Łuk bloczkowy (C): Młodzik (U15) through Seniorzy and Master 50+ (no U12; no U24)
- Łuk barebow (B): Senior, Junior (U21), and Junior młodszy (U18) only (no U12, U15, U24, Master)

### Age Categories (Classes)

| Polish class name                       | Code        | Age range          | Sex     |
| --------------------------------------- | ----------- | ------------------ | ------- |
| Seniorzy / Seniorki                     | M / W       | Open (21 and over) | M and W |
| Młodzieżowiec / Młodzieżowniczka        | U24M / U24W | 21–23 years        | M and W |
| Junior / Juniorka                       | U21M / U21W | 18–20 years        | M and W |
| Junior młodszy / Juniorka młodsza       | U18M / U18W | 15–17 years        | M and W |
| Master mężczyźni / Master kobiety (50+) | 50M / 50W   | 50 years and over  | M and W |

> **Młodzik (U15) and Dziecko (U12) do not participate in the standard 1440 Round.** They have separate shorter rounds (see `Setup_3_PL.php` and `Setup_6_PL.php`).

> **Age is calculated by year of birth:** age = competition year − birth year, ignoring the exact birth date.

### Shooting Distances by Category

| Category/ies                                              | Distance 1 | Distance 2 | Distance 3 | Distance 4 |
| --------------------------------------------------------- | ---------- | ---------- | ---------- | ---------- |
| R — Mężczyźni (M, U24M, U21M)                             | 90 m       | 70 m       | 50 m       | 30 m       |
| R — Kobiety (W, U24W, U21W) and R Junior młodszy M (U18M) | 70 m       | 60 m       | 50 m       | 30 m       |
| R — Juniorka młodsza K (U18W)                             | 60 m       | 50 m       | 40 m       | 30 m       |
| R — Master mężczyźni (50M)                                | 70 m       | 60 m       | 50 m       | 30 m       |
| R — Master kobiety (50W)                                  | 60 m       | 50 m       | 40 m       | 30 m       |
| Łuk bloczkowy (C) — all categories                        | 50 m       | 50 m       | 50 m       | 50 m       |
| Łuk barebow (B) — all categories                          | 50 m       | 50 m       | 50 m       | 50 m       |

> Łuk bloczkowy and Łuk barebow shoot 4 sessions of 18 arrows each at 50 m (72 arrows total). The distance does not change, but the 4-session structure is maintained for consistency with the round format.

### Target Faces and Scoring

| Distance                                  | Target           | Rings                     |
| ----------------------------------------- | ---------------- | ------------------------- |
| 90 m, 70 m, 60 m (Recurve long distances) | 122 cm full face | 1–10 + X (gold = 10)      |
| 50 m, 40 m, 30 m (Recurve, Barebow)       | 80 cm full face  | 1–10 + X                  |
| 50 m (Łuk bloczkowy, all sessions)        | 80 cm            | 5–10 + X (6-ring variant) |

**X ring (inner 10):** Counts as 10 points but is recorded separately for tiebreaking.

### Ends and Arrows

- **Standard:** 6 arrows per end, 6 ends per distance = 36 arrows per distance
- **Total:** 144 arrows (4 × 36)

### Tiebreaking

1. Greater number of 10s (including X)
2. Greater number of Xs
3. If still tied: same rank position; coin toss to determine bracket seeding

### Elimination Phase

The 1440 Round is **a qualification round only** — no elimination matches are configured within this script.

> If a tournament adds elimination matches separately, the post-elimination placement rules from §4 (Shared Rules → Post-Elimination Placement) apply.

---

## 2. Single-Distance Round (`Setup_3_PL.php`)

### Format Description

The Single-Distance Round is an outdoor competition where each archer shoots **72 arrows at one distance** (or two distances in the variant for Młodzicy). Used at the Polish Championships and the most common format for everyday competitions.

The script handles **two variants**:

- **Variant A — 70m / 60m Round (Recurve, Compound, Barebow):** 72 arrows in 2 sessions of 36 arrows at a single distance appropriate for the category
- **Variant B — 40m+20m Round (Młodzicy U15):** 36 arrows at 40 m + 36 arrows at 20 m

### Bow Types

Same as the 1440 Round (R, C, B) with the same age restrictions.

Additionally: **Młodzicy (U15M/U15W)** are active in this format.

### Shooting Distances by Category

**Łuk klasyczny (R):**

| Class                     | Distance        | Arrows  |
| ------------------------- | --------------- | ------- |
| Senior M (M), U24M, U21M  | 70 m            | 72      |
| Senior W (W), U24W, U21W  | 70 m            | 72      |
| Junior młodszy M (U18M)   | 60 m            | 72      |
| Juniorka młodsza K (U18W) | 60 m            | 72      |
| Master mężczyźni (50M)    | 60 m            | 72      |
| Master kobiety (50W)      | 60 m            | 72      |
| Młodzik M (U15M)          | **40 m + 20 m** | 36 + 36 |
| Młodziczka K (U15W)       | **40 m + 20 m** | 36 + 36 |

**Łuk bloczkowy (C):**

| Class                               | Distance | Arrows |
| ----------------------------------- | -------- | ------ |
| All categories (Senior through U15) | 50 m     | 72     |

**Łuk barebow (B):**

| Class                                                      | Distance | Arrows |
| ---------------------------------------------------------- | -------- | ------ |
| Senior M/W, Junior (U21M/U21W), Junior młodszy (U18M/U18W) | 50 m     | 72     |

### Target Faces

| Bow / Distance        | Target                           |
| --------------------- | -------------------------------- |
| Recurve 70 m and 60 m | 122 cm full face (1–10 + X)      |
| Recurve 40 m (U15)    | 122 cm full face                 |
| Recurve 20 m (U15)    | 80 cm full face                  |
| Łuk bloczkowy 50 m    | 80 cm, 6-ring variant (5–10 + X) |
| Łuk barebow 50 m      | 122 cm full face (1–10 + X)      |

### Ends and Arrows

**Standard (all except U15):** 2 sessions × (6 ends × 6 arrows) = 72 arrows total.

**40m+20m Round (U15):**

- 40 m session: 12 ends × 3 arrows = 36 arrows
- 20 m session: 12 ends × 3 arrows = 36 arrows

### Tiebreaking

Same as the 1440 Round — 10s first, then Xs, then same rank.

### Elimination Phase

After qualification, **the top 104 archers** in each category advance to matches (at the Polish Championships).

**Łuk klasyczny and Łuk barebow — Set System:**

- Match = max 5 sets; each set = 3 arrows
- Set win: +2 set points; draw: +1 each; loss: 0
- First to 6 set points wins the match
- Tied at 5–5 after 5 sets → shoot-off: 1 arrow each; higher value wins; if equal → closest to centre

**Łuk bloczkowy — Cumulative System:**

- Match = 5 ends × 3 arrows = 15 arrows total
- Higher cumulative score wins
- Tie → shoot-off (1 arrow); if equal → closest to centre

**Elimination distances = same as qualification distances.**

> **Important — U15 at the Polish Championships:** Młodzicy (U15) have **no elimination phase** — the qualification score is the final result.

> For all categories that do have elimination, the post-elimination placement rules from §4 (Shared Rules → Post-Elimination Placement) apply — each loser receives a unique place, not a shared round-based rank.

### Mixed Team Events (Outdoor)

Mixed teams are formed from qualification results (1 man + 1 woman from the same club, same division, same age category). See §4 Shared Rules → Mixed Team Composition for formation rules.

**Bracket:** Top **16 mixed teams** advance to elimination.

**Match format:**

| Bow type                            | System     | Max ends | Arrows per end          | Win condition         |
| ----------------------------------- | ---------- | -------- | ----------------------- | --------------------- |
| Łuk klasyczny (R) / Łuk barebow (B) | Set system | 4 sets   | 4 arrows (2 per archer) | First to 5 set points |
| Łuk bloczkowy (C)                   | Cumulative | 4 ends   | 4 arrows (2 per archer) | Highest total after 4 |

**Shoot-off:** 2 arrows (1 per archer); higher total wins; if equal → closest arrow to centre.

**Mixed team events per division:**

| Division          | Event codes                          |
| ----------------- | ------------------------------------ |
| Łuk klasyczny (R) | RX, RU24X, RU21X, RU18X, R50X, RU15X |
| Łuk bloczkowy (C) | CX, CU21X, CU18X, C50X, CU15X        |
| Łuk barebow (B)   | BX, BU21X, BU18X                     |

**Distances:** Same as the corresponding individual elimination distance for each division × category.

> **U15 mixed teams** have **no elimination** (same as U15 individual/team). They are ranked by combined qualification score only.

---

## 3. Indoor 18m / Indoor 15m (`Setup_6_PL.php`)

### Format Description

The Indoor Round is held indoors at a short distance. Each archer shoots **60 arrows total** (20 ends × 3 arrows). The distance and target size depend on the age category: senior archers shoot at 18 m on small precision targets; younger archers shoot at larger targets.

### Bow Types

- Łuk klasyczny (R), Łuk bloczkowy (C), Łuk barebow (B)
- Dziecko (U12): **Łuk klasyczny (R) only** — Compound and Barebow are not permitted

### Age Categories

| Polish class name                 | Code        | Notes                       |
| --------------------------------- | ----------- | --------------------------- |
| Seniorzy / Seniorki               | M / W       |                             |
| Młodzieżowiec / Młodzieżowniczka  | U24M / U24W | Recurve only                |
| Junior / Juniorka                 | U21M / U21W |                             |
| Junior młodszy / Juniorka młodsza | U18M / U18W |                             |
| Master mężczyźni / Master kobiety | 50M / 50W   |                             |
| Młodzik / Młodziczka              | U15M / U15W |                             |
| Dziecko                           | U12M / U12W | Recurve only; shoot at 15 m |

### Shooting Distance

All categories: **18 metres**, except **Dzieci (U12)** who shoot at **15 metres**.

### Target Faces

| Category                        | Bow type           | Distance | Target                                               |
| ------------------------------- | ------------------ | -------- | ---------------------------------------------------- |
| Senior (M/W), U24, Junior (U21) | Łuk klasyczny (R)  | 18 m     | **Triple 40 cm** (3 small faces arranged vertically) |
| Senior (M/W), U24, Junior (U21) | Łuk bloczkowy (C)  | 18 m     | **Triple 40 cm**                                     |
| Junior młodszy (U18)            | Łuk klasyczny (R)  | 18 m     | Single 40 cm full face                               |
| Junior młodszy (U18)            | Łuk bloczkowy (C)  | 18 m     | Single 40 cm full face                               |
| All categories                  | Łuk barebow (B)    | 18 m     | Single 40 cm full face                               |
| Młodzik / Młodziczka (U15)      | All bow types      | 18 m     | 60 cm full face                                      |
| Dziecko (U12)                   | Łuk klasyczny only | 15 m     | 80 cm full face                                      |

> **Triple 40 cm:** Three 40 cm faces arranged vertically on one stand. Within one end, each arrow must hit a different face (prevents stacking in the same zone). Used by Seniorzy and Juniorzy — it is the precision target for experienced archers.

### Ends and Arrows

All categories: **20 ends × 3 arrows = 60 arrows total.**

### Scoring and Tiebreaking

**Indoor tiebreaking:**

1. Greater number of 10s
2. Greater number of 9s
3. If still tied: same rank position; coin toss

> Note on Łuk bloczkowy (C): The inner 10 ring has a 2 cm diameter (vs 4 cm for Recurve). Tiebreaking rules are the same (10s first, then 9s), but the smaller X causes more frequent tiebreak situations.

### Elimination Phase

After qualification, **the top 32 archers** in each category advance to matches.

Set system and cumulative rules: **same as the outdoor round** (see Section 2).

**Indoor elimination target faces:**

| Category                  | Bow type                     | Target                       |
| ------------------------- | ---------------------------- | ---------------------------- |
| Senior, U24, Junior (U21) | Łuk klasyczny, Łuk bloczkowy | Triple 40 cm (linear layout) |
| Junior młodszy (U18)      | Łuk klasyczny, Łuk bloczkowy | Single 40 cm                 |
| All                       | Łuk barebow                  | Single 40 cm                 |
| Młodzicy (U15)            | All                          | 60 cm                        |

> For all categories with elimination, the post-elimination placement rules from §4 (Shared Rules → Post-Elimination Placement) apply — each loser receives a unique place, not a shared round-based rank.

### Mixed Team Events (Indoor)

Same mixed team formation rules as outdoor (see §4 Shared Rules → Mixed Team Composition).

**Bracket:** Top **16 mixed teams** advance to elimination.

**Match format:** Identical to outdoor mixed teams — set system (R/B) or cumulative (C), 4 arrows per end (2 per archer), shoot-off with 2 arrows.

**Mixed team events per division:**

| Division          | Event codes                          |
| ----------------- | ------------------------------------ |
| Łuk klasyczny (R) | RX, RU24X, RU21X, RU18X, R50X, RU15X |
| Łuk bloczkowy (C) | CX, CU21X, CU18X, C50X, CU15X        |
| Łuk barebow (B)   | BX, BU21X, BU18X                     |

**Indoor mixed team elimination target faces:** Same as indoor individual elimination — triple 40 cm for Senior/U24/U21 R and C; single 40 cm for U18 and all B; 60 cm for U15.

> **U15 mixed teams** have **no elimination** — ranked by combined qualification score only.

---

## 4. Shared Rules

### Age Calculation

Age is calculated **by year of birth**: age = competition year − birth year, ignoring the exact birth date. An archer born in 2008 competes in the U18 category in 2025 (2025 − 2008 = 17).

### Division × Age Eligibility Summary

| Bow type          | Minimum class        | Maximum class | Notes                    |
| ----------------- | -------------------- | ------------- | ------------------------ |
| Łuk klasyczny (R) | Dziecko (U12)        | Master 50+    | No age restrictions      |
| Łuk bloczkowy (C) | Młodzik (U15)        | Master 50+    | No U12; no U24           |
| Łuk barebow (B)   | Junior młodszy (U18) | Senior        | No U12, U15, U24, Master |
| Tradycyjny (T)    | Senior               | Senior        | 3D only                  |
| Longbow (L)       | Senior               | Senior        | 3D only                  |

### Post-Elimination Placement (§2.6.5)

PZŁucz regulations require **unique individual places** for all athletes after elimination rounds. This differs from the default ianseo behaviour, which assigns the same rank to all losers of the same round (e.g., all four 1/4-final losers share 5th place).

**PZŁucz rule:** Losers eliminated in the same round are sub-ranked as follows:

1. **Match score** — higher set points (set system: R, B) or higher cumulative score (cumulative system: C) in the losing match
2. **Qualification ranking** — if match scores are equal, the archer with the better qualification rank is placed higher
3. **Same rank** — only if both match score and qualification rank are identical do the athletes share a position

**Resulting placement example (104-archer bracket, outdoor):**

| Round lost in        | Default ianseo rank | PZŁucz ranks (unique)    |
| -------------------- | ------------------- | ------------------------ |
| Gold-medal match     | 2                   | 2                        |
| Bronze-medal match   | 4                   | 4                        |
| 1/2 final (2 losers) | 5 (shared)          | 5, 6 (sub-ranked)        |
| 1/4 final (4 losers) | 9 (shared)          | 7, 8, 9, 10 (sub-ranked) |
| 1/8 final (8 losers) | 17 (shared)         | 11–18 (sub-ranked)       |
| …and so on           | …                   | …                        |

> This sub-ranking applies to **both individual and team** elimination brackets.

> **⚠ CUSTOM NEEDED:** ianseo does not support sub-ranking of same-round losers out of the box. A custom ranking override is required — likely `Rank/Obj_Rank_GridInd_calc.php` (individual) and `Rank/Obj_Rank_GridTeam_calc.php` (team) — to re-sort losers within each phase by match score then qualification rank.

### Team Competitions

Team results are computed from the same qualification scores. Team score = **sum of the 3 best individual scores** from the same club in the same category (even if 4 archers were registered). The "Top 3 of 4" rule is already implemented in the PL ranking module.

### Mixed Team Composition (§2.3.1.2.4)

Mixed teams pair **1 man + 1 woman** from the same club, same division, same age category.

**Formation:**

- Best-ranked man + best-ranked woman from the same club form Mixed Team 1
- 2nd-best-ranked man + 2nd-best-ranked woman form Mixed Team 2; and so on
- Maximum **3 mixed teams per club** at the Polish Championships (no limit for Młodzicy U15)

**Roster changes:** The team manager may change the composition up to 30 minutes before the elimination round starts. Medals are awarded to the pair that actually competes.

**Match differences vs standard (3-person) teams:**

| Parameter      | Standard team         | Mixed team            |
| -------------- | --------------------- | --------------------- |
| Team size      | 3 (or 3+1 substitute) | 2 (1 man + 1 woman)   |
| Arrows per end | 6 (2 per archer)      | 4 (2 per archer)      |
| Shoot-off      | 3 arrows (1 each)     | 2 arrows (1 each)     |
| Bracket size   | Top 24                | Top 16                |
| Sets (R/B)     | First to 5 set points | First to 5 set points |
| Ends (C)       | 4 cumulative          | 4 cumulative          |

### Out of Scope

The following are explicitly **not** covered by these three scripts:

- **Para-archery** (R OPEN, C OPEN, W1, VI categories)
- **Field archery** (terenowe)
- **3D archery**
- **Special Shootings** (Kur, Słonecznik, Koniczyna, Mak) — PZŁucz Championships tradition

---

## Verification Checklist

1. Create `Setup_1_PL.php` for a test tournament type 1 — confirm R distances vary by category; C/B all show 4 × 50 m
2. Create `Setup_3_PL.php` — verify 70 m/60 m for R; 50 m for C and B; 40 m+20 m for U15 with 3-arrow ends
3. Create `Setup_6_PL.php` — verify triple 40 cm for Senior/U24/U21 R and C; single 40 cm for U18/B; 60 cm for U15; 80 cm for U12 at 15 m
4. Confirm U24 only appears under Łuk klasyczny (R), not C or B
5. Confirm U12 only appears in indoor (type 6), Łuk klasyczny only
6. Confirm U15 has no elimination configuration in type 3
7. Verify post-elimination placement: losers of the same round receive unique places (sub-ranked by match score, then qualification rank) — not shared ranks
8. Confirm sub-ranking applies to both individual and team brackets
9. Verify mixed team events are created for R, C, B with correct event codes (suffix `X`)
10. Confirm mixed team events use 2-person teams (`EvMaxTeamPerson = 2`), 4 arrows per end, 2-arrow shoot-off
11. Confirm `EvMixedTeam = 1` is set on all mixed team events
12. Confirm U15 mixed teams have no elimination (`EvFinalFirstPhase = 0`)

## Decisions

- Requirements written in English; Polish names kept for age class names only (e.g. "Junior młodszy", "Młodzik", "Dziecko")
- Compound 1440 = 4 sessions all at 50 m — same distance repeated
- Para-archery, field, 3D out of scope for this delivery
- U24 and U12 must be created as custom classes (no equivalent in the WA/FITA standard)
- Sub-rules: define at minimum one sub-rule per type ("Full configuration"); additional sub-rules can be added later
