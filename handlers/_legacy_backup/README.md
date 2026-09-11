# Legacy local-MySQL handlers (retired)

These two files are the last handlers that still read/wrote a clinic's local
per-clinic MySQL database directly (via the `db` mysql2 pool), from before
the multi-tenant API migration. They only ever ran for installs that still
had a local `dbConfig` saved in electron-store — new installs never got a
setup wizard to create one, so these were already dead for every new
install by the time they were removed.

Kept here for reference only — not required by the app and not wired into
`main.js` anymore.

- `legacyUserHandlers.js` — `export-data`, `import-data`, `open-file-dialog`,
  `debug-excel`. Replaced by `handlers/dataManagementHandlers.js`, which
  implements the same IPC channels through the multi-tenant API's existing
  list/create endpoints instead of raw SQL.
- `legacySettingsHandlers.js` — `update-profile` (local `Users` table).
  Replaced by the `update-profile` handler now in `handlers/authHandlers.js`,
  backed by `PATCH /api/auth/me`.
