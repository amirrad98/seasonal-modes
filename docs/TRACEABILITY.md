# Traceability

| Requirement ID | Source | Mandatory | Milestone | Acceptance criterion | Implementation evidence | Test evidence | Verification command | Status |
|---|---|---:|---|---|---|---|---|---|
| REQ-001 | SPEC.md | yes | M1 | Existing functionality/packs preserved | seasonal-modes.php, packs/ | baseline and PHP behavior tests | php tests/run.php | VERIFIED |
| REQ-002 | SPEC.md | yes | M1 | Stable release offers update; invalid data fails safely | includes/class-github-updater.php | updater behavior tests | php tests/run.php | VERIFIED |
| REQ-003 | SPEC.md | yes | M1 | Validated tag creates installable ZIP | scripts/, .github/workflows/ | package checks | python3 scripts/check.py; python3 scripts/build.py --tag v1.1.0 | VERIFIED |
| REQ-004 | SPEC.md | yes | M1 | Complete installation/release/pack instructions | README.md | independent documentation review | manual README audit | VERIFIED |
| REQ-005 | SPEC.md | yes | M2 | Public repo and first release available | Public amirrad98/seasonal-modes, release v1.1.0 | Release run 40441845432 all jobs pass; asset digests match local build | GitHub release API, tag ref and workflow logs | VERIFIED |
| REQ-006 | SPEC.md | yes | M1/M2 | Local validation plus honest integration status and independent audit | tests/run.php, tests/test_build.py, tests/wordpress-update.php and docs/ | local tests and real WordPress check | CI run 40441690156, jobs 121983290673 and 121983290964; independent guardian audit | VERIFIED |
