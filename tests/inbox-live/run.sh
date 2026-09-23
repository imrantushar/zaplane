#!/usr/bin/env bash
# Runs every live Inbox check against the WordPress install in the current
# directory (or WP_PATH). Each file rolls its changes back.
cd "${WP_PATH:-$(pwd)}" || exit 1
dir="$(dirname "$0")"
pass=0; fail=0
for f in "$dir"/[a-z]*.php; do
	out="$(wp eval-file "$f" 2>&1)"
	p=$(grep -c '^PASS' <<<"$out"); x=$(grep -c '^FAIL\|^ERROR' <<<"$out")
	pass=$((pass+p)); fail=$((fail+x))
	printf '%-22s %3d pass  %d fail\n' "$(basename "$f" .php)" "$p" "$x"
	[ "$x" -gt 0 ] && grep '^FAIL\|^ERROR' <<<"$out" | sed 's/^/    /'
done
echo "total: $pass pass, $fail fail"
[ "$fail" -eq 0 ]
