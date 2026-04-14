<?php
/**
 * Install.php — one-time registration of the Sportzona lookup path.
 *
 * Creates the LookUpPaths table if it does not yet exist, then inserts (or
 * updates) the row that tells ianseo to use SportzonaProxy.php as the athlete
 * data source for IOC code 'POL'.
 *
 * Must be run once by an authenticated ianseo administrator with a tournament
 * open. It is idempotent — re-running it is safe.
 */

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php';
CheckTourSession(true);

$PAGE_TITLE = 'Instalacja: Sportzona Lookup (POL)';
include $CFG->DOCUMENT_PATH . 'Common/Templates/head.php';

$lupPath   = '%Modules/Sets/PL/Lookup/SportzonaProxy.php';
$lupOrigin = 'POL';   // varchar(3) column — must be ≤ 3 chars; 'POL' matches the IOC code
$lupIoc    = 'POL';

$installed = false;
$error     = '';

if (!empty($_POST['install'])) {
    // Ensure the table exists (ianseo may not have run its full migration chain)
    safe_w_sql("CREATE TABLE IF NOT EXISTS `LookUpPaths` (
        `LupIocCode`      VARCHAR(5)   NOT NULL,
        `LupOrigin`       VARCHAR(3)   NOT NULL DEFAULT '',
        `LupPath`         VARCHAR(255) NOT NULL DEFAULT '',
        `LupPhotoPath`    VARCHAR(255) NOT NULL DEFAULT '',
        `LupFlagsPath`    VARCHAR(255) NOT NULL DEFAULT '',
        `LupLastUpdate`   DATETIME     NULL,
        `LupRankingPath`  VARCHAR(255) NOT NULL DEFAULT '',
        `LupClubNamesPath` VARCHAR(255) NOT NULL DEFAULT '',
        `LupRecordsPath`  VARCHAR(255) NOT NULL DEFAULT '',
        PRIMARY KEY (`LupIocCode`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // Upsert the POL row
    $sql = "INSERT INTO LookUpPaths (LupIocCode, LupPath, LupOrigin)
            VALUES (" . StrSafe_DB($lupIoc) . ", " . StrSafe_DB($lupPath) . ", " . StrSafe_DB($lupOrigin) . ")
            ON DUPLICATE KEY UPDATE
                LupPath   = " . StrSafe_DB($lupPath) . ",
                LupOrigin = " . StrSafe_DB($lupOrigin);

    safe_w_sql($sql);
    $installed = true;
}

// Read current state of the row (if any)
$existing = null;
$rs = safe_r_sql("SELECT * FROM LookUpPaths WHERE LupIocCode = " . StrSafe_DB($lupIoc));
if ($row = safe_fetch($rs)) {
    $existing = $row;
}
?>
<div style="max-width:700px; margin:1em auto;">
    <h2>Instalacja: Sportzona Lookup (POL)</h2>

    <?php if ($installed): ?>
        <div style="background:#dff0d8; border:1px solid #3c763d; padding:1em; margin-bottom:1em; color:#3c763d;">
            <strong>Gotowe.</strong> Ścieżka synchronizacji dla kodu IOC <code>POL</code> została zarejestrowana.
        </div>
    <?php endif; ?>

    <table class="Tabella" style="width:100%;">
        <tr>
            <th class="SubTitle" colspan="2">Aktualny stan rekordu LookUpPaths dla POL</th>
        </tr>
        <?php if ($existing): ?>
            <tr>
                <td class="Bold" style="width:180px;">LupIocCode</td>
                <td><?php echo htmlspecialchars($existing->LupIocCode); ?></td>
            </tr>
            <tr>
                <td class="Bold">LupPath</td>
                <td><?php echo htmlspecialchars($existing->LupPath); ?></td>
            </tr>
            <tr>
                <td class="Bold">LupOrigin</td>
                <td><?php echo htmlspecialchars($existing->LupOrigin); ?></td>
            </tr>
            <tr>
                <td class="Bold">LupLastUpdate</td>
                <td><?php echo htmlspecialchars($existing->LupLastUpdate ?? '(brak)'); ?></td>
            </tr>
        <?php else: ?>
            <tr>
                <td colspan="2" style="color:#a94442;">
                    Brak rekordu dla kodu IOC <strong>POL</strong> — należy kliknąć „Zainstaluj".
                </td>
            </tr>
        <?php endif; ?>
    </table>

    <br>

    <table class="Tabella" style="width:100%;">
        <tr>
            <th class="SubTitle" colspan="2">Wartości do zainstalowania</th>
        </tr>
        <tr>
            <td class="Bold" style="width:180px;">LupIocCode</td>
            <td><code><?php echo htmlspecialchars($lupIoc); ?></code></td>
        </tr>
        <tr>
            <td class="Bold">LupPath</td>
            <td><code><?php echo htmlspecialchars($lupPath); ?></code></td>
        </tr>
        <tr>
            <td class="Bold">LupOrigin</td>
            <td><code><?php echo htmlspecialchars($lupOrigin); ?></code></td>
        </tr>
    </table>

    <br>

    <form method="post" action="">
        <input type="hidden" name="install" value="1">
        <input type="submit"
               value="Zainstaluj / zaktualizuj rekord LookUpPaths"
               onclick="return confirm('Czy na pewno chcesz zarejestrować/zaktualizować ścieżkę Sportzona dla POL?');">
    </form>

    <br>

    <p style="color:#777; font-size:.9em;">
        Po instalacji otwórz
        <a href="<?php echo $CFG->ROOT_DIR; ?>Partecipants/LookupTableLoad.php">Synchronizację uczestników</a>
        i sprawdź, czy wiersz <strong>POL</strong> pojawia się z możliwością pobrania danych.
    </p>
</div>
<?php
include $CFG->DOCUMENT_PATH . 'Common/Templates/tail.php';
