#!/usr/bin/env python3
"""Verify a private backup and prepare a blocked, local-only recovery workspace.

This program never imports SQL, extracts site archives, invokes Docker, starts
WordPress, reads hosting credentials, or contacts a network. Preparation is not
a successful restore drill. Use --apply only to create a new private workspace.
"""

import argparse
import contextlib
import gzip
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import secrets
import shutil
import stat
import sys
import zipfile


SCHEMA = 1
TARGET = "https://bactiveph.com"
REMOTE_BACKUP_ROOT = "/home/waypmvhk/bactiveph.com/wp-content/updraft/"
WORK_ROOT = Path.home() / "Library/Application Support/BactivePH/catalog-recovery"
KINDS = frozenset({"db", "plugins", "themes", "uploads", "mu-plugins", "others"})
MAX_COMPRESSED = 8 * 1024**3
MAX_EXPANDED = 16 * 1024**3
MAX_MEMBER = 2 * 1024**3
MAX_ENTRIES = 200000
CHUNK = 1024**2
NAME = re.compile(r"[A-Za-z0-9][A-Za-z0-9_.-]{0,179}\Z")
RUN_ID = re.compile(r"[a-z0-9][a-z0-9-]{2,63}\Z")
HEX = re.compile(r"[a-f0-9]{64}\Z")
CORE_AND_ROOT = ("wp-config.php", ".htaccess", ".user.ini", "wordfence-waf.php",
                 "wp-load.php", "wp-settings.php", "wp-includes/version.php")
# Inspected local ARM64 images. These do not establish full production parity.
# The database image is 11.4.13; the inspected backup header says 11.4.12.
DATABASE_IMAGE = "mariadb@sha256:611a2fcc5fa7c6ceb8644c6f74b25ede004ff6c3a6b38c8f8c23d3bbf6c26430"
PHP_IMAGE = "php@sha256:0ca296c0ae905940c0a333a15de437f3acaa81743333a9437221f6a44e3f0981"
BLOCKERS = [
    "PARTIAL_PACKAGE: site-root configuration and WordPress core are not qualified",
    "RAW_DATABASE: a reviewed offline sanitizer and synthetic-user lockdown are not implemented",
    "RUNTIME_PARITY: core, PHP extensions, database patch and web-server behavior need review",
    "CONSISTENCY: archive integrity does not prove a database-wide consistent recovery point",
    "ENCRYPTION: these readable ZIP/gzip copies do not establish encrypted off-device custody",
    "NO_RESTORE: database import, WordPress boot and browser/network tests have not run",
    "FRESHNESS: this package does not replace a fresh pre-release backup",
]


class RecoveryError(ValueError):
    """A sanitized, operator-facing validation failure."""


def unique_keys(pairs):
    result = {}
    for key, value in pairs:
        if key in result:
            raise RecoveryError("Duplicate JSON keys are not accepted")
        result[key] = value
    return result


def json_bytes(value):
    return (json.dumps(value, indent=2, sort_keys=True) + "\n").encode()


@contextlib.contextmanager
def private_reader(path):
    """Reject symlinks and nonregular/private source files before reading."""
    path = Path(path)
    try:
        fd = os.open(path, os.O_RDONLY | os.O_NOFOLLOW)
    except OSError as exc:
        raise RecoveryError("A required private regular file cannot be opened") from exc
    with os.fdopen(fd, "rb") as stream:
        info = os.fstat(stream.fileno())
        if not stat.S_ISREG(info.st_mode) or info.st_uid != os.getuid():
            raise RecoveryError("Recovery input must be a regular file owned by this user")
        if stat.S_IMODE(info.st_mode) & 0o077:
            raise RecoveryError("Recovery input must not be group/world accessible")
        yield stream


def private_directory(path):
    path = Path(path)
    if path.is_symlink():
        raise RecoveryError("Recovery directories cannot be symbolic links")
    try:
        info = path.stat()
    except OSError as exc:
        raise RecoveryError("Required private directory is unavailable") from exc
    if (not stat.S_ISDIR(info.st_mode) or info.st_uid != os.getuid()
            or stat.S_IMODE(info.st_mode) & 0o077):
        raise RecoveryError("Recovery directories must be private and owned by this user")


