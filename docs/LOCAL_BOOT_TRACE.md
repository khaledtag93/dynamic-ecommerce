# Local Boot Trace

When `LOCAL_SAFE_BOOT=true`, the application now exposes lightweight probe endpoints before Laravel boots:

- `/ping`
- `/_probe`
- `/api/ping`

These return `ok` directly from `public/index.php`.

A trace file is also written to:

- `storage/logs/local_boot_trace.log`

Milestones include:

- `index_start`
- `before_autoload`
- `after_autoload`
- `after_bootstrap_app`
- `after_kernel_make`
- `after_request_capture`
- `after_kernel_handle`
- `after_response_send`
- `after_kernel_terminate`

If a request still hangs for non-probe routes, inspect the last milestone in the trace file to find the boot stage causing the block.
