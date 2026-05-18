<?php
/**
 * ScorecardsOcr.php — Main page for OCR-based scorecard verification.
 *
 * Allows officials to upload photos of physical scorecards, extract scores
 * via the OpenAI vision API (proxied server-side), and detect arithmetic
 * errors and ianseo data-entry mismatches in real time.
 *
 * No data is written to Qualifications or any score table.
 */

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php';
CheckTourSession(true);
require_once __DIR__ . '/Fun_ScorecardsOcr.php';

pl_ocr_install();

$hasApiKey  = pl_ocr_get_config('api_key') !== '';
$configUrl  = $CFG->ROOT_DIR . 'Modules/Sets/PL/ScorecardsOCR/OcrConfig.php';
$proxyUrl   = $CFG->ROOT_DIR . 'Modules/Sets/PL/ScorecardsOCR/AjaxOcrProxy.php';
$scoresUrl  = $CFG->ROOT_DIR . 'Modules/Sets/PL/ScorecardsOCR/AjaxGetScores.php';

$PAGE_TITLE  = 'Weryfikacja kart wyników (OCR)';
$JS_SCRIPT   = [
    '<link rel="stylesheet" href="' . $CFG->ROOT_DIR . 'Modules/Sets/PL/ScorecardsOCR/ScorecardsOcr.css">',
];

include $CFG->DOCUMENT_PATH . 'Common/Templates/head.php';
?>

<script>
window.PL_OCR = {
  proxyUrl:  <?php echo json_encode($proxyUrl); ?>,
  scoresUrl: <?php echo json_encode($scoresUrl); ?>
};
</script>

<div class="pl-ocr-wrap">

<?php if (!$hasApiKey): ?>
<div class="pl-ocr-no-key">
    ⚠ <strong>Klucz API nie jest ustawiony.</strong>
    Przejdź do <a href="<?php echo htmlspecialchars($configUrl); ?>">Konfiguracja OCR</a>,
    aby dodać klucz API OpenAI przed użyciem weryfikacji.
</div>
<?php endif; ?>

<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
    <h2 style="margin:0;">Weryfikacja kart wyników</h2>
    <div style="display:flex; gap:8px; align-items:center;">
        <button id="pl-ocr-clearBtn" class="clear-btn">Wyczyść wyniki</button>
        <a href="<?php echo htmlspecialchars($configUrl); ?>" style="font-size:11px; color:#888;">⚙ Konfiguracja</a>
    </div>
</div>

<!-- Drop zone -->
<div class="dz" id="pl-ocr-dropzone">
    <input type="file" id="pl-ocr-fileInput" accept="image/*" multiple style="display:none">
    <div class="dz-icon">📸</div>
    <div class="dz-title">Przeciągnij zdjęcia kart tutaj</div>
    <div class="dz-sub">lub kliknij, aby wybrać pliki · JPG, PNG, HEIC · wiele plików OK</div>
</div>

<!-- Tips -->
<div class="tips" id="pl-ocr-tips">
    <div class="tip">
        <div class="tip-icon">📷</div>
        <div class="tip-title">Płasko i jasno</div>
        <div class="tip-desc">Bez cieni na liczbach</div>
    </div>
    <div class="tip">
        <div class="tip-icon">🔍</div>
        <div class="tip-title">Wypełnij kadr</div>
        <div class="tip-desc">Karta zajmuje większość zdjęcia</div>
    </div>
    <div class="tip">
        <div class="tip-icon">📐</div>
        <div class="tip-title">Prosto z góry</div>
        <div class="tip-desc">Zdjęcie prostopadle do karty</div>
    </div>
    <div class="tip">
        <div class="tip-icon">✍️</div>
        <div class="tip-title">Czytelny zapis</div>
        <div class="tip-desc">Wszystkie wyniki czytelne</div>
    </div>
</div>

<!-- History panel -->
<div class="hist-panel" id="pl-ocr-historyPanel">
    <div class="hist-header">
        <button class="hist-toggle" id="pl-ocr-historyToggle">▶ HISTORIA</button>
        <span class="hist-count-badge" id="pl-ocr-historyCount">0</span>
        <span class="hist-fill"></span>
        <button class="hist-clear-btn" id="pl-ocr-historyClearBtn">Wyczyść historię</button>
    </div>
    <div class="hist-body" id="pl-ocr-historyBody" style="display:none">
        <div class="hist-list" id="pl-ocr-historyList"></div>
    </div>
</div>