def bounded_read(path, limit=1024**2):
    with private_reader(path) as stream:
        data = stream.read(limit + 1)
    if len(data) > limit:
        raise RecoveryError("Recovery metadata exceeds its bounded size")
    return data


def digest_file(path):
    digest = hashlib.sha256()
    size = 0
    with private_reader(path) as stream:
        for block in iter(lambda: stream.read(CHUNK), b""):
            size += len(block)
            if size > MAX_COMPRESSED:
                raise RecoveryError("Backup file exceeds the compressed-size limit")
            digest.update(block)
    return size, digest.hexdigest()


def validate_manifest(manifest):
    if not isinstance(manifest, dict) or set(manifest) != {"success", "time", "files", "environment"}:
        raise RecoveryError("Unsupported backup manifest shape")
    if type(manifest["success"]) is not int or manifest["success"] != 1:
        raise RecoveryError("The recorded backup job did not succeed")
    if manifest["environment"] != "production":
        raise RecoveryError("This recovery plan requires the production backup manifest")
    if type(manifest["time"]) is not int or manifest["time"] <= 0:
        raise RecoveryError("Backup timestamp is invalid")
    files = manifest["files"]
    if not isinstance(files, list) or not 6 <= len(files) <= 200:
        raise RecoveryError("Backup component inventory is invalid")
    seen = set()
    kinds = set()
    total = 0
    for entry in files:
        if not isinstance(entry, dict) or set(entry) != {"kind", "name", "path", "bytes", "sha256", "verified_offserver"}:
            raise RecoveryError("Unsupported backup component shape")
        kind, name = entry["kind"], entry["name"]
        if not isinstance(kind, str) or kind not in KINDS:
            raise RecoveryError("Unrecognized backup component")
        if not isinstance(name, str) or not NAME.fullmatch(name) or ".." in name or name in seen:
            raise RecoveryError("Unsafe or duplicate backup filename")
        expected_suffix = ".gz" if kind == "db" else ".zip"
        if not name.endswith(expected_suffix) or not name.startswith("backup_"):
            raise RecoveryError("Unexpected backup archive type")
        if entry["path"] != REMOTE_BACKUP_ROOT + name:
            raise RecoveryError("Manifest source path is outside the exact production backup root")
        if type(entry["bytes"]) is not int or not 0 < entry["bytes"] <= MAX_COMPRESSED:
            raise RecoveryError("Invalid backup size")
        if not isinstance(entry["sha256"], str) or not HEX.fullmatch(entry["sha256"]):
            raise RecoveryError("Invalid backup checksum")
        if entry["verified_offserver"] is not True:
            raise RecoveryError("The transfer receipt is incomplete")
        total += entry["bytes"]
        seen.add(name)
        kinds.add(kind)
    if kinds != KINDS or total > MAX_COMPRESSED:
        raise RecoveryError("Required component coverage or total-size limit failed")


def check_member(info, kind):
    name = info.filename
    path = PurePosixPath(name)
    if (not name or "\x00" in name or "\\" in name or ":" in name
            or path.is_absolute() or ".." in path.parts
            or any(ord(char) < 32 for char in name)):
        raise RecoveryError("Archive contains an unsafe member path")
    if kind != "others" and (not path.parts or path.parts[0] != kind):
        raise RecoveryError("Archive member is outside its declared content component")
    file_type = stat.S_IFMT(info.external_attr >> 16)
    if file_type not in (0, stat.S_IFREG, stat.S_IFDIR):
        raise RecoveryError("Archive symlinks and special files are not accepted")
    if info.flag_bits & 1:
        raise RecoveryError("Encrypted archive requires a separate reviewed decryption procedure")
    if info.compress_type not in (zipfile.ZIP_STORED, zipfile.ZIP_DEFLATED):
        raise RecoveryError("Unsupported archive compression")
    if info.file_size > MAX_MEMBER:
        raise RecoveryError("Archive member exceeds the expanded-size limit")


