#!/bin/sh
# -----------------------------------------------------------
#  [*] Apache — regression test runner
#
#  Builds the PRODUCTION image from this folder under a tag
#  of its own (the same PHP, Apache configuration and vendor
#  that ship — the running site's image is left alone),
#  builds the test image on top of it (Dockerfile.tests:
#  MySQL 8.0.36, Redis 7.2.3, PHPUnit, the suite) and runs
#  the suite inside a throwaway container shaped like the
#  production one: no network, a read-only root filesystem
#  with the tmpfs mounts docker-compose.yml gives the app,
#  user 1000. A test that reaches out fails instead of
#  passing by luck, and nothing outside the container is
#  touched. The schema is mounted read-only from
#  mysql/init.sql. Extra arguments go to PHPUnit — a suite,
#  a test file, a --filter; app/tests/README.md lists the
#  suites and the pinned defects.
# -----------------------------------------------------------
set -e
cd "$(dirname "$0")"

sudo docker build -t vuknflab-apache-check .
sudo docker build -f Dockerfile.tests -t vuknflab-apache-tests app
sudo docker run --rm --name vuknflab-apache-tests --network none --read-only \
  --tmpfs /tmp:uid=1000,gid=1000 \
  --tmpfs /var/run/apache2:uid=1000,gid=1000 \
  --tmpfs /var/www/html/storage/framework/cache:uid=1000,gid=1000 \
  --tmpfs /var/www/html/storage/framework/sessions:uid=1000,gid=1000 \
  --tmpfs /var/www/html/storage/framework/views:uid=1000,gid=1000 \
  --tmpfs /var/www/html/storage/app/public:uid=1000,gid=1000 \
  --tmpfs /var/www/html/storage/logs:uid=1000,gid=1000 \
  --tmpfs /var/www/logs:uid=1000,gid=1000 \
  --tmpfs /var/lib/mysql:uid=1000,gid=1000,size=512m \
  --tmpfs /run/mysqld:uid=1000,gid=1000 \
  -v "$(cd .. && pwd)/mysql/init.sql:/schema/init.sql:ro" \
  vuknflab-apache-tests "$@"
