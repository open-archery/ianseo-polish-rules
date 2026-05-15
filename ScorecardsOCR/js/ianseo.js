/**
 * ianseo.js — ScorecardsOCR orchestrator for the ianseo-embedded module.
 *
 * Replaces the standalone app.js from the PoC. Key differences:
 *  - Images are sent to AjaxOcrProxy.php (no API key in browser)
 *  - After OCR, barcode_text is used to fetch live DB scores from AjaxGetScores.php
 *  - DB scores are merged into the enriched scorecard before rendering
 *  - All user-facing labels in Polish
 *
 * Requires: scoring.js, render.js loaded before this file.
 * Requires: window.PL_OCR = { proxyUrl, scoresUrl } set by the PHP page.
 */

// ── DOM refs ──────────────────────────────────────────────────────────────────

const dz           = document.getElementById("pl-ocr-dropzone");
const fi           = document.getElementById("pl-ocr-fileInput");
const tipsEl       = document.getElementById("pl-ocr-tips");
const resultsEl    = document.getElementById("pl-ocr-results");
const clearBtn     = document.getElementById("pl-ocr-clearBtn");
const paginationEl = document.getElementById("pl-ocr-pagination");

// ── Pagination state ──────────────────────────────────────────────────────────

const PAGE_SIZE = 20;
let allCards    = [];
let currentPage = 1;

function renderPage(page) {
  const pages = Math.max(1, Math.ceil(allCards.length / PAGE_SIZE));
  currentPage = Math.min(Math.max(1, page), pages);

  const start = (currentPage - 1) * PAGE_SIZE;
  resultsEl.replaceChildren(...allCards.slice(start, start + PAGE_SIZE));

  if (pages <= 1) { paginationEl.hidden = true; return; }
  paginationEl.hidden = false;
  paginationEl.innerHTML = `
    <button class="page-btn" data-page="${currentPage - 1}" ${currentPage <= 1 ? "disabled" : ""}>← Poprzednia</button>
    <span class="page-info">Strona ${currentPage} z ${pages} · ${allCards.length} kart</span>
    <button class="page-btn" data-page="${currentPage + 1}" ${currentPage >= pages ? "disabled" : ""}>Następna →</button>`;
}

paginationEl.addEventListener("click", e => {
  const btn = e.target.closest(".page-btn");
  if (!btn || btn.disabled) return;
  renderPage(+btn.dataset.page);
  resultsEl.scrollIntoView({ behavior: "smooth", block: "start" });
});

// ── Dropzone ──────────────────────────────────────────────────────────────────

dz.addEventListener("click",     () => fi.click());
dz.addEventListener("dragover",  e  => { e.preventDefault(); dz.classList.add("dz--over"); });
dz.addEventListener("dragleave", ()  => dz.classList.remove("dz--over"));
dz.addEventListener("drop",      e  => { e.preventDefault(); dz.classList.remove("dz--over"); handleFiles(e.dataTransfer.files); });
fi.addEventListener("change",    e  => handleFiles(e.target.files));

clearBtn.addEventListener("click", () => {
  allCards    = [];
  currentPage = 1;
  resultsEl.replaceChildren();
  paginationEl.hidden = true;
  tipsEl.hidden = false;
  fi.value = "";
});

// Event delegation for log-toggle buttons
resultsEl.addEventListener("click", e => {
  const btn = e.target.closest(".log-btn");
  if (!btn) return;
  const lb      = btn.nextElementSibling;
  const visible = lb.style.display === "block";
  lb.style.display   = visible ? "none" : "block";
  btn.textContent    = `${visible ? "Pokaż" : "Ukryj"} log (${lb.dataset.count})`;
});

// Event delegation for editable arrow chips
resultsEl.addEventListener("click", e => {
  const chip = e.target.closest(".chip--editable");
  if (!chip) return;
  const currentVal = chip.textContent.trim();
  const options    = ["M", "1", "2", "3", "4", "5", "6", "7", "8", "9", "10", "X"];
  const sel        = document.createElement("select");
  sel.className    = "arrow-edit";
  sel.dataset.end   = chip.dataset.end;
  sel.dataset.row   = chip.dataset.row;
  sel.dataset.arrow = chip.dataset.arrow;
  options.forEach(opt => {
    const o = document.createElement("option");
    o.value = opt;
    o.textContent = opt;
    if (opt === currentVal) o.selected = true;
    sel.appendChild(o);
  });
  chip.replaceWith(sel);
  sel.focus();
});

