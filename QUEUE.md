# Native queued maintenance

`search.clear-phrases` runs through `PhraseCleanupJobHandler` in the `maintenance`
lane. It calls the package service directly: no ConsoleCommandJobHandler,
console route execution or extra console process. The existing CLI command
`cmsSearch/clear/phrase` calls the same service for backwards compatibility.

## Scheduling and compatibility

The schedule retains its original route key under cmsAgent.commands solely to
preserve existing cms_agent IDs, dates, activation, custom intervals and history
references. Its jobType selects the native handler; jobPayload is empty.
There is no execution of the route by the job. An explicit stored job_type
still wins. Existing queued messages with the former command payload are safe:
the native handler ignores it and executes only its own domain operation.
New sites use the standard schedule loader. The default interval is 86400
seconds. No schema migration or schedule reload is needed for this change.

Resource and deduplication keys remain shared across CMS sites in one
installation because the operation affects shared data. Manual and scheduled
starts use skip overlap and the existing cms.admin-role-access permission.
There is one attempt and no automatic retry of uncertain work. Timeout remains
7200 seconds with a 120-second lease. The handler reloads settings on a private
clone for the run's CMS site, without changing the worker's cached component.

## Cleanup

PhraseCleanup deletes at most 1000 selected IDs per batch. It captures a fixed
age cutoff and maximum ID, so newly inserted rows do not extend the operation.
The delete rechecks age after selection. Cancellation/heartbeat occurs between
batches; deleted rows and batch count are returned as structured results.
phraseLiveTime=0 still disables cleanup and is reported as skipped.
A partial cleanup can be rerun; already removed records are simply absent.

## Verification and rollout

Pause maintenance consumers gracefully before replacing these files; install
service/handler classes before their common config. Restart maintenance workers
after the update and check the effective registry and schedule types. Existing
cms-agent/cms-job dependencies and migrations remain required. No dependency
or migration is added by the conversion from console bridge to native handler.

Run the reusable SQLite scheduler test with this package's common config:

```sh
php vendor/skeeks/cms-agent/tests/package-command-bridge-smoke.php vendor/skeeks/cms-search/src/config/common.php
```

tests/native-cleanup-smoke.php uses only SQLite in memory, with empty and sx_
table prefixes. It verifies batching, retained recent/new rows, cancellation,
zero-retention semantics, run-site settings, counters and database failures.