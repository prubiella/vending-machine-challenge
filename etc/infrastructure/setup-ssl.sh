#!/bin/bash -e

RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

CERTIFICATES_DIR='etc/infrastructure/certs'

set -a
source .env
set +a

if ! command -v mkcert >/dev/null 2>&1; then
  echo -e "${RED}mkcert is not installed: https://github.com/FiloSottile/mkcert#installation${NC}"
  exit 1
fi

echo -e "${GREEN}Trusting the mkcert local certificate authority (may ask for sudo)${NC}"
mkcert -install

echo -e "${GREEN}Creating the certificate for ${PROJECT_HOSTNAME}, *.${PROJECT_HOSTNAME} and nginx${NC}"
mkcert -cert-file "${CERTIFICATES_DIR}/local-cert.pem" -key-file "${CERTIFICATES_DIR}/local-key.pem" \
  "${PROJECT_HOSTNAME}" "*.${PROJECT_HOSTNAME}" nginx

cp "$(mkcert -CAROOT)/rootCA.pem" "${CERTIFICATES_DIR}/rootCA.pem"
