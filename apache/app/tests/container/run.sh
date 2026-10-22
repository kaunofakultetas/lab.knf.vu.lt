#!/bin/sh
# -----------------------------------------------------------
#  [*] run.sh — the test container's entrypoint
#
#  Brings up inside the one throwaway container what the
#  production stack gives the app — a MySQL 8.0.36 server
#  holding the schema of mysql/init.sql, a Redis server,
#  and Apache serving the app on port 8080 the way the
#  image does in production — then runs PHPUnit over the
#  suite; every argument goes to PHPUnit (a suite, a file,
#  a --filter). The app's environment is the one
#  docker-compose.yml gives the production container, with
#  test values: a fixed throwaway key, the local database
#  and Redis, and the outside services (reCAPTCHA, Mailjet,
#  the VU SSO) left unreachable, since the container has
#  no network. MySQL, Redis and Apache write only to the
#  tmpfs mounts runTests.sh gives the container; when
#  PHPUnit fails, what the server side logged is printed
#  under its report — the tail of Apache's error log, the
#  first line of the latest Laravel log entries, MySQL's
#  errors. Dockerfile.tests makes this script the image's
#  entrypoint.
# -----------------------------------------------------------
set -e

CNF=/var/www/html/tests/container/my.cnf
LOGS=/var/www/logs

export APP_NAME="VU KNF Lab"
export APP_KEY=base64:dnVrbmZsYWItcmVncmVzc2lvbi10ZXN0cy1rZXkhISE=
export APP_DEBUG=false
export APP_URL=https://lab.knf.vu.lt
export DB_HOST=127.0.0.1
export DB_DATABASE=vuknflab
export DB_USERNAME=vuknflab
export DB_PASSWORD=vuknflab-tests
export REDIS_HOST=127.0.0.1
export RECAPTCHAV3_SITEKEY=test-sitekey
export RECAPTCHAV3_SECRET=test-secret
export NOCAPTCHA_SECRET=test-secret
export NOCAPTCHA_SITEKEY=test-sitekey
export IPGEO_API_KEY=test
export MAILJET_APIKEY=test
export MAILJET_APISECRET=test
export MAIL_FROM_ADDRESS=noreply@lab.knf.vu.lt
export MAIL_FROM_NAME="VU KNF Lab"

if [ ! -f /schema/init.sql ]; then
    echo "run.sh: /schema/init.sql is missing — runTests.sh mounts mysql/init.sql there" >&2
    exit 2
fi


# MySQL: a fresh data directory on tmpfs, the database and
# user docker-compose.yml creates, then the schema
mysqld --defaults-file=$CNF --initialize-insecure
mysqld --defaults-file=$CNF &
for i in $(seq 1 60); do
    mysqladmin --defaults-file=$CNF -uroot ping >/dev/null 2>&1 && break
    sleep 0.5
done
mysql --defaults-file=$CNF -uroot <<SQL
CREATE DATABASE $DB_DATABASE;
CREATE USER '$DB_USERNAME'@'%' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL ON $DB_DATABASE.* TO '$DB_USERNAME'@'%';
SQL
mysql --defaults-file=$CNF -uroot $DB_DATABASE < /schema/init.sql


# Redis, in memory only — the app reads and writes it on
# every request (the ranking table, the solve history, the
# votes)
redis-server --bind 127.0.0.1 --port 6379 --save '' --appendonly no \
    --dir /tmp --logfile $LOGS/redis.log &
for i in $(seq 1 60); do
    php -r 'exit(@fsockopen("127.0.0.1", 6379) ? 0 : 1);' && break
    sleep 0.5
done


# Apache, started the way the production image starts it
apache2-foreground > $LOGS/apache.out 2>&1 &
for i in $(seq 1 60); do
    php -r 'exit(@fsockopen("127.0.0.1", 8080) ? 0 : 1);' && break
    sleep 0.5
done


# The suite; its failures come with the server side's logs
cd /var/www/html
status=0
php /opt/test-tools/vendor/bin/phpunit "$@" || status=$?
if [ $status -ne 0 ]; then
    if [ -s $LOGS/error.log ]; then
        echo
        echo "----- Apache error log, last lines"
        tail -n 20 $LOGS/error.log
    fi
    if [ -s storage/logs/laravel.log ]; then
        echo
        echo "----- Laravel log, the latest entries (first line of each)"
        grep -E '^\[[0-9]{4}-' storage/logs/laravel.log | tail -n 10 | cut -c1-400
    fi
    if grep -q '\[ERROR\]' $LOGS/mysql.err 2>/dev/null; then
        echo
        echo "----- MySQL errors"
        grep '\[ERROR\]' $LOGS/mysql.err | tail -n 10
    fi
fi
exit $status
