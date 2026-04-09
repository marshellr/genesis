#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

OUTPUT_FILE="/projects/genesis/infra/nginx/static/status/snapshot.html"
COMPOSE_ENV="/projects/genesis/infra/compose/.env"
BACKUP_DIR="/projects/genesis/infra/backup/db"
GENERATED_AT="$(date -u '+%Y-%m-%d %H:%M UTC')"

service_status() {
  local name="$1"
  local url="$2"
  local ok_text="$3"
  local fail_text="$4"
  local code

  code="$(curl -ksS -o /dev/null -w '%{http_code}' --max-time 15 "$url" || true)"
  if [[ "$code" =~ ^2|3 ]]; then
    printf '%s|ok|%s|%s' "$name" "$ok_text" "$code"
  else
    printf '%s|degraded|%s|%s' "$name" "$fail_text" "${code:-000}"
  fi
}

backup_snapshot() {
  local latest latest_epoch age_minutes status detail
  latest="$(find "$BACKUP_DIR" -maxdepth 1 -type f -name '*.sql.gz' -printf '%T@ %p\n' | sort -nr | head -n1 | cut -d' ' -f2- || true)"
  if [[ -z "$latest" ]]; then
    printf 'degraded|No database backup found yet.'
    return
  fi

  latest_epoch="$(stat -c %Y "$latest")"
  age_minutes="$(( ( $(date +%s) - latest_epoch ) / 60 ))"

  if (( age_minutes <= 1500 )); then
    status="ok"
    detail="Last successful DB backup: $(basename "$latest") (${age_minutes} minutes old)."
  else
    status="degraded"
    detail="Backup is older than expected: $(basename "$latest") (${age_minutes} minutes old)."
  fi

  printf '%s|%s' "$status" "$detail"
}

read_env_value() {
  local key="$1"
  grep -E "^${key}=" "$COMPOSE_ENV" | tail -n1 | cut -d= -f2- || true
}

shellr="$(service_status "shellr.net" "https://shellr.net/health" "Landing page and health endpoint respond." "Landing page health endpoint is not responding as expected.")"
dma="$(service_status "dma.shellr.net" "https://dma.shellr.net/health.php" "DMA health endpoint responds." "DMA health endpoint is currently failing.")"
docs="$(service_status "docs.shellr.net" "https://docs.shellr.net/" "Docs site is reachable through GitHub Pages." "Docs site is currently unreachable or returning an error.")"
status_surface="$(service_status "status.shellr.net" "https://status.shellr.net/status/shellr" "Public status board is reachable." "Public status board is not returning cleanly.")"
backup_info="$(backup_snapshot)"

IFS='|' read -r shellr_name shellr_state shellr_text shellr_code <<< "$shellr"
IFS='|' read -r dma_name dma_state dma_text dma_code <<< "$dma"
IFS='|' read -r docs_name docs_state docs_text docs_code <<< "$docs"
IFS='|' read -r status_name status_state status_text status_code <<< "$status_surface"
IFS='|' read -r backup_state backup_text <<< "$backup_info"

cat > "$OUTPUT_FILE" <<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>shellr status</title>
  <meta name="description" content="Public platform snapshot for shellr.net.">
  <style>
    :root {
      color-scheme: dark;
      --bg: #071012;
      --panel: #0d171a;
      --line: rgba(255,255,255,0.1);
      --text: #edf3f2;
      --muted: #9eb0ad;
      --accent: #80de6c;
      --warn: #f6c85b;
      --ok: #7ce36e;
    }
    * { box-sizing: border-box; }
    body { margin:0; min-height:100vh; font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; color:var(--text); background:
      radial-gradient(circle at top left, rgba(128, 222, 108, 0.12), transparent 28%),
      linear-gradient(180deg, #050708, var(--bg)); }
    main { width:min(1080px, calc(100% - 32px)); margin:0 auto; padding:56px 0; }
    .panel { padding:28px; border:1px solid var(--line); background:rgba(13,23,26,.94); }
    .eyebrow { margin:0 0 12px; color:var(--accent); font-size:.82rem; letter-spacing:.08em; text-transform:uppercase; }
    h1,h2,h3,p { margin:0; }
    h1 { font-size:clamp(2.2rem, 5vw, 4.4rem); line-height:.94; max-width:12ch; }
    p, li { color:var(--muted); line-height:1.7; }
    .meta { display:flex; flex-wrap:wrap; gap:12px; margin-top:18px; color:var(--muted); font-size:.95rem; }
    .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:16px; margin-top:24px; }
    .card { padding:18px; border:1px solid var(--line); background:rgba(255,255,255,.02); }
    .state { display:inline-flex; align-items:center; gap:8px; margin-bottom:12px; font-size:.82rem; text-transform:uppercase; letter-spacing:.08em; }
    .state::before { content:''; width:10px; height:10px; border-radius:50%; background:var(--warn); box-shadow:0 0 0 4px rgba(246,200,91,.08); }
    .state.ok { color:var(--ok); }
    .state.ok::before { background:var(--ok); box-shadow:0 0 0 4px rgba(124,227,110,.08); }
    .links { display:flex; flex-wrap:wrap; gap:12px; margin-top:24px; }
    .button { display:inline-flex; align-items:center; justify-content:center; min-height:46px; padding:0 16px; border:1px solid var(--line); background:rgba(255,255,255,.03); color:inherit; text-decoration:none; }
    .button.primary { background:linear-gradient(135deg, var(--accent), #63c853); border-color:transparent; color:#07100a; font-weight:700; }
    code { font-family:ui-monospace,SFMono-Regular,Consolas,monospace; color:var(--text); }
  </style>
</head>
<body>
  <main>
    <section class="panel">
      <p class="eyebrow">Public Status Snapshot</p>
      <h1>shellr platform status</h1>
      <p>This snapshot is rendered on the VM and refreshed on a schedule. It gives visitors a readable platform overview even when the full Uptime Kuma UI is not the best first entrypoint.</p>
      <div class="meta">
        <span>Generated: ${GENERATED_AT}</span>
        <span>Host: $(hostname -s)</span>
        <span>Source: live HTTP checks + backup freshness</span>
      </div>

      <div class="grid">
        <article class="card">
          <div class="state ${shellr_state}">${shellr_name}</div>
          <h2>Response ${shellr_code}</h2>
          <p>${shellr_text}</p>
        </article>
        <article class="card">
          <div class="state ${dma_state}">${dma_name}</div>
          <h2>Response ${dma_code}</h2>
          <p>${dma_text}</p>
        </article>
        <article class="card">
          <div class="state ${docs_state}">${docs_name}</div>
          <h2>Response ${docs_code}</h2>
          <p>${docs_text}</p>
        </article>
        <article class="card">
          <div class="state ${status_state}">${status_name}</div>
          <h2>Response ${status_code}</h2>
          <p>${status_text}</p>
        </article>
        <article class="card">
          <div class="state ${backup_state}">backup freshness</div>
          <h2>Daily backup path</h2>
          <p>${backup_text}</p>
        </article>
        <article class="card">
          <div class="state ok">operator note</div>
          <h2>Protected surfaces</h2>
          <p><code>grafana.shellr.net</code> stays protected behind access control and is not part of the public status board.</p>
        </article>
      </div>

      <div class="links">
        <a class="button primary" href="/status/shellr">Open full public status</a>
        <a class="button" href="https://shellr.net">Open shellr.net</a>
        <a class="button" href="https://docs.shellr.net">Open docs</a>
      </div>
    </section>
  </main>
</body>
</html>
HTML

chmod 644 "$OUTPUT_FILE"
