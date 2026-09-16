# Isolated cashier verification

This runtime uses the repository's WordPress, WooCommerce and B Active gateway code with a disposable MariaDB database. It creates synthetic products, users, cash entries and provider responses. It never loads production configuration, credentials or customer data.

The database and CLI use an internal Docker network. The browser server binds only `127.0.0.1:8097`; its WordPress outbound HTTP is blocked by a fixture-only MU plugin. Synthetic PayMongo requests are intercepted before transport. The fixture uses a visibly fake live-mode key to exercise the restricted Sales Associate path without relaxing production checks. Its fake webhook identity uses HTTPS while the local browser uses HTTP. These tests establish local integration behavior, not actual PayMongo acceptance or production deployment.

## Start and prepare

The two pinned images must already be present (CI may explicitly pull these exact digests):

```sh
docker pull mariadb:11.4@sha256:611a2fcc5fa7c6ceb8644c6f74b25ede004ff6c3a6b38c8f8c23d3bbf6c26430
docker pull wordpress:cli-php8.2@sha256:837d55d02196b5f4c92d236317c6d089ab1471348b31d1708888d444a0390979
python3 tools/cashier/runtime.py start
python3 tools/cashier/runtime.py wp plugin activate bactive-cashier
python3 tools/cashier/runtime.py wp option update bactive_cashier_enabled yes
python3 tools/cashier/runtime.py wp eval-file /fixture-tools/setup-provider.php
python3 tools/cashier/runtime.py wp eval-file /fixture-tools/seed.php
```

The test-only setting above enables checkout only in the synthetic fixture. Production setup still requires its normal manager controls.

Generated login and database credentials live in the mode-0600 file `/tmp/bactive-cashier-fixture.json`. Do not commit that file or copy it into production. `BACTIVE_CASHIER_FIXTURE_STATE` selects a different state-file location. The runtime does not print credentials.

## Verify

```sh
python3 tools/cashier/runtime.py wp eval-file /reference/wp-content/plugins/bactive-cashier/tests/integration.php
python3 tools/cashier/run-http-tests.py
python3 tools/cashier/run-concurrency-tests.py
node wordpress/wp-content/plugins/bactive-cashier/tests/frontend.test.cjs
```

The integration suite covers real HPOS order writes, authorization and nonce checks, exact inventory reservations, cash effects, duplicate submissions, frozen basket validation, failure recovery, no replay of partial effects, email failure/retry and PayMongo reconciliation. The HTTP suite rejects valid-nonce public order-pay POSTs before any cashier order mutation, then sends forged, valid and duplicate signed anonymous callbacks for all four configured payment methods, verifies manager cancellation and retains holds when provider expiry cannot be proven. The concurrency suite synchronizes independent PHP workers to race same-sale creation, two associates for the last unit, and two associates plus ordinary Woo checkout for the last unit.

Each run creates fresh synthetic test users and products, avoiding the training catalog used for browser screenshots. Test-only provider faults are removed in a `finally` block. A failed/ambiguous payment deliberately leaves its order and stock hold for inspection.

## Stop

```sh
python3 tools/cashier/runtime.py stop
```

This removes only the fixture's named containers, network, temporary site and state file. The repository and production are untouched.
