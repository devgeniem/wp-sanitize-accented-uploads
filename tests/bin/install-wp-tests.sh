#!/usr/bin/env bash
##
# This script installs wordpress for phpunit tests and rspec integration tests
##
DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
DIR=$(dirname ${DIR})

if [ $# -lt 3 ]; then
echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]"
exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}

WP_VERSION=${5-latest}

# Use this for installing wordpress siteurl
WP_TEST_URL=${WP_TEST_URL-http://localhost:12000}

# Get port from url
WP_PORT=${WP_TEST_URL##*:}

WP_TESTS_DIR=${WP_TESTS_DIR-/tmp/wordpress-tests-lib/includes}
WP_CORE_DIR=${WP_CORE_DIR-/tmp/wordpress/}

# Use these credentials for installing wordpress
# Default test/test
WP_TEST_USER=${WP_TEST_USER-test}
WP_TEST_USER_PASS=${WP_TEST_USER_PASS-test}

set -ex

download() {
  if [ `which curl` ]; then
    curl -s "$1" > "$2";
  elif [ `which wget` ]; then
    wget -nv -O "$2" "$1"
  fi
}

install_wp() {
  if [ -d $WP_CORE_DIR ]; then
    return;
  fi

  mkdir -p $WP_CORE_DIR

  if [ $WP_VERSION == 'latest' ]; then
    local ARCHIVE_NAME='latest'
  else
    local ARCHIVE_NAME="wordpress-$WP_VERSION"
  fi

  download https://wordpress.org/${ARCHIVE_NAME}.tar.gz  /tmp/wordpress.tar.gz
  # Install into subfolder
  tar -zxmf /tmp/wordpress.tar.gz -C $(dirname $WP_CORE_DIR)

  download https://raw.github.com/markoheijnen/wp-mysqli/master/db.php $WP_CORE_DIR/wp-content/db.php
}

install_test_suite() {
  # portable in-place argument for both GNU sed and Mac OSX sed
  if [[ $(uname -s) == 'Darwin' ]]; then
    local ioption='-i .bak'
  else
    local ioption='-i'
  fi

  # set up testing suite if it doesn't yet exist
  if [ ! "$(ls -A $WP_TESTS_DIR)" ]; then
    # set up testing suite
    mkdir -p $WP_TESTS_DIR
      #if latest, use trunk develop version, if not, use major version
      if [ $WP_VERSION == 'latest' ]; then
        local TEST_BRANCH_NAME='trunk'
      else
        local TEST_BRANCH_NAME='branches/'$(sed 's/\([0-9]*\.[0-9]*\).*/\1/' <<< $WP_VERSION)
      fi
    svn co --quiet http://develop.svn.wordpress.org/${TEST_BRANCH_NAME}/tests/phpunit/includes/ $WP_TESTS_DIR
  fi

  cd $WP_TESTS_DIR

  # Install barebone wp-tests-config.php which is faster for unit tests
  if [ ! -f wp-tests-config.php ]; then
    download https://develop.svn.wordpress.org/trunk/wp-tests-config-sample.php $(dirname ${WP_TESTS_DIR})/wp-tests-config.php
    sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR':" $(dirname ${WP_TESTS_DIR})/wp-tests-config.php
    sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" $(dirname ${WP_TESTS_DIR})/wp-tests-config.php
    sed $ioption "s/yourusernamehere/$DB_USER/" $(dirname ${WP_TESTS_DIR})/wp-tests-config.php
    sed $ioption "s/yourpasswordhere/$DB_PASS/" $(dirname ${WP_TESTS_DIR})/wp-tests-config.php
    sed $ioption "s|localhost|${DB_HOST}|" $(dirname ${WP_TESTS_DIR})/wp-tests-config.php
  fi

  # Install real wp-config.php too
  cd $WP_CORE_DIR

  if [ ! -f wp-config.php ]; then
    mv wp-config-sample.php wp-config.php
    sed $ioption "s/database_name_here/$DB_NAME/" $WP_CORE_DIR/wp-config.php
    sed $ioption "s/username_here/$DB_USER/" $WP_CORE_DIR/wp-config.php
    sed $ioption "s/password_here/$DB_PASS/" $WP_CORE_DIR/wp-config.php
    sed $ioption "s|localhost|${DB_HOST}|" $WP_CORE_DIR/wp-config.php
    # Use different prefix for integration tests
    sed $ioption "s|^.*\$table_prefix.*$|\$table_prefix  = 'integ_';|" $WP_CORE_DIR/wp-config.php
  fi
}

install_db() {
  # parse DB_HOST for port or socket references
  local PARTS=(${DB_HOST//\:/ })
  local DB_HOSTNAME=${PARTS[0]};
  local DB_SOCK_OR_PORT=${PARTS[1]};
  local EXTRA=""

  if ! [ -z $DB_HOSTNAME ] ; then
    if [ $(echo $DB_SOCK_OR_PORT | grep -e '^[0-9]\{1,\}$') ]; then
      EXTRA="--host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
    elif ! [ -z $DB_SOCK_OR_PORT ] ; then
      EXTRA="--socket=$DB_SOCK_OR_PORT"
    elif ! [ -z $DB_HOSTNAME ] ; then
      EXTRA="--host=$DB_HOSTNAME --protocol=tcp"
    fi
  fi

  # create database
  mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS" $EXTRA
}

install_wp
install_test_suite
install_db
