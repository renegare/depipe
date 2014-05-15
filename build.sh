#!/bin/bash

cd "$(dirname $0)"

# INSTALL_PATH='/usr/local/bin/depipe'

echo "----------------------------------------------------------------------------------------------------"
echo "Checking for build deps"
echo "----------------------------------------------------------------------------------------------------"
if [[ "$(box --version)" == *"Box"* ]]; then
    echo "box executable found"
else
    echo "ERROR: box executable not found (see https://github.com/herrera-io/php-box)"
    exit 127
fi

echo "$(composer --version)"

if [[ "$(composer --version)" == *"Composer"* ]]; then
    echo "composer executable found"
else
    echo "ERROR: composer executable not found (see https://getcomposer.org/download/)"
    exit 127
fi
echo ""

echo "----------------------------------------------------------------------------------------------------"
echo "Updating Composer without dev deps"
echo "----------------------------------------------------------------------------------------------------"
composer update --no-dev --prefer-dist
echo ""

echo "----------------------------------------------------------------------------------------------------"
echo "Building PHAR with box"
echo "----------------------------------------------------------------------------------------------------"
box build
echo ""

# echo "----------------------------------------------------------------------------------------------------"
# echo "Installing depipe.phar to $INSTALL_PATH"
# echo "----------------------------------------------------------------------------------------------------"
# rm -f $INSTALL_PIPE
# mv depipe.phar $INSTALL_PIPE
# echo ""

echo "----------------------------------------------------------------------------------------------------"
echo "Restoring Composer dev deps"
echo "----------------------------------------------------------------------------------------------------"
composer update --prefer-dist
echo ""

echo "----------------------------------------------------------------------------------------------------"
echo "Build complete"
echo "----------------------------------------------------------------------------------------------------"

rm -rf
