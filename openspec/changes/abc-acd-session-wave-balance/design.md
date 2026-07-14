## Context

`Targets/Fun_SetTargetABCACD.php` assigns one division/class at a time to a target range using the ABC/ACD staggered pattern (`pl_abc_acd_build_slots`) and a club-column-priority algorithm (`pl_abc_acd_assign`): the largest club in the class fills column A end-to-end, the second-largest fills column C, and clubs 2+ fit into whichever column (remaining A → remaining C → B → D) has room. Letters A/B belong to "wave1" (shoot first) and C/D to "wave2" (shoot second) — see design rationale in the archived `abc-acd-target-assignment` change ("each boss has one shooting wave with a solo archer").

Organisers run this tool once per class (e.g. `RMO`, then `RWO`, then `CMO`, ...), each targeting the same or a disjoint boss range within one `QuSession`. Because each run picks columns independently, a club that is the largest in several classes shot in the same session always lands on column A (wave1) in every one of those runs — the cross-class analogue of PZŁucz §2.5.1.5 ("z jednego klubu... różne kolejki, jeśli to możliwe").

## Goals / Non-Goals

**Goals:**
- When assigning a class, bias which column (A vs C) a club's block starts in using that club's already-*saved* wave1/wave2 tally from other classes in the same `QuSession`.
- Preserve today's behavior exactly when there is no prior history (first class run in a session, or a club absent from earlier runs) — same defaults, same test expectations.
- Preserve today's behavior for a club occupying one column entirely within a single run — no intra-run splitting.

**Non-Goals:**
- Cross-session balancing (different `QuSession` values never interact — different simultaneous-shooting groups).
- Accounting for unsaved preview state from other classes — only committed (`QuTarget!=0`) rows count.
- Guaranteed balance — best-effort only, matching §2.5.1.5's "jeśli to możliwe".
- New DB tables/columns — reuses `Qualifications`/`Entries` exactly as today.

## Decisions

### Tally source: saved assignments only, computed on demand
`pl_abc_acd_session_wave_tally(int $tourId, int $sesOrder, string $excludeEvent): array` runs a read-only query joining `Qualifications`+`Entries` (same shape as `pl_abc_acd_load_athletes`), filtered to `EnTournament`, `QuSession`, `QuTarget!=0`, and `CONCAT(TRIM(EnDivision),TRIM(EnClass)) NOT LIKE excludeEvent`. Groups by `EnCountry`, counts `QuLetter IN ('A','B')` as wave1 and `QuLetter IN ('C','D')` as wave2. Returns `club_code => ['wave1' => int, 'wave2' => int]`.

**Alternative considered:** a persistent per-session/per-club counter (new PL-prefixed table), updated incrementally as classes are saved. Rejected — `QuTarget`/`QuLetter` is already the single source of truth for what's assigned; a derived counter can drift if assignments are erased/edited outside this tool (e.g. ianseo's native target page), and the module's convention is to avoid new tables when existing data answers the question.

**Consequence:** only *saved* class assignments count. Previewing several classes without saving produces no bias between them — organisers must save class N before the bias helps for class N+1. This matches the tool's existing erase-then-save-per-class workflow and needs no special-casing.

### Bias applies only at column-choice points, generalized from the existing rank rule
Today: `rank0 → column A`, `rank1 → column C` (hardcoded), clubs 2+ search `remaining A → remaining C → B → D` (hardcoded order). This becomes:

- Compute `needA(club) = tally[club]['wave2'] - tally[club]['wave1']` (positive = club is wave2-heavy this session so far, wants A now; negative = wave1-heavy, wants C now; 0/missing = no history).
- **Single club present:** use column A unless `needA(club0) < 0`, in which case use column C instead. (Generalizes the old hardcoded "always A".)
- **Two or more clubs:** club0 and club1 get columns A and C; swap the default (`club0→A, club1→C`) only if `needA(club1) > needA(club0)` (strict >, so ties keep today's rank-order default).
- **Clubs 2+ (overflow, "first column that fits"):** same `needA` sign decides whether that club's search tries `remaining A → remaining C` or `remaining C → remaining A` first; `B → D` fallback order is untouched (no real choice there — B/D are physically fixed leftover slots per boss, not an assignable pool).

**Alternative considered:** always fully alternate every appearance regardless of magnitude (e.g. round-robin every run). Rejected — that would fight the "one club can own a column within a single run" behavior confirmed as correct, and would flip column assignment on a coin-flip-sized imbalance instead of a proportional one.

**Alternative considered:** weight by imbalance magnitude relative to club size. Rejected as unnecessary complexity — sign-only bias already satisfies "best effort" and keeps the change small and testable; classes are typically similar enough in size that sign flips align with meaningful imbalance in practice.

### Function signature: additive, backward-compatible
`pl_abc_acd_assign(array $clubs, array $slots, array $waveTally = [])` — new third parameter, defaults to `[]` so existing call sites and tests (which pass no tally) get `needA = 0` for every club and reproduce today's exact rank-order behavior unchanged.

`SetTargetABCACD.php` calls `pl_abc_acd_session_wave_tally($tourId, $sesOrder, $event)` right before `pl_abc_acd_assign(...)` and passes the result through.

## Risks / Trade-offs

- **Order-dependency**: whichever class an organiser assigns first in a session "sets" the club's column with no bias (no history yet); later classes correct for it. The overall session balance is only as good as the order classes happen to be run in. → Accepted; matches the regulation's "if possible" framing, and organisers naturally run all classes of a session before moving to scoring anyway.
- **Preview-without-save blind spot**: repeatedly previewing multiple classes without saving any of them shows no bias between them, which could look like the feature "isn't working" in a dry-run. → Mitigate by documenting (in the spec scenario, not new UI copy) that the tally reflects saved state only, consistent with how `pl_abc_acd_load_athletes` already only sees saved `QuSession` membership.
- **Query cost**: one extra read query per class run, scanning the session's already-assigned rows. → Negligible; same order of magnitude as `pl_abc_acd_load_athletes`, no new indexes needed (`QuSession`/`EnTournament` already filtered elsewhere).

## Files

| Action | Path |
|--------|------|
| Modify | `Modules/Sets/PL/Targets/Fun_SetTargetABCACD.php` |
| Modify | `Modules/Sets/PL/Targets/SetTargetABCACD.php` |
| Modify | `Modules/Sets/PL/Targets/SetTargetABCACDTest.php` |
