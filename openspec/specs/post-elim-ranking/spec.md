# Post-Elimination Ranking — Feature Requirements

> **Feature:** Unique placement of athletes/teams after elimination rounds
> **Source:** PZŁucz regulations §2.6.5 (Post-Elimination Classification)
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

### Rule (§2.6.5)

All athletes eliminated in the **same round** of the bracket must receive
**unique individual places**, sub-ranked by the following criteria (in order):

1. **Match score** — higher match result is ranked higher:
   - For set-system matches (Recurve, Barebow): higher set-point total
   - For cumulative-system matches (Compound): higher cumulative score
2. **Qualification ranking** — if match scores are equal, the athlete with the
   better (lower) qualification rank is placed higher
3. **Same place** — only if **both** match score and qualification rank are
   identical do the athletes share a position (this is extremely rare and
   effectively impossible since qualification ranks are themselves unique)

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
| 1/48 final losers (48) | all 49 (shared)   | 57, 58, …, 104                |

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

## 4. Tiebreaking Detail

### 4.1 Individual Matches

For losers eliminated in the same round, compare:

| Priority | Criterion          | Set system (R, B)                    | Cumulative system (C)    |
| -------- | ------------------ | ------------------------------------ | ------------------------ |
| 1        | Match score        | Set-point total (0–6)                | Cumulative arrow total   |
| 2        | Qualification rank | Lower qual rank = better             | Lower qual rank = better |
| 3        | Share position     | Last resort (practically impossible) | Last resort              |

### 4.2 Team Matches

Same logic applies, using:

- Team match set-point total or team cumulative score
- Team qualification ranking

---

## 5. Session Structure

This feature is a **post-match ranking calculation** that runs after each
elimination phase completes. It does not change the session structure,
shooting order, or match format — only the ranking numbers assigned to losers.

---

## 6. Scoring & Tiebreaking Summary

The sub-ranking does **not** introduce new scoring. It reuses existing data:

- **Set points** — already recorded per match (for R, B divisions)
- **Cumulative score** — already recorded per match (for C division)
- **Qualification rank** — already recorded per athlete/team

No additional data collection is required.

---

## 7. Known Gaps

### ⚠ CUSTOM NEEDED — Unique placement of same-round losers

ianseo's default ranking engine assigns the same rank to all losers of the
same elimination round (e.g., all 1/8-final losers get 9th place). The PZŁucz
requirement for unique places with sub-ranking by match score and
qualification rank is **not available out of the box**.

A custom ranking override is needed to:

1. Identify all losers in a given round
2. Sort them by match score (set points or cumulative), then by qualification
   rank
3. Assign sequential unique rank numbers starting from the correct position

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

---

## Verification Checklist

1. With bronze match **played** (normal): gold/bronze winners and losers get
   places 1, 2, 3, 4 as usual
2. With bronze match **0-0 tie** (not shot): gold match produces 1st and
   2nd; both semifinal losers get 3rd (shared); no 4th place assigned
3. Losers of the same 1/4-final round get unique places (e.g., 5, 6, 7, 8)
   based on match score then qualification rank — not all "5th"
4. Losers of the same 1/8-final round get unique places (e.g., 9–16) — not
   all "9th"
5. Set-system matches (R, B): sub-ranking uses set-point total; higher set
   points → better rank
6. Cumulative matches (C): sub-ranking uses cumulative score; higher score →
   better rank
7. When match scores are equal, lower qualification rank → better place
8. Team brackets follow the same sub-ranking logic
9. Mixed-team brackets follow the same sub-ranking logic
10. Feature works across target archery and indoor PL tournament types
    (1440, 70m, Indoor — Field/3D excluded for now)
