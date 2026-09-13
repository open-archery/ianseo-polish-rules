<?php
/**
 * LiveQualification.php — read-only live view of a qualification round.
 *
 * Pick a session + distance; every active entry on it is shown grouped by
 * physical target with running score and arrows shot, auto-refreshed by
 * LiveQualification.js polling LiveQualificationData.php. No write path.
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
CheckTourSession(true);
checkFullACL(AclQualification, '', AclReadOnly);
require_once('Common/Fun_Sessions.inc.php');

$tourId = (int) $_SESSION['TourId'];

$rsTour = safe_r_sql(
    "SELECT ToNumDist FROM Tournament WHERE ToId = " . StrSafe_DB($tourId, true)
);
$rowTour = safe_fetch($rsTour);
$numDist = $rowTour ? (int) $rowTour->ToNumDist : 0;
safe_free_result($rsTour);

$comboSes = '<select name="x_Session" id="x_Session" onchange="LiveQual_Refresh();">';
$comboSes .= '<option value="-1">---</option>';
foreach (GetSessions('Q') as $ses) {
    $comboSes .= '<option value="' . $ses->SesOrder . '">' . htmlspecialchars($ses->Descr) . '</option>';
}
$comboSes .= '</select>';

$comboDist = '<select name="x_Distance" id="x_Distance" onchange="LiveQual_Refresh();">';
$comboDist .= '<option value="-1">---</option>';
for ($i = 1; $i <= $numDist; ++$i) {
    $comboDist .= '<option value="' . $i . '">' . $i . '</option>';
}
$comboDist .= '</select>';

$JS_SCRIPT = array(
    '<script type="text/javascript" src="' . $CFG->ROOT_DIR . 'Modules/Sets/PL/Live/LiveQualification.js"></script>',
);

$PAGE_TITLE = 'Podgląd na żywo';
include('Common/Templates/head.php');
?>
<table class="Tabella">
<tr><th class="Title" colspan="2"><?php echo htmlspecialchars($PAGE_TITLE); ?> — kwalifikacje</th></tr>
<tr class="Divider"><td colspan="2"></td></tr>
<tr>
<th width="15%">Sesja</th>
<th width="15%">Dystans</th>
</tr>
<tr>
<td class="Center"><?php print $comboSes; ?></td>
<td class="Center"><?php print $comboDist; ?></td>
</tr>
</table>
<br>
<div id="idLiveQualSummary" class="Bold"></div>
<br>
<table class="Tabella" id="idLiveQualTable">
<tr>
<th width="10%">Litera</th>
<th width="40%">Zawodnik</th>
<th width="15%">Wynik</th>
<th width="15%">Strzały</th>
<th width="20%">Status</th>
</tr>
<tbody id="tbodyLiveQual">
</tbody>
</table>
<?php
include('Common/Templates/tail.php');
