<style>
  :root {
    --bg: #FFFFFF; --surface: #FFFFFF; --surface-alt: #FAFAFA;
    --ink: #2B2620; --ink-muted: #756E63; --ink-faint: #A39C8E; --line: #EBEBEB;
    --accent: #F2994A; --accent-ink: #B8641E; --accent-soft: #FCEEDD;
    --brand-blue: #1D63C4; --brand-blue-soft: #E7F0FC;
    --table-head-bg: #F2F5F9; --table-head-ink: #1D3557; --table-head-current: #C9D5E1;
    --highlight: #C97C3D; --highlight-soft: #F5E6D8;
    --good: #4C7A5C; --good-soft: #E1EBE3;
    --warn: #A8783A; --warn-soft: #F3E9D8;
    --critical: #A85248; --critical-soft: #F5E1DF;
    --chart-1: #A8C3E6; --chart-2: #F0C9A0; --chart-3: #A8D0B4;
    --chart-4: #E8B4B8; --chart-5: #C3B8E0; --chart-6: #E8D9A8;
    --shadow: 0 2px 4px rgba(43, 38, 32, 0.06), 0 8px 20px rgba(43, 38, 32, 0.08);
    --shadow-hover: 0 8px 16px rgba(43, 38, 32, 0.10), 0 20px 40px rgba(43, 38, 32, 0.14);
    --radius: 10px;
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --bg: #1B1917; --surface: #232120; --surface-alt: #292624;
      --ink: #ECE8E1; --ink-muted: #A79E90; --ink-faint: #766E63; --line: #363230;
      --accent: #91ADD6; --accent-ink: #D9E4F5; --accent-soft: #29354A;
      --highlight: #E0A868; --highlight-soft: #3D2F1E;
      --good: #83B896; --good-soft: #24322A;
      --warn: #D8AC6E; --warn-soft: #3A2F1E;
      --critical: #E29A92; --critical-soft: #3B2523;
      --chart-1: #6B8FC0; --chart-2: #D4A876; --chart-3: #7FAF92;
      --chart-4: #C98B90; --chart-5: #9B8FC7; --chart-6: #C7B87A;
      --shadow: 0 2px 4px rgba(0,0,0,0.28), 0 10px 26px rgba(0,0,0,0.34);
      --shadow-hover: 0 10px 20px rgba(0,0,0,0.36), 0 24px 48px rgba(0,0,0,0.44);
    }
  }
  * { box-sizing: border-box; }
  * { scrollbar-width: thin; scrollbar-color: var(--ink-faint) transparent; }
  *::-webkit-scrollbar { width: 8px; height: 8px; }
  *::-webkit-scrollbar-track { background: transparent; }
  *::-webkit-scrollbar-thumb { background: var(--ink-faint); border-radius: 8px; }
  *::-webkit-scrollbar-thumb:hover { background: var(--ink-muted); }
  body {
    margin: 0; background: var(--bg); color: var(--ink);
    font-family: -apple-system, "Segoe UI", "SF Pro Text", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 14.5px; line-height: 1.5; -webkit-font-smoothing: antialiased;
  }
  .display {
    font-family: -apple-system, "Segoe UI Semibold", "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-weight: 700; letter-spacing: -0.02em;
  }
  .tnum { font-variant-numeric: tabular-nums; }
  a { color: inherit; }
  .card {
    background: var(--surface); border: 1px solid var(--line);
    border-radius: var(--radius); box-shadow: var(--shadow); padding: 18px 20px;
    transition: transform 0.16s ease, box-shadow 0.16s ease;
  }
  .card:not(.table-card):hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-hover);
  }
  .btn {
    font-family: inherit; font-size: 13px; font-weight: 500; border-radius: 10px;
    border: 1px solid var(--line); background: var(--surface); color: var(--ink);
    padding: 9px 14px; cursor: pointer;
    transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease, transform 0.08s ease;
  }
  .btn:hover { border-color: var(--ink-faint); box-shadow: 0 1px 2px rgba(43, 38, 32, 0.06); }
  .btn:active { transform: translateY(1px); }
  .btn:focus-visible { outline: none; box-shadow: 0 0 0 3px var(--accent-soft); border-color: var(--accent); }
  .btn-primary { background: var(--accent); border-color: var(--accent); color: var(--surface); font-weight: 600; width: 100%; }
  .btn-primary:hover { background: var(--accent-ink); border-color: var(--accent-ink); box-shadow: 0 3px 10px rgba(76, 111, 160, 0.28); }
  .field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
  .field label { font-size: 12px; font-weight: 600; color: var(--ink-muted); }

  /* Base input styling applies app-wide (not just inside .field) so every
     text/date/number/select input gets the same modern treatment, whether
     or not it's wrapped in the .field helper. */
  input[type="text"], input[type="email"], input[type="password"], input[type="number"],
  input[type="date"], input[type="datetime-local"], input[type="search"], input[type="tel"],
  input[type="url"], select, textarea, .field input, .field select {
    font-family: inherit; font-size: 14px; padding: 10px 13px;
    border: 1px solid var(--line); border-radius: 10px; background-color: var(--surface-alt); color: var(--ink);
    transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
  }
  input[type="text"]:hover, input[type="email"]:hover, input[type="password"]:hover, input[type="number"]:hover,
  input[type="date"]:hover, input[type="datetime-local"]:hover, input[type="search"]:hover, input[type="tel"]:hover,
  input[type="url"]:hover, select:hover, textarea:hover, .field input:hover, .field select:hover {
    border-color: var(--ink-faint);
  }
  input[type="text"]:focus, input[type="email"]:focus, input[type="password"]:focus, input[type="number"]:focus,
  input[type="date"]:focus, input[type="datetime-local"]:focus, input[type="search"]:focus, input[type="tel"]:focus,
  input[type="url"]:focus, select:focus, textarea:focus,
  .field input:focus, .field select:focus {
    outline: none; border-color: var(--accent); background-color: var(--surface);
    box-shadow: 0 0 0 3px var(--accent-soft);
  }
  input:disabled, select:disabled, textarea:disabled, input[readonly] {
    opacity: 0.7; cursor: not-allowed;
  }
  html select {
    appearance: none; -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%23A39C8E' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 10px center; background-size: 16px;
    padding-right: 34px !important;
  }
  input[type="checkbox"], input[type="radio"] { accent-color: var(--accent); width: 15px; height: 15px; cursor: pointer; }
  .field-error { font-size: 12px; color: var(--critical); margin-top: 4px; }
  .alert-error {
    background: var(--critical-soft); color: var(--critical);
    border-radius: 8px; padding: 10px 14px; font-size: 12.5px; margin-bottom: 16px;
  }
  .alert-success {
    background: var(--good-soft); color: var(--good);
    border-radius: 8px; padding: 10px 14px; font-size: 12.5px; margin-bottom: 16px;
  }
  .select-pill {
    font-family: inherit; font-size: 12.5px; border-radius: 100px; border: 1px solid var(--line);
    background: var(--surface); color: var(--ink); padding: 7px 30px 7px 14px;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
  }
  .select-pill:hover { border-color: var(--ink-faint); }
  .select-pill:focus {
    outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft);
  }
  .card-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 16px; }
  .card-title { font-size: 14.5px; font-weight: 600; }
  .card-hint { font-size: 11.5px; color: var(--ink-muted); }
  .table-scroll { overflow-x: auto; overflow-y: auto; max-height: 62vh; }
  table { width: 100%; border-collapse: collapse; }
  thead th {
    position: sticky; top: 0; z-index: 1;
    text-align: left; font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.06em;
    color: var(--table-head-ink); font-weight: 600; padding: 10px 20px;
    border-bottom: 1px solid var(--line); background: var(--table-head-bg);
  }
  tbody td { padding: 12px 20px; border-bottom: 1px solid var(--line); font-size: 13px; }
  tbody tr:last-child td { border-bottom: none; }
  .chip { display: inline-flex; align-items: center; gap: 5px; padding: 2px 8px; border-radius: 100px; font-size: 11px; font-weight: 600; white-space: nowrap; }
  .chip-good { background: var(--good-soft); color: var(--good); }
  .chip-warn { background: var(--warn-soft); color: var(--warn); }
  .chip-critical { background: var(--critical-soft); color: var(--critical); }
  .chip-accent { background: var(--accent-soft); color: var(--accent-ink); }
  .chip-highlight { background: var(--highlight-soft); color: var(--highlight); }
  .chip-neutral { background: var(--surface-alt); color: var(--ink-muted); border: 1px solid var(--line); }
  .reveal-toggle {
    display: inline-flex; align-items: center; gap: 3px; cursor: pointer;
    color: var(--ink-muted); font-size: 11px; font-weight: 500;
    border-bottom: 1px dashed var(--ink-faint); line-height: 1.4;
    transition: color 0.14s ease, border-color 0.14s ease;
  }
  .reveal-toggle:hover { color: var(--accent-ink); border-color: var(--accent); }
  .reveal-toggle svg { width: 10px; height: 10px; flex: none; transition: transform 0.15s ease; }
  .reveal-toggle.is-open svg { transform: rotate(180deg); }
  .dropzone {
    border: 1.5px dashed var(--line); border-radius: var(--radius); padding: 34px 24px;
    text-align: center; background: var(--surface-alt); cursor: pointer;
  }
  .dropzone:hover { border-color: var(--accent); background: var(--accent-soft); }
  .dropzone-title { font-size: 13.5px; font-weight: 600; margin-bottom: 4px; }
  .dropzone-hint { font-size: 12px; color: var(--ink-muted); }
  .field-row { display: flex; gap: 12px; margin-bottom: 18px; flex-wrap: wrap; }
  .modal-overlay {
    position: fixed; inset: 0; background: rgba(20, 18, 15, 0.45);
    display: flex; align-items: center; justify-content: center; z-index: 50; padding: 20px;
  }
  .modal-box {
    background: var(--surface); border-radius: 12px; border: 1px solid var(--line);
    box-shadow: var(--shadow); max-width: 440px; width: 100%; padding: 24px;
  }
  .modal-title { font-size: 15.5px; font-weight: 700; margin-bottom: 8px; letter-spacing: -0.01em; }
  .modal-body { font-size: 13px; color: var(--ink-muted); line-height: 1.6; margin-bottom: 20px; }
  .modal-body b { color: var(--ink); font-weight: 600; }
  .modal-actions { display: flex; justify-content: flex-end; gap: 10px; }
  .btn-danger { background: var(--critical); border-color: var(--critical); color: var(--surface); font-weight: 600; }
  .growth-pos { color: var(--good); }
  .growth-neg { color: var(--critical); }
</style>
