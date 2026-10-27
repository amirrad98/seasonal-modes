#!/usr/bin/env python3
"""Build only distributable files under the stable WordPress plugin folder."""
import argparse
import json
import re
import zipfile
from pathlib import Path
from check import ROOT, SEMVER, check


def build(tag=None, root=ROOT, output=None):
    fields = check(root)
    version = fields['Version']
    tag = tag or 'v' + version
    if not re.fullmatch('v' + SEMVER, tag) or tag != 'v' + version:
        raise ValueError('Release tag must match plugin version: v' + version)
    output = output or root / 'dist'
    output.mkdir(parents=True, exist_ok=True)
    files = [root / name for name in ('seasonal-modes.php', 'README.md', 'CHANGELOG.md', 'LICENSE')]
    for directory in ('includes', 'assets', 'packs'):
        for path in (root / directory).rglob('*'):
            if path.is_symlink():
                raise ValueError('Symlinks are not distributable: ' + str(path))
            if path.is_file() and not any(p.startswith('.') for p in path.relative_to(root).parts):
                if path.suffix.lower() not in ('.php', '.css', '.js', '.json', '.svg', '.png', '.jpg', '.jpeg', '.webp', '.gif', '.md', '.txt'):
                    raise ValueError('Unexpected runtime file: ' + str(path))
                files.append(path)
    archive = output / 'seasonal-modes.zip'
    with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_DEFLATED) as bundle:
        for path in sorted(files):
            info = zipfile.ZipInfo('seasonal-modes/' + path.relative_to(root).as_posix(), date_time=(2020, 1, 1, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            bundle.writestr(info, path.read_bytes())
    manifest = dict(version=version, requires=fields['Requires at least'], requires_php=fields['Requires PHP'],
                    download_url=fields['Update URI'] + '/releases/download/' + tag + '/seasonal-modes.zip')
    (output / 'update.json').write_text(json.dumps(manifest, indent=2) + '\n')
    return archive


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--tag')
    args = parser.parse_args()
    try:
        print(build(args.tag))
    except (ValueError, KeyError, TypeError, OSError) as error:
        raise SystemExit(str(error))
