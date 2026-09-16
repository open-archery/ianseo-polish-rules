# Requirements — PZŁucz Tournament Setup Scripts

## Purpose

When an organiser selects the **Poland (PZŁucz)** competition style and creates a new tournament in ianseo, the system must automatically configure the correct bow types, age categories, distances, and target faces according to the PZŁucz Archery Regulations. This document defines exactly what each setup script must configure.

Three competition formats are required as the first priority:

| Script           | Competition format      | Type                   |
| ---------------- | ----------------------- | ---------------------- |
| `Setup_1_PL.php` | 1440 Round              | Outdoor, 4 distances   |
| `Setup_3_PL.php` | Single-distance Round   | Outdoor, 1–2 distances |
| `Setup_6_PL.php` | Indoor 18m / Indoor 15m | Indoor                 |

Two further formats round out the outdoor offering:

| Script            | Competition format | Type                            |
| ----------------- | ------------------- | ------------------------------- |
| `Setup_37_PL.php` | Double Round        | Outdoor, 4 distances            |
| `Setup_16_PL.php` | Children's Round    | Outdoor, 4 distances, U12-only  |

---

## Category Presets (Sub-Rules)

The setup scripts support named category presets, registered per TourType in `sets.php` and selected by the organiser from ianseo's sub-rule dropdown when creating the tournament. A preset names the divisions and/or classes the competition uses; omitting an axis means "all" for that axis. When a preset is selected, the setup script creates only the divisions/classes it names — in only the divisions those classes are eligible for — and only the individual/team events those surviving division×class combinations compete in. The unfiltered default (equivalent to the module's former `Poland-Full`) remains available on every TourType and creates the full set exactly as an unfiltered setup would. A preset does not change distances, target faces, end structure, elimination cut counts or finals configuration for the categories it does create.

Sub-rule values reuse ianseo's own translated `Install.php` vocabulary wherever the meaning matches (`SetAllClass` for "everything", `SetSeniorClass` for "Senior class only", `SetYouthClass` for the broadest youth-only cut available on that TourType, `SetMasterClass` for the Masters preset — the same convention ianseo's own official PL module uses); presets with no matching core vocabulary use module-specific keys (e.g. `Poland-RU21`), which ianseo renders in the dropdown without a translated label — a known, accepted cosmetic limitation (see `gotchas.md`).

**Registered sub-rules per TourType:**

| TourType | Sub-rule value | Selects |
|---|---|---|
| 1 | `SetAllClass` | everything (R+C, all classes — no Barebow) |
| 1 | `SetSeniorClass` | R+C, M/W |
| 1 | `Poland-RU24` | Recurve only, U24 |
| 1 | `Poland-RU21` | Recurve only, U21 |
| 1 | `Poland-RU18` | Recurve only, U18 |
| 3 | `SetAllClass` | everything (R/C/B, all classes incl. U12/PU12) |
| 3 | `SetSeniorClass` | R/C/B, M/W |
| 3 | `Poland-RU24U21U18` | Recurve only, U24+U21+U18 |
| 3 | `SetYouthClass` | Recurve only, U24+U21 |
| 3 | `Poland-RU18` | Recurve only, U18 |
| 3 | `Poland-RU15` | Recurve only, U15 |
| 3 | `SetMasterClass` | Masters (R/C/B, 5 bands) — TourType 3 exclusive |
| 37 | `SetAllClass` | everything (R/C/B, all classes — no U12/PU12/Masters) |
| 37 | `SetSeniorClass` | R/C/B, M/W |
| 37 | `Poland-RU24U21U18` | Recurve only, U24+U21+U18 |
| 37 | `SetYouthClass` | Recurve only, U24+U21 |
| 37 | `Poland-RU18` | Recurve only, U18 |
| 37 | `Poland-RU15` | Recurve only, U15 |
| 6 | `SetAllClass` | everything (incl. PU12) |
| 6 | `SetSeniorClass` | R/C/B, M/W |
| 6 | `SetYouthClass` | Recurve only, U24+U21 |
| 6 | `Poland-RU18` | Recurve only, U18 |
| 6 | `Poland-RU15` | Recurve only, U15 |
| 16 | `SetAllClass` | fixed, not organiser-selectable (single entry) |

The Masters preset (`SetMasterClass`) is never offered on TourType 1, 6, 16 or 37.

---

## 1. 1440 Round (`Setup_1_PL.php`)

