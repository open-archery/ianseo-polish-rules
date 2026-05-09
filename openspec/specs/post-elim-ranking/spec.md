# Post-Elimination Ranking — Feature Requirements

> **Feature:** Unique placement of athletes/teams after elimination rounds
> **Source:** PZŁucz regulations §2.6.5–§2.6.6 (Post-Elimination Classification)
> **Scope:** Individual and team elimination brackets, target archery and indoor formats (Field/3D excluded for now)

---

## 1. Competition Format Summary

PZŁucz regulations §2.6.5 prescribe that after elimination rounds every athlete
(and every team) receives a **unique final place**, rather than sharing a rank
with all other losers from the same round.

Additionally, in some PZŁucz tournaments the bronze-medal match does not
occur. When there is no bronze match, both semifinal losers share 3rd place.

This feature covers two distinct but related requirements:

1. **No-bronze-medal-match handling** — when the bronze match result is a
   0-0 tie (i.e. the match was not actually shot), both semifinal losers
   receive 3rd place (shared)
2. **Unique sub-ranking of same-round losers** — losers eliminated in the same
   round are ranked individually using tiebreaking criteria, instead of all
   sharing the same rank

---

## 2. Requirement 1 — No Bronze-Medal Match

### Rule

In some PZŁucz tournaments the bronze-medal match is not actually shot. When
this happens:

- The **gold-medal match** is still held (1st place, 2nd place)
- Both losers of the **1/2 final (semifinal)** are awarded **3rd place** (shared)
- No 4th place is assigned

### Detection

The bronze-medal match is considered **not held** when its result is a
**0-0 tie** — both athletes (or teams) have a score of 0 and set points of 0.
This means no arrows were actually shot in the bronze match.

No explicit configuration option is needed — the ranking engine detects this
condition automatically from the match data.

### Resulting placement

| Match / Round       | With bronze match | Without bronze match (0-0) |
| ------------------- | ----------------- | -------------------------- |
| Gold match winner   | 1st               | 1st                        |
| Gold match loser    | 2nd               | 2nd                        |
| Bronze match winner | 3rd               | _(not shot)_               |
| Bronze match loser  | 4th               | _(not shot)_               |
| Semifinal loser 1   | _(in bronze)_     | 3rd (shared)               |
| Semifinal loser 2   | _(in bronze)_     | 3rd (shared)               |

### Behavioural notes

- When the bronze match is a 0-0 tie, the next available rank after 3rd is 5th
  (following standard ranking convention where shared 3rd means no 4th)

---

## 3. Requirement 2 — Unique Places for Same-Round Losers

### Rule (§2.6.6)

All athletes eliminated in the **same round** of the bracket must receive
**unique individual places**, sub-ranked by the following criteria (in order)
(§2.6.6.2):

1. **Average arrow value in the last match** (shoot-off excluded) — the
   total match score divided by the number of arrows shot in the match
   (excluding any shoot-off arrows), higher is better
2. **Average arrow value in the shoot-off** — total shoot-off score divided
   by number of shoot-off arrows; if the match ended without a shoot-off,
   this value is 0, higher is better
3. **Qualification score** — total score from the qualification round,
   higher is better
4. **Same place** — only if all three criteria are identical do athletes
   share a position (practically impossible)

### Position ranges (§2.6.6.1)

| Eliminated in | Final positions |
| ------------- | --------------- |
| 1/4           | 5–8             |
| 1/8           | 9–16            |
| 1/16          | 17–32           |
| 1/24          | 33–56           |
| 1/48          | 57–104          |
| 1/32          | 33–64           |
| 1/64          | 65–128          |

Within each range, athletes are placed sequentially using the criteria in §2.6.6.2 above.

### Resulting placement example

**Standard 32-archer bracket (e.g., indoor) — with bronze match:**

