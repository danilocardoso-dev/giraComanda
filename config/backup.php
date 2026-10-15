<?php

return [
    'disk' => env('BACKUP_DISK', 'local'),
    'external_disk' => env('BACKUP_EXTERNAL_DISK'),
    'directory' => env('BACKUP_DIRECTORY', 'backups/database'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
    'maximum_files' => (int) env('BACKUP_MAXIMUM_FILES', 30),
    'timeout_seconds' => (int) env('BACKUP_TIMEOUT_SECONDS', 300),
    'mysql_dump_binary' => env(
        'MYSQL_DUMP_BINARY',
        PHP_OS_FAMILY === 'Windows' ? 'C:\\xampp\\mysql\\bin\\mysqldump.exe' : 'mysqldump',
    ),
    'mysql_binary' => env(
        'MYSQL_BINARY',
        PHP_OS_FAMILY === 'Windows' ? 'C:\\xampp\\mysql\\bin\\mysql.exe' : 'mysql',
    ),
];
