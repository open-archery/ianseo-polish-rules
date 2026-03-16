<?php
/**
 * BibImport.php — Batch import of tournament entries from PZŁucz licence numbers.
 *
 * Operator pastes one licence number per line, selects a division, and clicks
 * "Importuj uczestników". The page looks up each licence in LookUpEntries and
 * creates Entries rows in the current tournament.
 *
 * GET  — shows the input form (with optional Sportzona warning)
 * POST — processes the batch, shows results, then shows the form again
 */

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php';
CheckTourSession(true);
require_once dirname(__FILE__) . '/Fun_BibImport.php';

$tourId = (int) $_SESSION['TourId'];

// ─── Check whether the LookUpEntries table has any POL records ───────────────
$rsLookupCheck = safe_r_sql(
    "SELECT COUNT(*) AS cnt FROM LookUpEntries WHERE LueIocCode = 'POL'"
);
$lookupRow         = safe_fetch($rsLookupCheck);
$lookupEmpty       = (!$lookupRow || (int) $lookupRow->cnt === 0);
safe_free_result($rsLookupCheck);

// ─── Load divisions for the dropdown ─────────────────────────────────────────
$divisions    = [];
$rsDivisions  = safe_r_sql(
    "SELECT DivId, DivDescription FROM Divisions"
    . " WHERE DivTournament = " . StrSafe_DB($tourId, true)
    . " ORDER BY DivViewOrder ASC, DivId ASC"
);
while ($div = safe_fetch($rsDivisions)) {
    $divisions[] = $div;
}
safe_free_result($rsDivisions);

// ─── Handle POST ──────────────────────────────────────────────────────────────
$importResult    = null;
$selectedDivision = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedDivision = isset($_POST['division']) ? trim($_POST['division']) : '';
    $rawInput         = isset($_POST['bibs'])     ? $_POST['bibs']           : '';

    // Basic validation: division must be non-empty and exist in this tournament
    $divisionValid = false;
    foreach ($divisions as $div) {
        if ($div->DivId === $selectedDivision) {
            $divisionValid = true;
            break;
        }
    }

    if (!$divisionValid || $selectedDivision === '') {
        $importResult = [
            'imported'        => 0,
            'duplicates'      => [],
            'unmatched'       => [],
            'classUnresolved' => [],
            'error'           => 'Nieprawidłowa dyscyplina. Wybierz dyscyplinę z listy.',
        ];
    } else {
        $importResult = pl_bibimport_run($tourId, $rawInput, $selectedDivision);
    }
}

// ─── Page setup ───────────────────────────────────────────────────────────────
$PAGE_TITLE   = 'Import uczestników (Bib)';
$IncludeJquery = false;

include $CFG->DOCUMENT_PATH . 'Common/Templates/head.php';

// ─── Sportzona warning ────────────────────────────────────────────────────────
if ($lookupEmpty): ?>
<div style="background:#f2dede;border:1px solid #a94442;padding:12px 16px;margin:10px 0;border-radius:4px;color:#a94442;">
    <strong>Uwaga:</strong> Tabela <code>LookUpEntries</code> nie zawiera żadnych rekordów dla kodu IOC <code>POL</code>.
    Przed użyciem importu należy uruchomić synchronizację Sportzona.
    <a href="<?php echo $CFG->ROOT_DIR; ?>Modules/Sets/PL/Lookup/Install.php" style="color:#a94442;font-weight:bold;">
        Przejdź do strony synchronizacji &rsaquo;
    </a>
</div>
<?php endif; ?>

<?php
// ─── Import results (shown only after POST) ───────────────────────────────────
if ($importResult !== null):

    // DB error banner
    if (!empty($importResult['error'])): ?>
<div style="background:#f2dede;border:1px solid #a94442;padding:12px 16px;margin:10px 0;border-radius:4px;color:#a94442;">
    <strong>Błąd bazy danych:</strong> <?php echo htmlspecialchars($importResult['error']); ?>
    Żaden uczestnik nie został zaimportowany.
</div>
    <?php else: ?>

        <?php // Success count box ?>
<div style="background:#dff0d8;border:1px solid #3c763d;padding:12px 16px;margin:10px 0;border-radius:4px;color:#3c763d;">
    <strong>Zaimportowano <?php echo (int) $importResult['imported']; ?> uczestników.</strong>
</div>

        <?php // Duplicate table ?>
        <?php if (!empty($importResult['duplicates'])): ?>
