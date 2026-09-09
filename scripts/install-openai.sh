#!/usr/bin/env bash

set -euo pipefail

project_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
package_dir="${project_root}/packages/openai"
local_manifest="${package_dir}/.composer.local.json"
local_lock="${package_dir}/.composer.local.lock"

cleanup() {
    rm -f "$local_manifest" "$local_lock"
}

trap cleanup EXIT

cp "${package_dir}/composer.json" "$local_manifest"
composer config repositories.core '{"type":"path","url":"../..","options":{"symlink":false,"versions":{"maeandrew/novaposhta-address-resolver":"0.2.0"}}}' --file="$local_manifest"
COMPOSER="$local_manifest" composer update \
    --working-dir="$package_dir" \
    --prefer-dist \
    --no-interaction \
    --no-progress
