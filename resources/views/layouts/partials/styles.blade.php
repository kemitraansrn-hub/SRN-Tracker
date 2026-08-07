<style>
  :root {
    --bg: #F6F5F2; --surface: #FFFFFF; --surface-alt: #FBFAF8;
    --ink: #2B2620; --ink-muted: #756E63; --ink-faint: #A39C8E; --line: #E7E3DC;
    --accent: #6E5C7D; --accent-ink: #4B3F57; --accent-soft: #EBE5EF;
    --good: #4C7A5C; --good-soft: #E1EBE3;
    --warn: #A8783A; --warn-soft: #F3E9D8;
    --critical: #A85248; --critical-soft: #F5E1DF;
    --shadow: 0 1px 2px rgba(43, 38, 32, 0.04), 0 4px 16px rgba(43, 38, 32, 0.05);
    --radius: 10px;
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --bg: #1B1917; --surface: #232120; --surface-alt: #292624;
      --ink: #ECE8E1; --ink-muted: #A79E90; --ink-faint: #766E63; --line: #363230;
      --accent: #B7A0C6; --accent-ink: #E4D9EB; --accent-soft: #362E3D;
      --good: #83B896; --good-soft: #24322A;
      --warn: #D8AC6E; --warn-soft: #3A2F1E;
      --critical: #E29A92; --critical-soft: #3B2523;
      --shadow: 0 1px 2px rgba(0,0,0,0.2), 0 8px 24px rgba(0,0,0,0.28);
    }
  }
  * { box-sizing: border-box; }
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
  }
  .btn {
    font-family: inherit; font-size: 13px; border-radius: 8px;
    border: 1px solid var(--line); background: var(--surface); color: var(--ink);
    padding: 9px 14px; cursor: pointer;
  }
  .btn:hover { border-color: var(--ink-faint); }
  .btn-primary { background: var(--accent); border-color: var(--accent); color: var(--surface); font-weight: 600; width: 100%; }
  .btn-primary:hover { background: var(--accent-ink); border-color: var(--accent-ink); }
  .field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
  .field label { font-size: 12px; font-weight: 600; color: var(--ink-muted); }
  .field input {
    font-family: inherit; font-size: 14px; padding: 10px 12px;
    border: 1px solid var(--line); border-radius: 8px; background: var(--surface-alt); color: var(--ink);
  }
  .field input:focus { outline: 2px solid var(--accent); outline-offset: 1px; background: var(--surface); }
  .field-error { font-size: 12px; color: var(--critical); margin-top: 4px; }
  .alert-error {
    background: var(--critical-soft); color: var(--critical);
    border-radius: 8px; padding: 10px 14px; font-size: 12.5px; margin-bottom: 16px;
  }
</style>
