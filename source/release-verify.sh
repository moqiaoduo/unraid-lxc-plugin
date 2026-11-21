#!/bin/bash
# Validates that the first CHANGES section in lxc.plg is the version being
# released, and prints that section as the release notes body.
#
# Usage: source/release-verify.sh <version> [previous-version]
# Exit 0 with the section text on stdout; exit 1 with the reason on stderr.
# When previous-version is given, version must also sort after it numerically.
#
# Called by .github/workflows/release-plugin.yml (before building) and by the
# unraid-lxc-release skill (before tagging), so both paths agree on what a
# valid release section looks like.
set -euo pipefail

VERSION="${1:-}"
if [ -z "${VERSION}" ]; then
  echo "usage: $0 <version>" >&2
  exit 1
fi
if ! [[ "${VERSION}" =~ ^[0-9]+(\.[0-9]+)+$ ]]; then
  echo "error: version '${VERSION}' must be dot-separated numbers, e.g. 2026.11.22" >&2
  exit 1
fi

PREV="${2:-}"
if [ -n "${PREV}" ]; then
  # Numeric dot-separated comparison, so it behaves the same on macOS and the
  # Ubuntu runner (BSD sort does have -V, but awk keeps the rule in one place).
  if ! awk -v a="${VERSION}" -v b="${PREV}" 'BEGIN {
    na = split(a, x, "."); nb = split(b, y, ".")
    n = (na > nb ? na : nb)
    for (i = 1; i <= n; i++) {
      if ((x[i] + 0) > (y[i] + 0)) exit 0
      if ((x[i] + 0) < (y[i] + 0)) exit 1
    }
    exit 1
  }'; then
    echo "error: version '${VERSION}' must be greater than the previous release '${PREV}'." >&2
    exit 1
  fi
fi

PLG="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/lxc.plg"
if [ ! -f "${PLG}" ]; then
  echo "error: ${PLG} not found" >&2
  exit 1
fi

FIRST="$(awk '
  /<CHANGES>/ { inside = 1; next }
  /<\/CHANGES>/ { inside = 0 }
  inside && /^###/ { sub(/^###/, ""); print; exit }
' "${PLG}")"

if [ "${FIRST}" != "${VERSION}" ]; then
  echo "error: the first CHANGES section is '${FIRST}', but the release is '${VERSION}'." >&2
  echo "       Add '###${VERSION}' as the first section under <CHANGES> in lxc.plg." >&2
  exit 1
fi

BODY="$(awk -v v="${VERSION}" '
  /<CHANGES>/ { inside = 1; next }
  /<\/CHANGES>/ { inside = 0 }
  inside && /^###/ {
    if (in_section) exit
    if (substr($0, 4) == v) { in_section = 1; next }
  }
  inside && in_section { print }
' "${PLG}")"

# Drop leading and trailing blank lines, keep the ones between entries.
BODY="$(printf '%s\n' "${BODY}" | awk '
  NF { started = 1 }
  started { line[++n] = $0 }
  END {
    while (n > 0 && line[n] == "") n--
    for (i = 1; i <= n; i++) print line[i]
  }
')"

if ! printf '%s\n' "${BODY}" | grep -q '^- '; then
  echo "error: CHANGES section '${VERSION}' has no '- ' entries." >&2
  exit 1
fi

printf '%s\n' "${BODY}"