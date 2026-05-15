function normalizeScorecard(sc) {
  sc.ends?.forEach(end => {
    for (const row of [end.sub_row_a, end.sub_row_b]) {
      if (row?.arrows) row.arrows = row.arrows.map(v => (v === 0 || v == null) ? "M" : v);
    }
  });
  return sc;
}

function normalizeArrow(v) {
  if (v === null || v === undefined) return 0;
  const s = String(v).trim().toUpperCase();
  if (s === "X") return "X";
  if (s === "M") return "M";
  if (s === "10") return 10;
  const n = Number(s);
  return Number.isFinite(n) ? n : 0;
}

function arrowValue(v) {
  v = normalizeArrow(v);
  if (v === "X") return 10;
  if (v === "M") return 0;
  if (typeof v === "string") return parseInt(v, 10) || 0;
  if (typeof v === "number") return v;
  return 0;
}

function computeSubRow(arrows = []) {
  const normalized = arrows.map(normalizeArrow);
  const suma      = normalized.reduce((acc, a) => acc + arrowValue(a), 0);
  const xCount    = normalized.filter(a => a === "X").length;
  const tenxCount = normalized.filter(a => a === "X" || a === 10).length;
  return { calculated_suma: suma, x_count: xCount, tenx_count: tenxCount };
}

function enrichScorecard(sc) {
  let running = 0;

  sc.ends.forEach(end => {
    const aArrows = (end.sub_row_a?.arrows || []).slice(0, 3);
    const bArrows = (end.sub_row_b?.arrows || []).slice(0, 3);

    const a = computeSubRow(aArrows);
    const b = computeSubRow(bArrows);

    const razem = a.calculated_suma + b.calculated_suma;
    running += razem;

    end.sub_row_a.calculated_suma = a.calculated_suma;
    end.sub_row_a.calculated_10x  = a.tenx_count;
    end.sub_row_a.calculated_x    = a.x_count;

    end.sub_row_b.calculated_suma = b.calculated_suma;
    end.sub_row_b.calculated_10x  = b.tenx_count;
    end.sub_row_b.calculated_x    = b.x_count;

    end.calculated_razem   = razem;
    end.calculated_running = running;

    end.sub_row_a.suma_error = end.sub_row_a.recorded_suma != null && end.sub_row_a.recorded_suma !== a.calculated_suma;
    end.sub_row_a.tenx_error = end.sub_row_a.recorded_10x  != null && end.sub_row_a.recorded_10x  !== a.tenx_count;
    end.sub_row_a.x_error    = end.sub_row_a.recorded_x    != null && end.sub_row_a.recorded_x    !== a.x_count;

    end.sub_row_b.suma_error = end.sub_row_b.recorded_suma != null && end.sub_row_b.recorded_suma !== b.calculated_suma;
    end.sub_row_b.tenx_error = end.sub_row_b.recorded_10x  != null && end.sub_row_b.recorded_10x  !== b.tenx_count;
    end.sub_row_b.x_error    = end.sub_row_b.recorded_x    != null && end.sub_row_b.recorded_x    !== b.x_count;

    end.razem_error   = end.recorded_razem   != null && end.recorded_razem   !== razem;
    end.running_error = end.recorded_running != null && end.recorded_running !== running;
  });

  const grand    = sc.ends.reduce((a, e) => a + e.calculated_razem, 0);
  const grand10x = sc.ends.reduce((acc, e) => acc + (e.sub_row_a?.calculated_10x || 0) + (e.sub_row_b?.calculated_10x || 0), 0);
  const grandX   = sc.ends.reduce((acc, e) => acc + (e.sub_row_a?.calculated_x   || 0) + (e.sub_row_b?.calculated_x   || 0), 0);

  sc.calculated_grand_total = grand;
  sc.calculated_grand_10x   = grand10x;
  sc.calculated_grand_x     = grandX;

  sc.grand_10x_error = sc.recorded_grand_10x != null && sc.recorded_grand_10x !== grand10x;
  sc.grand_x_error   = sc.recorded_grand_x   != null && sc.recorded_grand_x   !== grandX;

  // Collect all per-field errors into a structured list for display
  const errors = [];
  sc.ends.forEach(end => {
    if (end.sub_row_a?.suma_error)  errors.push({ location: `End ${end.end_number} Row A — Suma`,  recorded: end.sub_row_a.recorded_suma, correct: end.sub_row_a.calculated_suma, difference: end.sub_row_a.calculated_suma - end.sub_row_a.recorded_suma });
    if (end.sub_row_a?.tenx_error)  errors.push({ location: `End ${end.end_number} Row A — 10+X`, recorded: end.sub_row_a.recorded_10x,  correct: end.sub_row_a.calculated_10x,  difference: null });
    if (end.sub_row_a?.x_error)     errors.push({ location: `End ${end.end_number} Row A — X`,    recorded: end.sub_row_a.recorded_x,    correct: end.sub_row_a.calculated_x,    difference: null });
    if (end.sub_row_b?.suma_error)  errors.push({ location: `End ${end.end_number} Row B — Suma`,  recorded: end.sub_row_b.recorded_suma, correct: end.sub_row_b.calculated_suma, difference: end.sub_row_b.calculated_suma - end.sub_row_b.recorded_suma });
    if (end.sub_row_b?.tenx_error)  errors.push({ location: `End ${end.end_number} Row B — 10+X`, recorded: end.sub_row_b.recorded_10x,  correct: end.sub_row_b.calculated_10x,  difference: null });
    if (end.sub_row_b?.x_error)     errors.push({ location: `End ${end.end_number} Row B — X`,    recorded: end.sub_row_b.recorded_x,    correct: end.sub_row_b.calculated_x,    difference: null });
    if (end.razem_error)            errors.push({ location: `End ${end.end_number} — Razem`,       recorded: end.recorded_razem,          correct: end.calculated_razem,          difference: end.calculated_razem   - end.recorded_razem });
    if (end.running_error)          errors.push({ location: `End ${end.end_number} — Running`,     recorded: end.recorded_running,        correct: end.calculated_running,        difference: end.calculated_running - end.recorded_running });
  });
  if (sc.grand_10x_error) errors.push({ location: "Grand Total — 10+X", recorded: sc.recorded_grand_10x, correct: sc.calculated_grand_10x, difference: null });
  if (sc.grand_x_error)   errors.push({ location: "Grand Total — X",    recorded: sc.recorded_grand_x,   correct: sc.calculated_grand_x,   difference: null });
  sc.errors_found = errors;

  sc.overall_valid =
    errors.length === 0 &&
    (sc.recorded_grand_total == null || sc.recorded_grand_total === grand);

  return sc;
}
