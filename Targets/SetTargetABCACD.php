<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
CheckTourSession(true);
checkFullACL(AclParticipants, 'pTarget', AclReadWrite);
require_once('Common/Fun_Sessions.inc.php');

$tourId  = (int)$_SESSION['TourId'];
$sessions = GetSessions('Q');

// ─── Erase action ─────────────────────────────────────────────────────────────
if (isset($_REQUEST['Erase'])
    && isset($_REQUEST['Session'])
    && isset($_REQUEST['Event'])
    && (int)$_REQUEST['Session'] >= 1
    && preg_match('/^[0-9A-Z%_]+$/i', $_REQUEST['Event'])
) {
    $sesOrder = (int)$_REQUEST['Session'];
    $event    = $_REQUEST['Event'];
    $where    = "EnTournament=" . StrSafe_DB($tourId)
              . " AND QuSession=" . StrSafe_DB($sesOrder)
              . " AND CONCAT(TRIM(EnDivision),TRIM(EnClass)) LIKE " . StrSafe_DB($event);

    safe_w_sql(
        "UPDATE Entries INNER JOIN Qualifications ON EnId=QuId"
        . " SET EnTimestamp='" . date('Y-m-d H:i:s') . "',"
        . "     EnMainInfoUpdate='" . date('Y-m-d H:i:s') . "'"
        . " WHERE QuTarget!=0 AND " . $where
    );
    safe_w_sql(
        "UPDATE Qualifications INNER JOIN Entries ON QuId=EnId"
        . " SET QuTarget=0, QuLetter='', QuBacknoPrinted=0"
        . " WHERE " . $where
    );
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$PAGE_TITLE = 'Rozstawianie tarcz ABC/ACD';
include('Common/Templates/head.php');
?>
<form name="Frm" method="GET" action="">
<table class="Tabella">
<tr><th class="Title" colspan="7"><?php echo htmlspecialchars($PAGE_TITLE); ?></th></tr>
<tr class="Divider"><td colspan="7"></td></tr>
<tr>
  <td class="Center">Sesja</td>
  <td class="Center">Klasa</td>
  <td class="Center">Tarcza od</td>
  <td class="Center">Tarcza do</td>
  <td class="Center">Zapisz</td>
  <td class="Center" colspan="2">&nbsp;</td>
</tr>
<tr>
  <td class="Center">
    <select name="Session">
      <option value="">---</option>
      <?php foreach ($sessions as $s): ?>
      <option value="<?= (int)$s->SesOrder ?>"
        <?= (isset($_REQUEST['Session']) && (int)$_REQUEST['Session'] === (int)$s->SesOrder) ? ' selected' : '' ?>>
        <?= htmlspecialchars($s->Descr) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </td>
  <td class="Center">
    <input type="text" name="Event" maxlength="10" size="8"
           value="<?= htmlspecialchars($_REQUEST['Event'] ?? '') ?>">
  </td>
  <td class="Center">
    <input type="text" name="TgtFrom" maxlength="4" size="5"
           value="<?= htmlspecialchars($_REQUEST['TgtFrom'] ?? '') ?>">
  </td>
  <td class="Center">
    <input type="text" name="TgtTo" maxlength="4" size="5"
           value="<?= htmlspecialchars($_REQUEST['TgtTo'] ?? '') ?>">
  </td>
  <td class="Center">
    <input type="checkbox" name="DoAssign" value="1"
           <?= !empty($_REQUEST['DoAssign']) ? 'checked' : '' ?>>
  </td>
  <td class="Center"><input type="submit" value="OK"></td>
  <td class="Center"><input type="submit" name="Erase" value="Usuń rozstawienie"></td>
</tr>
</table>
</form>
<?php

// ─── Validate inputs ───────────────────────────────────────────────────────────
$sesOrder = isset($_REQUEST['Session']) ? (int)$_REQUEST['Session'] : 0;
$event    = (isset($_REQUEST['Event']) && preg_match('/^[0-9A-Z%_]+$/i', $_REQUEST['Event']))
            ? $_REQUEST['Event'] : '';
$tgtFrom  = isset($_REQUEST['TgtFrom']) ? (int)$_REQUEST['TgtFrom'] : 0;
$tgtTo    = isset($_REQUEST['TgtTo'])   ? (int)$_REQUEST['TgtTo']   : 0;

if ($sesOrder >= 1 && $event !== '' && $tgtFrom >= 1 && $tgtTo >= $tgtFrom) {

    // ─── Task 3.1–3.2: validate SesAth4Target ────────────────────────────────
    $sesDetail = GetSessions(null, false, [$sesOrder . '_Q']);

    if (empty($sesDetail)) {
        echo '<p style="color:red"><strong>Błąd: nie znaleziono wybranej sesji.</strong></p>';
    } elseif ((int)$sesDetail[0]->SesAth4Target !== 4) {
        $actual = (int)$sesDetail[0]->SesAth4Target;
        echo '<p style="color:red"><strong>Uwaga: Sesja musi być skonfigurowana z 4 zawodnikami'
           . ' na tarczę (SesAth4Target=4). Aktualna wartość: ' . $actual . '.'
           . ' Zmień ustawienie sesji przed rozstawieniem ABC/ACD.</strong></p>';
    } else {

        // ─── Task 4.1: build slot list ────────────────────────────────────────
        $slots = pl_abc_acd_build_slots($tgtFrom, $tgtTo);

        // ─── Tasks 5.1–5.3: query and group athletes ──────────────────────────
        $q = safe_r_sql(
            "SELECT EnId, EnFirstName, EnName, EnDivision, EnClass, CoCode EnCountry, CoName EnClubName"
            . " FROM Entries"
            . " INNER JOIN Countries ON EnCountry=CoId"
            . " INNER JOIN Qualifications ON EnId=QuId"
            . " INNER JOIN Divisions ON EnDivision=DivId AND EnTournament=DivTournament AND DivAthlete=1"
            . " INNER JOIN Classes   ON EnClass=ClId    AND EnTournament=ClTournament  AND ClAthlete=1"
            . " WHERE EnTournament=" . StrSafe_DB($tourId)
            . "   AND CONCAT(TRIM(EnDivision),TRIM(EnClass)) LIKE " . StrSafe_DB($event)
            . "   AND QuSession=" . StrSafe_DB($sesOrder)
            . " ORDER BY EnCountry, RAND()"
        );

        $clubCounts   = [];
        $clubAthletes = [];
        while ($r = safe_fetch($q)) {
            $code = $r->EnCountry;
            $clubAthletes[$code][] = [
                'id'       => (int)$r->EnId,
                'name'     => trim($r->EnName . ' ' . $r->EnFirstName),
                'club'     => $code,
                'clubName' => $r->EnClubName,
            ];
            $clubCounts[$code] = ($clubCounts[$code] ?? 0) + 1;
        }
        safe_free_result($q);

        arsort($clubCounts); // largest club first

        $palette    = [
            '#ffd6d6','#d6f0ff','#d6ffd6','#fff3d6','#f0d6ff','#d6ffee',
            '#ffd6f0','#e0e0ff','#ffeed6','#d6e8ff','#ffebd6','#d6ffe8',
            '#ffb3c1','#b3d4ff','#b3ffcc','#ffe6b3','#e0b3ff','#b3ffe6',
            '#ffb3e8','#c4c4ff','#ffd4b3','#b3c9ff','#fff0b3','#b3ffdb',
            '#f9c0c0','#c0ddf9','#c0f9d4','#f9f0c0','#e8c0f9','#c0f9ea',
            '#f9c0e4','#d4d4f9','#f9e4c0','#c0d4f9','#f9f4c0','#c0f9e4',
        ];
        $clubColors = [];
        $clubNames  = [];
        foreach (array_keys($clubCounts) as $i => $code) {
            $clubColors[$code] = $palette[$i % count($palette)];
            $clubNames[$code]  = $clubAthletes[$code][0]['clubName'] ?? $code;
        }

        $orderedClubs = [];
        foreach (array_keys($clubCounts) as $code) {
            $orderedClubs[$code] = $clubAthletes[$code];
        }

        // ─── Tasks 6.1–6.3: run assignment algorithm ──────────────────────────
        [$assignments, $unassigned] = pl_abc_acd_assign($orderedClubs, $slots);

        // ─── Tasks 7.1–7.2 + 8.1–8.2: erase and save ─────────────────────────
        if (!empty($_REQUEST['DoAssign'])) {
            $where = "EnTournament=" . StrSafe_DB($tourId)
                   . " AND QuSession=" . StrSafe_DB($sesOrder)
                   . " AND CONCAT(TRIM(EnDivision),TRIM(EnClass)) LIKE " . StrSafe_DB($event);

            // Erase: touch entries timestamps for rows that had an assignment
            safe_w_sql(
                "UPDATE Entries INNER JOIN Qualifications ON EnId=QuId"
                . " SET EnTimestamp='" . date('Y-m-d H:i:s') . "',"
                . "     EnMainInfoUpdate='" . date('Y-m-d H:i:s') . "'"
                . " WHERE QuTarget!=0 AND " . $where
            );
            // Erase: clear qualification slot fields
            safe_w_sql(
                "UPDATE Qualifications INNER JOIN Entries ON QuId=EnId"
                . " SET QuTarget=0, QuLetter='', QuBacknoPrinted=0"
                . " WHERE " . $where
            );

            // Write new assignments
            $now = date('Y-m-d H:i:s');
            foreach ($assignments as $slot => $athlete) {
                $tgtNum = (int)$slot;
                $letter = strtoupper(substr($slot, -1));
                safe_w_sql(
                    "UPDATE Qualifications"
                    . " SET QuTimestamp=QuTimestamp, QuTarget=" . $tgtNum . ", QuLetter='" . $letter . "'"
                    . " WHERE QuId=" . $athlete['id']
                );
                safe_w_sql(
                    "UPDATE Entries"
                    . " SET EnTimestamp='" . $now . "', EnMainInfoUpdate='" . $now . "'"
                    . " WHERE EnId=" . $athlete['id']
                );
                safe_w_sql(
                    "UPDATE Qualifications"
                    . " SET QuBacknoPrinted=0, QuTimestamp=QuTimestamp"
                    . " WHERE QuId=" . $athlete['id']
                );
            }

            echo '<p><strong>Rozstawienie zostało zapisane.</strong> '
               . count($assignments) . ' zawodnik(-ów) przydzielono do tarczy.</p>';
        }

        // ─── Tasks 9.1–9.2: preview output ───────────────────────────────────
        $mode = empty($_REQUEST['DoAssign']) ? ' (tryb podglądu — nie zapisano)' : ' (zapisano)';
        echo '<table class="Tabella" style="margin-top:1em; margin-left:0; margin-right:auto;">';
        echo '<tr><th class="Title" colspan="3">Podgląd rozstawienia' . $mode . '</th></tr>';
        echo '<tr>'
           . '<td class="Title">Tarcza</td>'
           . '<td class="Title">Klub</td>'
           . '<td class="Title">Zawodnik</td>'
           . '</tr>';

        $prevBoss = null;
        foreach ($slots as $slot) {
            $bossNum = (int)$slot;
            if ($bossNum !== $prevBoss) {
                $pattern = ($bossNum % 2 !== 0) ? 'ABC' : 'ACD';
                echo '<tr><td class="Center" colspan="3"><strong>Tarcza '
                   . $bossNum . ' (' . $pattern . ')</strong></td></tr>';
                $prevBoss = $bossNum;
            }
            if (!isset($assignments[$slot])) {
                echo '<tr>'
                   . '<td class="Center">' . htmlspecialchars($slot) . '</td>'
                   . '<td class="Center" colspan="2">—</td>'
                   . '</tr>';
            } else {
                $a     = $assignments[$slot];
                $bg    = $clubColors[$a['club']] ?? '';
                $style = $bg ? ' style="background-color:' . $bg . '"' : '';
                echo '<tr' . $style . '>'
                   . '<td class="Center">' . htmlspecialchars($slot) . '</td>'
                   . '<td class="Center">' . htmlspecialchars($clubNames[$a['club']] ?? $a['club']) . '</td>'
                   . '<td>' . htmlspecialchars($a['name']) . '</td>'
                   . '</tr>';
            }
        }
        echo '</table>';

        // Unassigned athlete report
        if (!empty($unassigned)) {
            echo '<br><strong>Nieprzydzieleni zawodnicy (' . count($unassigned) . '):</strong><br>';
            foreach ($unassigned as $a) {
                echo htmlspecialchars($a['club'] . ' – ' . $a['name']) . '<br>';
            }
        }
    }
}

include('Common/Templates/tail.php');

// ─── Functions ────────────────────────────────────────────────────────────────

/**
 * Builds an ordered slot list using the ABC/ACD alternating pattern.
 * Odd boss numbers → A, B, C (D empty); even → A, C, D (B empty).
 */
function pl_abc_acd_build_slots(int $from, int $to): array
{
    $slots = [];
    for ($boss = $from; $boss <= $to; $boss++) {
        if ($boss % 2 !== 0) {
            $slots[] = $boss . 'A';
            $slots[] = $boss . 'B';
            $slots[] = $boss . 'C';
        } else {
            $slots[] = $boss . 'A';
            $slots[] = $boss . 'C';
            $slots[] = $boss . 'D';
        }
    }
    return $slots;
}

/**
 * Assigns athletes to slots using a column-priority strategy:
 *
 *   Column A  (every boss, wave 1) → largest club fills from the start
 *   Column C  (every boss, wave 2) → second largest club fills from the start
 *   Clubs 2+  → each club is placed in the first column that can hold all its
 *               athletes: remaining A, then remaining C, then B, then D.
 *               If no single column fits, falls back to slot-by-slot A→C→B→D.
 *
 * Placing whole clubs in single columns keeps teammates on consecutive bosses
 * and on a consistent wave. Smaller clubs that don't fit in A or C get B or D.
 *
 * @param  array $clubs  club_code => [[id, name, club], ...]  (sorted DESC by size)
 * @param  array $slots  ordered slot strings from pl_abc_acd_build_slots()
 * @return array [assignments: slot => athlete_array, unassigned: [athlete_array, ...]]
 */
function pl_abc_acd_assign(array $clubs, array $slots): array
{
    // Partition slots into named columns
    $colA  = [];
    $colC  = [];
    $colBD = [];  // B on odd bosses, D on even bosses, interleaved

    foreach ($slots as $slot) {
        $l = substr($slot, -1);
        if ($l === 'A')     $colA[]  = $slot;
        elseif ($l === 'C') $colC[]  = $slot;
        else                $colBD[] = $slot;
    }

    $assignments = [];
    $unassigned  = [];
    $clubList    = array_values($clubs);
    $numClubs    = count($clubList);

    if ($numClubs === 0) return [$assignments, $unassigned];

    // Club 0 (largest) → A column: always wave 1 on every boss
    $idxA = 0;
    foreach ($clubList[0] as $athlete) {
        if ($idxA < count($colA)) {
            $assignments[$colA[$idxA++]] = $athlete;
        } else {
            $unassigned[] = $athlete;
        }
    }

    if ($numClubs === 1) return [$assignments, $unassigned];

    // Club 1 (second largest) → C column: always wave 2 on every boss
    $idxC = 0;
    foreach ($clubList[1] as $athlete) {
        if ($idxC < count($colC)) {
            $assignments[$colC[$idxC++]] = $athlete;
        } else {
            $unassigned[] = $athlete;
        }
    }

    if ($numClubs <= 2) return [$assignments, $unassigned];

    // Clubs 2+: fit the whole club into the first column that has enough room.
    // Priority: remaining A → remaining C → B → D → slot-by-slot fallback.
    $remainingA = array_slice($colA, $idxA);
    $remainingC = array_slice($colC, $idxC);
    $colB       = array_values(array_filter($colBD, fn($s) => substr($s, -1) === 'B'));
    $colD       = array_values(array_filter($colBD, fn($s) => substr($s, -1) === 'D'));
    $idxRA = 0; $idxRC = 0; $idxB = 0; $idxD = 0;

    foreach (array_slice($clubList, 2) as $athletes) {
        $n = count($athletes);
        if ($idxRA + $n <= count($remainingA)) {
            foreach ($athletes as $a) $assignments[$remainingA[$idxRA++]] = $a;
        } elseif ($idxRC + $n <= count($remainingC)) {
            foreach ($athletes as $a) $assignments[$remainingC[$idxRC++]] = $a;
        } elseif ($idxB + $n <= count($colB)) {
            foreach ($athletes as $a) $assignments[$colB[$idxB++]] = $a;
        } elseif ($idxD + $n <= count($colD)) {
            foreach ($athletes as $a) $assignments[$colD[$idxD++]] = $a;
        } else {
            // Club is larger than any remaining single column — fill slot by slot
            foreach ($athletes as $a) {
                if ($idxRA < count($remainingA))     { $assignments[$remainingA[$idxRA++]] = $a; }
                elseif ($idxRC < count($remainingC)) { $assignments[$remainingC[$idxRC++]] = $a; }
                elseif ($idxB < count($colB))        { $assignments[$colB[$idxB++]] = $a; }
                elseif ($idxD < count($colD))        { $assignments[$colD[$idxD++]] = $a; }
                else                                  { $unassigned[] = $a; }
            }
        }
    }

    return [$assignments, $unassigned];
}
