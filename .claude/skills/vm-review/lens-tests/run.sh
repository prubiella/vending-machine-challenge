#!/bin/bash -e

# Runs one vm-review lens at a time against a published eval tag, outside the skill, and checks its
# findings without a model. Each test is a single reviewer on sonnet, so it costs a fraction of an
# eval iteration and tells early whether a change to a lens or to reviewer.md lost a finding. A
# model does not answer the same way twice: when a test fails, read its output and run it again
# before blaming the lens.
#
# Usage: run.sh [<test> ...]   (no argument: every *.conf next to this script)

cd "$(git rev-parse --show-toplevel)"
tests_dir=.claude/skills/vm-review/lens-tests
skill_dir=$(realpath .claude/skills/vm-review)
out_dir=.audit/vm-review-lens-tests/$(date +%Y%m%d-%H%M)
mkdir -p "${out_dir}"

if (($# > 0)); then
  tests=("$@")
else
  tests=()
  for conf in "${tests_dir}"/*.conf; do tests+=("$(basename "${conf}" .conf)"); done
fi

# Prints the "## Findings" section of a reviewer's output.
findings() {
  awk '/^## Findings/ { on = 1; next } /^## / { on = 0 } on' "$1"
}

# Succeeds when one finding block names a file matching $2, says something matching $3 and has a
# severity matching $4. Case and Markdown emphasis around the field names do not matter.
has_finding() {
  findings "$1" | awk -v file="$2" -v text="$3" -v severity="$4" '
    function check(b) {
      b = tolower(block); gsub(/[*_`]/, "", b)
      if (b ~ ("file: *(" tolower(file) ")") && b ~ tolower(text) && b ~ ("severity: *(" tolower(severity) ")")) found = 1
    }
    tolower($0) ~ /^[-*] *[*_`]*file/ { check(); block = "" }
    { block = block "\n" $0 }
    END { check(); exit found ? 0 : 1 }'
}

# Succeeds when the Findings section never says Critical or Important, in any format. A quiet test
# passes on that absence, so it must not depend on the reviewer keeping the expected layout; a Minor
# finding that uses one of those words fails it too, which a rerun or a look at the output settles.
is_quiet() {
  ! findings "$1" | grep -qiwE 'critical|important'
}

failures=0
for test in "${tests[@]}"; do
  LENS='' RANGE='' SPEC='' EXPECT_FILE='' EXPECT_TEXT='' EXPECT_SEVERITY='Critical|Important' QUIET=''
  # shellcheck source=/dev/null
  source "${tests_dir}/${test}.conf"

  base=${RANGE%...*}
  head=${RANGE#*...}
  head_sha=$(git rev-parse "${head}^{commit}")
  rules="$(realpath "${out_dir}")/${test}-AGENTS-at-head.md"
  git show "${head_sha}:AGENTS.md" > "${rules}"

  prompt="Read ${skill_dir}/agents/reviewer.md and follow it.
Lens file: ${skill_dir}/references/lenses/${LENS}.md
Target: range ${RANGE}
Diff command: git diff ${RANGE}
Untracked files, read in full: none
Base: ${base}  Head: ${head} (tag) at ${head_sha}
Local head matches: no
Project rules: ${rules}
Changed files:
$(git diff --name-only "${RANGE}")"
  [[ -z "${SPEC}" ]] || prompt+="
Spec: ${skill_dir}/${SPEC}"

  # Isolated from the user's plugins, hooks, MCP servers and effort settings: only what the
  # project configures, so the result and its cost do not depend on whose machine runs it.
  claude -p --name "vm-lens-${test}" --no-session-persistence \
    --setting-sources project --strict-mcp-config \
    --model sonnet --effort medium \
    --tools Read Grep Glob Bash \
    --output-format json "${prompt}" > "${out_dir}/${test}.json"
  jq -r '.result' "${out_dir}/${test}.json" > "${out_dir}/${test}.md"

  # A quiet test passes on the absence of a finding, so first make sure there was a review at all.
  if [[ $(jq -r '.is_error' "${out_dir}/${test}.json") != false ]] || ! grep -q '^## Findings' "${out_dir}/${test}.md"; then
    verdict='FAIL (no review: see the json)'
  elif [[ -n "${QUIET}" ]]; then
    is_quiet "${out_dir}/${test}.md" && verdict=PASS || verdict=FAIL
  else
    has_finding "${out_dir}/${test}.md" "${EXPECT_FILE}" "${EXPECT_TEXT}" "${EXPECT_SEVERITY}" && verdict=PASS || verdict=FAIL
  fi
  [[ "${verdict}" == PASS ]] || failures=$((failures + 1))

  tokens=$(jq -r '.usage | .input_tokens + .cache_creation_input_tokens + .cache_read_input_tokens' "${out_dir}/${test}.json")
  echo "${verdict} ${test} (${LENS}): ${tokens} input tokens, output in ${out_dir}/${test}.md"
done

((failures == 0))