resultsEl.addEventListener("change", e => {
  const sel = e.target.closest(".arrow-edit");
  if (!sel) return;
  const card     = sel.closest(".card");
  const sc       = card?._sc;
  if (!sc) return;
  const endIdx   = +sel.dataset.end;
  const row      = sel.dataset.row;   // "a" or "b"
  const arrowIdx = +sel.dataset.arrow;
  const subRow   = sc.ends[endIdx]?.[`sub_row_${row}`];
  if (!subRow?.arrows) return;
  subRow.arrows[arrowIdx] = normalizeArrow(sel.value);
  if (!card._editedCells) card._editedCells = new Set();
  card._editedCells.add(`${endIdx}-${row}-${arrowIdx}`);
  enrichScorecard(sc);
  card._rerender?.();
  if (card._historyId) updateHistoryEntry(card._historyId, sc, card._editedCells);
});

function updateHistoryEntry(id, sc, editedCells) {
  const KEY = "pl_ocr_history";
  let entries;
  try { entries = JSON.parse(localStorage.getItem(KEY) || "[]"); } catch { return; }
  const idx = entries.findIndex(e => e.id === id);
  if (idx === -1) return;
  const e = entries[idx];
  e.scorecard              = sc;
  e.calculated_grand_total = sc.calculated_grand_total ?? null;
  e.errors_count           = sc.errors_found?.length  || 0;
  e.overall_valid          = sc.overall_valid          ?? false;
  e.manually_corrected     = true;
  e.editedCells            = editedCells ? [...editedCells] : [];
  try { localStorage.setItem(KEY, JSON.stringify(entries)); } catch {}
  if (typeof History !== "undefined") History.refresh();
}

// ── File handling ─────────────────────────────────────────────────────────────

function handleFiles(files) {
  const imgs = Array.from(files)
    .filter(f => f.type.startsWith("image/") || /\.(jpg|jpeg|png|heic|webp)$/i.test(f.name));
  if (!imgs.length) return;
  tipsEl.hidden = true;
  Promise.allSettled(imgs.map(splitAndAnalyze));
}

async function splitAndAnalyze(file) {
  let rawDataUrl;
  try { rawDataUrl = await readFileAsDataURL(file); } catch { return; }

  const pageGroup = document.createElement("div");
  pageGroup.className = "ocr-page-group";

  const pageLabel = document.createElement("div");
  pageLabel.className = "ocr-page-label";
  pageLabel.textContent = file.name;
  pageGroup.appendChild(pageLabel);

  let quadrants;
  try { quadrants = await splitIntoQuadrants(rawDataUrl); } catch { quadrants = null; }

  if (!quadrants) {
    const card = document.createElement("div");
    card.className = "card";
    pageGroup.appendChild(card);
    allCards.unshift(pageGroup);
    renderPage(1);
    await analyze({ name: file.name, dataUrl: rawDataUrl, label: null, cardEl: card });
    return;
  }

  const labels = ["Lewy-górny", "Prawy-górny", "Lewy-dolny", "Prawy-dolny"];

  const gridWrap = document.createElement("div");
  gridWrap.className = "ocr-quad-grid";

  const cards = labels.map(() => {
    const card = document.createElement("div");
    card.className = "card";
    gridWrap.appendChild(card);
    return card;
  });

  pageGroup.appendChild(gridWrap);
  allCards.unshift(pageGroup);
  renderPage(1);

  await Promise.all(quadrants.map((q, i) =>
    analyze({ name: file.name, dataUrl: q, label: labels[i], cardEl: cards[i] })
  ));
}

function splitIntoQuadrants(dataUrl) {
  return new Promise((res, rej) => {
    const img = new Image();
    img.onload = () => {
      const hw = Math.floor(img.width  / 2);
      const hh = Math.floor(img.height / 2);
      const rw = img.width  - hw;
      const rh = img.height - hh;
      const regions = [[0, 0, hw, hh], [hw, 0, rw, hh], [0, hh, hw, rh], [hw, hh, rw, rh]];
      const result = regions.map(([x, y, w, h]) => {
        const c = document.createElement("canvas");
        c.width = w; c.height = h;
        c.getContext("2d").drawImage(img, x, y, w, h, 0, 0, w, h);
        return c.toDataURL("image/jpeg", 0.92);
      });
      res(result);
    };
    img.onerror = () => rej(new Error("Podział na ćwiartki nie powiódł się"));
    img.src = dataUrl;
  });
}

// ── Utilities ─────────────────────────────────────────────────────────────────

