Production deployed and verified 2026-09-06 10:40 UTC.

PR #40 merged; source commit 292d3a1. Independent diff review and PHP syntax checks passed. Both source mirrors match.

Authenticated destination SHA-256, confirmed through a new SSH connection: `ebaacea23b496737feb9185f233dc20e9004383c016a8b727d2d53dde75e78a9`. Only the requested literal sentence changed.

Raw public readback: home HTTP 200; shop HTTP 200; Court Dress HTTP 200. All display “Join the club for 5% off your first order and new drops.” and contain no Davao court days phrase. Authenticated browser visual inspection confirms intact signup layout, email field and Join button. Native site page cache invalidated and browser transport returned body 0.

Six-component full backup was independently reverified off-server; exact prechange footer retained privately on-server and off-server. Rollback: restore that preimage only if destination still matches the after hash. All provider connections closed and production writer returned to coordinator. No mailing-list submission was performed; this was a copy-only change.

Durable record: [issue #39](https://github.com/john8barry/bactiveph_com/issues/39).