def inspect_archive(path, kind, expanded_remaining):
    expanded = 0
    if kind == "db":
        header = bytearray()
        with private_reader(path) as source, gzip.GzipFile(fileobj=source) as archive:
            for block in iter(lambda: archive.read(CHUNK), b""):
                expanded += len(block)
                if expanded > min(MAX_MEMBER, expanded_remaining):
                    raise RecoveryError("Database expansion exceeds the bounded limit")
                if len(header) < 8192:
                    header.extend(block[:8192-len(header)])
        version = re.search(rb"# WordPress Version: ([0-9.]+), running on PHP ([0-9.]+).*?MySQL ([0-9.]+(?:-MariaDB)?)", header)
        versions = dict(zip(("wordpress", "php", "database"), (v.decode() for v in version.groups()))) if version else {}
        return {"integrity": "PASS", "expanded_bytes": expanded,
                "encryption": "readable_gzip", "header_versions": versions}
    with private_reader(path) as source, zipfile.ZipFile(source) as archive:
        infos = archive.infolist()
        if len(infos) > MAX_ENTRIES:
            raise RecoveryError("Archive member-count limit exceeded")
        seen = set()
        for info in infos:
            check_member(info, kind)
            normalized = str(PurePosixPath(info.filename))
            if normalized in seen:
                raise RecoveryError("Duplicate archive member path")
            seen.add(normalized)
            expanded += info.file_size
            if expanded > expanded_remaining:
                raise RecoveryError("Archive expansion exceeds the bounded limit")
        # Read every member to EOF for CRC verification without extracting it.
        for info in infos:
            count = 0
            with archive.open(info) as member:
                for block in iter(lambda: member.read(CHUNK), b""):
                    count += len(block)
                    if count > info.file_size:
                        raise RecoveryError("Archive member length disagrees with its directory")
            if count != info.file_size:
                raise RecoveryError("Archive member was incomplete")
    return {"integrity": "PASS", "expanded_bytes": expanded,
            "member_count": len(infos), "encryption": "readable_zip",
            "archive_paths_validated": True}


def verify_backup(manifest_path):
    path = Path(manifest_path)
    if path.name != "manifest.json":
        raise RecoveryError("Expected the named backup manifest.json")
    private_directory(path.parent)
    raw = bounded_read(path)
    try:
        manifest = json.loads(raw, object_pairs_hook=unique_keys)
    except (json.JSONDecodeError, UnicodeError) as exc:
        raise RecoveryError("Backup manifest is not valid UTF-8 JSON") from exc
    validate_manifest(manifest)
    results = []
    expanded = 0
    for entry in manifest["files"]:
        component = path.parent / entry["name"]
        size, checksum = digest_file(component)
        if size != entry["bytes"] or checksum != entry["sha256"]:
            raise RecoveryError("A backup component disagrees with its size/checksum receipt")
        try:
            inspection = inspect_archive(component, entry["kind"], MAX_EXPANDED-expanded)
        except (OSError, EOFError, zipfile.BadZipFile, RuntimeError) as exc:
            raise RecoveryError("Archive integrity verification failed; raw contents withheld") from exc
        if digest_file(component) != (size, checksum):
            raise RecoveryError("Backup component changed during integrity inspection")
        expanded += inspection["expanded_bytes"]
        results.append({key: entry[key] for key in ("kind", "name", "bytes", "sha256")} | inspection)
    if bounded_read(path) != raw:
        raise RecoveryError("Manifest changed during verification")
    return {"schema": SCHEMA, "target_claim": TARGET,
            "authenticated_target_verified": False, "backup_time": manifest["time"],
            "manifest_sha256": hashlib.sha256(raw).hexdigest(),
            "result": "ARCHIVE_INTEGRITY_PASS_RESTORE_BLOCKED", "files": results,
            "total_bytes": sum(item["bytes"] for item in results),
            "expanded_bytes": expanded,
            "core_and_root_coverage": {name: "NOT_QUALIFIED_BY_CONTENT_ONLY_PACKAGE" for name in CORE_AND_ROOT},
            "blockers": list(BLOCKERS), "source_mutations": False,
            "network_operations": False, "database_imported": False, "wordpress_booted": False}


