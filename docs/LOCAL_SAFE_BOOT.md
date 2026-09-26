# Local Safe Boot

This project can bypass boot-time database lookups while running locally.

## Env

```env
LOCAL_SAFE_BOOT=true
```

## What it does

- Skips locale/settings/notification boot queries during local startup
- Keeps `/ping` and `/api/ping` reachable even when the local database is not ready
- Does not change production behavior

## Ping endpoints

- `/ping`
- `/api/ping`

Outside the local pre-boot shortcut, both return a minimal JSON health signal without environment or database configuration. `local_boot_trace.log` is written only while `LOCAL_SAFE_BOOT` is enabled.
