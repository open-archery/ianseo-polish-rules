function arrowChip(v, editMeta = null) {
  const s   = String(v).toUpperCase();
  const cls = s === "X"               ? "chip chip-x"
            : (v === 10 || s === "10") ? "chip chip-10"
            : s === "M"               ? "chip chip-m"
            :                           "chip chip-norm";
  if (editMeta != null) {
    const { endIdx, row, arrowIdx, isEdited = false } = editMeta;
    const editedCls = isEdited ? " chip--corrected" : "";
    if (endIdx !== undefined) {
      return `<span class="${cls} chip--editable${editedCls}" data-end="${endIdx}" data-row="${row}" data-arrow="${arrowIdx}" title="Kliknij, aby poprawić">${s}</span>`;
    }
    return `<span class="${cls}${editedCls}">${s}</span>`;
  }
  return `<span class="${cls}">${s}</span>`;
}

function valCell(recorded, calculated, error) {
  if (recorded == null && (calculated == null || calculated === 0))
    return `<span class="dim">—</span>`;
  if (error)
    return `<span class="val-err">${recorded ?? "?"}</span><span class="val-fix">${calculated}</span>`;
  if (recorded == null)
    return `<span class="val-calc">${calculated}</span>`;
  return `<span class="val-ok">${recorded}</span>`;
}

function cardBadgeClass(status, isValid) {
  if (status === "loading") return "sbadge sbadge--loading";
  if (status === "error" || status === "raw") return "sbadge sbadge--error";
  return isValid ? "sbadge sbadge--valid" : "sbadge sbadge--error";
}

function cardBadgeText(status, isValid, errCount) {
  if (status === "loading") return `<span class="spin">⟳</span> Analizowanie…`;
  if (status === "error")   return "✗ Błąd";
  if (status === "raw")     return "✗ Błąd parsowania";
  return isValid ? "✓ Poprawna" : `✗ ${errCount} błąd${errCount === 1 ? "" : errCount < 5 ? "y" : "ów"}`;
}

function renderDbRow(d) {
  if (!d.db_looked_up) {
    return "";
  }
  if (!d.db_found) {
    return `<div class="db-row"><span class="db-label">Baza danych:</span><span class="db-notfound">Zawodnik nie znaleziony (${d.db_bib ?? "?"})</span></div>`;
  }

  const dbScore = d.db_score;
  const calc    = d.calculated_grand_total;
  const session = d.db_session;

  let scoreHtml;
  if (dbScore == null || dbScore === 0) {
    scoreHtml = `<span class="db-pending">— (nie wprowadzono sesji ${session})</span>`;
  } else if (dbScore === calc) {
    scoreHtml = `<span class="db-ok">${dbScore} ✓</span>`;
  } else {
    scoreHtml = `<span class="db-mismatch">DB: ${dbScore} ≠ OCR: ${calc} (różnica: ${dbScore - calc > 0 ? "+" : ""}${dbScore - calc})</span>`;
  }

  return `<div class="db-row">
    <span class="db-label">Baza danych (sesja ${session}):</span>
    ${scoreHtml}
  </div>`;
}