def safe_run_path(run_id, root=None):
    root = Path(root) if root is not None else WORK_ROOT
    if not isinstance(run_id, str) or not RUN_ID.fullmatch(run_id):
        raise RecoveryError("Run ID must contain 3 to 64 lowercase letters, digits or hyphens")
    if root != root.resolve():
        raise RecoveryError("The private work root cannot traverse symbolic links")
    if root.exists():
        private_directory(root)
    destination = root / run_id
    if destination.exists() or destination.is_symlink():
        raise RecoveryError("Run directory already exists; never overwrite a prior recovery run")
    return destination


def containment_config(workdir, port=8879):
    workdir = Path(workdir)
    if type(port) is not int or not 1024 <= port <= 65535:
        raise RecoveryError("Choose an unprivileged local port")
    def bind(relative, target):
        return {"type": "bind", "source": str(workdir / relative), "target": target,
                "read_only": True, "bind": {"create_host_path": False}}
    common = {
        "entrypoint": ["/bin/sh", "/recovery/blocked-entrypoint.sh"],
        "read_only": True, "cap_drop": ["ALL"],
        "security_opt": ["no-new-privileges:true"],
        "dns": ["127.0.0.1"], "dns_opt": ["timeout:1", "attempts:1"],
        "restart": "no", "pids_limit": 128, "mem_limit": "512m",
        "tmpfs": ["/tmp:rw,noexec,nosuid,nodev,size=64m"],
        "logging": {"driver": "none"},
        "volumes": [bind("control/blocked-entrypoint.sh", "/recovery/blocked-entrypoint.sh"),
                    bind("control/resolv.conf", "/etc/resolv.conf")],
    }
    db = common | {"image": DATABASE_IMAGE, "pull_policy": "never", "user": "999:999",
                   "networks": {"recovery": {"ipv4_address": "172.31.252.2"}}}
    wp = common | {"image": PHP_IMAGE, "pull_policy": "never", "user": "1000:1000",
                   "networks": {"recovery": {"ipv4_address": "172.31.252.3"}},
                   "extra_hosts": {"catalog-db": "172.31.252.2"},
                   "ports": [{"target": 8080, "published": str(port), "host_ip": "127.0.0.1", "protocol": "tcp"}],
                   "volumes": common["volumes"] + [bind("site", "/var/www/html"),
                              bind("control/local.ini", "/usr/local/etc/php/conf.d/zz-recovery.ini")]}
    # Raw archives and credential directories are deliberately mounted nowhere.
    return {"name": "bactive-recovery-" + workdir.name,
            "services": {"database": db, "wordpress": wp},
            "networks": {"recovery": {"internal": True, "enable_ipv6": False,
                         "ipam": {"config": [{"subnet": "172.31.252.0/24"}]}}}}


