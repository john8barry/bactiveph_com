# GrabPay footer badge

Work item: [#53](https://github.com/john8barry/bactiveph_com/issues/53).
Source review: [PR #55](https://github.com/john8barry/bactiveph_com/pull/55),
runtime candidate `38384446a8e52b02d68a0d7d47b538966d29018b`.
Severity: low, display-only. Owner: courier/payment footer task.
Status: **LIVE VERIFIED**.
The exact two-file footer update is installed on staging and production.

## Scope

Add GrabPay after Maya in the existing payment-options footer. Preserve QR Ph,
ShopeePay, BPI Online, UnionBank Online, enabled-only COD, separate PayMongo
attribution, J&T/LBC nationwide and GrabExpress with its Davao City-only label.
Typography, badge sizing and aspect-ratio handling are unchanged. The current
Sage footer now accommodates seven columns from 768px and three columns below,
with the seventh COD badge centered. No payment, checkout, shipping, order, plugin or
configuration change is included. This logo does not activate GrabPay checkout;
runtime payment readiness remains a separate task in #2.

The Impeccable polish review preserved the incumbent visual system and original
brand artwork. Its context discovery stalled on an unrelated canonical-checkout
file; direct target source and existing design notes were used instead. The
design notes contain historical typography; current source typography was kept.

## Asset provenance and exact overlay

Use the original [PayMongo GrabPay SVG](https://www.paymongo.com/images/logos/payment-methods/grabpay.svg),
retrieved and independently verified on 2026-09-08. It is the same 121 by 49
pill-shaped family used by the other payment marks, with original green and
white artwork. Its 5,468 bytes are unchanged. No scripts, event handlers,
external references, embedded images or foreign objects are present.

Only these two child-theme files are deployable; both source mirrors agree:

| File | SHA-256 |
| --- | --- |
| `template-parts/trust-bar.php` | `57b84bbc2fb81c7c5d60a1cc934adee1f4b21d23b7467e64161d71160686d6f4` |
| `assets/images/payments/grabpay.svg` | `d140bd7323719935e2e05c102553ac3283e5ccb3ee22739673b3b3164d3e6e23` |

Render marker: `2026-09-08-v5`. Tests, release notes and private helpers are not
deployed. Source base: remote main
`5dcfbdece8067850b9665f6b5d33dd0557bf0212`, branch `codex/grabpay-footer`.
The canonical dirty checkout remains untouched; its filesystem reads stall and
its full current status is unavailable. This change uses an isolated clone.

## Verification

### Production release, September 10

The production native Updraft backup `3e02593fb8f1` completed with exit zero,
zero errors/warnings and all six archive groups. A slow read-only SFTP transfer
was interrupted only after native completion was proven; its original failure
and closed-connection receipts are retained. A separately reviewed read-only
transport copied all six archives off-server. Full gzip/ZIP integrity, 137 table
schemas, root/core/config integrity and source coverage passed: 27,547 of 27,547
files, no missing files and no supplemental production archives needed.

During backup, exactly two existing payment-readiness verification timestamps
advanced. Native database values and fresh private reads were HMAC-bound and
compared: only `recorded_at` and `verified_at` changed; all remaining serialized
fields, secrets, capabilities, settings and protected orders were unchanged.
The initiating request was not attributed. A nonce-specific qualification
preserves these two raw changed fields and rejects all other or subsequent
drift. Fresh qualification before/after state and original system crontab match.
Qualified receipt SHA-256:
`c59991be4c5ca7832b8f23f45c5471ae851d944ce8ee40f8fce4fd1ab0b4b975`.
Backup freshness is enforced from original start 18:45:13 UTC, not qualification.

Production overlay `8509631f9203` installed only the two listed files during the
footer-owned 19:26–19:44 UTC window, preserving every other child-theme file,
including the separately updated Sage footer. Remote PHP lint and both installed
hashes passed. The previous partial is preserved locally and privately on-host
for atomic rollback. Initial post-install logs had zero new bytes/errors.

Production LiteSpeed 7.9.1 source was reviewed separately from staging 7.8.1.
One header-only page-cache refresh (`987fbc173d60`) ran before normal plugins;
no object/OPcache purge, payment setting or provider operation was performed.
All three exact temporary transport paths were removed and independently checked
absent; existing MU files were unchanged and connections closed.

At 19:28:17 UTC, ordinary anonymous home, shop and shipping/returns URLs returned
HTTP 200 and marker `2026-09-08-v5`, all seven payment badges and PayMongo. The
public GrabPay SVG exactly matches the reviewed 5,468-byte source. Logged-in
in-app and logged-out Chrome screenshots show the live footer. At 390px all
seven payment badges render approximately 106.33×43.05; at 650px all are 121×49.
All images load with `object-fit: contain`, no horizontal overflow, and centered
mobile COD. Desktop seven-column alignment and Davao City-only GrabExpress are
preserved. Temporary viewport overrides were reset.

Final guarded state read passed and closed. Protected order groups, settings,
runtime/worker sources, logical scheduling and all unrelated theme/root files
match the qualified baseline. The only application metadata delta was another
on-demand readiness refresh at 19:28:20 UTC: both verification timestamps
advanced, while HMAC-bound comparison proves every other serialized field
unchanged. This is separately recorded, not hidden as raw state equality; the
initiating request is not independently attributed. The footer display path
does not call gateway availability or change payment readiness.

Bounded monitoring through 19:32:55 UTC repeated all three anonymous URL checks
successfully. Final server/debug log readback reports zero new bytes, warnings,
fatal or parse errors since installation. All connections are closed; the
footer-owned host window was explicitly released at 19:33:30 UTC.

Seven runtime fixtures, four negative layout fixtures, twelve timestamp-proof
tests and freshness/one-use cache negative tests pass. PR #55 has no configured
CI status checks; these are direct local and destination checks, not CI claims.

### September 10 continuation

John explicitly authorized proceeding with the display-only release. No payment
activation or checkout configuration is included. Current installed Sage CSS
was read directly: its six-column override required a scoped responsive fix in
the trust partial, not an overwrite of the independently updated Sage footer.
Impeccable review preserved the original logo artwork and uniform proportions.
An independent review caught the 600–767px breakpoint gap; it is corrected and
covered by four negative layout fixtures. Both mirrors pass all seven runtime
scenarios, PHP syntax checks and diff whitespace checks.

The local browser preview combines the current installed staging Sage styles
with the exact candidate partial. Desktop, 390px and 650px previews show all
seven marks, centered mobile COD and no horizontal overflow. At 650px all seven
images load at 121×49 with object-fit contain. These are local previews only.

The first September 10 native-backup preflight failed locally before backup
creation because Python 3.9 lacks hashlib.file_digest. A chunked SHA-256 reader
replaces it; independent review and 12 positive/17 negative SQL guard tests pass.
The previous attempt's closure receipt confirms backup_started=false and closed
connections. The retry also stopped before native backup creation: the root
archive contains an existing test.txt symlink, which the archive guard rejected.
Its closure receipt again confirms no active process and closed connections.
Safe preservation of that exact link without following it is under review.
No backup or deployment is claimed successful until independent artifact and
destination checks pass.

Later September 10 evidence supersedes those preparation holds: the exact
staging symlink is preserved as metadata without following its target. Transport
now drains SSH output through EOF and verifies the full gzip trailer. A failed
native attempt was stopped by the SQL guard on Updraft's session-only SQL-mode
adjustment; its four exact job/lock bookkeeping rows were transactionally
reconciled with before-images, fresh protected-state equality and independent
closure proof. No archives, backup history, payment state or theme files were
deleted or changed by that reconciliation.

The subsequent native staging backup `255a517fc3d4` completed with zero Updraft
errors/warnings, all six database/file groups, preserved prior history and a
17,705-entry source inventory. The runner retained a failure receipt because
stderr contained five identical cPanel Mounts.pm warnings (505 bytes total).
An independently reviewed, read-only continuation qualifies that existing
attempt rather than rerunning it. It preserves the original failure and permits
only the exact captured warning bytes with complete exit-zero/native-success
proof. The original system-crontab hash was not persisted; this evidence gap is
explicit, with fresh continuation crontab equality, exact worker attestation,
original logical payment-state comparison and the reviewed no-cron-write guard
used as independent controls.

Final qualification verified all six native archive groups, root/core/config
archive and two exact opaque historical-backup supplements: 17,705 of 17,705
source files are covered. Full comparison of 32 order rows and 295 metadata
rows found only test-order 374's update timestamp and its reconciliation polling
counter (113 to 114) changed. Existing Action Scheduler action 1013 and worker
logs independently attribute this to the scheduled reconciliation at 17:35:30
UTC. Payment status/details, notes, stock, other orders and scoped settings did
not change. The exact changed fields remain in the qualified receipt, bound to
this staging nonce only; production retains its strict unchanged-state gate.
Original failed receipts remain intact. Backup freshness is conservatively
measured from the original 17:19:14 UTC start, not the later qualification time.
The exact staging receipt passed the overlay gate and eleven negative checks.

Production bootstrap/configuration and the version-specific Updraft source were
reviewed privately. The production hero MU file is `bactiveph-hero-glass.php`,
not the staging filename. Production backup and the exact two-file overlay are
prepared and separately sealed; the backup began at 18:45:13 UTC. No production
footer overlay has been executed at this checkpoint.
Current task owns backup qualification and release. The payment task supplies
shared-host scheduling only, not authorization or review.

The exact two-file staging overlay completed with independent deployed hashes
and no unrelated theme changes. A header-only, staging-specific page-cache purge
used a short-lived, token-gated MU transport; it loaded no normal plugins and
all three temporary paths were removed with absence and unchanged-MU checks.
Ordinary home, shop and shipping/returns URLs returned HTTP 200 and the v5
footer with all seven payment marks. Actual browser checks at 1280, 390 and
650px confirm loaded assets, original proportions, no horizontal overflow and
centered mobile COD. GrabExpress retains its Davao City-only qualification.

Post-cache settings, identity, logical worker invariants, theme and root files
matched the fresh pre-cache snapshot. Full comparison of 32 orders and 295
metadata rows found only order 374's timestamp and polling counter (114 to 115)
advanced. Existing action 1014 ran via WP Cron at 18:36:45–18:36:46 UTC, before
the cache runner started at 18:37:01. Payment status, other order fields, notes
and stock were unchanged. There were no new log bytes across the cache
snapshots; an earlier 486-byte increase contains three existing DOING_CRON
warnings and no fatal/parse/uncaught errors. One earlier invariant read was
blocked by the original SQL guard; later guarded reads passed without relaxing
it, and that transient failure's cause remains unreproduced.

The staging window is explicitly released. The payment scheduling task is now
in Plan mode and reports no competing writer or active host connection. Under
John's current explicit authorization, this footer task records and notifies
its own bounded backup/release windows; the other task's expired queue is not
silently renewed or edited. Production backup qualification is the next gate.

### Earlier evidence (historical candidate)

PASS: native PHP 8.3.33 syntax checks; seven runtime scenarios (ready,
not-ready, missing gateway, gateway error, manager error, all disabled and no
commerce); exact payment order/count and accessible local GrabPay image;
enabled-only COD with fail-closed behavior; both theme mirrors; passive SVG
inspection; `git diff --check`; Impeccable detector `[]`; independent exact-file
source/security/brand review.

PASS: seven negative fixtures reject missing GrabPay, wrong asset, missing alt,
distorted dimensions, COD fail-open, missing Davao-only label and stale version.
Local component browser screenshots at desktop and 390px phone width show the
matching badge. At 390px all seven payment images load at 121 by 49 with
`object-fit: contain`, and no horizontal overflow. These are component previews,
not staging or production verification.

Staging authenticated identity at 09:08:30 UTC: exact home/siteurl
`https://staging.bactiveph.com`, isolated DB `waypmvhk_stg`, `blocksy-child`,
`blog_public=0`. Original partial hash is
`1a1c06aec18da9ea80ae659eadea100b78e3a9966b08871a99482fb3302536f1`.
Original staging `template-parts/footer-sage.php` is
`6e03b8048bf197cbae05e1e52b92c3e92e448048106a2fd463fcdc533f35a7e4`,
which differs from production and is not in this deployment allowlist.

The first backup action-time guard stopped before backup creation: aggregate
settings and payment-cron hashes advanced, while full child-theme, order/meta/
item hashes and gateway flags stayed unchanged. A same-connection diagnostic
subsequently found stable values. Original snapshots are retained and the
payment coordinator is classifying this drift before a new baseline/backup.
No deployment has occurred. The stopped guard is not a successful backup.

Independent review subsequently found that the historical helper's ordinary
`wp eval` bootstrap and gateway construction were not guaranteed read-only.
Those diagnostic runs therefore cannot prove absence of incidental bootstrap
effects, and the original aggregate delta remains **UNATTRIBUTED**. The old
helper is stopped, retained as evidence and not cleared for further use.
Its ignored stderr, removable Python assertions and incomplete root/core
recovery coverage were also identified. A distinct read-only collector was
independently reviewed before its single authorized invocation, with
pre-bootstrap SQL/mail/HTTP/async guards, plugins/themes skipped, exact dependency
pins, raw keyed options/order/notes/stock snapshots and separate scheduling
observations. Backup execution remains
explicitly held: process-only backup guards may not survive a scheduled Updraft
resume. No existing backup helper is treated as a qualified drop-in recipe.

### Final guarded staging baseline

At 09:39:24 UTC, the new collector passed with `changed_fields=[]` between its
two snapshots. Exact staging identity and `blog_public=0` were verified again;
265 scoped option hashes (active plugins plus literal `woocommerce_` and
`bactive_` prefixes), 11 protected order/notes/stock groups, the held test-order
state, scheduling observations, theme/root-file inventory, 11 gateway runtime
pins, five worker pins and reviewed bootstrap pins remained unchanged.
All three MU sources were independently inspected; no active drop-ins were
found and the production-only MailPoet dependency was absent, so its SQL
exceptions were not carried over. This is an application-guarded read under
reviewed bootstrap assumptions, not an operating-system sandbox.

The payment coordinator independently read and accepted the new receipt only.
The original unguarded delta remains unattributed. The staging partial is still
v4 and GrabPay is absent. All transports were closed before the closure receipt
was written, and the staging preparation window was explicitly released.
No more host calls are needed in this lane while the backup gate is held.

Private receipt directory: `guarded-staging-20260908T093807.808801Z` beneath the
private artifact directory below. Final state receipt SHA-256:
`87eaa56a073260326a00788ee2aae03e6b2f452f05b2c30f3506e430961a31c7`.
The source-manifest binding, transport failure tests and optimized-Python
negative manifest test passed before the single guarded invocation.

Private recovery/diagnostic artifacts are held in
`/private/tmp/bactiveph-grabpay-20260908-6jE1ip/`, outside the repository. Never
publish backup contents, option values, credentials or order data.
A private durable evidence archive is also retained under
`~/Library/Application Support/BactivePH/footer-releases/grabpay-20260908/`.
Its SHA-256 is `ed2ea280dbf802a725c3bc902ca967cf941e00d088dd99164d3e229710780ee4`;
gzip integrity passed. This is a diagnostic/source archive, **not a site backup**.

## Remaining gates and rollback

The footer task owns backup qualification and the release; the payment task
provides shared-host conflict information. Staging backup, exact two-file
installation, ordinary public readback and actual browser verification passed.
John authorized this exact display-only release on September 10. Production
has its own qualified fresh backup, serialized writer window, exact preflight
and independent public/browser readback. Existing #2, #7 and #9 holds are not cleared.

For rollback, first verify no later writer superseded this exact v5 partial,
then atomically restore the snapshotted v4 partial. Lint/hash before installation,
refresh only page cache, and verify the public footer. The unused new SVG can
remain; no deletion or database restore is necessary.

The reviewed one-shot backup confines native bookkeeping to its exact nonce,
suppresses the known broad old-lock maintenance query and does not schedule a
resume, send mail or invoke a remote provider. No unbounded cleanup, payment
configuration change or old/incomplete backup is allowed. Issue #53 and PR #55
carry the completed two-file deployment and public verification record.

No global or project memory files were updated.
