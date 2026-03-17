# Proposal: Diploma Titles

## What

Extend the existing diplomas module to print a championship title line on diplomas for athletes finishing in places 1–3. The title is composed dynamically from configurable per-event parts and auto-detected tournament context.

**Example output on diploma:**

> i zdobywa tytuł Zespołowego Młodzieżowego Wicemistrza Polski na rok 2026

## Why

PZŁucz regulations (§1.6) require that championship titles be conferred at Mistrzostwa Polski and certain other competitions. The diploma is the primary physical record of that title. Administrators currently have no way to include this text without manual workarounds.

## How It Works

The title is composed from this template:

```
[Zespołowego] [prefix] [Mistrza|Wicemistrza|II Wicemistrza] [text] [w mikście] na rok [year]
```

- `Zespołowego` — auto-added for regular team events (not mixed)
- `prefix` — configured per event (e.g., `Młodzieżowego`, `Międzywojewódzkiego`, or empty)
- Place infix — derived from rank: `Mistrza` / `Wicemistrza` / `II Wicemistrza`
- `text` — configured per event (e.g., `Polski Juniorów`, `Ogólnopolskiej Olimpiady Młodzieży`)
- `w mikście` — auto-added for mixed team events
- `na rok [year]` — auto-extracted from tournament dates

Title fields come with **hardcoded defaults per age class and division**, matching standard PZŁucz competition titles (see design for full table). Admins override per event when needed.

A **global on/off toggle** in the diploma config controls whether titles appear at all — allowing the module to be used at non-championship tournaments without changes.

## Non-goals

- No gendered title forms (`Mistrzyni`/`Wicemistrzyni`) — always uses regulation masculine genitive form
- No automatic enforcement of §1.6.5 minimum-athlete thresholds — admin responsibility
- No changes to diploma layout for places 4+ (no title infix displayed)
- No changes outside `Diplomas/`

## Regulation Reference

§1.6.1–§1.6.3 — titles conferred per age class, per discipline (individual/team/mixed), per competition type.

## Agent Roles

- **Advisor** — spec lives in `openspec/specs/diplomas/spec.md` (no separate spec needed; design fully captures requirements)
- **Developer** — implements design below
- **Reviewer** — reviews before archiving