def validate_containment(config, workdir):
    """Validate the rendered proposal, including independent DNS containment."""
    workdir = Path(workdir).resolve()
    if set(config) != {"name", "services", "networks"}:
        raise RecoveryError("Unexpected Compose control surface")
    network = config["networks"]
    if (set(network) != {"recovery"} or network["recovery"].get("internal") is not True
            or network["recovery"].get("enable_ipv6") is not False):
        raise RecoveryError("Only an internal IPv4 recovery network is permitted")
    if set(config["services"]) != {"database", "wordpress"}:
        raise RecoveryError("Unexpected recovery service")
    for name, service in config["services"].items():
        if (service.get("entrypoint") != ["/bin/sh", "/recovery/blocked-entrypoint.sh"]
                or service.get("read_only") is not True
                or service.get("cap_drop") != ["ALL"]
                or service.get("security_opt") != ["no-new-privileges:true"]
                or service.get("user") not in {"999:999", "1000:1000"}
                or service.get("pull_policy") != "never"):
            raise RecoveryError("Required blocked/nonroot container controls are missing")
        if service.get("dns") != ["127.0.0.1"]:
            raise RecoveryError("External DNS must be explicitly disabled")
        targets = set()
        for mount in service.get("volumes", []):
            source = Path(mount.get("source", ""))
            if (mount.get("type") != "bind" or mount.get("read_only") is not True
                    or mount.get("bind") != {"create_host_path": False}
                    or not source.is_absolute() or source.resolve() != source
                    or not source.is_relative_to(workdir)):
                raise RecoveryError("Only immutable mounts inside this private run are permitted")
            if "snapshot" in source.relative_to(workdir).parts or "secrets" in source.relative_to(workdir).parts:
                raise RecoveryError("Raw backup and credential mounts are prohibited")
            targets.add(mount.get("target"))
        if "/etc/resolv.conf" not in targets:
            raise RecoveryError("Mount a loopback resolver file; Docker DNS alone is insufficient")
        if name == "database" and service.get("ports"):
            raise RecoveryError("The database must never publish a host port")
    ports = config["services"]["wordpress"].get("ports", [])
    if len(ports) != 1 or ports[0].get("host_ip") != "127.0.0.1":
        raise RecoveryError("Only one loopback web port is permitted")
    try:
        port = int(ports[0]["published"])
    except (KeyError, ValueError, TypeError) as exc:
        raise RecoveryError("Invalid web-port specification") from exc
    # Reject any extra override (network_mode, host-gateway, privileged, command,
    # build, env_file, additional network, Docker socket, DNS search, etc.).
    if json_bytes(config) != json_bytes(containment_config(workdir, port)):
        raise RecoveryError("Compose differs from the reviewed preparation-only configuration")


def write_new(path, data, mode=0o600):
    path = Path(path)
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, mode)
    with os.fdopen(fd, "wb") as stream:
        stream.write(data)


