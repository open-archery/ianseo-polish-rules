# Athlete Sync from PZŁucz API — Requirements (Draft)

**PZŁucz PDF not attached — cannot produce authoritative spec. Please attach the regulations PDF.**

## 1) Competition format summary

This feature is an administrative synchronization feature, not a new shooting round format.  
Its purpose is to keep the tournament athlete roster aligned with a federation source (PZŁucz API), while preserving competition setup already defined by the organizer.

Regulatory citation: pending PDF attachment.

## 2) Divisions

- Division assignment must remain consistent with tournament configuration already prepared by the organizer.
- If API data includes bow-style metadata, it must be translated into local division labels using an explicit mapping table approved by the organizer.
- No automatic creation of new division definitions during sync without operator approval.

## 3) Age classes

- Age class assignment must follow tournament rules already configured by the organizer.
- If date of birth is provided by API, it may be used to validate age-category eligibility.
- If API age-category and local age-category disagree, the case must be flagged for manual review before final acceptance.

## 4) Events

- Athlete records synchronized from API must include core entry data needed for competition participation:
  - unique athlete identity key,
  - personal identity fields,
  - club/organization affiliation,
  - sex/date of birth (if available),
  - event participation flags (individual/team/mixed where relevant).
- Session and target assignments are operational data and may be synchronized only when explicitly enabled by organizer policy.

## 5) Session structure

- Synchronization may run in two modes:
  - **full snapshot** (complete roster state),
  - **incremental update** (only changed athletes).
- System must support repeated synchronization before and during tournament preparation.
- Operator must be able to execute a pre-check run (no data commit) to review detected inserts, updates, conflicts, and removals.

## 6) Team rules

- Team affiliation data (club/country representation for team ranking contexts) must be synchronized only from trusted source fields.
- Team-related affiliation changes must be traceable in sync logs.
- If affiliation data is ambiguous or missing, athlete entry is synchronized but team linkage is flagged for manual correction.

## 7) Scoring & tiebreaking

- Synchronization feature must not alter scoring systems, ranking logic, or tiebreak procedures.
- Existing competition scoring/tiebreak setup remains the source of truth.

## 8) Known gaps

- ⚠ **CUSTOM NEEDED**: Secure connector to PZŁucz API (authentication, token refresh, transport hardening).
- ⚠ **CUSTOM NEEDED**: Deterministic field mapping layer between PZŁucz athlete payload and local roster fields.
- ⚠ **CUSTOM NEEDED**: Idempotent sync engine (same input should not produce duplicate athletes or unstable updates).
- ⚠ **CUSTOM NEEDED**: Conflict handling policy for mismatched identity data (name/date of birth/club/category differences).
- ⚠ **CUSTOM NEEDED**: Operational safety controls (dry-run preview, confirmation before deletions, full change summary).
- ⚠ **CUSTOM NEEDED**: Audit trail of synchronization operations (who ran sync, when, source snapshot, applied changes).
- ⚠ **CUSTOM NEEDED**: Rollback/recovery procedure for incorrect source payloads.
- ⚠ **CUSTOM NEEDED**: Scheduler/retry strategy for temporary API outages.

## 9) Open questions

1. What is the authoritative unique key in PZŁucz API for athlete identity across seasons?
2. Is the API contract stable and versioned, and how are breaking changes announced?
3. What is the expected data freshness (near-real-time vs periodic batch)?
4. Should missing athletes in source payload be removed automatically, or only after operator confirmation?
5. Which fields are federation-authoritative vs organizer-overridable locally?
6. Are there legal/privacy constraints for syncing personal data fields into local tournament systems?
7. Should synchronization be permitted after qualification scoring has started, or frozen at a defined milestone?

---

### Draft status

This is a future-planning draft based on currently discovered synchronization capabilities and integration intent.  
For authoritative federation-rule references and final requirement confirmation, attach the PZŁucz regulations PDF and update this document.

---

### Sportzona Endpoint

All athletes can be downloaded from the PZŁucz API endpoint. Data is not secured behind authentication, and can be accessed by any client. The endpoint supports pagination, but the entire roster can be retrieved in a single request by setting a sufficiently large page size.

Example curl request to download all archery athletes:

```
curl 'https://sportzona.pl/wsx/players/list/discipline' \
  -H 'Content-Type: application/json;charset=UTF-8' \
  --data-raw '{"mainList":true,"discipline":"archery","pageSize":10000,"page":1,"count":true,"fltLName":"","fltBirth":"","fltLicence":"","fltClub":"","fltRR":"","fltRP":"","fltRS":"","isPublicList":true}'
```
