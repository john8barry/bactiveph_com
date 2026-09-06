"""Disposable real WordPress/WooCommerce datastore regression, with no host ports."""
import json
import itertools
from concurrent.futures import ThreadPoolExecutor
import os
from pathlib import Path
import secrets
import shutil
import subprocess
import tempfile
import time

source = Path(__file__).resolve().parents[4]
assert (source / 'wp-settings.php').is_file()
run_root = Path(tempfile.mkdtemp(prefix='bactive-datastore-integration-'))
run_root.chmod(0o700)
suffix = secrets.token_hex(5)
network = 'bactive-payment-fixture-' + suffix
database = 'bactive-payment-db-' + suffix
db_password = secrets.token_urlsafe(24)
root_password = secrets.token_urlsafe(24)
db_image = os.environ.get('BACTIVE_TEST_DB_IMAGE', 'mariadb:11.4@sha256:611a2fcc5fa7c6ceb8644c6f74b25ede004ff6c3a6b38c8f8c23d3bbf6c26430')
cli_image = os.environ.get('BACTIVE_TEST_CLI_IMAGE', 'wordpress:cli-php8.2@sha256:837d55d02196b5f4c92d236317c6d089ab1471348b31d1708888d444a0390979')
created_network = False
created_db = False
cli_containers = set()
cli_sequence = itertools.count(1)

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
    env_file.write_text('MARIADB_DATABASE=bactive_payment_integration\nMARIADB_USER=bactivefixture\n'
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
        for slug in ('woocommerce', 'bactive-paymongo-hosted-checkout'):
            (plugins / slug).symlink_to('/reference/wp-content/plugins/' + slug, target_is_directory=True)

        def wp(*args, stdin=None, fixture_env=None):
            container = 'bactive-payment-cli-' + suffix + '-' + str(next(cli_sequence))
            environment = []
            for key, value in (fixture_env or {}).items():
                environment.extend(['--env', key + '=' + value])
            cli_containers.add(container)
            try:
                return command(['docker', 'run', '--rm', '--name', container, '--pull=never',
                                '--network', network, '--user=' + str(os.getuid()) + ':' + str(os.getgid()),
                                '--env', 'WP_CLI_ALLOW_ROOT=1', '--env', 'BACTIVE_INTEGRATION_STORE=' + store,
                                '--volume', str(source) + ':/reference:ro', '--volume', str(site) + ':/var/www/html',
                                '--workdir', '/var/www/html', '--entrypoint', 'wp', *environment, '-i', cli_image,
                                *args], stdin=stdin)
            finally:
                # Killing an attached Docker client does not stop its container.
                if cleanup_command(['docker', 'rm', '-f', container], 'no such container'):
                    cli_containers.discard(container)
                else:
                    raise RuntimeError('Disposable CLI container cleanup failed.') from None

        wp('config', 'create', '--dbname=bactive_payment_integration', '--dbuser=bactivefixture',
           '--dbpass=' + db_password, '--dbhost=' + database, '--dbprefix=' + store + '_',
           '--skip-check', '--extra-php', stdin="define('DISABLE_WP_CRON', true);\ndefine('AUTOMATIC_UPDATER_DISABLED', true);\ndefine('WP_AUTO_UPDATE_CORE', false);\n")
        wp('core', 'install', '--url=https://bactive-payment-integration.invalid', '--title=Disposable payment fixture',
           '--admin_user=fixture', '--admin_password=' + root_password, '--admin_email=fixture@example.invalid', '--skip-email')
        wp('plugin', 'activate', 'woocommerce')
        # Match the deployed Action Scheduler database store. A fresh install's
        # hybrid migration store uses different action-uniqueness semantics.
        wp('action-scheduler', 'migrate', '--batch-size=100')
        wp('option', 'update', 'woocommerce_currency', 'PHP')
        wp('option', 'update', 'woocommerce_custom_orders_table_enabled', 'yes' if store == 'hpos' else 'no')
        wp('plugin', 'activate', 'bactive-paymongo-hosted-checkout')
        output = wp('eval-file', '/reference/wp-content/plugins/bactive-paymongo-hosted-checkout/tests/datastore-integration.php')
        result = json.loads(output.splitlines()[-1])
        atomic_test = '/reference/wp-content/plugins/bactive-paymongo-hosted-checkout/tests/atomic-options-datastore.php'
        atomic_result = json.loads(wp('eval-file', atomic_test,
            fixture_env={'BACTIVE_ATOMIC_ACTOR': 'self'}).splitlines()[-1])
        result['atomic_insert_checks'] = atomic_result['checks']
        result['atomic_contenders'] = []
        for family in ('order', 'checkout', 'settings', 'event', 'payment', 'effects'):
            (site / 'atomic-proof' / family).mkdir(parents=True)
            with ThreadPoolExecutor(max_workers=2) as pool:
                futures = [pool.submit(wp, 'eval-file', atomic_test, fixture_env={
                    'BACTIVE_ATOMIC_ACTOR': actor, 'BACTIVE_ATOMIC_FAMILY': family})
                    for actor in ('A', 'B')]
                actors = [json.loads(future.result().splitlines()[-1]) for future in futures]
            result['atomic_contenders'].append({'family': family, 'actors': actors})
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
