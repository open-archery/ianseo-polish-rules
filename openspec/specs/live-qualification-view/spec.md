# Live Qualification View Specification

## Purpose

Give tournament staff a read-only, auto-refreshing view of a qualification round in progress — current score and arrows shot per athlete, grouped by physical target, with targets falling behind their peers visibly flagged.

## Requirements

### Requirement: Session and distance selection
The system SHALL let the user select one qualification session and one distance of the tournament (`1..Tournament.ToNumDist`), and SHALL display data scoped to exactly that session+distance pair.

#### Scenario: No selection made yet
- **WHEN** the user opens the live view and has not yet chosen a session and distance
- **THEN** the system shows the selection controls and no result rows

#### Scenario: Selection changed
- **WHEN** the user changes the session or distance selection
- **THEN** the displayed rows and flags update to reflect only entries for the newly selected session+distance, without a full page reload

### Requirement: Per-target progress display
For the selected session and distance, the system SHALL display every active entry (`EnStatus <= 1`) grouped by its physical target number (`QuTarget`), ordered by target number and then by target letter (`QuLetter`, A/B/C/D). Each row SHALL show the athlete's name, current running score for the selected distance, and arrows shot so far for the selected distance.

#### Scenario: Multiple athletes share a target
- **WHEN** target 12 has athletes assigned to letters A, B, and D for the selected session
- **THEN** the three athletes appear together under target 12, in the order A, B, D

#### Scenario: Withdrawn entry excluded
- **WHEN** an entry's status is DNS, DNF, or withdrawn (`EnStatus > 1`)
- **THEN** that entry does not appear in the display and is not counted toward any target's or the round's progress

### Requirement: Arrow count derived from stored arrow string
The system SHALL derive "arrows shot so far" for an entry's distance from the stored, fixed-width, space-padded arrow string for that distance (`QuD{n}ArrowString`), counting only the non-space (already-shot) positions. The system SHALL NOT introduce any new storage for arrow counts or scores.

#### Scenario: Distance partially shot
- **WHEN** an entry's arrow string for the selected distance has 24 recorded arrow characters followed by padding for the remaining unshot arrows
- **THEN** the displayed arrows-shot count for that entry is 24

#### Scenario: Distance not started
- **WHEN** an entry has no arrows recorded yet for the selected distance
- **THEN** the displayed arrows-shot count for that entry is 0 and its score is 0

### Requirement: Peer-lag lacking-results flag
The system SHALL flag an entry as "lacking results" when its arrows-shot count for the selected session+distance trails the highest arrows-shot count among active entries in that same session+distance by a full end's worth of arrows or more (`DistanceInformation.DiArrows` for that session+distance). A target SHALL be visually flagged (counted as a "tarcza z zaległościami" in the summary) if any entry assigned to it is flagged.

#### Scenario: One target stalled while others progress
- **WHEN** the highest arrows-shot count across the selected session+distance is 30, the round shoots 6 arrows per end, and an entry on target 7 has 18 arrows shot
- **THEN** that entry (and target 7) is flagged as lacking results, since the 12-arrow gap is at least one end (6 arrows)

#### Scenario: A full end behind is already flagged
- **WHEN** the highest arrows-shot count is 30, the round shoots 6 arrows per end, and an entry has 24 arrows shot
- **THEN** that entry (and its target) is flagged as lacking results, since the 6-arrow gap equals a full end

#### Scenario: Normal within-target stagger is not flagged
- **WHEN** the highest arrows-shot count is 30, the round shoots 6 arrows per end, and an entry has 25 arrows shot
- **THEN** that entry is not flagged, since the 5-arrow gap is less than one end

#### Scenario: Everyone at the same point
- **WHEN** every active entry in the selected session+distance has the same arrows-shot count
- **THEN** no entry or target is flagged

### Requirement: Round-status entries are never flagged as behind
An entry recorded as DNS, DNF, DSQ, or DQB for the round (`Qualifications.QuIrmType != 0`) SHALL display its status instead of the lacking-results flag, and SHALL be excluded from the peer-lag comparison pool (it SHALL NOT be counted when determining the session+distance's current maximum arrows-shot).

#### Scenario: DNS entry does not drag down or trigger a flag
- **WHEN** an entry is marked DNS for the round and every other active entry on its target is progressing normally
- **THEN** the DNS entry displays "DNS" in its status and is not highlighted as lacking results, and its target is not flagged solely because of it

#### Scenario: DNS entry is not counted as a peer for others
- **WHEN** a DNS entry has 0 arrows shot and every other active entry in the session+distance has shot at least one full end
- **THEN** the DNS entry's 0 does not by itself change any other entry's lacking-results flag beyond what the other active entries' own progress already determines

### Requirement: Score-without-arrows entries are marked as a data gap, not behind
An active entry with a nonzero score for the selected distance but zero arrows shot and no round status (`QuIrmType = 0`) SHALL be marked as a data gap rather than flagged as lacking results, since this combination only arises when the distance was scored through a path other than arrow-by-arrow entry (e.g. bulk/manual entry) — never from a live phone sync, which always writes the arrow string alongside the score.

#### Scenario: Bulk-scored distance is not mistaken for a stalled target
- **WHEN** an active entry has a nonzero score for the selected distance, zero arrows shot, and no DNS/DNF/DSQ/DQB status
- **THEN** the entry is marked as a data gap, distinct from both "on pace" and "lacking results", and is excluded from the peer-lag comparison pool

### Requirement: Flagged-target summary count
The system SHALL display, alongside the per-target display, a count of how many targets in the selected session+distance are currently flagged as lacking results, out of the total number of targets shown, updated on every refresh.

#### Scenario: Summary reflects current flags
- **WHEN** 2 of the 30 targets in the selected session+distance have at least one entry flagged as lacking results
- **THEN** the summary shows 2 out of 30 targets flagged

### Requirement: Auto-refresh without page reload
The system SHALL periodically re-fetch the current session+distance's data in the background and update the displayed rows and flags without a full page navigation, so newly arrived phone results appear without user action.

#### Scenario: New result arrives from a phone
- **WHEN** a phone submits new arrow results for an entry currently shown on the live view
- **THEN** within one refresh cycle the entry's displayed score and arrow count update to reflect the new result, and its lacking-results flag is re-evaluated

### Requirement: Read-only access
The system SHALL NOT provide any control, link, or endpoint on this view capable of creating, editing, or deleting a score, entry, or any other tournament data. Access SHALL require at least read-only qualification permission.

#### Scenario: User without qualification access
- **WHEN** a user lacking read-only qualification ACL requests the live view or its data endpoint
- **THEN** access is denied and no tournament data is returned

#### Scenario: No edit affordance present
- **WHEN** a user with access views any row on the live view
- **THEN** no input field, edit link, or write action is available for that row
