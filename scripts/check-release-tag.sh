#!/usr/bin/env bash
# Run in the checkout that was validated and used to build the release assets.
set -euo pipefail
release_tag="${1:?Pass a stable version tag}"
expected_commit="${2:?Pass the validated commit SHA}"
if [[ ! "$release_tag" =~ ^v(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]]; then
  echo 'Invalid release tag.' >&2
  exit 1
fi
validated_commit="$(git rev-parse --verify "$expected_commit^{commit}")"
if git show-ref --verify --quiet "refs/tags/$release_tag"; then
  tag_commit="$(git rev-parse --verify "$release_tag^{commit}")"
  if [ "$tag_commit" != "$validated_commit" ]; then
    echo 'Existing tag points to different source. Choose its commit or a new version.' >&2
    exit 1
  fi
fi
