# Preferences
_How you want the agent to work on WordPress, Cloudflare, cPanel, and memory-driven website operations. Read every session._
_Last updated: 2026-05-30_

> The default operating behavior lives in `.antigravity/rules/wordpress-cloudflare-cpanel-workflow.mdc`. This file tunes communication style, implementation preferences, and project-specific guardrails.


## Autonomy tuning
- Act autonomously by default and complete the task end-to-end.
- Only stop for catastrophic or irreversible actions, especially production-destructive changes, nameserver swaps, credential/security ownership changes, irreversible database operations, live domain moves, or production deployments with unclear rollback.
- Prefer reversible work: backups, exports, staging, scoped purges, temporary rules, and narrow changes before broad ones.
- When production risk exists but does not require stopping, proceed conservatively and document rollback clearly.

## Communication
- Be concise, direct, and outcome-first.
- Lead with what changed and what was verified, not a long play-by-play.
- Use clear sections and checklists when helpful.
- Surface assumptions, risks, and blockers explicitly instead of burying them.
- Do not pad responses with generic advice when project-specific action is possible.

## Working style
- Read the memory bank before non-trivial work and update it after.
- Treat code and actual system state as the source of truth for what exists; treat memory as the source of truth for why decisions were made.
- Research before guessing, especially for WordPress plugin behavior, Cloudflare edge behavior, DNS, shared-hosting constraints, and cPanel quirks.
- Prefer fixing root causes over applying cosmetic or one-off patches.
- Keep implementations small, testable, and reversible.
- For debugging, isolate layers: Cloudflare edge, hosting/origin, then WordPress application.

## Code and implementation style
- Prefer version-controlled changes over manual dashboard-only changes when repeatability matters.
- Prefer child themes, custom plugins, or mu-plugins over editing parent themes directly.
- Avoid burying important business logic inside fragile snippet managers unless the project already uses that pattern intentionally.
- Use explicit, readable code and preserve upgrade safety.
- Do not leave commented-out code, placeholders, TODO scaffolding, or mystery fixes without explanation.
- Preserve existing conventions unless there is a clear reason to improve them.

## WordPress preferences
- Verify where functionality actually lives before changing anything: theme, child theme, plugin, mu-plugin, custom code, or settings.
- Be careful with WooCommerce, memberships, multilingual plugins, page builders, forms, SMTP/email, redirects, and caching because they often create side effects.
- Clear only the caches that matter instead of performing blanket purges first.
- Verify both logged-in and logged-out behavior when relevant.

## Cloudflare preferences
- Be conservative with caching, WAF rules, bot controls, rate limits, and challenge rules.
- Do not cache admin paths, authenticated flows, checkout/cart/account pages, preview URLs, or dynamic endpoints unless explicitly known to be safe.
- Confirm whether records are proxied or DNS-only before changing them.
- Protect email routing, verification records, webhooks, and API traffic from accidental breakage.
- Note propagation expectations and rollback steps whenever DNS or edge behavior changes.

## cPanel/shared hosting preferences
- Check PHP version, limits, logs, docroot, cron, SSL state, and database connectivity early when diagnosing issues.
- Back up files/databases before risky changes.
- Prefer structured edits over ad-hoc live File Manager surgery whenever possible.
- Respect shared-hosting limitations and do not assume root access or modern server tooling.

## Pet peeves
- Do not claim something is fixed without verification.
- Do not stop at diagnosis when implementation is possible.
- Do not mix DNS, origin, and application concerns into one vague explanation.
- Do not make broad cache purges, security disablements, or plugin deactivations before isolating the cause.
- Do not overwrite memory casually or invent undocumented project context.
