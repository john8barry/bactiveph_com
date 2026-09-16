#!/usr/bin/env python3
"""Disposable, synthetic WordPress/Woo fixture. Never reads production config.

Usage: runtime.py start | wp <arguments...> | status | stop
The private state path can be changed with BACTIVE_CASHIER_FIXTURE_STATE.
"""
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess
import sys
import tempfile
import time

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / 'wordpress'
STATE = Path(os.environ.get('BACTIVE_CASHIER_FIXTURE_STATE', '/tmp/bactive-cashier-fixture.json'))
CLI = 'wordpress:cli-php8.2@sha256:837d55d02196b5f4c92d236317c6d089ab1471348b31d1708888d444a0390979'
DB = 'mariadb:11.4@sha256:611a2fcc5fa7c6ceb8644c6f74b25ede004ff6c3a6b38c8f8c23d3bbf6c26430'


def run(args, stdin=None, timeout=120):
    try:
        result = subprocess.run(args, input=stdin, capture_output=True, text=True, timeout=timeout)
    except subprocess.TimeoutExpired:
        raise RuntimeError('Synthetic fixture command timed out.') from None
    if result.returncode:
        raise RuntimeError((result.stdout + result.stderr)[-5000:])
    return result.stdout.strip()


def mounted(state):
    return ['--network', state['network'], '--user', '0:0', '--env', 'WP_CLI_ALLOW_ROOT=1',
            '--add-host', 'checkout.paymongo.com:203.0.113.10',
            '--volume', str(SOURCE) + ':/reference:ro',
            '--volume', str(ROOT / 'tools/cashier') + ':/fixture-tools:ro',
            '--volume', state['site'] + ':/var/www/html', '--workdir', '/var/www/html']


def wp(state, args, stdin=None):
    return run(['docker', 'run', '--rm', '--pull=never', *mounted(state), '--entrypoint', 'wp', '-i', CLI, *args], stdin)


def stop(state):
    for name in (state['web'], state['database']):
        subprocess.run(['docker', 'rm', '-f', name], capture_output=True)
    subprocess.run(['docker', 'network', 'rm', state['network']], capture_output=True)
    shutil.rmtree(state['root'], ignore_errors=True)
    STATE.unlink(missing_ok=True)


