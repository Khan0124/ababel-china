#!/bin/bash

################################################################################
# Automatic Backup Script for China Office Accounting System
# 
# @author System Improvement Update
# @date 2025-01-10
# @description Automated backup script to run via cron job
#              Creates compressed backups of database and files
#              Maintains backup rotation (keeps last 30 days)
################################################################################

# Configuration
BACKUP_DIR="/www/wwwroot/khxtech.xyz/storage/backups"
DB_NAME="khan"
DB_USER="khan"
DB_PASS="Khan@70990100"
SITE_DIR="/www/wwwroot/khxtech.xyz"
LOG_FILE="/www/wwwroot/khxtech.xyz/logs/backup.log"
RETENTION_DAYS=30
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"
mkdir -p "$(dirname "$LOG_FILE")"

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# Function to check disk space
check_disk_space() {
    AVAILABLE_SPACE=$(df "$BACKUP_DIR" | awk 'NR==2 {print $4}')
    REQUIRED_SPACE=1048576  # 1GB in KB
    
    if [ "$AVAILABLE_SPACE" -lt "$REQUIRED_SPACE" ]; then
        log_message "ERROR: Insufficient disk space for backup. Available: ${AVAILABLE_SPACE}KB"
        exit 1
    fi
}

# Start backup process
log_message "===== Starting automatic backup process ====="

# Check disk space
check_disk_space

# 1. Database Backup
log_message "Backing up database: $DB_NAME"
DB_BACKUP_FILE="$BACKUP_DIR/db_backup_${DATE}.sql"

mysqldump -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$DB_BACKUP_FILE" 2>/dev/null

if [ $? -eq 0 ]; then
    # Compress the database backup
    gzip "$DB_BACKUP_FILE"
    log_message "Database backup completed: ${DB_BACKUP_FILE}.gz"
    
    # Calculate backup size
    DB_SIZE=$(du -h "${DB_BACKUP_FILE}.gz" | cut -f1)
    log_message "Database backup size: $DB_SIZE"
else
    log_message "ERROR: Database backup failed"
    exit 1
fi

# 2. Files Backup (Important directories only)
log_message "Backing up application files"
FILES_BACKUP_FILE="$BACKUP_DIR/files_backup_${DATE}.tar.gz"

# Create a list of directories to backup
BACKUP_DIRS=(
    "app"
    "config"
    "lang"
    "migrations"
    "public/assets"
    ".env"
)

# Change to site directory
cd "$SITE_DIR" || exit 1

# Create tar archive with compression
tar -czf "$FILES_BACKUP_FILE" "${BACKUP_DIRS[@]}" 2>/dev/null

if [ $? -eq 0 ]; then
    log_message "Files backup completed: $FILES_BACKUP_FILE"
    
    # Calculate backup size
    FILES_SIZE=$(du -h "$FILES_BACKUP_FILE" | cut -f1)
    log_message "Files backup size: $FILES_SIZE"
else
    log_message "ERROR: Files backup failed"
    exit 1
fi

# 3. Create backup manifest
MANIFEST_FILE="$BACKUP_DIR/backup_manifest_${DATE}.txt"
cat > "$MANIFEST_FILE" << EOF
Backup Manifest
===============
Date: $(date '+%Y-%m-%d %H:%M:%S')
Database: ${DB_BACKUP_FILE}.gz
Files: $FILES_BACKUP_FILE
DB Size: $DB_SIZE
Files Size: $FILES_SIZE
System Version: $(git rev-parse HEAD 2>/dev/null || echo "Not a git repository")
PHP Version: $(php -v | head -n 1)
MySQL Version: $(mysql --version | awk '{print $5}' | sed 's/,//')
EOF

log_message "Backup manifest created: $MANIFEST_FILE"

# 4. Clean old backups (keep last RETENTION_DAYS days)
log_message "Cleaning old backups (keeping last $RETENTION_DAYS days)"

# Find and delete old database backups
find "$BACKUP_DIR" -name "db_backup_*.sql.gz" -type f -mtime +$RETENTION_DAYS -delete 2>/dev/null
DELETED_DB=$(find "$BACKUP_DIR" -name "db_backup_*.sql.gz" -type f -mtime +$RETENTION_DAYS 2>/dev/null | wc -l)

# Find and delete old file backups
find "$BACKUP_DIR" -name "files_backup_*.tar.gz" -type f -mtime +$RETENTION_DAYS -delete 2>/dev/null
DELETED_FILES=$(find "$BACKUP_DIR" -name "files_backup_*.tar.gz" -type f -mtime +$RETENTION_DAYS 2>/dev/null | wc -l)

# Find and delete old manifests
find "$BACKUP_DIR" -name "backup_manifest_*.txt" -type f -mtime +$RETENTION_DAYS -delete 2>/dev/null
DELETED_MANIFEST=$(find "$BACKUP_DIR" -name "backup_manifest_*.txt" -type f -mtime +$RETENTION_DAYS 2>/dev/null | wc -l)

log_message "Cleaned up old backups: DB=$DELETED_DB, Files=$DELETED_FILES, Manifests=$DELETED_MANIFEST"

# 5. Verify backup integrity
log_message "Verifying backup integrity"

# Test database backup
gunzip -t "${DB_BACKUP_FILE}.gz" 2>/dev/null
if [ $? -eq 0 ]; then
    log_message "Database backup integrity: OK"
else
    log_message "WARNING: Database backup integrity check failed"
fi

# Test files backup
tar -tzf "$FILES_BACKUP_FILE" > /dev/null 2>&1
if [ $? -eq 0 ]; then
    log_message "Files backup integrity: OK"
else
    log_message "WARNING: Files backup integrity check failed"
fi

# 6. Send notification (if email is configured)
if [ -n "$NOTIFICATION_EMAIL" ]; then
    SUBJECT="Backup Report - $(date '+%Y-%m-%d')"
    BODY="Automatic backup completed successfully.
    
Database Backup: ${DB_BACKUP_FILE}.gz ($DB_SIZE)
Files Backup: $FILES_BACKUP_FILE ($FILES_SIZE)
Old Backups Cleaned: DB=$DELETED_DB, Files=$DELETED_FILES

Check log file for details: $LOG_FILE"
    
    echo "$BODY" | mail -s "$SUBJECT" "$NOTIFICATION_EMAIL" 2>/dev/null
    
    if [ $? -eq 0 ]; then
        log_message "Notification email sent to $NOTIFICATION_EMAIL"
    fi
fi

# 7. Update last backup timestamp
echo "$(date '+%Y-%m-%d %H:%M:%S')" > "$BACKUP_DIR/.last_backup"

log_message "===== Backup process completed successfully ====="

# Exit successfully
exit 0