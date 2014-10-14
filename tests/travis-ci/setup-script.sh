#!/bin/bash
#
# Run PHPUnit tests, use one run to generate test coverage

set -e
set -x

DB=$1
TRAVIS_PHP_VERSION=$2
SHORT_PHP=${TRAVIS_PHP_VERSION:0:3}

# Run phpunit tests for the site
if [ "$SHORT_PHP" == "5.4" -a "$DB" == "mysqli" ]
then
    /var/www/vendor/bin/phpunit --configuration /var/www/tests/travis-ci/phpunit-with-coverage-$DB-travis.xml
else
    /var/www/vendor/bin/phpunit --configuration /var/www/tests/travis-ci/phpunit-$DB-travis.xml
fi