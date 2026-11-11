#!/bin/bash -e

# symbolic-ref also works before the first commit; detached HEAD falls back to the hash.
local_branch=$(git symbolic-ref --short -q HEAD || git rev-parse --short HEAD)
max_chars_branch_name=15
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color
TAB='\t'
NEW_LINE='\n'

if [[ ${local_branch} =~ ^(feature|fix|hotfix|release|chore|docs)/[a-z0-9-]{1,15}$ ]]; then
    echo -e "${GREEN}Congrats! you've chosen a short name for your branch:${YELLOW} ${local_branch} ${GREEN} Total chars: ${#local_branch} .${NC}"
else

    echo -e "${RED}"
    echo -e "Hey! There is something wrong with your branch name:${YELLOW} ${local_branch} ${RED}"
    echo -e "It seems it's larger than ${max_chars_branch_name} characters!! Total chars:${YELLOW} ${#local_branch} ${RED}"
    echo -e "Also remind the branch name only support:${YELLOW} lowercase letters, numbers and special char '-'${RED}"
    echo -e "${YELLOW}In your current branch just run 'git branch -m <new_name>' and try to commit again!${NC}"
fi
