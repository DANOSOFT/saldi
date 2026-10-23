#!/usr/bin/env bash
# 20261022 CL/LH SD-763: run each characterization file on its own and compare with the
# known-failures list. Per file, because one test that exits (missing connect.php) would
# otherwise stop the whole PHPUnit run. Fails on a new failure, and on a listed file that
# now passes (remove it from the list), so the list can only shrink.
set -u
cd "$(dirname "$0")/../.."
known=.github/ci/known-failing-characterization.txt
mapfile -t expected < <(grep -v '^\s*#' "$known" | sed '/^\s*$/d')
new=() fixed=() ran=0
while IFS= read -r f; do
	ran=$((ran + 1))
	if timeout 300 php vendor/bin/phpunit "$f" > /tmp/char.out 2>&1; then
		printf '%s\n' "${expected[@]}" | grep -qxF "$f" && fixed+=("$f")
	else
		if ! printf '%s\n' "${expected[@]}" | grep -qxF "$f"; then
			new+=("$f")
			echo "::group::FAILED $f"; tail -n 40 /tmp/char.out; echo "::endgroup::"
		fi
	fi
done < <(find tests/characterization -name '*.test.php' | sort)

echo "characterization: $ran files, ${#expected[@]} known failures, ${#new[@]} new failures, ${#fixed[@]} now passing"
[ "$ran" -gt 0 ] || { echo "::error::no characterization files found"; exit 1; }
for f in "${new[@]}"; do echo "::error file=$f::new characterization failure"; done
for f in "${fixed[@]}"; do echo "::error file=$f::now passes: remove it from $known"; done
gone=()
for f in "${expected[@]}"; do [ -f "$f" ] || { gone+=("$f"); echo "::error::$f is listed in $known but does not exist"; }; done
[ ${#new[@]} -eq 0 ] && [ ${#fixed[@]} -eq 0 ] && [ ${#gone[@]} -eq 0 ]
