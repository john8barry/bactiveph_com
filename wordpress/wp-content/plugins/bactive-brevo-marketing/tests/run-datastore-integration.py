"""Disposable real WordPress/WooCommerce datastore regression, with no host ports."""
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess
import tempfile
import time
import threading
import argparse
from concurrent.futures import ThreadPoolExecutor

parser = argparse.ArgumentParser()
parser.add_argument('--coupon-only', action='store_true', help='Run only the coupon datastore lane while integrating')
fixture_options = parser.parse_args()
plugin_source = Path(__file__).resolve().parents[1]
source = Path(os.environ.get('BACTIVE_TEST_WORDPRESS_SOURCE', str(Path(__file__).resolve().parents[4]))).resolve()
assert (source / 'wp-settings.php').is_file()
run_root = Path(tempfile.mkdtemp(prefix='bactive-datastore-integration-'))
run_root.chmod(0o700)
suffix = secrets.token_hex(5)
network = 'bactive-marketing-fixture-' + suffix
database = 'bactive-marketing-db-' + suffix
db_password = secrets.token_urlsafe(24)
root_password = secrets.token_urlsafe(24)
db_image = os.environ.get('BACTIVE_TEST_DB_IMAGE', 'mariadb:11.4@sha256:611a2fcc5fa7c6ceb8644c6f74b25ede004ff6c3a6b38c8f8c23d3bbf6c26430')
cli_image = os.environ.get('BACTIVE_TEST_CLI_IMAGE', 'wordpress:cli-php8.2@sha256:837d55d02196b5f4c92d236317c6d089ab1471348b31d1708888d444a0390979')
created_network = False
created_db = False
cli_containers = set()
cli_sequence = 0
cli_lock = threading.Lock()

def command(args, timeout=120, stdin=None):
    try:
        result = subprocess.run(args, input=stdin, capture_output=True, text=True, timeout=timeout)
    except subprocess.TimeoutExpired:
        # TimeoutExpired includes command arguments, including fixture passwords.
        raise RuntimeError('Disposable fixture command timed out.') from None
    if result.returncode:
        # Every service and value here belongs to the disposable fixture.
        diagnostic = (result.stdout + result.stderr)[-6000:]
        for value in (db_password, root_password):
            diagnostic = diagnostic.replace(value, '[fixture credential]')
        raise RuntimeError('Fixture command failed: ' + diagnostic)
    return result.stdout.strip()

def cleanup_command(args, missing_message):
    try:
        result = subprocess.run(args, capture_output=True, text=True, timeout=30)
    except (subprocess.TimeoutExpired, OSError):
        return False
    return result.returncode == 0 or missing_message in result.stderr.lower()

