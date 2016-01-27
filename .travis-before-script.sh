#!/bin/bash

set -e $DRUPAL_TI_DEBUG

# Ensure the right Drupal version is installed.
# The first time this is run, it will install Drupal.
# Note: This function is re-entrant.
drupal_ti_ensure_drupal

# Change to the Drupal directory
cd "$DRUPAL_TI_DRUPAL_DIR"

# IEF currently requires the following core patch.
curl -o 2626548_12.patch https://www.drupal.org/files/issues/2626548_12.patch
patch -p1 < 2626548_12.patch
