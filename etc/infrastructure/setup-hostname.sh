#!/bin/bash -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

set -a
source .env
set +a

host_entry="127.0.0.1 ${PROJECT_HOSTNAME}"

if grep -q -w -F "${PROJECT_HOSTNAME}" /etc/hosts; then
  echo -e "${YELLOW}${PROJECT_HOSTNAME} is already in /etc/hosts${NC}"
else
  echo -e "${GREEN}Adding '${host_entry}' to /etc/hosts (asks for sudo)${NC}"
  echo "${host_entry}" | sudo tee -a /etc/hosts >/dev/null
  echo -e "${GREEN}Added '${host_entry}'${NC}"
fi