function readFileAsDataURL(file) {
  return new Promise((res, rej) => {
    const r = new FileReader();
    r.onload  = () => res(r.result);
    r.onerror = () => rej(new Error("Błąd odczytu pliku"));
    r.readAsDataURL(file);
  });
}

function resizeImage(dataUrl, maxPx = 2400, quality = 0.92) {
  return new Promise((res, rej) => {
    const img = new Image();
    img.onload = () => {
      let { width: w, height: h } = img;
      if (w > maxPx || h > maxPx) {
        if (w >= h) { h = Math.round(h * maxPx / w); w = maxPx; }
        else        { w = Math.round(w * maxPx / h); h = maxPx; }
      }
      const c = document.createElement("canvas");
      c.width = w; c.height = h;
      c.getContext("2d").drawImage(img, 0, 0, w, h);
      const out = c.toDataURL("image/jpeg", quality);
      res({ b64: out.split(",")[1], w, h, sizeKB: Math.round(out.length * 0.75 / 1024) });
    };
    img.onerror = () => rej(new Error("Błąd dekodowania obrazu"));
    img.src = dataUrl;
  });
}

// ── DB score lookup ───────────────────────────────────────────────────────────

async function fetchDbScores(barcodeText) {
  if (!barcodeText) return null;
  try {
    const fd = new FormData();
    fd.append("barcode_text", barcodeText);
    const res = await fetch(window.PL_OCR.scoresUrl, { method: "POST", body: fd });
    if (!res.ok) return null;
    return await res.json();
  } catch {
    return null;
  }
}

// ── Core analysis ─────────────────────────────────────────────────────────────

