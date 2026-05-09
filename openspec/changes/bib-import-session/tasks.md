## 1. Fun_BibImport.php — Logic changes

- [x] 1.1 Change `pl_bibimport_create_entry()` return type from `void` to `int`: call `safe_w_last_id()` inside the function after the INSERT and return the new `EnId`
- [x] 1.2 Add `pl_bibimport_create_qualification(int $enId, int $session): void` that does `INSERT INTO Qualifications (QuId, QuSession) VALUES ($enId, $session)`
- [x] 1.3 Add `$session` parameter to `pl_bibimport_run()` signature; pass it through to `pl_bibimport_create_qualification()` after each entry insert inside the transaction

## 2. BibImport.php — Session loading and guard

- [x] 2.1 After loading divisions, query Q sessions: `SELECT SesOrder, SesName FROM Session WHERE SesTournament=$tourId AND SesType='Q' ORDER BY SesOrder ASC`; store in `$sessions`
- [x] 2.2 Set `$sessionsEmpty = (empty($sessions))` and use it to conditionally show a "no sessions" warning banner and hide the form (same pattern as `$lookupEmpty`)

## 3. BibImport.php — Form and POST handling

- [x] 3.1 Add the session `<select name="session">` row to the form table, placed between the division row and the licence textarea; each `<option>` shows `{SesOrder} – {SesName}`
- [x] 3.2 In the POST block, read `$_POST['session']`, validate it exists in `$sessions` (check `SesOrder`), and reject with an error message if invalid — same pattern as the division validation
- [x] 3.3 Pass the validated `$selectedSession` (int) to `pl_bibimport_run()` as the new third argument
- [x] 3.4 Persist the selected session across POST/re-render so the dropdown re-selects the previously chosen session (same as `$selectedDivision`)
