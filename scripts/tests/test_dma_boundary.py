import importlib.util
import subprocess
import unittest
from pathlib import Path
from unittest.mock import patch


spec = importlib.util.spec_from_file_location(
    "dma_boundary", Path(__file__).resolve().parents[1] / "check-dma-boundary.py"
)
boundary = importlib.util.module_from_spec(spec)
spec.loader.exec_module(boundary)


class DMABoundaryTests(unittest.TestCase):
    def test_rejects_the_former_static_fallback_for_php(self):
        with patch.object(boundary.subprocess, "run", return_value=subprocess.CompletedProcess([], 0, b"200")):
            with self.assertRaises(ValueError):
                boundary.check("/index.php", 404)

    def test_accepts_missing_endpoints_and_rejects_transport_failure(self):
        with patch.object(boundary.subprocess, "run", return_value=subprocess.CompletedProcess([], 0, b"404")):
            boundary.check("/index.php", 404)
        with patch.object(boundary.subprocess, "run", return_value=subprocess.CompletedProcess([], 7, b"000")):
            with self.assertRaises(ValueError):
                boundary.check("/index.php", 404)
