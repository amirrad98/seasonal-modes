#!/usr/bin/env python3
"""Validate version metadata, annual dates, and local asset references."""
import datetime
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SEMVER = r'(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)'


def metadata(root=ROOT):
    source = (root / 'seasonal-modes.php').read_text()
    fields = {}
    for name in ('Version', 'Requires at least', 'Requires PHP', 'Update URI'):
        match = re.search(r'^ \* ' + re.escape(name) + r':\s*(\S+)\s*$', source, re.M)
        if not match:
            raise ValueError('Missing plugin header: ' + name)
        fields[name] = match.group(1)
    if not re.fullmatch(SEMVER, fields['Version']):
        raise ValueError('Plugin version must be stable major.minor.patch')
    constant = re.search(r"const VERSION\s*=\s*'([^']+)';", source)
    if not constant or constant.group(1) != fields['Version']:
        raise ValueError('Plugin header and VERSION constant must match')
    if fields['Update URI'] != 'https://github.com/amirrad98/seasonal-modes':
        raise ValueError('Unexpected update repository')
    for field in ('Requires at least', 'Requires PHP'):
        if not re.fullmatch(r'\d+\.\d+(?:\.\d+)?', fields[field]):
            raise ValueError('Invalid compatibility version: ' + field)
    if not re.search(r'^## ' + re.escape(fields['Version']) + r'$', (root / 'CHANGELOG.md').read_text(), re.M):
        raise ValueError('Add this version to CHANGELOG.md')
    return fields


def check(root=ROOT):
    fields = metadata(root)
    packs = list((root / 'packs').glob('*/pack.json'))
    if not packs:
        raise ValueError('No packs found')
    for path in packs:
        if not re.fullmatch(r'[a-z0-9]+(?:-[a-z0-9]+)*', path.parent.name):
            raise ValueError('Invalid pack slug: ' + path.parent.name)
        pack = json.loads(path.read_text())
        if not isinstance(pack, dict) or not isinstance(pack.get('name'), str) or not pack['name'].strip():
            raise ValueError('Pack needs a name: ' + str(path))
        if not isinstance(pack.get('version'), str) or not re.fullmatch(SEMVER, pack['version']):
            raise ValueError('Pack needs a stable version: ' + str(path))
        for boundary in ('start', 'end'):
            value = pack.get('window', {}).get(boundary, '')
            if not isinstance(value, str) or not re.fullmatch(r'\d{2}-\d{2}', value):
                raise ValueError('Dates must use MM-DD: ' + str(path))
            datetime.datetime.strptime('2000-' + value, '%Y-%m-%d')
        if not (path.parent / 'CREDITS.md').is_file():
            raise ValueError('Missing credits: ' + str(path))

        def walk(value):
            if isinstance(value, dict):
                for key, child in value.items():
                    if key == 'image':
                        if not isinstance(child, str) or not child or '\\' in child or Path(child).is_absolute() or '..' in Path(child).parts:
                            raise ValueError('Unsafe asset path: ' + repr(child))
                        asset = path.parent / child
                        if not asset.is_file() or asset.is_symlink() or path.parent.resolve() not in asset.resolve().parents:
                            raise ValueError('Missing or unsafe asset: ' + str(asset))
                    else:
                        walk(child)
            elif isinstance(value, list):
                for child in value:
                    walk(child)
        walk(pack)
    return fields


if __name__ == '__main__':
    try:
        result = check()
        print('Version and seasonal packs valid:', result['Version'])
    except (ValueError, KeyError, TypeError, OSError) as error:
        raise SystemExit(str(error))