def prepare(manifest_path, run_id, port=8879, root=None):
    """Copy verified archives and emit blocked controls; never run a restore."""
    destination = safe_run_path(run_id, root)
    report = verify_backup(manifest_path)
    config = containment_config(destination, port)
    validate_containment(config, destination)
    root = destination.parent
    existing_parent = root
    while not existing_parent.exists():
        existing_parent = existing_parent.parent
    required_free = report["total_bytes"] + report["expanded_bytes"] + 2 * 1024**3
    if shutil.disk_usage(existing_parent).free < required_free:
        raise RecoveryError("Insufficient free space for private copy and later rehearsal headroom")
    root.mkdir(mode=0o700, parents=True, exist_ok=True)
    private_directory(root)
    destination.mkdir(mode=0o700, exist_ok=False)
    for folder in ("snapshot", "control", "site", "secrets"):
        (destination / folder).mkdir(mode=0o700)
    write_new(destination / "PREPARATION-INCOMPLETE", b"Preparation has not completed. No restore is authorized.\n")
    source_manifest = Path(manifest_path)
    raw_manifest = bounded_read(source_manifest)
    if hashlib.sha256(raw_manifest).hexdigest() != report["manifest_sha256"]:
        raise RecoveryError("Source manifest changed before the private copy")
    write_new(destination / "snapshot/manifest.json", raw_manifest)
    for entry in report["files"]:
        target = destination / "snapshot" / entry["name"]
        digest = hashlib.sha256()
        copied = 0
        fd = os.open(target, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
        with os.fdopen(fd, "wb") as output, private_reader(source_manifest.parent / entry["name"]) as source:
            for block in iter(lambda: source.read(CHUNK), b""):
                copied += len(block)
                if copied > entry["bytes"]:
                    raise RecoveryError("Source grew while copying; incomplete run retained")
                output.write(block)
                digest.update(block)
        if copied != entry["bytes"] or digest.hexdigest() != entry["sha256"]:
            raise RecoveryError("Source changed while copying; incomplete run retained")
    if bounded_read(source_manifest) != raw_manifest:
        raise RecoveryError("Source manifest changed while copying; incomplete run retained")
    templates = Path(__file__).resolve().parent
    for filename in ("blocked-entrypoint.sh", "resolv.conf", "local.ini", "block-outbound.php"):
        # Individual read-only mounts must be readable by nonroot containers.
        # The containing run/control directories remain owner-only (0700).
        write_new(destination / "control" / filename, (templates / filename).read_bytes(), 0o444)
    # Fresh local-only values; not imported credentials and never included in output.
    local_config = (templates / "wp-config.local.php").read_text()
    local_config = local_config.replace("@@LOCAL_PASSWORD@@", secrets.token_hex(32))
    local_config = local_config.replace("@@LOCAL_PORT@@", str(port))
    for salt in ("AUTH_KEY", "SECURE_AUTH_KEY", "LOGGED_IN_KEY", "NONCE_KEY",
                 "AUTH_SALT", "SECURE_AUTH_SALT", "LOGGED_IN_SALT", "NONCE_SALT"):
        local_config = local_config.replace("@@" + salt + "@@", secrets.token_hex(32))
    write_new(destination / "site/wp-config.php", local_config.encode())
    # The blocker is a draft MU plugin; no site archive/core has been extracted.
    (destination / "site/wp-content").mkdir(mode=0o700)
    (destination / "site/wp-content/mu-plugins").mkdir(mode=0o700)
    write_new(destination / "site/wp-content/mu-plugins/000-recovery-lockdown.php",
              (templates / "block-outbound.php").read_bytes())
    write_new(destination / "compose.json", json_bytes(config))
    write_new(destination / "verification.json", json_bytes(report))
    state = {"schema": SCHEMA, "status": "PREPARED_RESTORE_BLOCKED", "run_id": run_id,
             "manifest_sha256": report["manifest_sha256"], "blockers": list(BLOCKERS),
             "control_sha256": {str(p.relative_to(destination)): hashlib.sha256(p.read_bytes()).hexdigest()
                                for p in sorted((destination / "control").iterdir())},
             "compose_sha256": hashlib.sha256(json_bytes(config)).hexdigest(),
             "database_imported": False, "wordpress_booted": False, "containers_started": False}
    write_new(destination / "state.json", json_bytes(state))
    # Only this run's marker is removed, after every output has been written.
    (destination / "PREPARATION-INCOMPLETE").unlink()
    return {"status": state["status"], "workdir": str(destination),
            "manifest_sha256": report["manifest_sha256"], "source_mutations": False,
            "database_imported": False, "wordpress_booted": False, "containers_started": False,
            "blockers": list(BLOCKERS)}


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--backup-manifest", type=Path, required=True)
    parser.add_argument("--run-id", help="New private directory name, required with --apply")
    parser.add_argument("--port", type=int, default=8879, help="Proposed loopback port; no listener is started")
    parser.add_argument("--apply", action="store_true", help="Create a blocked local preparation only")
    args = parser.parse_args(argv)
    try:
        if args.apply:
            if args.run_id is None:
                raise RecoveryError("--apply requires a new --run-id")
            result = prepare(args.backup_manifest, args.run_id, args.port)
        else:
            report = verify_backup(args.backup_manifest)
            if args.run_id:
                destination = safe_run_path(args.run_id)
                validate_containment(containment_config(destination, args.port), destination)
            result = report | {"mode": "PREVIEW_ONLY", "files_written": False}
        print(json.dumps(result, sort_keys=True))
        return 0
    except (RecoveryError, OSError, ValueError) as exc:
        message = str(exc) if isinstance(exc, RecoveryError) else "Local preparation failed; raw exception withheld"
        print(json.dumps({"status": "BLOCKED", "reason": message}), file=sys.stderr)
        return 2


if __name__ == "__main__":
    sys.exit(main())
