import os
import subprocess
import tempfile
import unittest
from pathlib import Path


class MonitoringRollbackTests(unittest.TestCase):
    def run_rollback(self, result):
        source = (Path(__file__).resolve().parents[1] / "deploy.sh").read_text()
        functions = source.split("trap cleanup EXIT", 1)[0]
        with tempfile.TemporaryDirectory() as directory:
            calls = Path(directory) / "calls"
            script = functions + '''
BACKUP_ITEMS=(first second)
restore_item() { printf 'restore %s\\n' "$1" >> "$CALLS"; }
docker() { :; }
restart_platform_services() { printf 'platform\\n' >> "$CALLS"; return "$PLATFORM_RESULT"; }
healthcheck() { printf 'health\\n' >> "$CALLS"; }
if rollback; then exit 0; else exit 23; fi
'''
            process = subprocess.run(
                ["bash", "-c", script], capture_output=True,
                env={**os.environ, "CALLS": str(calls), "PLATFORM_RESULT": str(result),
                     "GENESIS_INSTALL_HOST_INTEGRATION": "0"},
            )
            return process.returncode, calls.read_text().splitlines()

    def test_restores_monitoring_after_files_and_before_health(self):
        status, calls = self.run_rollback(0)
        self.assertEqual(status, 0)
        self.assertEqual(calls, ["restore first", "restore second", "platform", "health"])

    def test_platform_failure_prevents_successful_rollback(self):
        status, calls = self.run_rollback(1)
        self.assertEqual(status, 23)
        self.assertEqual(calls, ["restore first", "restore second", "platform"])