| Round                  | Default placement | PZŁucz placement (unique)     |
| ---------------------- | ----------------- | ----------------------------- |
| Gold match winner      | 1                 | 1                             |
| Gold match loser       | 2                 | 2                             |
| Bronze match winner    | 3                 | 3                             |
| Bronze match loser     | 4                 | 4                             |
| 1/4 final losers (4)   | all 5 (shared)    | 5, 6, 7, 8                    |
| 1/8 final losers (8)   | all 9 (shared)    | 9, 10, 11, 12, 13, 14, 15, 16 |
| 1/16 final losers (16) | all 17 (shared)   | 17, 18, 19, …, 32             |

> Note: 1/2 final losers go to the bronze match — they get 3rd or 4th from
> that match, not from the semifinal.

**Standard 104-archer bracket (outdoor) — with bronze match:**

| Round                  | Default placement | PZŁucz placement (unique)     |
| ---------------------- | ----------------- | ----------------------------- |
| Gold match winner      | 1                 | 1                             |
| Gold match loser       | 2                 | 2                             |
| Bronze match winner    | 3                 | 3                             |
| Bronze match loser     | 4                 | 4                             |
| 1/4 final losers (4)   | all 5 (shared)    | 5, 6, 7, 8                    |
| 1/8 final losers (8)   | all 9 (shared)    | 9, 10, 11, 12, 13, 14, 15, 16 |
| 1/16 final losers (16) | all 17 (shared)   | 17, 18, …, 32                 |
| 1/24 final losers (24) | all 33 (shared)   | 33, 34, …, 56                 |
| 1/48 final losers (48) | all 57 (shared)   | 57, 58, …, 104                |

**No-bronze variant (0-0 tie in bronze match):**

| Round             | Placement     |
| ----------------- | ------------- |
| Gold match winner | 1             |
| Gold match loser  | 2             |
| 1/2 losers (2)    | 3, 3 (shared) |
| 1/4 losers (4)    | 5, 6, 7, 8    |
| 1/8 losers (8)    | 9, 10, …, 16  |
| …                 | …             |

> Both semifinal losers share 3rd place — they do NOT get 3 and 4 when
> there is no bronze match. Unique sub-ranking applies only from the
> quarterfinals downward.

### Sub-ranking applies to

- **Individual elimination brackets** — outdoor target and indoor formats
- **Team elimination brackets** — standard teams (3/4-member) and mixed teams
  (2-member)

> Field and 3D elimination brackets are **out of scope** for now.

---

## 4. Tiebreaking Detail (§2.6.6.2)

### 4.1 Individual Matches

For losers eliminated in the same round, compare in order:

| Priority | Criterion                           | How computed                                         |
| -------- | ----------------------------------- | ---------------------------------------------------- |
| 1        | Average arrow value in match        | Total match score ÷ arrows shot (shoot-off excluded) |
| 2        | Average arrow value in shoot-off    | Shoot-off total ÷ shoot-off arrows; 0 if no shoot-off |
| 3        | Qualification score                 | Total score from the qualification round             |
| 4        | Share position                      | Last resort (practically impossible)                 |

The criteria apply identically for set-system (R, B) and cumulative-system (C) events.
The "match score" in criterion 1 is always the cumulative arrow total, not set points —
the average is computed from `FinScore` (all arrows summed) divided by the number of
arrows actually shot, regardless of match format.

### 4.2 Team Matches

Same criteria apply, using:

- Team cumulative match score and team shoot-off score from `TeamFinals`
- Team qualification score (`TeScore` in `Teams`)

---

## 5. Session Structure

This feature is a **post-match ranking calculation** that runs after each
elimination phase completes. It does not change the session structure,
shooting order, or match format — only the ranking numbers assigned to losers.

---

## 6. Scoring & Tiebreaking Summary

The sub-ranking does **not** introduce new scoring. It reuses existing data:

- **Match score (`FinScore` / `TfScore`)** — cumulative arrow total, already
  recorded per match for all divisions
