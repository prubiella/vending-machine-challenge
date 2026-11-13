#!/bin/bash -e

# Checks every project skill under .claude/skills without calling a model, so it costs nothing and
# runs on each commit that touches a skill. It runs on the host: it only needs bash, git and grep.

cd "$(git rev-parse --show-toplevel)"

errors=0
fail() {
  echo "skill-check: $1" >&2
  errors=$((errors + 1))
}

for skill_dir in .claude/skills/*/; do
  skill_dir=${skill_dir%/}
  skill=$(basename "${skill_dir}")
  skill_file="${skill_dir}/SKILL.md"

  if [[ ! -f "${skill_file}" ]]; then
    fail "${skill_dir} has no SKILL.md"
    continue
  fi

  frontmatter=$(awk 'NR == 1 && $0 != "---" { exit } NR > 1 && $0 == "---" { exit } NR > 1 { print }' "${skill_file}")
  grep -qx "name: ${skill}" <<< "${frontmatter}" || fail "${skill_file}: the frontmatter needs 'name: ${skill}'"
  grep -q '^description: .' <<< "${frontmatter}" || fail "${skill_file}: the frontmatter needs a description"

  # Paths the skill tells an agent to read, relative to the skill folder. A path with a <placeholder>
  # is filled in at run time, so it is left out.
  while IFS=: read -r file reference; do
    path=${reference#\`}
    path=${path#\$\{CLAUDE_SKILL_DIR\}/}
    path=${path#<Skill dir>/}
    path=${path%.}
    # A glob matches when at least one file does.
    compgen -G "${skill_dir}/${path}" > /dev/null || fail "${file} cites ${path}, which is not in ${skill_dir}"
  done < <(grep -roE '(\$\{CLAUDE_SKILL_DIR\}/|<Skill dir>/|`)(agents|references|assets|evals)/[A-Za-z0-9_./*-]+' "${skill_dir}" --include='*.md')

  # Each eval case in the layout skill-creator reads, on a tag that exists.
  for case_dir in "${skill_dir}"/evals/*/; do
    [[ -d "${case_dir}" ]] || continue
    case_dir=${case_dir%/}
    case_name=$(basename "${case_dir}")
    for required in prompt.md case.yaml; do
      [[ -f "${case_dir}/${required}" ]] || fail "${case_dir} has no ${required}"
    done
    compgen -G "${case_dir}/graders/*.md" > /dev/null || fail "${case_dir} has no graders"
    [[ ! -f "${case_dir}/case.yaml" ]] || grep -qx "name: ${case_name}" "${case_dir}/case.yaml" \
      || fail "${case_dir}/case.yaml: needs 'name: ${case_name}'"
    for tag in $(grep -ohE 'eval/[a-z0-9-]+' "${case_dir}/prompt.md" 2> /dev/null | sort -u); do
      git rev-parse -q --verify "refs/tags/${tag}" > /dev/null \
        || fail "${case_dir}/prompt.md uses tag ${tag}, which is not here (git fetch --tags)"
    done
    [[ ! -f "${skill_dir}/evals/README.md" ]] || grep -qF "\`${case_name}\`" "${skill_dir}/evals/README.md" \
      || fail "${skill_dir}/evals/README.md does not list the case ${case_name}"
  done
done

# Reviews and handoffs must not use em or en dashes, and agents copy the wording they read. The
# dashes are matched as their UTF-8 bytes, so the check does not depend on the locale, and a grep
# error (status 2) fails the check instead of passing as "no dashes".
if dash_lines=$(LC_ALL=C grep -rnF -e $'\xe2\x80\x94' -e $'\xe2\x80\x93' .claude/skills AGENTS.md); then
  while IFS= read -r line; do
    fail "${line}: em or en dash"
  done < <(cut -d: -f1,2 <<< "${dash_lines}")
elif (($? > 1)); then
  fail "grep failed while looking for em or en dashes"
fi

if ((errors > 0)); then
  echo "skill-check: ${errors} problem(s)" >&2
  exit 1
fi

echo "skill-check: every skill passed"
