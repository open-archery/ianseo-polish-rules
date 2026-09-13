## Purpose

Give tournament staff a read-only, auto-refreshing view of a qualification round in progress — current score and arrows shot per athlete, grouped by physical target, with targets falling behind their peers visibly flagged.

## ADDED Requirements

### Requirement: Session and distance selection
The system SHALL let the user select one qualification session and one distance configured for that session (as defined in `DistanceInformation`), and SHALL display data scoped to exactly that session+distance pair.

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
The system SHALL flag an entry as "lacking results" when its arrows-shot count for the selected session+distance is more than one end's worth of arrows (`DistanceInformation.DiArrows` for that session+distance) behind the highest arrows-shot count among active entries in that same session+distance. A target SHALL be visually flagged if any entry assigned to it is flagged.

#### Scenario: One target stalled while others progress
- **WHEN** the highest arrows-shot count across the selected session+distance is 30, the round shoots 6 arrows per end, and an entry on target 7 has 18 arrows shot
- **THEN** that entry (and target 7) is flagged as lacking results, since the 12-arrow gap exceeds one end (6 arrows)

#### Scenario: Normal within-target stagger is not flagged
- **WHEN** the highest arrows-shot count is 30, the round shoots 6 arrows per end, and an entry has 25 arrows shot
- **THEN** that entry is not flagged, since the 5-arrow gap does not exceed one end

#### Scenario: Everyone at the same point
- **WHEN** every active entry in the selected session+distance has the same arrows-shot count
- **THEN** no entry or target is flagged

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
