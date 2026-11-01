<?php

return [

    /*
    |--------------------------------------------------------------------------
    | System Backup Artifact
    |--------------------------------------------------------------------------
    |
    | System backups are an operator-owned artifact: a database snapshot, the
    | application-owned media files, and a checksum manifest. They are stored
    | on a private local disk and are never exposed to merchants. Merchant
    | business exports are a separate feature (config/exports.php).
    |
    */

    'disk' => 'local',

    'path' => 'backups',

    'schema_version' => 1,

    'archive_version' => 1,

    /*
    |--------------------------------------------------------------------------
    | Disk Space Preflight
    |--------------------------------------------------------------------------
    |
    | Before writing, the target volume must fit the estimated payload (media
    | bytes plus the database size reported by the driver) multiplied by
    | `safety_margin`, plus `min_free_bytes` of headroom. When the database
    | size cannot be estimated, only `min_free_bytes` is required. The check
    | fails closed when free space cannot be read.
    |
    */

    'disk_space' => [
        'safety_margin' => (float) env('BACKUP_DISK_SAFETY_MARGIN', 1.5),
        'min_free_bytes' => (int) env('BACKUP_MIN_FREE_BYTES', 268435456),
        'temp_min_free_bytes' => (int) env('BACKUP_TEMP_MIN_FREE_BYTES', 268435456),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authenticated Encryption
    |--------------------------------------------------------------------------
    |
    | Encrypted archives use the SBX1 container: an authenticated 80-byte
    | header, a 64-byte key envelope that wraps a random per-archive data key,
    | and 1 MiB AES-256-GCM records plus an authenticated end record. Keys are
    | read only from explicitly configured files; no key is ever written to the
    | archive, the manifest, the audit log or the application config.
    |
    | `BACKUP_ENCRYPTION_KEYS` accepts a JSON object of key id => absolute key
    | file path, which is how old keys stay available for decryption/rewrap.
    |
    */

    'encryption' => [
        'enabled' => (bool) env('BACKUP_ENCRYPTION_ENABLED', false),
        'key_id' => env('BACKUP_ENCRYPTION_KEY_ID'),
        'key_file' => env('BACKUP_ENCRYPTION_KEY_FILE'),
        'keys' => json_decode((string) env('BACKUP_ENCRYPTION_KEYS', '{}'), true) ?: [],
        'chunk_size' => 1048576,
        'max_payload_bytes' => (int) env('BACKUP_ENCRYPTION_MAX_PAYLOAD_BYTES', 51539607552),
    ],

    /*
    |--------------------------------------------------------------------------
    | Off-Site Copy
    |--------------------------------------------------------------------------
    |
    | Optional upload of the finished archive to another Laravel filesystem
    | disk (S3-compatible once the operator installs the driver, or any
    | configured disk). Only encrypted archives are uploaded by default, and
    | an upload is reported as successful only after the remote size and
    | SHA-256 have been read back and matched.
    |
    */

    'offsite' => [
        'enabled' => (bool) env('BACKUP_OFFSITE_ENABLED', false),
        'disk' => env('BACKUP_OFFSITE_DISK'),
        'path' => env('BACKUP_OFFSITE_PATH', 'system-backups'),
        'require_encryption' => (bool) env('BACKUP_OFFSITE_REQUIRE_ENCRYPTION', true),
        'verify_readback' => (bool) env('BACKUP_OFFSITE_VERIFY_READBACK', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention (disabled until explicitly configured)
    |--------------------------------------------------------------------------
    |
    | Retention only ever considers files that match the system backup naming
    | pattern inside the configured backup directory. It never touches media,
    | exports, tenant data, or any other application record. `scheduled` must
    | also be true before the scheduler may run a pruning pass.
    |
    */

    'retention' => [
        'enabled' => (bool) env('BACKUP_RETENTION_ENABLED', false),
        'keep_last' => (int) env('BACKUP_RETENTION_KEEP_LAST', 7),
        'max_age_days' => (int) env('BACKUP_RETENTION_MAX_AGE_DAYS', 30),
        'scheduled' => (bool) env('BACKUP_RETENTION_SCHEDULED', false),
        'require_offsite' => (bool) env('BACKUP_RETENTION_REQUIRE_OFFSITE', false),
    ],

    'schedule' => [
        'enabled' => (bool) env('BACKUP_SCHEDULE_ENABLED', false),
        'time' => env('BACKUP_SCHEDULE_TIME', '02:30'),
        'health_time' => env('BACKUP_SCHEDULE_HEALTH_TIME', '07:00'),
        'max_age_hours' => (int) env('BACKUP_SCHEDULE_MAX_AGE_HOURS', 26),
    ],

    /*
    |--------------------------------------------------------------------------
    | Restore Safety
    |--------------------------------------------------------------------------
    |
    | Restore is CLI-only and refuses its own application databases, the
    | production environment, and any target it did not create. Targets must
    | match one of these prefixes and be confirmed twice on the command line.
    |
    */

    'restore' => [
        'allowed_prefixes' => ['restore_', 'drill_'],
        'mysql_binary' => env('BACKUP_MYSQL_BINARY'),
        'max_inline_sql_bytes' => (int) env('BACKUP_RESTORE_INLINE_SQL_BYTES', 268435456),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Snapshot
    |--------------------------------------------------------------------------
    |
    | `connection` defaults to the application's default connection. When a
    | mysqldump binary is configured and executable it is preferred because
    | `--single-transaction` gives a consistent InnoDB snapshot. Without a
    | binary, a PDO snapshot is written inside a REPEATABLE READ transaction
    | started with a consistent snapshot. Credentials are passed through a
    | temporary defaults file and never appear in the archive or manifest.
    |
    */

    'database' => [
        'connection' => env('BACKUP_DB_CONNECTION'),
        'dump_binary' => env('BACKUP_DUMP_BINARY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    |
    | Only local disks are bundled. Remote disks (for example Cloudinary) are
    | recorded as skipped in the manifest. Excluded paths match either the
    | full relative path or any single path segment, so generated archives,
    | temporary files, caches and logs stay out of the backup.
    |
    */

    'media' => [
        'disks' => ['public'],
        'exclude' => [
            'exports',
            'backups',
            'tmp',
            'temp',
            'cache',
            'logs',
            '*.tmp',
            '*.log',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Restore Compatibility
    |--------------------------------------------------------------------------
    |
    | Preflight validation fails closed: an archive is only accepted when its
    | numeric schema and archive versions are listed here. Restore execution
    | itself remains an operator-only, test-verified procedure.
    |
    */

    'supported_schema_versions' => [1],

    'supported_archive_versions' => [1],

    /*
    |--------------------------------------------------------------------------
    | Backup Audit Log
    |--------------------------------------------------------------------------
    |
    | Operator audit records are written to a dedicated channel. The channel
    | must never be public, and records are whitelisted to operational fields
    | so database credentials and environment secrets cannot leak into it.
    |
    */

    'log_channel' => env('BACKUP_LOG_CHANNEL', 'backups'),
];
