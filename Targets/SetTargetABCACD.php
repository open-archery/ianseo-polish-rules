<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
CheckTourSession(true);
checkFullACL(AclParticipants, 'pTarget', AclReadWrite);
require_once('Common/Fun_Sessions.inc.php');
require_once(__DIR__ . '/Fun_SetTargetABCACD.php');

$tourId  = (int)$_SESSION['TourId'];
$sessions = GetSessions('Q');

// Both grouping checkboxes default to checked on first load (no FormSubmitted
// marker yet); once the form has been submitted, an unchecked box is simply
// absent from $_REQUEST like any HTML checkbox, so its real value is honored.
$groupByDiv   = isset($_REQUEST['FormSubmitted']) ? !empty($_REQUEST['GroupByDiv'])   : true;
$groupByClass = isset($_REQUEST['FormSubmitted']) ? !empty($_REQUEST['GroupByClass']) : true;

// ─── Erase action ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_REQUEST['Erase'])
    && isset($_REQUEST['Session'])
    && isset($_REQUEST['Event'])
    && (int)$_REQUEST['Session'] >= 1
    && preg_match('/^[0-9A-Z%_]+$/i', $_REQUEST['Event'])
) {
    pl_abc_acd_erase($tourId, (int)$_REQUEST['Session'], $_REQUEST['Event']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$PAGE_TITLE = 'Rozstawianie tarcz ABC/ACD';
include('Common/Templates/head.php');
?>
<form name="Frm" method="POST" action="">
<input type="hidden" name="FormSubmitted" value="1">
<table class="Tabella">
<tr><th class="Title" colspan="9"><?php echo htmlspecialchars($PAGE_TITLE); ?></th></tr>
<tr class="Divider"><td colspan="9"></td></tr>
<tr>
  <td class="Center">Sesja</td>
  <td class="Center">Klasa</td>
  <td class="Center">Tarcza od</td>
  <td class="Center">Tarcza do</td>
  <td class="Center">Rozdziel dywizje</td>
  <td class="Center">Rozdziel klasy</td>
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
    <input type="checkbox" name="GroupByDiv" value="1"
           <?= $groupByDiv ? 'checked' : '' ?>>
  </td>
  <td class="Center">
    <input type="checkbox" name="GroupByClass" value="1"
           <?= $groupByClass ? 'checked' : '' ?>>
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

    // ─── Validate SesAth4Target ────────────────────────────────
    $sesDetail = GetSessions(null, false, [$sesOrder . '_Q']);

    if (empty($sesDetail)) {
        echo '<p style="color:red"><strong>Błąd: nie znaleziono wybranej sesji.</strong></p>';
    } elseif ((int)$sesDetail[0]->SesAth4Target !== 4) {
        $actual = (int)$sesDetail[0]->SesAth4Target;
        echo '<p style="color:red"><strong>Uwaga: Sesja musi być skonfigurowana z 4 zawodnikami'
           . ' na tarczę (SesAth4Target=4). Aktualna wartość: ' . $actual . '.'
           . ' Zmień ustawienie sesji przed rozstawieniem ABC/ACD.</strong></p>';
    } else {

        // ─── Build slot list (whole requested range, for rendering) ──
        $slots = pl_abc_acd_build_slots($tgtFrom, $tgtTo);

        // ─── Load and group athletes ──────────────────────────
        // When both checkboxes are unchecked this is a single group covering
        // everything $event matches (today's pooled behavior).
        $groups = pl_abc_acd_load_athletes($tourId, $sesOrder, $event, $groupByDiv, $groupByClass);

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
        $colorIdx   = 0;
        foreach ($groups as $group) {
            foreach ($group['clubs'] as $code => $athletes) {
                if (!isset($clubColors[$code])) {
                    $clubColors[$code] = $palette[$colorIdx % count($palette)];
                    $clubNames[$code]  = $athletes[0]['clubName'] ?? $code;
                    $colorIdx++;
                }
            }
        }

        // ─── Carve one boss-aligned sub-range per group ───────────────
        $groupSizes = array_map(
            fn($group) => array_sum(array_map('count', $group['clubs'])),
            $groups
        );
        $groupRanges = pl_abc_acd_carve_group_ranges($tgtFrom, $tgtTo, $groupSizes);

        // ─── Run assignment algorithm per group ────────────────────
        // Wave tally from other classes already saved in this session biases
        // which column (A vs C) each club gets (PZŁucz §2.5.1.5). Groups
        // processed earlier in this same request also feed the tally seen by
        // later groups, on top of previously-saved history.
        $runningTally = pl_abc_acd_session_wave_tally($tourId, $sesOrder, $event);
        $assignments  = [];
        $unassigned   = [];
        foreach ($groups as $i => $group) {
            [$from, $to]  = $groupRanges[$i];
            $groupSlots   = pl_abc_acd_build_slots($from, $to);
            [$groupAssignments, $groupUnassigned] = pl_abc_acd_assign($group['clubs'], $groupSlots, $runningTally);

            $assignments  = array_merge($assignments, $groupAssignments);
            $unassigned   = array_merge($unassigned, $groupUnassigned);
            $runningTally = pl_abc_acd_merge_tally($runningTally, $groupAssignments);
        }

        // ─── Erase and save ─────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_REQUEST['DoAssign'])) {
            $saved = pl_abc_acd_save($tourId, $sesOrder, $event, $assignments);

            echo '<p><strong>Rozstawienie zostało zapisane.</strong> '
               . $saved . ' zawodnik(-ów) przydzielono do tarczy.</p>';
        }

        // ─── Preview output ───────────────────────────────────
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