<!-- Results -->
<div id="pl-ocr-results"></div>
<div id="pl-ocr-pagination" hidden></div>

</div><!-- .pl-ocr-wrap -->

<script src="<?php echo $CFG->ROOT_DIR; ?>Modules/Sets/PL/ScorecardsOCR/js/scoring.js"></script>
<script src="<?php echo $CFG->ROOT_DIR; ?>Modules/Sets/PL/ScorecardsOCR/js/render.js"></script>
<script>
// Minimal history panel wired to the ianseo page DOM IDs
const History = (() => {
  const KEY = "pl_ocr_history";
  const MAX = 500;

  function load() {
    try { return JSON.parse(localStorage.getItem(KEY) || "[]"); } catch { return []; }
  }

  function save(entry) {
    const entries = load();
    entries.unshift(entry);
    if (entries.length > MAX) entries.length = MAX;
    try { localStorage.setItem(KEY, JSON.stringify(entries)); } catch {}
    refresh();
  }

  function clear() { localStorage.removeItem(KEY); refresh(); }

  function refresh() {
    const listEl  = document.getElementById("pl-ocr-historyList");
    const countEl = document.getElementById("pl-ocr-historyCount");
    if (!listEl) return;
    const entries = load();
    if (countEl) countEl.textContent = entries.length;
    if (!entries.length) { listEl.innerHTML = `<div class="hist-empty">Brak zeskanowanych kart.</div>`; return; }

    listEl.innerHTML = entries.map((e, i) => {
      const d       = new Date(e.timestamp);
      const dateStr = d.toLocaleDateString("pl-PL") + " " + d.toLocaleTimeString("pl-PL", { hour: "2-digit", minute: "2-digit" });
      const badge   = e.overall_valid
        ? `<span class="sbadge sbadge--valid">✓ Poprawna</span>`
        : `<span class="sbadge sbadge--error">✗ ${e.errors_count} błąd${e.errors_count === 1 ? "" : e.errors_count < 5 ? "y" : "ów"}</span>`;
      const code    = e.barcode_text ? ` · <code style="font-size:10px">${e.barcode_text}</code>` : "";
      return `
        <div class="hist-row" data-idx="${i}">
          <div class="hist-row-main">
            <div class="hist-left">
              <span class="hist-time">${dateStr}</span>
              <span class="hist-fname">${e.filename}${e.label ? " — " + e.label : ""}${code}</span>
              ${e.archer_name ? `<span class="hist-archer">${e.archer_name}</span>` : ""}
            </div>
            <div class="hist-right">
              <span class="hist-score">${e.calculated_grand_total ?? "—"}</span>
              ${badge}
            </div>
          </div>
          <div class="hist-detail" id="pl-ocr-hd-${i}" style="display:none"></div>
        </div>`;
    }).join("");

    listEl.querySelectorAll(".hist-row").forEach(row => {
      row.addEventListener("click", evt => {
        if (evt.target.closest(".hist-detail")) return;
        const idx      = +row.dataset.idx;
        const detailEl = document.getElementById(`pl-ocr-hd-${idx}`);
        const isOpen   = detailEl.style.display === "block";
        listEl.querySelectorAll(".hist-detail").forEach(d => d.style.display = "none");
        listEl.querySelectorAll(".hist-row").forEach(r => r.classList.remove("hist-row--open"));
        if (!isOpen && load()[idx]?.scorecard) {
          const entry = load()[idx];
          const editedCells = entry.editedCells?.length ? new Set(entry.editedCells) : null;
          detailEl.innerHTML = renderDetails(entry.scorecard, { editedCells });
          detailEl.style.display = "block";
          row.classList.add("hist-row--open");
        }
      });
    });
  }

  function init() {
    document.getElementById("pl-ocr-historyToggle")?.addEventListener("click", () => {
      const body = document.getElementById("pl-ocr-historyBody");
      const open = body.style.display !== "none";
      body.style.display = open ? "none" : "block";
    });
    document.getElementById("pl-ocr-historyClearBtn")?.addEventListener("click", () => {
      if (confirm("Wyczyścić całą historię skanowania?")) clear();
    });
    refresh();
  }

  return { save, load, clear, init, refresh };
})();

History.init();
</script>
<script src="<?php echo $CFG->ROOT_DIR; ?>Modules/Sets/PL/ScorecardsOCR/js/ianseo.js"></script>

<?php
include $CFG->DOCUMENT_PATH . 'Common/Templates/tail.php';