def start():
    if STATE.exists():
        raise RuntimeError('Fixture state exists; use status or stop before starting another fixture.')
    suffix = secrets.token_hex(4)
    private = Path(tempfile.mkdtemp(prefix='bactive-cashier-'))
    private.chmod(0o700)
    site = private / 'site'
    site.mkdir()
    state = {'root': str(private), 'site': str(site), 'network': 'bactive-cashier-net-' + suffix,
             'database': 'bactive-cashier-db-' + suffix, 'web': 'bactive-cashier-web-' + suffix,
             'url': 'http://localhost:8097', 'admin_user': 'fixture-manager',
             'admin_password': secrets.token_urlsafe(24), 'db_password': secrets.token_urlsafe(24)}
    STATE.write_text(json.dumps(state))
    STATE.chmod(0o600)
    try:
        run(['docker', 'network', 'create', '--internal', state['network']])
        env = private / 'database.env'
        env.write_text('MARIADB_DATABASE=cashier_fixture\nMARIADB_USER=fixture\nMARIADB_PASSWORD=' + state['db_password']
                       + '\nMARIADB_ROOT_PASSWORD=' + secrets.token_urlsafe(24) + '\n')
        env.chmod(0o600)
        run(['docker', 'run', '-d', '--pull=never', '--name', state['database'], '--network', state['network'],
             '--memory=768m', '--cpus=1', '--tmpfs', '/var/lib/mysql:rw,size=512m', '--env-file', str(env), DB])
        for _ in range(45):
            check = subprocess.run(['docker', 'exec', state['database'], 'healthcheck.sh', '--connect', '--innodb_initialized'], capture_output=True)
            if check.returncode == 0:
                break
            time.sleep(1)
        else:
            raise RuntimeError('Synthetic database did not become ready.')
        shutil.copytree(SOURCE / 'wp-admin', site / 'wp-admin')
        for name in ('wp-includes',):
            (site / name).symlink_to('/reference/' + name)
        for name in SOURCE.glob('*.php'):
            if name.name.startswith('wp-') or name.name in ('index.php', 'xmlrpc.php'):
                if name.name != 'wp-config.php':
                    shutil.copyfile(name, site / name.name)
        plugins = site / 'wp-content/plugins'
        plugins.mkdir(parents=True)
        for slug in ('woocommerce', 'bactive-paymongo-hosted-checkout', 'bactive-cashier'):
            (plugins / slug).symlink_to('/reference/wp-content/plugins/' + slug)
        mu = site / 'wp-content/mu-plugins'
        mu.mkdir()
        (mu / 'fixture-only.php').write_text("<?php\nadd_filter('pre_http_request', static function(){ return new WP_Error('fixture_network_denied', 'Synthetic fixture blocks outbound HTTP.'); }, PHP_INT_MAX);\nadd_filter('pre_wp_mail', '__return_true');\n")
        (mu / 'provider-fixture.php').symlink_to('/fixture-tools/provider-fixture.php')
        theme = site / 'wp-content/themes/fixture'
        theme.mkdir(parents=True)
        (theme / 'style.css').write_text('/* Theme Name: Synthetic cashier fixture */\n')
        (theme / 'index.php').write_text('<?php wp_head(); while(have_posts()){the_post(); the_content();} wp_footer();')
        extra = "define('DISABLE_WP_CRON', true);\ndefine('AUTOMATIC_UPDATER_DISABLED', true);\ndefine('WP_AUTO_UPDATE_CORE', false);\ndefine('WP_ENVIRONMENT_TYPE', 'local');\ndefine('WP_DEBUG', true);\ndefine('WP_DEBUG_DISPLAY', false);\n"
        wp(state, ['config', 'create', '--dbname=cashier_fixture', '--dbuser=fixture', '--dbpass=' + state['db_password'],
                   '--dbhost=' + state['database'], '--skip-check', '--extra-php'], extra)
        wp(state, ['core', 'install', '--url=' + state['url'], '--title=Synthetic B Active cashier',
                   '--admin_user=' + state['admin_user'], '--admin_password=' + state['admin_password'],
                   '--admin_email=fixture@example.invalid', '--skip-email'])
        wp(state, ['theme', 'activate', 'fixture'])
        wp(state, ['plugin', 'activate', 'woocommerce'])
        wp(state, ['action-scheduler', 'migrate', '--batch-size=100'])
        wp(state, ['option', 'update', 'woocommerce_currency', 'PHP'])
        wp(state, ['option', 'update', 'woocommerce_default_country', 'PH:DAV'])
        wp(state, ['option', 'update', 'woocommerce_manage_stock', 'yes'])
        wp(state, ['option', 'update', 'woocommerce_hold_stock_minutes', '60'])
        wp(state, ['option', 'update', 'woocommerce_custom_orders_table_enabled', 'yes'])
        wp(state, ['rewrite', 'structure', '/%postname%/'])
        wp(state, ['plugin', 'activate', 'bactive-paymongo-hosted-checkout'])
        web_mounts = mounted(state)
        web_mounts[1] = 'bridge'
        run(['docker', 'run', '-d', '--pull=never', '--name', state['web'], *web_mounts,
             '--publish', '127.0.0.1:8097:8080', '--entrypoint', 'php', CLI,
             '-S', '0.0.0.0:8080', '-t', '/var/www/html', '/fixture-tools/router.php'])
        run(['docker', 'network', 'connect', state['network'], state['web']])
        print(json.dumps({'url': state['url'], 'state': str(STATE), 'network': 'database internal; browser loopback only; WordPress outbound HTTP blocked', 'data': 'synthetic only'}))
    except Exception:
        stop(state)
        raise


if __name__ == '__main__':
    action = sys.argv[1] if len(sys.argv) > 1 else 'status'
    if action == 'start':
        start()
    elif action == 'stop':
        stop(json.loads(STATE.read_text()))
    elif action == 'wp':
        print(wp(json.loads(STATE.read_text()), sys.argv[2:]))
    elif action == 'status':
        state = json.loads(STATE.read_text())
        print(json.dumps({k: state[k] for k in ('url', 'root', 'web', 'database')}))
    else:
        raise SystemExit('Unknown action.')