function renderDetails(d, { editable = false, editedCells = null } = {}) {
  const errCount = d.errors_found?.length || 0;
  const diff = d.calculated_grand_total != null && d.recorded_grand_total != null
    ? d.calculated_grand_total - d.recorded_grand_total : null;

  const totHtml = `
    <div class="tot-row">
      <div class="tot-box">
        <div class="tot-label">Obliczone (OCR)</div>
        <div class="tot-val ${errCount > 0 ? "tot-val--error" : "tot-val--valid"}">${d.calculated_grand_total ?? "—"}</div>
      </div>
      <div class="tot-sep">→</div>
      <div class="tot-box">
        <div class="tot-label">Zapisane na karcie</div>
        <div class="tot-val tot-val--dim">${d.recorded_grand_total ?? "—"}</div>
      </div>
      ${diff != null && errCount > 0 ? `
        <div class="tot-sep">→</div>
        <div class="tot-box">
          <div class="tot-label">Różnica</div>
          <div class="tot-val tot-val--warn">${diff > 0 ? "+" : ""}${diff}</div>
        </div>` : ""}
      <div class="tot-fill"></div>
      <div class="tot-box tot-box--sm">
        <div class="tot-label">10+X</div>
        <div class="tot-val tot-val--lg ${d.grand_10x_error ? "tot-val--error" : "tot-val--valid"}">
          ${d.calculated_grand_10x ?? "—"}
          ${d.grand_10x_error ? `<span class="tot-sub-err">karta:${d.recorded_grand_10x}</span>` : ""}
        </div>
      </div>
      <div class="tot-box tot-box--sm">
        <div class="tot-label">X</div>
        <div class="tot-val tot-val--lg ${d.grand_x_error ? "tot-val--error" : "tot-val--warn"}">
          ${d.calculated_grand_x ?? "—"}
          ${d.grand_x_error ? `<span class="tot-sub-err">karta:${d.recorded_grand_x}</span>` : ""}
        </div>
      </div>
    </div>`;

  const dbHtml = renderDbRow(d);

  let errHtml = "";
  if (errCount > 0) {
    errHtml = `
      <div class="err-sec">
        <div class="sec-lbl">⚠ Wykryte błędy</div>
        ${d.errors_found.map(e => `
          <div class="err-row">
            <span class="err-loc">${e.location}</span>
            <span class="err-detail">Zapisano <b class="val-err">${e.recorded}</b> → poprawnie <b class="val-green">${e.correct}</b>
              ${e.difference != null ? `<span class="err-diff">(${e.difference > 0 ? "+" : ""}${e.difference})</span>` : ""}
            </span>
          </div>`).join("")}
      </div>`;
  }

  let tableHtml = "";
  if (d.ends?.length > 0) {
    const rows = d.ends.map((end, endIdx) => {
      const aErr = end.sub_row_a?.suma_error || end.sub_row_a?.tenx_error || end.sub_row_a?.x_error;
      const bErr = end.sub_row_b?.suma_error || end.sub_row_b?.tenx_error || end.sub_row_b?.x_error || end.razem_error || end.running_error;
      const aArrows = (end.sub_row_a?.arrows || []).map((v, arrowIdx) => {
        const isEdited = editedCells?.has(`${endIdx}-a-${arrowIdx}`) ?? false;
        return arrowChip(v, editable ? { endIdx, row: "a", arrowIdx, isEdited } : isEdited ? { isEdited } : null);
      }).join("");
      const bArrows = (end.sub_row_b?.arrows || []).map((v, arrowIdx) => {
        const isEdited = editedCells?.has(`${endIdx}-b-${arrowIdx}`) ?? false;
        return arrowChip(v, editable ? { endIdx, row: "b", arrowIdx, isEdited } : isEdited ? { isEdited } : null);
      }).join("");
      return `
        <tr class="${aErr ? "row-error" : ""}">
          <td rowspan="2" class="td-end">${end.end_number}</td>
          <td class="td-row">A</td>
          <td>${aArrows}</td>
          <td>${valCell(end.sub_row_a?.recorded_suma, end.sub_row_a?.calculated_suma, end.sub_row_a?.suma_error)}</td>
          <td class="dim">—</td><td class="dim">—</td>
          <td>${valCell(end.sub_row_a?.recorded_10x, end.sub_row_a?.calculated_10x, end.sub_row_a?.tenx_error)}</td>
          <td>${valCell(end.sub_row_a?.recorded_x,   end.sub_row_a?.calculated_x,   end.sub_row_a?.x_error)}</td>
          <td>${aErr ? '<span class="e-chip">✗</span>' : '<span class="o-chip">✓</span>'}</td>
        </tr>
        <tr class="end-sep ${bErr ? "row-error" : ""}">
          <td class="td-row">B</td>
          <td>${bArrows}</td>
          <td>${valCell(end.sub_row_b?.recorded_suma, end.sub_row_b?.calculated_suma, end.sub_row_b?.suma_error)}</td>
          <td>${valCell(end.recorded_razem,           end.calculated_razem,           end.razem_error)}</td>
          <td>${valCell(end.recorded_running,         end.calculated_running,         end.running_error)}</td>
          <td>${valCell(end.sub_row_b?.recorded_10x, end.sub_row_b?.calculated_10x, end.sub_row_b?.tenx_error)}</td>
          <td>${valCell(end.sub_row_b?.recorded_x,   end.sub_row_b?.calculated_x,   end.sub_row_b?.x_error)}</td>
          <td>${bErr || aErr ? '<span class="e-chip">✗</span>' : '<span class="o-chip">✓</span>'}</td>
        </tr>`;
    }).join("");

    tableHtml = `
      <div class="sec-lbl">Seria po serii</div>
      <div class="tbl-wrap">
        <table class="ends">
          <thead><tr>
            <th>Seria</th><th>Wiersz</th><th class="th-arrows">Strzały</th>
            <th>Suma</th><th>Razem</th><th>Bieżący</th><th>10+X</th><th>X</th><th>OK?</th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
  }

  const notesHtml = d.notes ? `<div class="notes">📝 ${d.notes}</div>` : "";
  return `<div class="details">${totHtml}${dbHtml}${errHtml}${tableHtml}${notesHtml}</div>`;
}