### Format Description

The 1440 Round is the classic outdoor competition shot over one day. Each archer shoots **36 arrows at each of 4 decreasing distances** — starting from the farthest target and finishing at the closest. The maximum possible score is 1440 points. Archers in different age categories shoot different sets of distances; younger archers shoot shorter distances.

Used at the Polish Championships and regional competitions.

### Bow Types (Divisions)

| Polish name   | Code | Description                                      |
| ------------- | ---- | ------------------------------------------------- |
| Łuk klasyczny | R    | Recurve — sight, stabilisers, clicker allowed    |
| Łuk bloczkowy | C    | Compound — release aid, magnifying scope allowed |

**Łuk barebow (B) is not offered on this TourType** — PZŁucz's 1440 Round does not have a Barebow category.

**Age eligibility per bow type:**

- Łuk klasyczny (R): Senior, Młodzieżowiec (U24), Junior (U21), Junior młodszy (U18)
- Łuk bloczkowy (C): Senior, Junior (U21), Junior młodszy (U18) — no U24 (U24 is Recurve-only, same restriction as everywhere else in this module)

### Age Categories (Classes)

| Polish class name                 | Code        | Age range           | Sex     |
| ---------------------------------- | ----------- | -------------------- | ------- |
| Seniorzy / Seniorki                | M / W       | Open (21 and over)   | M and W |
| Młodzieżowiec / Młodzieżowniczka   | U24M / U24W | 21–23 years          | M and W |
| Junior / Juniorka                  | U21M / U21W | 18–20 years          | M and W |
| Junior młodszy / Juniorka młodsza  | U18M / U18W | 15–17 years          | M and W |

There is no Master category at the 1440 Round — the flat "Master 50+" class that used to exist here was removed (see §2's Masters age-band classes, which are TourType 3 only; no 1440-round Masters format is defined).

> **Młodzik (U15) and Dziecko (U12) do not participate in the standard 1440 Round.** They have separate shorter rounds (see `Setup_3_PL.php` and `Setup_6_PL.php`).

> **Age is calculated by year of birth:** age = competition year − birth year, ignoring the exact birth date.

### Shooting Distances by Category

**Łuk klasyczny (R):**

| Category/ies                                              | Distance 1 | Distance 2 | Distance 3 | Distance 4 |
| --------------------------------------------------------- | ---------- | ---------- | ---------- | ---------- |
| Mężczyźni (M, U24M, U21M)                                 | 90 m       | 70 m       | 50 m       | 30 m       |
| Kobiety (W, U24W, U21W) and Junior młodszy M (U18M)        | 70 m       | 60 m       | 50 m       | 30 m       |
| Juniorka młodsza K (U18W)                                  | 60 m       | 50 m       | 40 m       | 30 m       |

**Łuk bloczkowy (C):** identical per-class distances to Łuk klasyczny above — Compound mirrors Recurve's distance table exactly on this TourType (this is a change from the round's earlier flat 4×50m Compound distance).

> Compound's target face stays the 80cm 6-ring face regardless of which of the distances above it's shooting at any given leg — only the *distances* mirror Recurve, not the scoring face (see Target Faces below).

### Target Faces and Scoring

| Division / Distance                        | Target           | Rings                     |
| ------------------------------------------- | ---------------- | ------------------------- |
| Łuk klasyczny, 90 m / 70 m / 60 m           | 122 cm full face | 1–10 + X (gold = 10)      |
| Łuk klasyczny, 50 m / 40 m / 30 m           | 80 cm full face  | 1–10 + X                  |
| Łuk bloczkowy, any distance                 | 80 cm            | 5–10 + X (6-ring variant) |

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

> If a tournament adds elimination matches separately, the post-elimination placement rules from §6 (Shared Rules → Post-Elimination Placement) apply.

### Sub-rules

See "Category Presets (Sub-Rules)" above for the full list. TourType 1 offers: everything (R+C, all classes), Senior-only (R+C, M/W), and three Recurve-only single-class cuts (U24, U21, U18) — each excludes the class from Compound even though it's normally eligible there in the unfiltered default.

---

## 2. Single-Distance Round (`Setup_3_PL.php`)

### Format Description

The Single-Distance Round is an outdoor competition where each archer shoots **72 arrows at one distance** (or two distances in the variant for Młodzicy). Used at the Polish Championships and the most common format for everyday competitions.

The script handles **several category variants**:

