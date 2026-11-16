#!/bin/bash -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

set -a
source .env
set +a

# /etc/hosts has no wildcards: the API and its Swagger UI each need a line.
for host in "${PROJECT_HOSTNAME}" "docs.${PROJECT_HOSTNAME}"; do
  host_entry="127.0.0.1 ${host}"
  hostname_pattern="${host//./\\.}"

  if grep -Eq "^[[:space:]]*127\.0\.0\.1[[:space:]]+([^#]*[[:space:]])?${hostname_pattern}([[:space:]]|$)" /etc/hosts; then
    echo -e "${YELLOW}${host} is already in /etc/hosts${NC}"
  else
    echo -e "${GREEN}Adding '${host_entry}' to /etc/hosts (asks for sudo)${NC}"
    echo "${host_entry}" | sudo tee -a /etc/hosts >/dev/null
    echo -e "${GREEN}Added '${host_entry}'${NC}"
  fi
done
