#!/usr/bin/env bash
# ==============================================================================
# Barangay Sta. Felomina Document Archiving System - Automated Backup Script
# Cross-platform runner for Linux, Cron, and Docker environments.
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

cd "${ROOT_DIR}"

TIMESTAMP=$(date +"%Y-%m-%d %H:%M:%S")
echo "[${TIMESTAMP}] Starting Barangay System automated backup..."

# Detect environment: Docker container vs Host with Docker vs Bare Metal
if [ -f "/.dockerenv" ] || [ -n "${CONTAINER:-}" ]; then
    echo "[INFO] Running inside Docker container."
    php artisan backup:create --no-interaction "$@"
elif command -v docker &>/dev/null && docker compose ps --services --filter "status=running" 2>/dev/null | grep -q "^app$"; then
    echo "[INFO] Running via Docker Compose app service."
    docker compose exec -T app php artisan backup:create --no-interaction "$@"
elif command -v php &>/dev/null; then
    echo "[INFO] Running via host PHP environment."
    php artisan backup:create --no-interaction "$@"
else
    echo "[ERROR] Neither PHP nor a running Docker app service could be found." >&2
    exit 1
fi

EXIT_CODE=$?

# Prune archives older than 30 days if backup directory exists
BACKUP_DIR="${ROOT_DIR}/storage/app/backups"
if [ -d "${BACKUP_DIR}" ]; then
    find "${BACKUP_DIR}" -name "backup-*.zip" -type f -mtime +30 -delete 2>/dev/null || true
fi

if [ ${EXIT_CODE} -eq 0 ]; then
    echo "[$(date +"%Y-%m-%d %H:%M:%S")] Backup completed successfully."
else
    echo "[$(date +"%Y-%m-%d %H:%M:%S")] Backup failed with exit code ${EXIT_CODE}." >&2
fi

exit ${EXIT_CODE}
