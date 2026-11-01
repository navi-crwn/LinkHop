# Embedded Bio upload protection

When `BIO_ENABLED` is on, the bundled Bio editor enforces the same disk guard as standalone BioHop: HTTP 507 and `Retry-After: 60` for uploads when capacity is low or cannot be measured. Text edits and public reads remain available. Avatar updates share the editor save lane and return its new revision; public views do not alter editor revisions.

`BIO_UPLOAD_MIN_FREE_MB` defaults to 1024 MiB and `BIO_UPLOAD_MIN_FREE_PERCENT` to 5. Nonpositive values use those defaults. The greater reserve applies on local storage, the public upload disk and PHP temporary storage. Admission reserves three request copies and uses a filesystem lock across PHP workers. Remote bucket capacity and other services' disk writes are not controlled by this guard.

The editor checks `/bio/upload-status` every 30 seconds, displays a warning and disables file inputs while paused. Recovery is automatic after space returns. PHP/proxy buffering happens before Laravel; keep request-size limits and temporary directories aligned with available storage. Use normal maintenance/deletion controls or expand capacity; never delete live database/WAL files.
