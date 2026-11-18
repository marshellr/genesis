import importlib.util
import sqlite3
import tempfile
import unittest
from pathlib import Path


spec = importlib.util.spec_from_file_location(
    "sqlite_snapshot", Path(__file__).resolve().parents[1] / "sqlite-snapshot.py"
)
snapshot = importlib.util.module_from_spec(spec)
spec.loader.exec_module(snapshot)


class SQLiteSnapshotTests(unittest.TestCase):
    def test_committed_wal_is_copied_without_changing_the_source(self):
        with tempfile.TemporaryDirectory() as directory:
            source = Path(directory) / "source.db"
            target = Path(directory) / "copy.db"
            with sqlite3.connect(source) as connection:
                connection.execute("PRAGMA journal_mode=WAL")
                connection.execute("CREATE TABLE example (value INTEGER)")
                connection.execute("INSERT INTO example VALUES (7)")
                connection.commit()
                snapshot.snapshot(source, target)
                with sqlite3.connect(target) as copy:
                    self.assertEqual(copy.execute("SELECT value FROM example").fetchall(), [(7,)])
                self.assertEqual(connection.execute("SELECT value FROM example").fetchall(), [(7,)])
                with self.assertRaises(ValueError):
                    snapshot.snapshot(source, source)
