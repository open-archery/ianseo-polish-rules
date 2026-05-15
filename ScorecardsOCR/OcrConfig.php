<?php
/**
 * OcrConfig.php — Configuration page for the ScorecardsOCR module.
 *
 * Allows an administrator to set and update the OpenAI API key and model used
 * for scorecard OCR. The API key is stored in PLOcrConfig and is never echoed
 * back to the browser after saving.
 */

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php';
CheckTourSession(true);
require_once __DIR__ . '/Fun_ScorecardsOcr.php';

pl_ocr_install();

$saved   = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newKey   = trim($_POST['api_key']   ?? '');
    $newModel = trim($_POST['model']     ?? '');

    // Only update key if a non-empty value was submitted (empty = keep existing)
    if ($newKey !== '') {
        pl_ocr_save_config('api_key', $newKey);
    }

    if ($newModel === '') {
        $newModel = 'gpt-4.1-mini';
    }
    pl_ocr_save_config('model', $newModel);

    $saved = true;
}

$currentModel  = pl_ocr_get_config('model', 'gpt-4.1-mini');
$hasKey        = pl_ocr_get_config('api_key') !== '';

$PAGE_TITLE = 'Konfiguracja OCR kart wyników';
include $CFG->DOCUMENT_PATH . 'Common/Templates/head.php';
?>

<div style="max-width:640px; margin:1.5em auto;">

    <h2>Konfiguracja OCR kart wyników</h2>
    <p style="color:#555; margin:.5em 0 1.5em;">
        Moduł OCR kart korzysta z API OpenAI. Klucz jest przechowywany w bazie danych
        i nigdy nie jest wysyłany do przeglądarki po zapisaniu.
    </p>

    <?php if ($saved): ?>
        <div style="background:#dff0d8; border:1px solid #3c763d; padding:.8em 1em; margin-bottom:1.2em; color:#3c763d;">
            <strong>Zapisano.</strong> Konfiguracja OCR została zaktualizowana.
        </div>
    <?php endif; ?>

    <form method="post" action="" autocomplete="off">

        <table class="Tabella" style="width:100%;">
            <tr>
                <th class="SubTitle" colspan="2">Klucz API OpenAI</th>
            </tr>
            <tr>
                <td class="Bold" style="width:180px; vertical-align:top; padding-top:.8em;">Klucz API</td>
                <td>
                    <input type="password"
                           name="api_key"
                           id="api_key"
                           placeholder="<?php echo $hasKey ? '●●●●●●●● (klucz jest ustawiony)' : 'sk-...'; ?>"
                           style="width:100%; max-width:420px; font-family:monospace; padding:.4em .6em;"
                           autocomplete="new-password">
                    <p style="color:#777; font-size:.85em; margin:.3em 0 0;">
                        Zostaw puste, jeśli nie chcesz zmieniać obecnego klucza.
                        <?php if (!$hasKey): ?>
                            <span style="color:#a94442;"><strong>Klucz nie jest ustawiony.</strong></span>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th class="SubTitle" colspan="2">Model</th>
            </tr>
            <tr>
                <td class="Bold" style="vertical-align:top; padding-top:.8em;">Model OpenAI</td>
                <td>
                    <input type="text"
                           name="model"
                           value="<?php echo htmlspecialchars($currentModel); ?>"
                           style="width:100%; max-width:280px; font-family:monospace; padding:.4em .6em;">
                    <p style="color:#777; font-size:.85em; margin:.3em 0 0;">
                        Zalecany: <code>gpt-4.1-mini</code> (szybki i tani).
                        Dla wyższej dokładności: <code>gpt-4o</code>.
                    </p>
                </td>
            </tr>
        </table>

        <br>
        <input type="submit" value="Zapisz konfigurację"
               onclick="return confirm('Zaktualizować konfigurację OCR?');">
        &nbsp;
        <a href="<?php echo $CFG->ROOT_DIR; ?>Modules/Sets/PL/ScorecardsOCR/ScorecardsOcr.php">
            ← Wróć do weryfikacji kart
        </a>
    </form>

</div>

<?php
include $CFG->DOCUMENT_PATH . 'Common/Templates/tail.php';
