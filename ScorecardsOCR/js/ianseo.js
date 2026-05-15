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

// Event delegation for editable count cells (10+X / X / Suma / Razem / Bieżący)
resultsEl.addEventListener("click", e => {
  const span = e.target.closest(".val-cell--editable");
  if (!span) return;
  const card   = span.closest(".card");
  const sc     = card?._sc;
  if (!sc) return;
  const endIdx = +span.dataset.end;
  const row    = span.dataset.row;   // "a", "b", or ""
  const field  = span.dataset.field;
  const end    = sc.ends[endIdx];
  if (!end) return;

  let curVal;
  if (row === "a" || row === "b") {
    const subRow = end[`sub_row_${row}`];
    if (field === "10x")       curVal = subRow?.recorded_10x;
    else if (field === "x")    curVal = subRow?.recorded_x;
    else if (field === "suma") curVal = subRow?.recorded_suma;
  } else if (field === "razem") {
    curVal = end.recorded_razem;
  } else if (field === "running") {
    curVal = end.recorded_running;
  }

  const maxVal = (field === "10x" || field === "x") ? "3"
               : field === "suma" ? "30"
               : "9999";

  const inp       = document.createElement("input");
  inp.type        = "number";
  inp.min         = "0";
  inp.max         = maxVal;
  inp.className   = "count-edit";
  inp.dataset.end   = span.dataset.end;
  inp.dataset.row   = span.dataset.row;
  inp.dataset.field = span.dataset.field;
  inp.value = curVal ?? "";
  span.replaceWith(inp);
  inp.focus();
  inp.select();
});

function commitCountEdit(inp) {
  const card   = inp.closest(".card");
  const sc     = card?._sc;
  if (!sc) return;
  const endIdx = +inp.dataset.end;
  const row    = inp.dataset.row;   // "a", "b", or ""
  const field  = inp.dataset.field;
  const end    = sc.ends[endIdx];
  if (!end) return;

  const maxVal = (field === "10x" || field === "x") ? 3
               : field === "suma" ? 30
               : 9999;

  const val = inp.value === "" ? null : Math.max(0, Math.min(maxVal, parseInt(inp.value, 10)));

  if (row === "a" || row === "b") {
    const subRow = end[`sub_row_${row}`];
    if (!subRow) return;
    if (field === "10x")       subRow.recorded_10x  = val;
    else if (field === "x")    subRow.recorded_x    = val;
    else if (field === "suma") subRow.recorded_suma  = val;
  } else if (field === "razem") {
    end.recorded_razem   = val;
  } else if (field === "running") {
    end.recorded_running = val;
  }

  if (!card._editedCells) card._editedCells = new Set();
  const cellKey = (row === "a" || row === "b") ? `${endIdx}-${row}-${field}` : `${endIdx}-${field}`;
  card._editedCells.add(cellKey);
  enrichScorecard(sc);
  card._rerender?.();
  if (card._historyId) updateHistoryEntry(card._historyId, sc, card._editedCells);
}

resultsEl.addEventListener("blur", e => {
  const inp = e.target.closest(".count-edit");
  if (inp) commitCountEdit(inp);
}, true);

