# Remove repetitive footer payment notes

Work record: [#10](https://github.com/john8barry/bactiveph_com/issues/10).
Owner: footer-copy task `01a07062-0209-7a60-9a70-8f21d68533f8`.
Severity: low, redundant customer-facing copy.

**Current status: LIVE VERIFIED in combined footer v4.** The copy-only v3
artifact below was never deployed separately. Its staging/approval language is
historical; the combined backup, approval, deployment and independent live
acceptance are recorded in the [GrabExpress v4 release](2026-09-05-grabexpress-davao.md)
and [PR #12](https://github.com/john8barry/bactiveph_com/pull/12).

John requested removing the online-setup and COD-eligibility paragraphs. They
were presentation notes introduced by v2, not payment controls. Both paragraphs,
their unused CSS, and the unused PayMongo readiness calculation are removed.
The five approved online marks, separate PayMongo branding, enabled-only COD
badge, courier links, and business/BIR content are preserved. Checkout remains
responsible for payment availability and COD fee/eligibility disclosure.

## Historical copy-only v3 evidence and release boundary

- Base: `c8a922f8b73bb133fb01017c4c14b50b15c8f299`. Initial authenticated
  production and staging reads independently matched the v2 partial hash
  `8bf5a8dfb2ab216c19489717cbb07860191ec289f2066b03de789650a0a47d00`.
- Both source mirrors contain identical v3 bytes, SHA-256
  `f8299dfa2c72e01be73a04be5c884f607ad8783b92d9c96ed06e7d11ccf09545`.
  Only `template-parts/trust-bar.php` is deployable from this patch.
- PASS: seven existing PHP 8.2 WASM scenarios, including disabled/missing/error
  gateway states. The assertions retain exact logo order/count and COD/courier
  checks and now reject both removed notes. Independent source review passed;
  the interface detector returned `[]`; `git diff --check` passed.
- Six-component recent off-server backup hashes were independently matched;
  staging archive ZIP/gzip integrity was also checked. These are recovery
  evidence for the prior release, not a substitute for the combined release's
  fresh backup gate.
- This task made no staging or production file/settings writes. A subsequent
  SSH test attempt failed at DNS resolution before connecting; runtime tests
  instead used the existing local PHP WASM installation.
- Staging visuals and production acceptance are **UNVERIFIED** for v3. The
  existing courier coordinator owns one combined release with the separately
  requested GrabExpress update [#11](https://github.com/john8barry/bactiveph_com/issues/11).
  It will integrate this patch, take a fresh backup, verify the combined staging
  artifact, obtain the required explicit production approval, and perform
  independent destination/visual readback for both issues.
- Payment activation and existing security holds remain owned by #2, #7 and #9.
  This copy change does not establish PayMongo readiness or alter checkout.

The canonical dirty checkout is preserved. Work is isolated in
`/private/tmp/bactiveph-footer-copy-20260905`; sanitized local test/source receipts
are in `/Users/johnbarry/.codex/tmp/bactiveph-footer-copy-20260905`.
No global or project memory files were updated.

## Rollback and next control point

Before any release, snapshot the exact destination partial. To roll back this
copy-only patch, verify no later writer has superseded its hash, then atomically
restore the saved v2 partial and refresh the affected page cache. A combined
GrabExpress release needs its own complete current rollback receipt; do not
restore v2 over a newer approved courier change without coordination. No
database restore is part of this change.

The historical next control point was combined staging acceptance and the
single production approval in **Update courier and COD options**. Both are now
satisfied by v4, and both removed notes were independently verified absent from
the live site. Final issue closure follows the PR #12 main/ref reconciliation
in the combined release record.
