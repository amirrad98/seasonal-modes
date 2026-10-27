from pathlib import Path
import subprocess
import tempfile
import unittest

SCRIPT = Path(__file__).resolve().parents[1] / 'scripts/check-release-tag.sh'


class ReleaseTagTests(unittest.TestCase):
    def test_existing_annotated_and_missing_tags(self):
        with tempfile.TemporaryDirectory() as temp:
            def git(*args):
                return subprocess.check_output(['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.test', *args], cwd=temp, text=True).strip()
            git('init', '-q')
            path = Path(temp) / 'file.txt'
            path.write_text('first')
            git('add', 'file.txt')
            git('commit', '-qm', 'First fixture commit')
            first = git('rev-parse', 'HEAD')
            git('tag', 'v1.0.0')
            git('tag', '-a', 'v1.0.1', '-m', 'Annotated fixture tag')
            path.write_text('second')
            git('add', 'file.txt')
            git('commit', '-qm', 'Second fixture commit')
            second = git('rev-parse', 'HEAD')
            for tag, sha, expected in [('v1.0.0', first, 0), ('v1.0.1', first, 0), ('v1.0.0', second, 1), ('v1.0.1', second, 1), ('v2.0.0', second, 0), ('--bad', second, 1)]:
                result = subprocess.run(['bash', str(SCRIPT), tag, sha], cwd=temp, capture_output=True, text=True)
                self.assertEqual(expected, result.returncode, (tag, sha, result.stdout, result.stderr))


if __name__ == '__main__':
    unittest.main()
