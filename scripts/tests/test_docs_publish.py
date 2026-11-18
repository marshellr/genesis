import os
import subprocess
import tempfile
import unittest
from pathlib import Path


class DocsPublishTests(unittest.TestCase):
    def test_publication_preserves_the_pages_workflow(self):
        script = Path(__file__).resolve().parents[1] / "publish-docs-site.sh"
        with tempfile.TemporaryDirectory() as directory:
            work = Path(directory)
            project = work / "project"
            source = project / "docs/site"
            source.mkdir(parents=True)
            (source / "index.html").write_text("updated synthetic documentation")
            target = work / "published"
            target.mkdir()
            workflow = target / ".github/workflows/pages.yml"
            workflow.parent.mkdir(parents=True)
            workflow.write_text("synthetic workflow")
            (target / "obsolete.html").write_text("obsolete synthetic page")
            upstream = work / "upstream.git"
            env = {**os.environ, "GIT_AUTHOR_NAME": "Test", "GIT_COMMITTER_NAME": "Test",
                   "GIT_AUTHOR_EMAIL": "test@example.invalid", "GIT_COMMITTER_EMAIL": "test@example.invalid",
                   "PROJECT_ROOT": str(project), "TARGET_REPO_DIR": str(target),
                   "TARGET_REPO_SSH": str(upstream)}
            commands = (
                ["git", "init", "--bare", "--initial-branch=main", str(upstream)],
                ["git", "init", "--initial-branch=main", str(target)],
                ["git", "-C", str(target), "add", "."],
                ["git", "-C", str(target), "commit", "-m", "Initial synthetic pages"],
                ["git", "-C", str(target), "remote", "add", "origin", str(upstream)],
                ["git", "-C", str(target), "push", "--set-upstream", "origin", "main"],
                ["bash", str(script)],
            )
            for command in commands:
                subprocess.run(command, env=env, capture_output=True, check=True)
            self.assertEqual(workflow.read_text(), "synthetic workflow")
            self.assertEqual((target / "index.html").read_text(), "updated synthetic documentation")
            self.assertFalse((target / "obsolete.html").exists())
