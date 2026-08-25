#!/usr/bin/env bash
#
# Proves GSD Phase 01 success criteria 1 and 5:
#   1. every compose service reports healthy
#   5. GET /api/v1/health returns 200 with all dependency checks true,
#      called from inside the Docker network
#
# Usage: make smoke
set -euo pipefail

cd "$(dirname "$0")/.."

SERVICES=(api postgres redis reverb horizon minio mailpit)

echo "==> starting the stack"
docker compose up -d --wait

failed=0

echo "==> service health"
for svc in "${SERVICES[@]}"; do
  cid="$(docker compose ps -q "$svc" || true)"
  if [ -z "$cid" ]; then
    printf '  %-10s MISSING\n' "$svc"
    failed=1
    continue
  fi
  state="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}no-healthcheck{{end}}' "$cid")"
  if [ "$state" = "healthy" ]; then
    printf '  %-10s healthy\n' "$svc"
  else
    printf '  %-10s UNHEALTHY (%s)\n' "$svc" "$state"
    failed=1
  fi
done

echo "==> health endpoint (from inside the network)"
if body="$(docker compose exec -T api curl -fsS http://localhost:8000/api/v1/health)"; then
  echo "  $body"
  echo "$body" | grep -q '"status":"ok"' || { echo "  status is not ok"; failed=1; }
else
  echo "  request failed"
  failed=1
fi

if [ "$failed" -ne 0 ]; then
  echo "==> SMOKE FAILED"
  exit 1
fi

echo "==> SMOKE OK"
