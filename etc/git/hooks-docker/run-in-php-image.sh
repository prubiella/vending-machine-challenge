#!/bin/bash -e

root=$(pwd)
git_common_dir=$(git rev-parse --path-format=absolute --git-common-dir)

# A git worktree has no .env of its own (it is git-ignored): use the main checkout's.
env_file=.env
[[ -f "${env_file}" ]] || env_file="$(dirname "${git_common_dir}")/.env"

if [[ ! -f "${env_file}" ]]; then
  echo "No .env file: run 'make first-run' before committing." >&2
  exit 1
fi

source "${env_file}"

# git hands hooks absolute paths (GIT_DIR in a worktree, GIT_INDEX_FILE for `commit -a`), so the
# checkout and the git directory are mounted at the same paths as on the host.
docker run --rm \
  --user "$(id -u):$(id -g)" \
  -e HOME=/tmp \
  -e GIT_DIR \
  -e GIT_INDEX_FILE \
  -v "${root}:${root}" \
  -v "${git_common_dir}:${git_common_dir}" \
  -w "${root}" \
  "${PROJECT_HOSTNAME}-php-fpm" \
  sh -c 'git config --global --add safe.directory "$PWD" && "$@"' -- "$@"
