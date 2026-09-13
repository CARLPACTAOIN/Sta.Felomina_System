#!/usr/bin/env bash
# ==============================================================================
# Barangay Sta. Felomina Document Archiving System - Restore Script
# Runner for Linux and Docker environments with maintenance mode protection.
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

cd "${ROOT_DIR}"

echo "========================================================================"
echo "Barangay Sta. Felomina Document Archiving System - Disaster Recovery"
echo "========================================================================"

# Determine execution prefix
EXEC_PREFIX=""
if [ -f "/.dockerenv" ] || [ -n "${CONTAINER:-}" ]; then
    EXEC_PREFIX=""
elif command -v docker &>/dev/null && docker compose ps --services --filter "status=running" 2>/dev/null | grep -q "^app$"; then
    EXEC_PREFIX="docker compose exec -T app"
elif command -v php &>/dev/null; then
    EXEC_PREFIX=""
else
    echo "[ERROR] Neither PHP nor a running Docker app service could be found." >&2
    exit 1
fi

# Enable maintenance mode if application is running
echo "[INFO] Putting application into maintenance mode..."
${EXEC_PREFIX} php artisan down --render="errors::503" || true

# Execute backup restoration
RESTORE_STATUS=0
if [ -n "${EXEC_PREFIX}" ]; then
    ${EXEC_PREFIX} php artisan backup:restore "$@" || RESTORE_STATUS=$?
else
    php artisan backup:restore "$@" || RESTORE_STATUS=$?
fi

# Bring application back up
echo "[INFO] Bringing application back online..."
${EXEC_PREFIX} php artisan up || true

if [ ${RESTORE_STATUS} -eq 0 ]; then
    echo "[SUCCESS] Restoration completed successfully."
else
    echo "[ERROR] Restoration failed with status code ${RESTORE_STATUS}." >&2
fi

exit ${RESTORE_STATUS}
