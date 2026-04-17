<?php

return [
    'enabled' => env('ALLOW_DEPLOY_CENTER', true),
    'workspace_path' => env('DEPLOY_WORKSPACE_PATH', base_path()),
    'deploy_script' => env('DEPLOY_SCRIPT_PATH', base_path('deploy.sh')),
    'rollback_script' => env('ROLLBACK_SCRIPT_PATH', base_path('rollback.sh')),
    'logs_path' => env('DEPLOY_LOGS_PATH', dirname(base_path()).DIRECTORY_SEPARATOR.'deploy_logs'),
    'backups_path' => env('DEPLOY_BACKUPS_PATH', dirname(base_path()).DIRECTORY_SEPARATOR.'deploy_backups'),
    'healthcheck_url' => env('HEALTHCHECK_URL', config('app.url')),
    'timeout_seconds' => env('DEPLOY_TIMEOUT_SECONDS', 900),
    'disk_space_warning_mb' => env('DEPLOY_DISK_SPACE_WARNING_MB', 2048),
    'disk_space_blocker_mb' => env('DEPLOY_DISK_SPACE_BLOCKER_MB', 512),
    'max_log_preview_bytes' => env('DEPLOY_MAX_LOG_PREVIEW_BYTES', 262144),
    'lock_file' => env('DEPLOY_LOCK_FILE', storage_path('app/deploy-center/deploy.lock')),
    'lock_ttl_seconds' => env('DEPLOY_LOCK_TTL_SECONDS', 3600),
    'remote' => [
        'base_url' => env('DEPLOY_REMOTE_BASE_URL', ''),
        'shared_secret' => env('DEPLOY_REMOTE_SECRET', ''),
        'timeout_seconds' => env('DEPLOY_REMOTE_TIMEOUT_SECONDS', 900),
        'connect_timeout_seconds' => env('DEPLOY_REMOTE_CONNECT_TIMEOUT_SECONDS', 10),
        'max_request_age_seconds' => env('DEPLOY_REMOTE_MAX_REQUEST_AGE_SECONDS', 300),
    ],
];
