import os
import subprocess
import tempfile
import unittest
from pathlib import Path


class OffsiteMarkerTests(unittest.TestCase):
    def test_only_a_completed_success_updates_the_marker(self):
        source = (Path(__file__).resolve().parents[1] / "render-status-snapshot.sh").read_text()
        functions = source.split("if python3 /projects/genesis/scripts/check-public-routing.py", 1)[0]
        with tempfile.TemporaryDirectory() as directory:
            marker = Path(directory) / "marker"
            for result, finished, expected in (
                ("success", "2026-11-18 12:00:00 UTC", "1795003200"),
                ("exit-code", "2026-11-18 13:00:00 UTC", "1795003200"),
                ("success", "", "1795003200"),
                ("success", "invalid", "1795003200"),
            ):
                script = functions + '''
OFFSITE_HEALTH_FILE="$1"
systemctl() {
  case "$*" in
    *--property=Result*) printf '%s' "$TEST_RESULT" ;;
    *--property=ExecMainExitTimestamp*) printf '%s' "$TEST_FINISHED" ;;
    *) return 1 ;;
  esac
}
record_offsite_success
'''
                subprocess.run(
                    ["bash", "-c", script, "test", str(marker)], check=True,
                    env={**os.environ, "TEST_RESULT": result, "TEST_FINISHED": finished},
                )
                self.assertEqual(marker.read_text().strip(), expected)
                self.assertEqual(marker.stat().st_mode & 0o777, 0o600)
