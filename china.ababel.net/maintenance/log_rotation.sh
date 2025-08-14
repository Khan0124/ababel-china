#!/bin/bash
# Log rotation script for Ababel logistics system

LOG_DIR="/www/wwwroot/khxtech.xyz/logs"
MAX_SIZE=10485760  # 10MB in bytes
ARCHIVE_DIR="$LOG_DIR/archive"

# Create archive directory if it doesn't exist
mkdir -p "$ARCHIVE_DIR"

# Function to rotate a log file
rotate_log() {
    local logfile=$1
    local filename=$(basename "$logfile")
    local timestamp=$(date +%Y%m%d_%H%M%S)
    
    if [ -f "$logfile" ]; then
        size=$(stat -c%s "$logfile" 2>/dev/null || stat -f%z "$logfile" 2>/dev/null)
        
        if [ "$size" -gt "$MAX_SIZE" ]; then
            echo "Rotating $filename (size: $size bytes)"
            cp "$logfile" "$ARCHIVE_DIR/${filename}.${timestamp}"
            echo "" > "$logfile"
            
            # Compress archived log
            gzip "$ARCHIVE_DIR/${filename}.${timestamp}"
        fi
    fi
}

# Rotate all log files
for logfile in "$LOG_DIR"/*.log; do
    rotate_log "$logfile"
done

# Clean up old archives (older than 30 days)
find "$ARCHIVE_DIR" -name "*.gz" -mtime +30 -delete

echo "Log rotation completed at $(date)"