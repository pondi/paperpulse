#!/bin/bash

set -e

echo "=== Graceful Shutdown Initiated ==="
echo "Timestamp: $(date '+%Y-%m-%d %H:%M:%S')"

# Send terminate signal to Horizon - stops accepting new jobs
echo "Sending terminate signal to Horizon..."
/usr/bin/php /var/www/html/artisan horizon:terminate

# Wait for Horizon to gracefully shut down (checks status every 2s)
MAX_WAIT=120  # Maximum 2 minutes wait
ELAPSED=0

echo "Waiting for Horizon to finish current jobs..."
while [ $ELAPSED -lt $MAX_WAIT ]; do
    # Check if Horizon is still running
    if ! /usr/bin/php /var/www/html/artisan horizon:status &>/dev/null; then
        echo "Horizon has stopped gracefully after ${ELAPSED}s"
        exit 0
    fi

    echo "Horizon still processing jobs... (${ELAPSED}s elapsed)"
    sleep 2
    ELAPSED=$((ELAPSED + 2))
done

echo "WARNING: Horizon did not stop within ${MAX_WAIT}s - forcing shutdown"
exit 0