async function analyze({ name, dataUrl, label, cardEl = null }) {
  const card = cardEl ?? document.createElement("div");
  card.className = "card";
  if (!cardEl) {
    allCards.unshift(card);
    renderPage(1);
  }

  const displayName = label ? `${name} — ${label}` : name;
  const logs   = [];
  const addLog = msg => {
    logs.push(msg);
    const lb = card.querySelector(".log-box");
    if (!lb) return;
    lb.innerHTML     = logs.map(l => `<div>› ${l}</div>`).join("");
    lb.dataset.count = logs.length;
    const btn = card.querySelector(".log-btn");
    if (btn && lb.style.display !== "block")
      btn.textContent = `Pokaż log (${logs.length})`;
  };

  function renderCard(status, data, error, rawText) {
    const isValid      = data?.overall_valid;
    const errCount     = data?.errors_found?.length || 0;
    const badge        = `<div class="${cardBadgeClass(status, isValid)}">${cardBadgeText(status, isValid, errCount)}</div>`;
    const barcodeInfo  = data?.barcode_text
      ? `<div style="font-size:11px;color:#888;font-family:monospace">${data.barcode_text}${data.target_label ? " · " + data.target_label : ""}</div>`
      : "";

    card.innerHTML = `
      <div class="card-header">
        <div class="card-info">
          ${dataUrl
            ? `<img src="${dataUrl}" class="thumb" onerror="this.style.opacity='0.1'">`
            : `<div class="thumb thumb--placeholder">📄</div>`}
          <div>
            <div class="fname">${displayName}</div>
            ${data?.archer_name ? `<div class="aname">${data.archer_name}</div>` : ""}
            ${data?.round_type  ? `<div class="rtype">${data.round_type}</div>`  : ""}
            ${barcodeInfo}
          </div>
        </div>
        <div class="card-actions">${badge}</div>
      </div>
      <div class="log-section">
        <button class="log-btn">Pokaż log (${logs.length})</button>
        <div class="log-box" data-count="${logs.length}">
          ${logs.map(l => `<div>› ${l}</div>`).join("")}
          ${error ? `<div class="log-error">BŁĄD: ${error}</div>` : ""}
        </div>
      </div>
      ${status === "error"          ? `<div class="alert">⚠ ${error}</div>`                                                                     : ""}
      ${status === "raw" && rawText ? `<div class="alert">⚠ Odpowiedź API nie jest poprawnym JSON:</div><pre class="raw">${rawText}</pre>` : ""}
      ${status === "done" && data   ? renderDetails(data, { editable: true, editedCells: card._editedCells ?? null })                   : ""}`;
  }

  renderCard("loading");

  try {
    addLog(`Ćwiartka: ${displayName}`);

    let imgData;
    try {
      imgData = await resizeImage(dataUrl, 2400, 0.92);
      addLog(`Zmiana rozmiaru: ${imgData.w}×${imgData.h}, ~${imgData.sizeKB}KB`);
    } catch {
      addLog("Zmiana rozmiaru nie powiodła się, używam oryginału");
      const b64 = dataUrl.split(",")[1];
      imgData = { b64, sizeKB: Math.round(b64.length * 0.75 / 1024) };
    }

    // Send to PHP proxy instead of OpenAI directly
    addLog("Wysyłanie do AjaxOcrProxy.php…");
    const fd = new FormData();
    fd.append("image_b64", imgData.b64);
    if (label) fd.append("label", label);

    let apiRes;
    try {
      apiRes = await fetch(window.PL_OCR.proxyUrl, { method: "POST", body: fd });
    } catch {
      throw new Error("Błąd sieci — sprawdź połączenie z serwerem ianseo.");
    }

    addLog(`HTTP ${apiRes.status}`);

    if (!apiRes.ok) {
      let errJson = {};
      try { errJson = await apiRes.json(); } catch {}
      throw new Error(errJson.error || `Błąd serwera (HTTP ${apiRes.status})`);
    }

    let json;
    try { json = await apiRes.json(); }
    catch { throw new Error("Nie można sparsować odpowiedzi serwera."); }

    // The proxy returns the raw OpenAI response; extract content
    const choice     = json.choices?.[0];
    const stopReason = choice?.finish_reason;
    if (stopReason === "content_filter") throw new Error("Odpowiedź zablokowana przez filtr treści.");

    const rawText = choice?.message?.content ?? "";
    addLog(`Odpowiedź: ${rawText.length} znaków · finish: ${stopReason}`);

    if (!rawText) throw new Error("Pusta odpowiedź z API.");

    let parsed;
    try { parsed = JSON.parse(rawText); }
    catch { addLog("Błąd parsowania JSON"); renderCard("raw", null, null, rawText); return; }

    addLog("Sparsowano ✓");
    if (parsed.reasoning) addLog("REASONING:\n" + parsed.reasoning);

    const barcodeText  = parsed.barcode_text  ?? null;
    const targetLabel  = parsed.target_label  ?? null;
    const sessionLabel = parsed.session_label ?? null;
    addLog(`Kod kreskowy: ${barcodeText ?? "(brak)"} · cel: ${targetLabel ?? "(brak)"}`);

    const scorecards = parsed.scorecards || (Array.isArray(parsed) ? parsed : [parsed]);
    const enriched   = scorecards.map(normalizeScorecard).map(enrichScorecard);
    const sc         = enriched[0];

    // Attach meta fields from the outer response
    sc.barcode_text  = barcodeText;
    sc.target_label  = targetLabel;
    sc.session_label = sessionLabel;
    sc.db_looked_up  = false;

    // Fetch DB scores if barcode was read
    if (barcodeText) {
      addLog("Pobieranie wyników z bazy danych…");
      const dbResult = await fetchDbScores(barcodeText);
      if (dbResult) {
        sc.db_looked_up = true;
        sc.db_found  = dbResult.found ?? false;
        sc.db_score  = dbResult.score ?? null;
        sc.db_gold   = dbResult.gold  ?? null;
        sc.db_xnine  = dbResult.xnine ?? null;
        sc.db_session = dbResult.session ?? null;
        sc.db_bib    = dbResult.bib ?? null;
        addLog(sc.db_found
          ? `DB: sesja ${sc.db_session} → wynik ${sc.db_score}, 10+X ${sc.db_gold}, X ${sc.db_xnine}`
          : `DB: zawodnik nie znaleziony (${dbResult.bib ?? "?"})`);
      } else {
        addLog("Nie można pobrać wyników z bazy danych.");
      }
    }

    renderCard("done", sc);

    // Attach live scorecard state to card element for inline editing
    card._sc       = sc;
    card._rerender = () => renderCard("done", sc);

    // Persist to localStorage history (reuse History from history.js if available)
    if (typeof History !== "undefined") {
      const historyId = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
      card._historyId = historyId;
      History.save({
        id:                     historyId,
        timestamp:              new Date().toISOString(),
        filename:               name,
        label,
        barcode_text:           barcodeText,
        target_label:           targetLabel,
        archer_name:            sc.archer_name ?? null,
        round_type:             sc.round_type  ?? null,
        calculated_grand_total: sc.calculated_grand_total ?? null,
        recorded_grand_total:   sc.recorded_grand_total  ?? null,
        errors_count:           sc.errors_found?.length  || 0,
        overall_valid:          sc.overall_valid          ?? false,
        scorecard:              sc,
      });
    }

  } catch (e) {
    renderCard("error", null, e.message);
  }
}
