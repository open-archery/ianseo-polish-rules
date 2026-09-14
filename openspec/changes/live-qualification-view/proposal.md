## Why

Tournament staff run scoring on phones (Scorekeepr Lite) that push results into `Qualifications` as the round is shot. There is currently no way to watch a qualification round in progress from the tournament office: the only built-in tool, `Qualification/CheckTargetUpdate.php`, is session-only (no distance axis), shows a 3-color dot per target based on a manually-typed cutoff time, and carries no athlete name, score, or arrow count. Staff running a live round on the field (e.g. tournament XVMJK) need a read-only screen that shows exactly who has shot how much, and which target has quietly stopped sending results, without risking an accidental edit to a live score.

## What Changes

- New PL admin page: pick a qualification session + distance, see every entry on it grouped by physical target (A/B/C/D letters together), each row showing running score and arrows shot so far for that distance.
- Arrow count is read from `QuD{n}ArrowString` (space-padded, one char per arrow) — no new write path, no new score storage.
- Auto-refresh via polling (interval matches core's own `CheckTargetUpdate` cadence), no page reload.
- "Lacking results" flag: an entry (and its target) is flagged when its arrows-shot count trails the current max across the selected session+distance by one full end or more (`DistanceInformation.DiArrows`), among active entries (`EnStatus <= 1`) only.
- Strictly read-only: reuses `checkFullACL(AclQualification, '', AclReadOnly)`, same guard as core's own live-status page. No form fields, no write endpoints.

## Non-goals

- No editing, no navigating into a scorecard from this screen.
- No historical/replay view — current live state only.
- No change to how phones write scores (ISK-NG sync path untouched).
- No kiosk/TV auto-cycling mode — this is a filterable admin-dashboard page (session/distance dropdowns stay visible), per explore-mode decision.

## Capabilities

### New Capabilities
- `live-qualification-view`: read-only live monitoring of a qualification session+distance — per-target athlete progress (score, arrows shot) and a peer-lag "lacking results" flag, auto-refreshing.

### Modified Capabilities
_(none — no existing spec's requirements change)_

## Impact

- New files under `Modules/Sets/PL/` only (new subdirectory, e.g. `Live/`); no ianseo core files touched.
- Reads `Qualifications`, `Entries`, `Session`, `DistanceInformation` — no schema changes, no new tables.
- New menu entry under the `QUAL` menu key in `menu.php`, guarded the existing `TourLocRule=='PL'` way.
- Advisor role: not needed — this is operational tooling, not a PZŁucz competition rule (no § reference applies). Spec is written directly against the behavior above. Developer role produces `design.md` (page/query/polling shape) and the implementation.
