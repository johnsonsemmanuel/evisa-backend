<?php

return [

    'encryption_password' => env('BACKUP_ENCRYPTION_PASSWORD'),

    'local_path' => env('BACKUP_DIR', '/var/backups/evisa'),

    's3_bucket' => env('BACKUP_S3_BUCKET'),

    's3_prefix' => 'database',

    'retention_days_local' => (int) env('BACKUP_RETENTION_DAYS_LOCAL', 30),

    'retention_days_offsite' => (int) env('BACKUP_RETENTION_DAYS_OFFSITE', 365),

    'test_connection' => 'mysql_restore_test',

    /*
    | File storage backup (uploaded documents: passport, visa docs)
    */
    'files_source' => env('BACKUP_FILES_SOURCE'), // default: storage_path('app/private')
    'files_s3_bucket' => env('BACKUP_FILES_S3_BUCKET', env('BACKUP_S3_BUCKET')),
    'files_s3_prefix' => env('BACKUP_FILES_S3_PREFIX', 'files'),
    'files_rsync_dest' => env('BACKUP_FILES_RSYNC_DEST'),
    'files_last_success_marker' => 'backup-files-last-success.txt',
    'files_max_age_hours' => (int) env('BACKUP_FILES_MAX_AGE_HOURS', 5),

];
