"""Validate the actual workflow shell blocks before publishing a workflow change."""
import re
import subprocess
import unittest
from pathlib import Path


class WorkflowShellTests(unittest.TestCase):
    def test_all_literal_run_blocks_are_valid_bash(self):
        source = Path(__file__).resolve().parents[2] / '.github/workflows/deploy.yml'
        lines = source.read_text().splitlines()
        checked = 0
        for index, line in enumerate(lines):
            match = re.fullmatch(r'(\s+)run: \|', line)
            if not match:
                continue
            indent = len(match[1])
            block = []
            for following in lines[index+1:]:
                if following.strip() and len(following) - len(following.lstrip()) <= indent:
                    break
                block.append(following[indent+2:])
            result = subprocess.run(['bash', '-n'], input='\n'.join(block), text=True, capture_output=True)
            self.assertEqual(result.returncode, 0, 'Invalid workflow run block at line ' + str(index+1) + ': ' + result.stderr)
            checked += 1
        self.assertGreater(checked, 5)
