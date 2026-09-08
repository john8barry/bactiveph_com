"""Security/negative-path checks for local recovery preparation, not restore proof."""

import contextlib
import copy
import gzip
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import stat
import tempfile
import unittest
from unittest import mock
import zipfile

spec = importlib.util.spec_from_file_location(
    "catalog_recovery", Path(__file__).resolve().parents[1] / "tools/catalog-recovery/recovery.py")
recovery = importlib.util.module_from_spec(spec)
spec.loader.exec_module(recovery)


class CatalogueRecoveryTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.base = Path(self.temporary.name).resolve()
        self.source = self.base / "production-backup"
        self.source.mkdir(mode=0o700)
        self.manifest = {"success": 1, "time": 1788836317,
                         "environment": "production", "files": []}
        for kind in sorted(recovery.KINDS):
            name = "backup_fixture-" + kind + (".gz" if kind == "db" else ".zip")
            path = self.source / name
            if kind == "db":
                with gzip.open(path, "wb") as archive:
                    archive.write(b"# WordPress Version: 7.1, running on PHP 8.2.33 (), MySQL 11.4.12-MariaDB\nCREATE TABLE fixture (id INT);\n")
            else:
                with zipfile.ZipFile(path, "w", zipfile.ZIP_DEFLATED) as archive:
                    archive.writestr((kind if kind != "others" else "litespeed") + "/fixture.txt", b"synthetic fixture\n")
            path.chmod(0o600)
            self.manifest["files"].append({"kind": kind, "name": name,
                "path": recovery.REMOTE_BACKUP_ROOT + name, "bytes": path.stat().st_size,
                "sha256": hashlib.sha256(path.read_bytes()).hexdigest(), "verified_offserver": True})
        self.path = self.source / "manifest.json"
        self.save_manifest()

    def tearDown(self):
        self.temporary.cleanup()

    def save_manifest(self):
        self.path.write_bytes(recovery.json_bytes(self.manifest))
        self.path.chmod(0o600)

    def change_archive(self, kind, modify):
        entry = next(row for row in self.manifest["files"] if row["kind"] == kind)
        path = self.source / entry["name"]
        modify(path)
        path.chmod(0o600)
        entry.update(bytes=path.stat().st_size, sha256=hashlib.sha256(path.read_bytes()).hexdigest())
        self.save_manifest()

    def fingerprints(self):
        return {p.name: (hashlib.sha256(p.read_bytes()).hexdigest(), stat.S_IMODE(p.stat().st_mode))
                for p in self.source.iterdir()}

    def test_verified_preview_is_read_only_and_still_blocks_restore(self):
        before = self.fingerprints()
        output = io.StringIO()
        with contextlib.redirect_stdout(output):
            self.assertEqual(recovery.main(["--backup-manifest", str(self.path)]), 0)
        report = json.loads(output.getvalue())
        self.assertEqual(report["mode"], "PREVIEW_ONLY")
        self.assertEqual(report["result"], "ARCHIVE_INTEGRITY_PASS_RESTORE_BLOCKED")
        self.assertFalse(report["wordpress_booted"])
        self.assertFalse(report["database_imported"])
        self.assertFalse(report["files_written"])
        self.assertEqual(before, self.fingerprints())
        self.assertEqual(set(self.base.iterdir()), {self.source})

    def test_verification_and_preparation_never_start_network_or_processes(self):
        forbidden = AssertionError("Network or external process attempted")
        with contextlib.ExitStack() as guards:
            for target in ("subprocess.Popen", "os.system", "socket.create_connection",
                           "socket.socket.connect", "socket.socket.connect_ex"):
                guards.enter_context(mock.patch(target, side_effect=forbidden))
            report = recovery.verify_backup(self.path)
            result = recovery.prepare(self.path, "offline-only", root=self.base / "private-runs")
        self.assertFalse(report["network_operations"])
        self.assertFalse(result["containers_started"])
        self.assertFalse(result["wordpress_booted"])

    def test_tampered_bytes_and_incomplete_coverage_fail(self):
        entry = self.manifest["files"][0]
        path = self.source / entry["name"]
        original = path.read_bytes()
        path.write_bytes(original + b"tamper")
        with self.assertRaises(recovery.RecoveryError):
            recovery.verify_backup(self.path)
        path.write_bytes(original)
        self.manifest["files"] = [row for row in self.manifest["files"] if row["kind"] != "mu-plugins"]
        self.save_manifest()
        with self.assertRaises(recovery.RecoveryError):
            recovery.verify_backup(self.path)

    def test_manifest_traversal_other_target_and_duplicate_keys_rejected(self):
        original = copy.deepcopy(self.manifest)
        for field, value in [("name", "../../private.gz"), ("path", "/home/other/site/backup.gz"),
                             ("bytes", True), ("kind", "unknown"), ("sha256", "x" * 64)]:
            with self.subTest(field=field):
                self.manifest = copy.deepcopy(original)
                self.manifest["files"][0][field] = value
                self.save_manifest()
                with self.assertRaises(recovery.RecoveryError):
                    recovery.verify_backup(self.path)
        with self.assertRaises(recovery.RecoveryError):
            json.loads('{"success":1,"success":0}', object_pairs_hook=recovery.unique_keys)

    def test_archive_traversal_symlink_and_duplicate_members_rejected(self):
        def make_member(path, member):
            with zipfile.ZipFile(path, "w") as archive:
                archive.writestr(member, "synthetic")
        for name in ("../../outside", "/absolute", "plugins/../outside", "wrong-component/file"):
            with self.subTest(name=name):
                self.change_archive("plugins", lambda p: make_member(p, name))
                with self.assertRaises(recovery.RecoveryError):
                    recovery.verify_backup(self.path)
        def symlink(path):
            with zipfile.ZipFile(path, "w") as archive:
                member = zipfile.ZipInfo("plugins/link")
                member.create_system = 3
                member.external_attr = (stat.S_IFLNK | 0o777) << 16
                archive.writestr(member, "/private")
        self.change_archive("plugins", symlink)
        with self.assertRaises(recovery.RecoveryError):
            recovery.verify_backup(self.path)
        def duplicate(path):
            with zipfile.ZipFile(path, "w") as archive:
                archive.writestr("plugins/file", "first")
                archive.writestr("plugins/./file", "second")
        self.change_archive("plugins", duplicate)
        with self.assertRaises(recovery.RecoveryError):
            recovery.verify_backup(self.path)

    def test_nonprivate_and_symlink_source_rejected(self):
        entry = self.manifest["files"][0]
        path = self.source / entry["name"]
        path.chmod(0o644)
        with self.assertRaises(recovery.RecoveryError):
            recovery.verify_backup(self.path)
        path.chmod(0o600)
        other = self.base / "other"
        path.rename(other)
        path.symlink_to(other)
        with self.assertRaises(recovery.RecoveryError):
            recovery.verify_backup(self.path)

    def test_bad_crc_and_expansion_limits_rejected(self):
        def invalid_gzip(path):
            data = bytearray(path.read_bytes())
            data[-8] ^= 1
            path.write_bytes(data)
        self.change_archive("db", invalid_gzip)
        with self.assertRaises(recovery.RecoveryError):
            recovery.verify_backup(self.path)
        def valid_gzip(path):
            with gzip.open(path, "wb") as archive:
                archive.write(b"bounded" * 500)
        self.change_archive("db", valid_gzip)
        with mock.patch.object(recovery, "MAX_EXPANDED", 100):
            with self.assertRaises(recovery.RecoveryError):
                recovery.verify_backup(self.path)

    def test_apply_creates_only_new_private_copy_and_blocked_controls(self):
        before = self.fingerprints()
        root = self.base / "private-runs"
        result = recovery.prepare(self.path, "synthetic-run", port=8881, root=root)
        run = Path(result["workdir"])
        self.assertEqual(result["status"], "PREPARED_RESTORE_BLOCKED")
        self.assertFalse(result["containers_started"])
        self.assertFalse((run / "PREPARATION-INCOMPLETE").exists())
        self.assertEqual(before, self.fingerprints())
        for entry in self.manifest["files"]:
            copy_path = run / "snapshot" / entry["name"]
            self.assertEqual(hashlib.sha256(copy_path.read_bytes()).hexdigest(), entry["sha256"])
            self.assertEqual(stat.S_IMODE(copy_path.stat().st_mode), 0o600)
        self.assertEqual(stat.S_IMODE(run.stat().st_mode), 0o700)
        config = json.loads((run / "compose.json").read_text())
        recovery.validate_containment(config, run)
        local_config = (run / "site/wp-config.php").read_text()
        self.assertNotIn("@@", local_config)
        self.assertIn("http://127.0.0.1:8881", local_config)
        self.assertNotIn("https://bactiveph.com", local_config)
        self.assertFalse((run / "site/wp-settings.php").exists())
        blocker = (run / "control/blocked-entrypoint.sh").read_text()
        self.assertIn("exit 78", blocker)
        self.assertNotIn("exec ", blocker)
        with self.assertRaises(recovery.RecoveryError):
            recovery.prepare(self.path, "synthetic-run", root=root)

    def test_apply_requires_new_local_id_and_does_not_accept_symlink_root(self):
        for run_id in ("../../outside", "A", "bad id", "x/host", ""):
            with self.subTest(run_id=run_id), self.assertRaises(recovery.RecoveryError):
                recovery.safe_run_path(run_id, self.base / "private-runs")
        actual = self.base / "actual"
        actual.mkdir(mode=0o700)
        linked = self.base / "linked"
        linked.symlink_to(actual, target_is_directory=True)
        with self.assertRaises(recovery.RecoveryError):
            recovery.safe_run_path("synthetic-run", linked)
        output = io.StringIO()
        with contextlib.redirect_stderr(output):
            self.assertEqual(recovery.main(["--backup-manifest", str(self.path), "--apply"]), 2)
        self.assertNotIn("CREATE TABLE", output.getvalue())

    def test_network_dns_host_mount_and_startup_overrides_rejected(self):
        run = self.base / "private-runs" / "synthetic-run"
        original = recovery.containment_config(run)
        mutations = [
            lambda c: c["networks"]["recovery"].update(internal=False),
            lambda c: c["services"]["wordpress"].update(dns=["8.8.8.8"]),
            lambda c: c["services"]["wordpress"].update(network_mode="host"),
            lambda c: c["services"]["database"].update(ports=["3306:3306"]),
            lambda c: c["services"]["wordpress"]["ports"][0].update(host_ip="0.0.0.0"),
            lambda c: c["services"]["wordpress"].update(extra_hosts={"host.docker.internal": "host-gateway"}),
            lambda c: c["services"]["wordpress"].update(entrypoint=["php"]),
            lambda c: c["services"]["wordpress"].update(privileged=True),
            lambda c: c["services"]["wordpress"]["volumes"][0].update(source="/var/run/docker.sock"),
            lambda c: c["services"]["wordpress"]["volumes"].pop(1),
            lambda c: c["services"]["wordpress"]["volumes"][0].update(source=str(run / "snapshot/manifest.json")),
            lambda c: c["services"]["wordpress"].update(command=["php", "-S", "0.0.0.0:8080"]),
        ]
        for index, mutation in enumerate(mutations):
            with self.subTest(index=index):
                config = copy.deepcopy(original)
                mutation(config)
                with self.assertRaises(recovery.RecoveryError):
                    recovery.validate_containment(config, run)
        resolver = (Path(recovery.__file__).parent / "resolv.conf").read_text()
        self.assertEqual([line for line in resolver.splitlines() if line.startswith("nameserver")],
                         ["nameserver 127.0.0.1"])

    def test_failure_keeps_private_incomplete_run_without_touching_source(self):
        before = self.fingerprints()
        root = self.base / "private-runs"
        original_write = recovery.write_new
        def fail_on_compose(path, data, mode=0o600):
            if Path(path).name == "compose.json":
                raise OSError("synthetic disk failure")
            return original_write(path, data, mode)
        with mock.patch.object(recovery, "write_new", side_effect=fail_on_compose):
            with self.assertRaises(OSError):
                recovery.prepare(self.path, "failed-run", root=root)
        self.assertTrue((root / "failed-run/PREPARATION-INCOMPLETE").is_file())
        self.assertFalse((root / "failed-run/state.json").exists())
        self.assertEqual(before, self.fingerprints())


if __name__ == "__main__":
    unittest.main()
