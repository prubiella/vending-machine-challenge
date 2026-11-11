# Sourced by the MySQL entrypoint once, when the volume is created. The app user also owns the
# test database (Doctrine adds the _test suffix in the test environment).
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}_test\`;
GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}_test\`.* TO '${MYSQL_USER}'@'%';
SQL
