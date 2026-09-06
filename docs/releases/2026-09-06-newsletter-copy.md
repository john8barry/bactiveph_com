# Newsletter copy correction

Requested change: remove the unsupported Davao court days claim from the site-wide signup band. Owner: current B Active task. Severity: low; misleading marketing copy.

New copy: Join the club for 5% off your first order and new drops.

Scope: one literal sentence in both footer-sage.php source mirrors. No form, offer, styling, or integration changes.

Acceptance: both source mirrors match; PHP syntax passes; production signup displays the new sentence with the existing layout and form preserved.

Status: source prepared in an isolated worktree from origin/main 7ea4a58. Production deployment and live acceptance pending. Unrelated canonical changes preserved.

Next control point: John approved production deployment in this task; verified backup and serialized deployment with exact destination readback. Rollback: restore the captured prechange footer file only after checking for intervening edits.
