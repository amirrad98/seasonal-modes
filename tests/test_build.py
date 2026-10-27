import hashlib
import json
import re
from pathlib import Path
import shutil
import sys
import tempfile
import unittest
import zipfile

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts'))
from build import build
from check import ROOT, check


class PackageTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name) / 'source'
        shutil.copytree(ROOT, self.root, ignore=shutil.ignore_patterns('.git', 'dist', '__pycache__'))

    def tearDown(self):
        self.temp.cleanup()

    def test_package_is_complete_and_reproducible(self):
        version = check(self.root)['Version']
        archive = build('v' + version, self.root)
        original = archive.read_bytes()
        self.assertEqual(original, build('v' + version, self.root).read_bytes())
        with zipfile.ZipFile(archive) as bundle:
            names = bundle.namelist()
            self.assertTrue(all(n.startswith('seasonal-modes/') for n in names))
            for required in ('seasonal-modes.php', 'includes/class-github-updater.php', 'LICENSE', 'packs/halloween/CREDITS.md', 'packs/christmas/CREDITS.md'):
                self.assertIn('seasonal-modes/' + required, names)
            self.assertFalse(any('/tests/' in n or '/scripts/' in n or '/docs/' in n or '/.github/' in n for n in names))
            for directory in ('assets', 'packs', 'includes'):
                for path in (self.root / directory).rglob('*'):
                    if path.is_file():
                        self.assertEqual(path.read_bytes(), bundle.read('seasonal-modes/' + path.relative_to(self.root).as_posix()))
        metadata = json.loads((self.root / 'dist/update.json').read_text())
        self.assertEqual(version, metadata['version'])
        self.assertTrue(metadata['download_url'].endswith('/v' + version + '/seasonal-modes.zip'))

    def test_reject_wrong_or_prerelease_tag(self):
        for tag in ('v0.0.1', 'v1.1.0-beta', 'main', 'v01.1.0'):
            with self.assertRaises(ValueError):
                build(tag, self.root)

    def test_reject_version_mismatch(self):
        path = self.root / 'seasonal-modes.php'
        path.write_text(re.sub(r"const VERSION\s*=\s*'[^']+';", "const VERSION = '999.0.0';", path.read_text()))
        with self.assertRaises(ValueError):
            check(self.root)

    def test_reject_bad_date_missing_asset_and_path_traversal(self):
        path = self.root / 'packs/halloween/pack.json'
        original = path.read_text()
        for target, value in (('date', '02-30'), ('image', 'images/missing.svg'), ('image', '../christmas/images/tree.svg')):
            data = json.loads(original)
            if target == 'date':
                data['window']['start'] = value
            else:
                data['logoBadge']['image'] = value
            path.write_text(json.dumps(data))
            with self.assertRaises(ValueError):
                check(self.root)

    def test_next_version_and_additional_event_can_be_released(self):
        path = self.root / 'seasonal-modes.php'
        source = re.sub(r'( \* Version:\s*)\S+', r'\g<1>99.0.0', path.read_text())
        source = re.sub(r"const VERSION\s*=\s*'[^']+';", "const VERSION = '99.0.0';", source)
        path.write_text(source)
        changelog = self.root / 'CHANGELOG.md'
        changelog.write_text(changelog.read_text() + '\n## 99.0.0\n\nNew event.\n')
        new_pack = self.root / 'packs/new-event'
        new_pack.mkdir(exist_ok=True)
        (new_pack / 'pack.json').write_text(json.dumps(dict(name='New event', version='1.0.0', window=dict(start='03-01', end='03-31'))))
        (new_pack / 'CREDITS.md').write_text('No third party artwork.\n')
        self.assertTrue(build('v99.0.0', self.root).is_file())
        import subprocess
        result = subprocess.run(['php', 'tests/run.php'], cwd=self.root, capture_output=True, text=True)
        self.assertEqual(0, result.returncode, result.stdout + result.stderr)

    def test_initial_artwork_and_runtime_assets_unchanged(self):
        baseline = json.loads((ROOT / 'tests/baseline-assets.json').read_text())
        for name, digest in baseline.items():
            self.assertEqual(digest, hashlib.sha256((ROOT / name).read_bytes()).hexdigest(), name)


if __name__ == '__main__':
    unittest.main()
