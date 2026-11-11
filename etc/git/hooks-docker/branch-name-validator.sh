#!/bin/bash -e

RED='\033[0;31m'
NC='\033[0m'
BRANCH_NAME_PATTERN='^(feature|fix|chore|docs)/[a-z0-9-]{1,15}$'

branch=$(git symbolic-ref --short -q HEAD || true)

if [[ -n "${branch}" && ! ${branch} =~ ${BRANCH_NAME_PATTERN} ]]; then
  echo -e "${RED}Branch '${branch}' does not match <feature|fix|chore|docs>/<name>, where <name> is 1 to 15 lowercase letters, digits or '-'.${NC}" >&2
  echo -e "${RED}Rename it with 'git branch -m <type>/<name>' and commit again.${NC}" >&2
  exit 1
fi
