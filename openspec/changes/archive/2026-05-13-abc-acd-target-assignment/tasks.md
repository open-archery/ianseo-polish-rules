## 1. Entry Point and Menu

- [x] 1.1 Create directory `Modules/Sets/PL/Targets/`
- [x] 1.2 Create `Modules/Sets/PL/Targets/SetTargetABCACD.php` with ianseo bootstrap, `CheckTourSession(true)`, ACL check, and head/tail template include
- [x] 1.3 Add menu entry `'Rozstaw. tarcze ABC/ACD|...'` under `$ret['PART']` in `menu.php`

## 2. UI Form

- [x] 2.1 Render session dropdown (use `GetSessions('Q')`) and repopulate on submit
- [x] 2.2 Render class text input (e.g. `RMO`) and target-from / target-to inputs; repopulate on submit
- [x] 2.3 Render "Zapisz" checkbox for save mode vs preview mode
- [x] 2.4 Render erase button that clears all assignments for the selected class + session

## 3. Session Validation

- [x] 3.1 After session is selected, load `SesAth4Target` from the session record
- [x] 3.2 If `SesAth4Target != 4`, display a Polish-language warning and abort further processing

## 4. Slot Builder

- [x] 4.1 Implement `pl_abc_acd_build_slots(int $from, int $to): array` — returns ordered list of slot strings (e.g. `['1A','1B','1C','2A','2C','2D',...]`) using odd→ABC / even→ACD parity on the absolute boss number

## 5. Athlete Query and Grouping

- [x] 5.1 Query athletes matching the selected class + session from `Entries` ⋈ `Qualifications` ⋈ `Countries` ⋈ `Divisions` ⋈ `Classes`, randomising row order within each club (`ORDER BY EnCountry, RAND()`)
- [x] 5.2 Group results into `$clubs[EnCountry][] = EnId` and compute per-club counts
- [x] 5.3 Sort clubs descending by count (largest first)

## 6. Assignment Algorithm

- [x] 6.1 Implement the column-jump assignment loop: for each club, start at first available slot index, jump by 3 (boss width) for each subsequent athlete in the club
- [x] 6.2 At each club boundary, advance to the next slot whose letter is `A`, marking skipped slots as unavailable
- [x] 6.3 Collect unassigned athletes (overflow beyond available slots) into a separate list

## 7. Erase Logic

- [x] 7.1 On save or erase action: `UPDATE Qualifications INNER JOIN Entries ON QuId=EnId SET QuTarget=0, QuLetter='', QuBacknoPrinted=0 WHERE EnTournament=? AND QuSession=? AND CONCAT(TRIM(EnDivision),TRIM(EnClass)) LIKE ?`
- [x] 7.2 Touch `Entries.EnMainInfoUpdate` and `Entries.EnTimestamp` for affected rows after erase

## 8. Save Logic

- [x] 8.1 When save mode is active, write each assignment: `UPDATE Qualifications SET QuTarget=?, QuLetter=? WHERE QuId=?`
- [x] 8.2 Touch `Entries.EnMainInfoUpdate`, `Entries.EnTimestamp`, and reset `QuBacknoPrinted=0` for each assigned athlete

## 9. Preview Output

- [x] 9.1 Render the assignment table grouped by boss: boss number as row separator, then each slot → club code + athlete name
- [x] 9.2 Display unassigned athlete list (name + club) at the bottom if any overflow occurred