resultsEl.addEventListener("keydown", e => {
  if (e.key !== "Enter") return;
  const inp = e.target.closest(".count-edit");
  if (inp) inp.blur();
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

  let processedDataUrl = rawDataUrl;
  try {
    const { dataUrl: straightened, angleDeg } = await straightenImage(rawDataUrl);
    if (angleDeg !== 0) {
      processedDataUrl = straightened;
      pageLabel.textContent += ` (obrót: ${angleDeg > 0 ? "+" : ""}${angleDeg.toFixed(1)}°)`;
    }
  } catch { /* use original */ }

  let quadrants;
  try { quadrants = await splitIntoQuadrants(processedDataUrl); } catch { quadrants = null; }

  if (!quadrants) {
    const card = document.createElement("div");
    card.className = "card";
    pageGroup.appendChild(card);
    allCards.unshift(pageGroup);
    renderPage(1);
    await analyze({ name: file.name, dataUrl: processedDataUrl, label: null, cardEl: card });
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

// ── Skew correction ───────────────────────────────────────────────────────────

function pl_ocr_toGrayscale(imgData) {
  const { data, width, height } = imgData;
  const gray = new Uint8Array(width * height);
  for (let i = 0; i < gray.length; i++) {
    const j = i * 4;
    gray[i] = (data[j] * 77 + data[j + 1] * 150 + data[j + 2] * 29) >> 8;
  }
  return gray;
}

function pl_ocr_detectSkewDeg(gray, w, h) {
  const hist       = new Float64Array(181);
  const EDGE_THRESH = 20;

  for (let y = 1; y < h - 1; y++) {
    for (let x = 1; x < w - 1; x++) {
      const gx =
        -gray[(y - 1) * w + (x - 1)] + gray[(y - 1) * w + (x + 1)]
        - 2 * gray[y * w + (x - 1)] + 2 * gray[y * w + (x + 1)]
        - gray[(y + 1) * w + (x - 1)] + gray[(y + 1) * w + (x + 1)];
      const gy =
        -gray[(y - 1) * w + (x - 1)] - 2 * gray[(y - 1) * w + x] - gray[(y - 1) * w + (x + 1)]
        + gray[(y + 1) * w + (x - 1)] + 2 * gray[(y + 1) * w + x] + gray[(y + 1) * w + (x + 1)];

      const mag = Math.sqrt(gx * gx + gy * gy);
      if (mag < EDGE_THRESH) continue;

      let angleDeg = Math.atan2(gy, gx) * 180 / Math.PI; // -180..180
      if (angleDeg < 0) angleDeg += 180;                  // fold to 0..180
      const bin = Math.min(180, Math.max(0, Math.round(angleDeg)));
      hist[bin] += mag;
    }
  }

  // 5-tap smoothing
  const smoothed = new Float64Array(181);
  for (let b = 0; b <= 180; b++) {
    let sum = 0, cnt = 0;
    for (let d = -2; d <= 2; d++) {
      const nb = b + d;
      if (nb >= 0 && nb <= 180) { sum += hist[nb]; cnt++; }
    }
    smoothed[b] = sum / cnt;
  }

  // Find peak in bins 70–110 (near-horizontal edges, ±20° tolerance)
  let peakBin = 90, peakVal = 0;
  for (let b = 70; b <= 110; b++) {
    if (smoothed[b] > peakVal) { peakVal = smoothed[b]; peakBin = b; }
  }

  // correction: rotate by (90 - peakBin) degrees to level horizontal edges
  return 90 - peakBin;
}

function straightenImage(dataUrl) {
  return new Promise(resolve => {
    const img = new Image();
    img.onload = () => {
      const DETECT_W = 400;
      const scale = Math.min(1, DETECT_W / img.width);
      const dw    = Math.round(img.width  * scale);
      const dh    = Math.round(img.height * scale);

      const tmp  = document.createElement("canvas");
      tmp.width  = dw; tmp.height = dh;
      const tCtx = tmp.getContext("2d");
      tCtx.drawImage(img, 0, 0, dw, dh);
      const gray    = pl_ocr_toGrayscale(tCtx.getImageData(0, 0, dw, dh));
      const skewDeg = pl_ocr_detectSkewDeg(gray, dw, dh);

      if (Math.abs(skewDeg) < 0.3) { resolve({ dataUrl, angleDeg: 0 }); return; }
      const clamped = Math.max(-20, Math.min(20, skewDeg));
      const rad     = clamped * Math.PI / 180;
      const sinA    = Math.abs(Math.sin(rad));
      const cosA    = Math.abs(Math.cos(rad));
      const newW    = Math.round(img.width  * cosA + img.height * sinA);
      const newH    = Math.round(img.width  * sinA + img.height * cosA);

      const rc  = document.createElement("canvas");
      rc.width  = newW; rc.height = newH;
      const ctx = rc.getContext("2d");
      ctx.fillStyle = "#ffffff";
      ctx.fillRect(0, 0, newW, newH);
      ctx.translate(newW / 2, newH / 2);
      ctx.rotate(rad);
      ctx.drawImage(img, -img.width / 2, -img.height / 2);

      resolve({ dataUrl: rc.toDataURL("image/jpeg", 0.92), angleDeg: clamped });
    };
    img.onerror = () => resolve({ dataUrl, angleDeg: 0 });
    img.src = dataUrl;
  });
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

function parseSessionNum(label) {
  if (!label) return null;
  const m = String(label).match(/(\d+)\s*$/);
  return m ? parseInt(m[1], 10) : null;
}

async function fetchDbScores(barcodeText, sessionLabel) {
  if (!barcodeText) return null;
  try {
    const fd = new FormData();
    fd.append("barcode_text", barcodeText);
    const sessionNum = parseSessionNum(sessionLabel);
    if (sessionNum !== null) fd.append("session", sessionNum);
    const res = await fetch(window.PL_OCR.scoresUrl, { method: "POST", body: fd });
    if (!res.ok) {
      const body = await res.text().catch(() => "");
      console.error("[OCR] fetchDbScores HTTP", res.status, body);
      return { _error: `HTTP ${res.status}`, _body: body };
    }
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch {
      console.error("[OCR] fetchDbScores JSON parse failed:", text);
      return { _error: "JSON parse", _body: text };
    }
  } catch (err) {
    console.error("[OCR] fetchDbScores fetch error:", err);
    return { _error: String(err) };
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
      const dbResult = await fetchDbScores(barcodeText, sessionLabel);
      if (dbResult && !dbResult._error) {
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
        const detail = dbResult?._error ?? "brak odpowiedzi";
        const body   = dbResult?._body  ? ` — ${dbResult._body.slice(0, 120)}` : "";
        addLog(`Nie można pobrać wyników z bazy danych. (${detail}${body})`);
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