<table class="Tabella" style="margin:10px 0;">
    <tr>
        <th class="SubTitle" colspan="2">
            Duplikaty — uczestnicy już zarejestrowani w turnieju
            (<?php echo count($importResult['duplicates']); ?>)
        </th>
    </tr>
    <tr>
        <th class="Bold" style="width:200px;">Numer licencji</th>
        <th class="Bold">Nazwisko i imię</th>
    </tr>
            <?php foreach ($importResult['duplicates'] as $dup): ?>
    <tr>
        <td><?php echo htmlspecialchars($dup['code']); ?></td>
        <td><?php echo htmlspecialchars($dup['name']); ?></td>
    </tr>
            <?php endforeach; ?>
</table>
        <?php endif; ?>

        <?php // Unmatched table ?>
        <?php if (!empty($importResult['unmatched'])): ?>
<table class="Tabella" style="margin:10px 0;">
    <tr>
        <th class="SubTitle">
            Numery nieodnalezione w bazie Sportzona
            (<?php echo count($importResult['unmatched']); ?>)
        </th>
    </tr>
    <tr>
        <th class="Bold">Numer licencji</th>
    </tr>
            <?php foreach ($importResult['unmatched'] as $code): ?>
    <tr>
        <td><?php echo htmlspecialchars($code); ?></td>
    </tr>
            <?php endforeach; ?>
</table>
        <?php endif; ?>

        <?php // Class-unresolved table ?>
        <?php if (!empty($importResult['classUnresolved'])): ?>
<table class="Tabella" style="margin:10px 0;">
    <tr>
        <th class="SubTitle" colspan="3">
            Uczestnicy zaimportowani bez klasy wiekowej — przypisz ręcznie
            (<?php echo count($importResult['classUnresolved']); ?>)
        </th>
    </tr>
    <tr>
        <th class="Bold" style="width:200px;">Numer licencji</th>
        <th class="Bold">Nazwisko i imię</th>
        <th class="Bold" style="width:140px;">Rok urodzenia</th>
    </tr>
            <?php foreach ($importResult['classUnresolved'] as $item): ?>
    <tr>
        <td><?php echo htmlspecialchars($item['code']); ?></td>
        <td><?php echo htmlspecialchars($item['name']); ?></td>
        <td><?php echo htmlspecialchars($item['birthYear']); ?></td>
    </tr>
            <?php endforeach; ?>
</table>
        <?php endif; ?>

    <?php endif; // end: no DB error ?>
<?php endif; // end: $importResult !== null ?>

<?php
// ─── Input form ───────────────────────────────────────────────────────────────
?>
<table class="Tabella" style="margin:10px 0;">
    <tr>
        <th class="Title" colspan="2">Import uczestników z listy numerów licencji (Bib)</th>
    </tr>

    <?php if (empty($divisions)): ?>
    <tr>
        <td colspan="2" style="color:#a94442;padding:10px;">
            <strong>Uwaga:</strong> W tym turnieju nie zdefiniowano żadnych dyscyplin.
            Skonfiguruj turniej przed importem.
        </td>
    </tr>
    <?php else: ?>

    <tr>
        <td colspan="2">
            <form method="post" action="">

                <table style="border-collapse:collapse;width:100%;max-width:700px;">

                    <tr>
                        <td style="padding:8px 10px;width:180px;font-weight:bold;vertical-align:top;">
                            Dyscyplina:
                        </td>
                        <td style="padding:8px 0;">
                            <select name="division" style="padding:4px 8px;min-width:200px;">
                                <?php foreach ($divisions as $div): ?>
                                <option value="<?php echo htmlspecialchars($div->DivId); ?>"
                                    <?php if ($selectedDivision === $div->DivId) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($div->DivDescription); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:8px 10px;font-weight:bold;vertical-align:top;">
                            Numery licencji<br>
                            <span style="font-weight:normal;font-size:.85em;color:#666;">
                                (jeden numer na linię)
                            </span>
                        </td>
                        <td style="padding:8px 0;">
                            <textarea name="bibs" rows="20"
                                style="width:100%;max-width:500px;font-family:monospace;font-size:13px;padding:6px;"
                                placeholder="Wklej tutaj numery licencji — po jednym w każdej linii&#10;Przykład:&#10;1234&#10;6789&#10;1122"></textarea>
                        </td>
                    </tr>

                    <tr>
                        <td></td>
                        <td style="padding:8px 0;">
                            <input type="submit"
                                value="Importuj uczestników"
                                style="padding:8px 20px;font-size:14px;cursor:pointer;">
                        </td>
                    </tr>

                </table>

            </form>
        </td>
    </tr>

    <?php endif; // end: divisions not empty ?>

</table>

<?php
include $CFG->DOCUMENT_PATH . 'Common/Templates/tail.php';