try:
    # Track names before creation so an ambiguous timeout still receives cleanup.
    created_network = True
    command(['docker', 'network', 'create', '--internal', network])
    env_file = run_root / 'database.env'
    env_file.write_text('MARIADB_DATABASE=bactive_marketing_integration\nMARIADB_USER=bactivefixture\n'
                        + 'MARIADB_PASSWORD=' + db_password + '\nMARIADB_ROOT_PASSWORD=' + root_password + '\n')
    env_file.chmod(0o600)
    created_db = True
    command(['docker', 'run', '-d', '--pull=never', '--name', database, '--network', network,
             '--memory=512m', '--cpus=1', '--tmpfs', '/var/lib/mysql:rw,size=256m',
             '--env-file', str(env_file), db_image])
    for attempt in range(40):
        probe = subprocess.run(['docker', 'exec', database, 'healthcheck.sh', '--connect', '--innodb_initialized'],
                               capture_output=True, text=True, timeout=10)
        if probe.returncode == 0:
            break
        time.sleep(1)
    else:
        raise RuntimeError('Disposable MariaDB did not become ready.')
    print('Disposable database ready; outgoing network and host ports disabled.', flush=True)
    results = []
    for store in ('hpos', 'cpt'):
        site = run_root / store
        site.mkdir(mode=0o700)
        for name in ('wp-admin', 'wp-includes'):
            (site / name).symlink_to('/reference/' + name, target_is_directory=True)
        for name in ('index.php', 'wp-load.php', 'wp-blog-header.php', 'wp-settings.php', 'wp-login.php',
                     'wp-cron.php', 'wp-comments-post.php', 'wp-links-opml.php', 'wp-mail.php',
                     'wp-signup.php', 'wp-trackback.php', 'xmlrpc.php', 'wp-config-sample.php'):
            shutil.copyfile(source / name, site / name)
        plugins = site / 'wp-content/plugins'
        plugins.mkdir(parents=True)
        (plugins / 'woocommerce').symlink_to('/reference/wp-content/plugins/woocommerce', target_is_directory=True)
        (plugins / 'bactive-brevo-marketing').symlink_to('/plugin', target_is_directory=True)
        mu = site / 'wp-content/mu-plugins'
        mu.mkdir()
        (mu / 'fixture-safety.php').write_text("<?php\nadd_filter('pre_wp_mail', '__return_true');\nadd_filter('pre_http_request', static function () { return new WP_Error('fixture_network', 'Outbound requests disabled'); }, -999);\n")

        def wp(*args, stdin=None):
            global cli_sequence
            with cli_lock:
                cli_sequence += 1
                sequence = cli_sequence
            container = 'bactive-marketing-cli-' + suffix + '-' + str(sequence)
            cli_containers.add(container)
            try:
                return command(['docker', 'run', '--rm', '--name', container, '--pull=never',
                                '--network', network, '--user=' + str(os.getuid()) + ':' + str(os.getgid()),
                                '--env', 'WP_CLI_ALLOW_ROOT=1', '--env', 'BACTIVE_TEST_COUPON_ONLY=' + ('1' if fixture_options.coupon_only else '0'), '--env', 'BACTIVE_INTEGRATION_STORE=' + store,
                                '--volume', str(source) + ':/reference:ro', '--volume', str(plugin_source) + ':/plugin:ro', '--volume', str(site) + ':/var/www/html',
                                '--workdir', '/var/www/html', '--entrypoint', 'wp', '-i', cli_image,
                                *args], stdin=stdin)
            finally:
                # Killing an attached Docker client does not stop its container.
                if cleanup_command(['docker', 'rm', '-f', container], 'no such container'):
                    cli_containers.discard(container)
                else:
                    raise RuntimeError('Disposable CLI container cleanup failed.') from None

        wp('config', 'create', '--dbname=bactive_marketing_integration', '--dbuser=bactivefixture',
           '--dbpass=' + db_password, '--dbhost=' + database, '--dbprefix=' + store + '_',
           '--skip-check', '--extra-php', stdin="define('BACTIVE_BREVO_TEST_FIXTURE', true);\ndefine('DISABLE_WP_CRON', true);\ndefine('AUTOMATIC_UPDATER_DISABLED', true);\ndefine('WP_AUTO_UPDATE_CORE', false);\n")
        wp('core', 'install', '--url=https://bactiveph.com', '--title=Disposable marketing fixture',
           '--admin_user=fixture', '--admin_password=' + root_password, '--admin_email=fixture@example.invalid', '--skip-email')
        wp('plugin', 'activate', 'woocommerce')
        # Match the deployed Action Scheduler database store. A fresh install's
        # hybrid migration store uses different action-uniqueness semantics.
        wp('action-scheduler', 'migrate', '--batch-size=100')
        wp('option', 'update', 'woocommerce_currency', 'PHP')
        wp('option', 'update', 'woocommerce_enable_coupons', 'yes')
        wp('option', 'update', 'woocommerce_custom_orders_table_enabled', 'yes' if store == 'hpos' else 'no')
        wp('plugin', 'activate', 'bactive-brevo-marketing')
        output = wp('eval-file', '/plugin/tests/datastore-integration.php')
        result = json.loads(output.splitlines()[-1])
        ids = result['coupon']['concurrency_orders']
        def claim(order_id):
            script = 'require "/plugin/tests/coupon-datastore.php"; echo bactive_brevo_coupon_datastore_claim(' + str(int(order_id)) + ');'
            return wp('eval', script).splitlines()[-1]
        with ThreadPoolExecutor(max_workers=2) as executor:
            outcomes = list(executor.map(claim, ids))
        if outcomes[0] != 'accepted' or outcomes[1] not in ('bactive_first_order_reserved', 'bactive_first_order_busy'):
            raise RuntimeError('Concurrent claims violated first-order isolation: ' + repr(outcomes))
        if claim(ids[0]) != 'accepted':
            raise RuntimeError('Original order retry was not idempotent.')
        if claim(ids[1]) != 'bactive_first_order_reserved':
            raise RuntimeError('Losing order became eligible after the race.')
        result['coupon']['concurrent_processes'] = 'passed'
        results.append(result)
        print(json.dumps(result), flush=True)
finally:
    cleanup_failures = []
    for container in sorted(cli_containers):
        if not cleanup_command(['docker', 'rm', '-f', container], 'no such container'):
            cleanup_failures.append('CLI container')
    if created_db:
        if not cleanup_command(['docker', 'rm', '-f', database], 'no such container'):
            cleanup_failures.append('database container')
    if created_network:
        if not cleanup_command(['docker', 'network', 'rm', network], 'not found'):
            cleanup_failures.append('network')
    try:
        shutil.rmtree(run_root)
    except OSError:
        cleanup_failures.append('temporary directory')
    if cleanup_failures:
        raise RuntimeError('Disposable fixture cleanup failed: ' + ', '.join(cleanup_failures)) from None
print(json.dumps({'real_datastore_integration': 'passed', 'results': results}), flush=True)