- **Variant A — 70m / 60m Round (Recurve, Compound, Barebow):** one distance per session, appropriate for the category
- **Variant B — 40m+20m Round (Młodzicy U15):** alternating 40 m and 20 m sessions
- **Variant C — 15m Round (Dziecko U12):** one distance per session, Recurve only
- **Variant D — 10m Round (łuk popularny / PU12):** one distance per session, Recurve only
- **Variant E — Masters bands:** one distance per session, varying by age band, Recurve/Compound/Barebow

The Double Round variant of this format — every class shooting the same session structure twice, 144 arrows in 4 sessions — used to be offered here as a `Poland-4x70m` sub-rule; it is now its own script and TourType, `Setup_37_PL.php` (§4 Double Round), since a `Poland-4x70m` tournament was reporting four qualification sessions on a tournament type ianseo itself believes has two. A tournament created earlier under `Poland-4x70m` keeps that value on its `ToTypeSubRule` column, but the module no longer registers or supports it — re-running setup on such a tournament is unsupported; recreate it as `Setup_37_PL.php` if its setup needs rebuilding.

### Bow Types

Same as the 1440 Round (R, C), **plus Łuk barebow (B)** — Barebow exists on this TourType, unlike the 1440 Round.

Additionally active on this format:

- **Młodzicy (U15M/U15W)**
- **Dziecko (U12M/U12W)** — new to this TourType (previously indoor- and Children's-Round-only)
- **Łuk popularny (PU12M/PU12W)** — simplified-recurve equipment category, same 9–12 age bracket as U12 but a distinct class, Recurve only
- **Master age bands (`40M`/`40W`…`80M`/`80W`)** — Recurve, Compound and Barebow, TourType 3 exclusive (own sub-rule, `SetMasterClass`)

### Shooting Distances by Category

**Łuk klasyczny (R):**

| Class                     | Distance        | Arrows  |
| ------------------------- | --------------- | ------- |
| Senior M (M), U24M, U21M  | 70 m            | 72      |
| Senior W (W), U24W, U21W  | 70 m            | 72      |
| Junior młodszy M (U18M)   | 60 m            | 72      |
| Juniorka młodsza K (U18W) | 60 m            | 72      |
| Młodzik M (U15M)          | **40 m + 20 m** | 36 + 36 |
| Młodziczka K (U15W)       | **40 m + 20 m** | 36 + 36 |
| Dziecko (U12M/U12W)       | 15 m            | 72      |
| Łuk popularny (PU12M/PU12W) | 10 m          | 72      |
| Master 40-49 / 50-59 (`40M/W`, `50M/W`) | 70 m | 72 |
| Master 60-69 (`60M/W`)    | 60 m            | 72      |
| Master 70+ / 80+ (`70M/W`, `80M/W`) | 50 m  | 72      |

**Łuk bloczkowy (C):**

| Class                                    | Distance | Arrows |
| ------------------------------------------ | -------- | ------ |
| Senior, U21, Junior młodszy, Młodzik (M/W/U21M/U21W/U18M/U18W/U15M/U15W) | 50 m     | 72     |
| Master, all 5 bands (`40M/W`…`80M/W`)      | 50 m     | 72     |

U12 and PU12 do not shoot Compound — Recurve only.

**Łuk barebow (B):**

| Class                                                       | Distance | Arrows |
| ------------------------------------------------------------ | -------- | ------ |
| Senior M/W, Junior (U21M/U21W), Junior młodszy (U18M/U18W)   | 50 m     | 72     |
| Master, all 5 bands (`40M/W`…`80M/W`)                        | 50 m     | 72     |

Master Barebow is new — previously Barebow's oldest eligible class was Senior; the age-band Masters classes now shoot Barebow at every band.

### Target Faces

| Bow / Category                          | Target                                     |
| ---------------------------------------- | ------------------------------------------- |
| Recurve 70 m / 60 m                      | 122 cm full face (1–10 + X)                 |
| Recurve 40 m (U15)                       | 122 cm full face                            |
| Recurve 20 m (U15)                       | 80 cm full face                             |
| Recurve 15 m (U12) / 10 m (łuk popularny) | 122 cm full face                           |
| Recurve, Masters (all bands)             | 122 cm full face                            |
| Łuk bloczkowy 50 m, bands 40-49/50-59/60-69 and non-Masters | 80 cm, 6-ring variant (5–10 + X) |
| Łuk bloczkowy 50 m, Masters 70+/80+      | 80 cm full face (1–10 + X) — **not** the 6-ring variant |
| Łuk barebow 50 m                         | 122 cm full face (1–10 + X)                 |

### Ends and Arrows

**Standard (adult classes, U12, PU12, Masters):** 2 sessions × (6 ends × 6 arrows) = 72 arrows total.

**40m+20m Round (U15 only):**

- 40 m session: 12 ends × 3 arrows = 36 arrows
- 20 m session: 12 ends × 3 arrows = 36 arrows

> The Double Round (§4, `Setup_37_PL.php`) shoots every session above twice — 144 arrows total for the classes it offers (U15: 40m, 40m, 20m, 20m). U12, PU12 and Masters are not offered on the Double Round at all (see §4).

### Tiebreaking

Same as the 1440 Round — 10s first, then Xs, then same rank.

### Elimination Phase

After qualification, **the top 104 archers** in each category advance to matches (at the Polish Championships) — this includes every Masters band.

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

> Elimination structure (cut counts, set/cumulative match format) is identical on the Double Round (§4) — doubling only affects the qualification round.

> **U15, U12 and PU12 have no elimination phase** — the qualification score is the final result. Masters classes *do* have an elimination phase (unlike U15/U12/PU12), following the same rules as the corresponding adult class.

> For all categories that do have elimination, the post-elimination placement rules from §6 (Shared Rules → Post-Elimination Placement) apply — each loser receives a unique place, not a shared round-based rank.

### Mixed Team Events (Outdoor)

Mixed teams are formed from qualification results (1 man + 1 woman from the same club, same division, same age category). See §6 Shared Rules → Mixed Team Composition for formation rules.

**Bracket:** Top **16 mixed teams** advance to elimination.

**Match format:**

| Bow type                            | System     | Max ends | Arrows per end          | Win condition         |
| ----------------------------------- | ---------- | -------- | ----------------------- | --------------------- |
| Łuk klasyczny (R) / Łuk barebow (B) | Set system | 4 sets   | 4 arrows (2 per archer) | First to 5 set points |
| Łuk bloczkowy (C)                   | Cumulative | 4 ends   | 4 arrows (2 per archer) | Highest total after 4 |

**Shoot-off:** 2 arrows (1 per archer); higher total wins; if equal → closest arrow to centre.

**Mixed team events per division:**

| Division          | Event codes                    |
| ----------------- | ------------------------------- |
| Łuk klasyczny (R) | RX, RU24X, RU21X, RU18X, RU15X |
| Łuk bloczkowy (C) | CX, CU21X, CU18X, CU15X        |
| Łuk barebow (B)   | BX, BU21X, BU18X               |

**No mixed team events exist for U12, PU12 or Master classes.**

**Distances:** Same as the corresponding individual elimination distance for each division × category.

> **U15 mixed teams** have **no elimination** (same as U15 individual/team). They are ranked by combined qualification score only.

### Sub-rules

See "Category Presets (Sub-Rules)" above for the full list — 7 sub-rules on this TourType, including the TourType-3-exclusive Masters preset.

---

## 3. Indoor 18m / Indoor 15m (`Setup_6_PL.php`)

### Format Description

The Indoor Round is held indoors at a short distance. Each archer shoots **60 arrows total** (20 ends × 3 arrows). The distance and target size depend on the age category: senior archers shoot at 18 m on small precision targets; younger archers shoot at larger targets.

### Bow Types

- Łuk klasyczny (R), Łuk bloczkowy (C), Łuk barebow (B)
- Dziecko (U12) and Łuk popularny (PU12): **Łuk klasyczny (R) only** — Compound and Barebow are not permitted

### Age Categories

| Polish class name                 | Code        | Notes                       |
| ---------------------------------- | ----------- | ---------------------------- |
| Seniorzy / Seniorki                | M / W       |                               |
| Młodzieżowiec / Młodzieżowniczka   | U24M / U24W | Recurve only                 |
| Junior / Juniorka                  | U21M / U21W |                               |
| Junior młodszy / Juniorka młodsza  | U18M / U18W |                               |
| Młodzik / Młodziczka               | U15M / U15W |                               |
| Dziecko                            | U12M / U12W | Recurve only; shoot at 15 m  |
| Łuk popularny                      | PU12M / PU12W | Recurve only; shoot at 10 m |

There is no Master category indoors — the flat "Master 50+" class that used to exist here was removed; no indoor Masters replacement is defined.

### Shooting Distance

All categories: **18 metres**, except **Dzieci (U12)** who shoot at **15 metres** and **łuk popularny (PU12)** who shoot at **10 metres**.

### Target Faces

| Category                        | Bow type            | Distance | Target                                               |
| -------------------------------- | -------------------- | -------- | ----------------------------------------------------- |
| Senior (M/W), U24, Junior (U21)  | Łuk klasyczny (R)     | 18 m     | **Triple 40 cm** (3 small faces arranged vertically) |
| Senior (M/W), Junior (U21)       | Łuk bloczkowy (C)     | 18 m     | **Triple 40 cm**                                     |
| Junior młodszy (U18)             | Łuk klasyczny (R)     | 18 m     | Single 40 cm full face                                |
| Junior młodszy (U18)             | Łuk bloczkowy (C)     | 18 m     | Single 40 cm full face                                |
| All categories                   | Łuk barebow (B)       | 18 m     | Single 40 cm full face                                |
| Młodzik / Młodziczka (U15)       | All bow types         | 18 m     | 60 cm full face                                       |
| Dziecko (U12)                    | Łuk klasyczny only    | 15 m     | 80 cm full face                                       |
| Łuk popularny (PU12)             | Łuk klasyczny only    | 10 m     | 122 cm full face                                      |

> **Triple 40 cm:** Three 40 cm faces arranged vertically on one stand. Within one end, each arrow must hit a different face (prevents stacking in the same zone). Used by Seniorzy and Juniorzy — it is the precision target for experienced archers.

### Ends and Arrows

All categories: **20 ends × 3 arrows = 60 arrows total.**

Individual and team events use the same distance/target-size configuration per class — U12's team event shoots the same 15 m / 80 cm single face as U12's individual event (not U15's 18 m / 40 cm triple face).

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
| ------------------------- | ---------------------------- | ----------------------------- |
| Senior, Junior (U21)      | Łuk klasyczny, Łuk bloczkowy | Triple 40 cm (linear layout)  |
| Junior młodszy (U18)      | Łuk klasyczny, Łuk bloczkowy | Single 40 cm                  |
| All                       | Łuk barebow                  | Single 40 cm                  |
| Młodzicy (U15)            | All                          | 60 cm                         |

> U12 and PU12 have no elimination — see Ends and Arrows / Elimination note below.

> For all categories with elimination, the post-elimination placement rules from §6 (Shared Rules → Post-Elimination Placement) apply — each loser receives a unique place, not a shared round-based rank.

> **U15, U12 and PU12 have no elimination phase** — the qualification score is the final result.

### Mixed Team Events (Indoor)

Same mixed team formation rules as outdoor (see §6 Shared Rules → Mixed Team Composition).

**Bracket:** Top **16 mixed teams** advance to elimination.

**Match format:** Identical to outdoor mixed teams — set system (R/B) or cumulative (C), 4 arrows per end (2 per archer), shoot-off with 2 arrows.

**Mixed team events per division:**

| Division          | Event codes                    |
| ----------------- | -------------------------------- |
| Łuk klasyczny (R) | RX, RU24X, RU21X, RU18X, RU15X  |
| Łuk bloczkowy (C) | CX, CU21X, CU18X, CU15X         |
| Łuk barebow (B)   | BX, BU21X, BU18X                |

**No mixed team events exist for U12 or PU12.**

**Indoor mixed team elimination target faces:** Same as indoor individual elimination — triple 40 cm for Senior/U21 R and C; single 40 cm for U18 and all B; 60 cm for U15.

> **U15 mixed teams** have **no elimination** — ranked by combined qualification score only.

### Sub-rules

See "Category Presets (Sub-Rules)" above for the full list — 5 sub-rules on this TourType.

---

## 4. Double Round (`Setup_37_PL.php`)

### Format Description

The Double Round (Podwójna runda, §2.3.1.10.7) is the Single-Distance Round (§2) shot twice: every class shoots its §2 session structure **twice**, at the same distances, with no other change — **144 arrows in 4 sessions**. Runs on ianseo TourType 37 (`Type_2x70mRound`).

### Bow Types

Same as the Single-Distance Round's Senior/U24/U21/U18/U15 classes (§2), with the same age restrictions.

**U12, łuk popularny (PU12) and Masters are explicitly not offered on this TourType** — this is the one place §2's roster and §4's diverge. A tournament needing U12/PU12/Masters must use TourType 3 instead.

### Shooting Distances by Category

| Class group | Single-Distance Round (§2, 2 sessions) | Double Round (4 sessions) |
| --- | --- | --- |
| R Senior/U24/U21 (M, W) | 70m, 70m | 70m, 70m, 70m, 70m |
| R U18 | 60m, 60m | 60m, 60m, 60m, 60m |
| R Młodzik (U15) | 40m, 20m | 40m, 40m, 20m, 20m |
| C (all categories) | 50m, 50m | 50m, 50m, 50m, 50m |
| B (all categories) | 50m, 50m | 50m, 50m, 50m, 50m |

`tourDetNumDist` is `4`; `tourDetMaxDistScore` stays `360` — a per-session cap, unaffected by session count. Target faces, event codes, and team scoring are identical to the Single-Distance Round (§2), for the classes both formats share.

### Ends and Arrows

4 sessions, each per the Single-Distance Round's own end/arrow structure (§2) — 144 arrows total.

### Elimination Phase

Identical to the Single-Distance Round (§2): top 104 individual / top 24 team qualify, same set/cumulative match formats, same finals structure. Młodzik (U15) has no elimination phase, same as §2.

### Mixed Team Events

Identical to the Single-Distance Round (§2) for the classes both formats share — same event codes, bracket size, and match format.

### Sub-rules

See "Category Presets (Sub-Rules)" above for the full list — 6 sub-rules on this TourType (same as TourType 3 minus the Masters preset).

---

## 5. Children's Round (`Setup_16_PL.php`)

### Format Description

The Children's Round (Runda dziecięca, §2.3.1.10.11) is a competition exclusively for Dzieci (U12), Łuk klasyczny only — no other age category or bow type is created. Runs on ianseo TourType 16 (`Type_GiochiGioventuW`), with one fixed, non-organiser-selectable configuration.

**Łuk popularny (PU12) is explicitly not offered on this TourType**, even though it shares U12's 9–12 age bracket on TourType 3/6.

Each archer shoots **18 arrows at each of four distances**, longest to shortest — **72 arrows total**, matching every other format's arrow count, just split across four shorter legs instead of two longer ones.

> **U15 is not part of this format.** Młodzicy (U15) keep shooting the 40m/20m Round via `Setup_3_PL.php`/`Setup_37_PL.php` (§2, §4) exactly as before. A single ianseo tournament cannot host both U12 and U15 together: U15's round is 2 distance legs and U12's is 4, and ianseo's qualification score-entry screen has no per-class awareness of how many legs a class actually uses — confirmed by direct testing. A combined U12+U15 event needs two separate ianseo tournaments; nothing in the PZŁucz regulations requires one tournament per event.

### Bow Types

Łuk klasyczny (R) only — Dzieci do not compete in Łuk bloczkowy or Łuk barebow.

### Age Categories

| Polish class name    | Code        |
| --------------------- | ----------- |
| Dziecko (chłopcy/dziewczęta) | U12M / U12W |

### Shooting Distances and Target Faces

| Distance | Arrows | Target |
| -------- | ------ | ------ |
| 25 m     | 18     | 122 cm full face |
| 20 m     | 18     | 122 cm full face |
| 15 m     | 18     | 80 cm full face  |
| 10 m     | 18     | 80 cm full face  |

Shot longest to shortest. The exact distance values (25/20/15/10 m) are per the Polish federation's own target-face-by-distance table (§2.1.2.3.1-2) — the round-definition section (§2.3.1.10.11) itself only fixes the arrow count, ordering, and face split, leaving distance values to the organiser.

### Ends and Arrows

**3-arrow ends** (§2.4.1.1): 6 ends × 3 arrows = 18 arrows per distance, × 4 distances = 72 arrows total.

### Elimination Phase

**None.** The PZŁucz regulations define no elimination format for U12 anywhere, outdoor or indoor — qualification score is the final result.

---

## 6. Shared Rules

### Age Calculation

Age is calculated **by year of birth**: age = competition year − birth year, ignoring the exact birth date. An archer born in 2008 competes in the U18 category in 2025 (2025 − 2008 = 17).

### Division × Age Eligibility Summary

| Bow type          | Minimum class          | Maximum class    | Notes                              |
| ----------------- | ----------------------- | ------------------ | ------------------------------------ |
| Łuk klasyczny (R) | Dziecko (U12) / łuk popularny (PU12) | Master (all bands) | No age restrictions |
| Łuk bloczkowy (C) | Młodzik (U15)            | Master (all bands) | No U12; no PU12; no U24              |
| Łuk barebow (B)   | Junior młodszy (U18)     | Master (all bands) | No U12, U15, U24, PU12               |
| Tradycyjny (T)    | Senior                   | Senior              | 3D only                              |
| Longbow (L)       | Senior                   | Senior              | 3D only                              |

Master classes reach every division (R, C, B) — this is new: the flat Master class Barebow never included, but the five age-band Masters classes (TourType 3 only) shoot Barebow at every band.

**Senior age ceiling:** Senior (`M`/`W`) has no PZŁucz-mandated upper age bound. In the schema, `Classes.ClAgeTo` is a `tinyint` column, so the practical ceiling is 127 rather than literal infinity. This guarantees an archer of any age resolves to Senior when no narrower age-band class exists in the tournament (e.g. TourType 1 or 6, or TourType 3 without the Masters preset). When Masters age-band classes are present in the same tournament, every Masters band is still narrower than Senior's range and is matched first — widening Senior's ceiling does not change which class a narrowest-range match picks.

### Upward Class Eligibility (Event Reassignment)

Beyond the age-category assignment above, an archer's entry may be voluntarily reassigned to an older class for event participation (`ClValidClass`). This upward chain is restricted below U21:

- U21 and U24 may still opt up to Senior (`M`/`W`).
- U18 may opt up to U21 only — never directly to Senior.
- U12 and U15 are self-only, with no upward eligibility — the same pattern already used by Masters bands and łuk popularny (PU12), which are parallel tracks rather than steps in the main age progression.

### Post-Elimination Placement (§2.6.5)

PZŁucz regulations require **unique individual places** for all athletes after elimination rounds. This differs from the default ianseo behaviour, which assigns the same rank to all losers of the same round (e.g., all four 1/4-final losers share 5th place).

**PZŁucz rule:** Losers eliminated in the same round are sub-ranked as follows:

1. **Match score** — higher set points (set system: R, B) or higher cumulative score (cumulative system: C) in the losing match
2. **Qualification ranking** — if match scores are equal, the archer with the better qualification rank is placed higher
3. **Same rank** — only if both match score and qualification rank are identical do the athletes share a position

**Resulting placement example (104-archer bracket, outdoor):**

| Round lost in        | Default ianseo rank | PZŁucz ranks (unique)    |
| -------------------- | -------------------- | -------------------------- |
| Gold-medal match     | 2                     | 2                           |
| Bronze-medal match   | 4                     | 4                           |
| 1/2 final (2 losers) | 5 (shared)            | 5, 6 (sub-ranked)           |
| 1/4 final (4 losers) | 9 (shared)            | 7, 8, 9, 10 (sub-ranked)    |
| 1/8 final (8 losers) | 17 (shared)           | 11–18 (sub-ranked)          |
| …and so on           | …                     | …                            |

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

| Parameter      | Standard team          | Mixed team             |
| -------------- | ------------------------ | ------------------------- |
| Team size      | 3 (or 3+1 substitute)    | 2 (1 man + 1 woman)       |
| Arrows per end | 6 (2 per archer)         | 4 (2 per archer)          |
| Shoot-off      | 3 arrows (1 each)        | 2 arrows (1 each)         |
| Bracket size   | Top 24                   | Top 16                    |
| Sets (R/B)     | First to 5 set points    | First to 5 set points     |
| Ends (C)       | 4 cumulative              | 4 cumulative               |

No mixed team events exist for U12, PU12, or Master classes on any TourType.

### Out of Scope

The following are explicitly **not** covered by these scripts:

- **Para-archery** (R OPEN, C OPEN, W1, VI categories)
- **Field archery** (terenowe)
- **3D archery**
- **Special Shootings** (Kur, Słonecznik, Koniczyna, Mak) — PZŁucz Championships tradition
- **Masters format on TourType 1 or 6** — no Masters replacement is defined outside TourType 3

---

## Verification Checklist

1. Create `Setup_1_PL.php` for a test tournament type 1 — confirm R distances vary by category; C mirrors R's per-class distances exactly; confirm no Barebow division/class/event is created
2. Create `Setup_3_PL.php` — verify 70 m/60 m for R; 50 m for C and B; 40 m+20 m for U15 with 3-arrow ends; 15 m for U12; 10 m for łuk popularny (PU12); Masters bands at their documented per-band distances
3. Create `Setup_6_PL.php` — verify triple 40 cm for Senior/U24/U21 R and C; single 40 cm for U18/B; 60 cm for U15; 80 cm for U12 at 15 m; 122 cm for łuk popularny at 10 m
4. Confirm U24 only appears under Łuk klasyczny (R), not C or B, on every TourType including the 1440 Round
5. Confirm U12 appears in indoor (type 6), the Single-Distance Round (type 3, new), and the Children's Round (type 16, U12-only) — Łuk klasyczny only in all three — never in type 1 or 37
6. Confirm łuk popularny (PU12) appears only on types 3 and 6, never on 1, 16 or 37
7. Confirm the flat Master class (`50M`/`50W`) is never created on any TourType; confirm the five age-band Masters classes are created only when the `SetMasterClass` sub-rule is selected on TourType 3
8. Confirm Masters classes have an elimination phase; confirm U12, PU12 and U15 do not
9. Confirm U15 has no elimination configuration in type 3
10. Verify post-elimination placement: losers of the same round receive unique places (sub-ranked by match score, then qualification rank) — not shared ranks
11. Confirm sub-ranking applies to both individual and team brackets
12. Verify mixed team events are created for R, C, B with correct event codes (suffix `X`); confirm no mixed team events exist for U12, PU12, or Masters
13. Confirm mixed team events use 2-person teams (`EvMaxTeamPerson = 2`), 4 arrows per end, 2-arrow shoot-off
14. Confirm `EvMixedTeam = 1` is set on all mixed team events
15. Confirm U15 mixed teams have no elimination (`EvFinalFirstPhase = 0`)
16. Create `Setup_37_PL.php` — verify every shared class's §2 session structure is doubled exactly (e.g. U15 = 40m, 40m, 20m, 20m), `tourDetNumDist = 4`, elimination/finals/mixed-team configuration identical to type 3; confirm U12/PU12/Masters are never created
17. Create `Setup_16_PL.php` — verify only U12M/U12W classes exist (no senior/U24/U21/U18/Master/PU12, no C or B division), distances are 25m/20m/15m/10m with 122/122/80/80cm faces, 3-arrow ends, and no elimination configuration
18. Select each registered sub-rule on the tournament-creation form and confirm the category list matches this document's Category Presets table, with no leftover distance, target-face or event row for a class the preset didn't create
19. Confirm a 55-year-old archer resolves to Senior M/W when auto-assigned in a tournament without Masters classes (TourType 1, 6, or TourType 3 without `SetMasterClass`)
20. Confirm a 55-year-old archer in a TourType 3 tournament with `SetMasterClass` resolves to the `50M`/`50W` Masters band, not Senior — and a 110-year-old resolves to Senior (older than every Masters band's own ceiling)
21. Confirm `ClValidClass` assignable-class options: U12/U15 self-only; U18 can opt up to U21 only (not directly to Senior); U21/U24 unchanged, can still opt up to Senior

## Decisions

- Requirements written in English; Polish names kept for age class names only (e.g. "Junior młodszy", "Młodzik", "Dziecko")
- Every target face label the setup scripts create uses this module's established Polish division vocabulary (`Łuk klasyczny`/`Łuk bloczkowy`/`Łuk barebow`), not the English bow-type words some labels used before this module's category-presets rework
- Para-archery, field, 3D out of scope for this delivery
- U24 and U12 must be created as custom classes (no equivalent in the WA/FITA standard); łuk popularny (PU12) likewise has no WA/FITA equivalent
- Category presets (sub-rules) are a fixed list defined in the module, per TourType — there is no runtime editing; adding a preset is a code change
- The flat "Master 50+" class does not reflect how PZŁucz Masters competitions are actually run — replaced by five age-band classes (40-49/50-59/60-69/70+/80+), TourType 3 only; 70+ and 80+ are both open-ended and deliberately overlap (an 80-or-older archer may choose either band)
- Senior's `ClAgeTo` is capped at 127 (the `tinyint` column's real ceiling), not a literal unbounded value — PZŁucz rules impose no upper bound but the schema does
- `ClValidClass` upward reassignment stops short below U21: U12/U15 are self-only and U18 tops out at U21, matching the self-only pattern Masters bands and PU12 already use, rather than letting every class eventually reach Senior