- **Arrow count** — derived from `FinArrowstring` or `FinSetPoints` (already
  recorded), with configured arrows-per-match as fallback
- **Shoot-off score (`FinTie` / `TfTie`)** and **shoot-off arrows
  (`FinTiebreak` / `TfTiebreak`)** — already recorded when a shoot-off occurs
- **Qualification score (`IndScore` / `TeScore`)** — already stored on
  `Individuals` / `Teams` from the qualification ranking step

No additional data collection is required.

---

## 7. Known Gaps

### ⚠ CUSTOM NEEDED — Unique placement of same-round losers

ianseo's default ranking engine (as of rev 114, aligned with WA rules) sorts
same-round losers by average arrow value in the match and average arrow value
in the shoot-off, but still assigns the **same rank** to all losers with
identical scores. The PZŁucz requirement for unique sequential places is
**not available out of the box**.

A custom ranking override is needed to:

1. Identify all losers in a given round
2. Sort by average match arrow value, then shoot-off average, then
   qualification score
3. Assign **sequential unique rank numbers** starting from the correct
   position — no two athletes share a place unless all three criteria
   are identical

### ⚠ CUSTOM NEEDED — Qualification score as third tiebreaker

ianseo natively handles criteria 1 (average match score) and 2 (average
shoot-off score) after the rev 114 update. It does not add a third
tiebreaker. PZŁucz §2.6.6.2 requires qualification score as the third
criterion, which the custom override must add.

### ⚠ CUSTOM NEEDED — No-bronze-medal-match handling

ianseo's default ranking always assumes a bronze match was played. When the
bronze match result is a 0-0 tie (match not actually shot), the default
engine assigns 3rd and 4th normally, which is incorrect. A custom override
must:

1. Detect a 0-0 tie in the bronze match (both athletes have score 0 and
   set points 0)
2. Assign both semifinal losers rank 3 (shared)
3. Skip rank 4 and continue unique ranking from 5

---

## 8. Resolved Questions

1. **Mixed teams:** ✅ Confirmed — mixed-team events follow the same
   sub-ranking rules as individual and standard team brackets.

2. **Field/3D elimination:** ⏭ Out of scope for now — Field and 3D
   elimination brackets are excluded from this feature. May be added later.

3. **Semifinal losers with no-bronze:** ✅ Confirmed — both semifinal losers
   get **shared 3rd** (not unique 3rd and 4th) when the bronze match is 0-0.
   Unique sub-ranking starts from the quarterfinals downward.

4. **Average vs total match score:** ✅ Confirmed — §2.6.6.2 uses average
   arrow value (score ÷ arrows), not raw total. This matters for set-system
   matches where different archers shoot different numbers of sets/arrows.
   Aligns with ianseo rev 114 / WA rules, with qualification score added as
   third tiebreaker.

---

## Verification Checklist

1. With bronze match **played** (normal): gold/bronze winners and losers get
   places 1, 2, 3, 4 as usual
2. With bronze match **0-0 tie** (not shot): gold match produces 1st and
   2nd; both semifinal losers get 3rd (shared); no 4th place assigned
3. Losers of the same 1/4-final round get unique places (5, 6, 7, 8)
   based on average arrow value — not all "5th"
4. Losers of the same 1/8-final round get unique places (9–16) — not
   all "9th"
5. Primary criterion: average arrow value in the match (total score ÷ arrows
   shot); higher average → better rank, for both set-system (R, B) and
   cumulative (C) events
6. Secondary criterion: average arrow value in the shoot-off; higher → better;
   0 when no shoot-off occurred
7. Tertiary criterion: qualification score; higher → better
8. When all three criteria are equal, athletes share a position (last resort)
9. Team brackets follow the same sub-ranking logic using team scores
10. Mixed-team brackets follow the same sub-ranking logic
11. Feature works across target archery and indoor PL tournament types
    (1440, 70m, Indoor — Field/3D excluded for now)
