#!/usr/bin/env bash

set -euo pipefail

project_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
package_dir="${project_root}/packages/openai"
local_manifest="${package_dir}/.composer.local.json"
local_lock="${package_dir}/.composer.local.lock"
local_core_dir="${project_root}/var/core-package-openai"

cleanup() {
    rm -f "$local_manifest" "$local_lock"
    rm -rf "$local_core_dir"
}

trap cleanup EXIT

mkdir -p "$local_core_dir"
cp "${project_root}/composer.json" "$local_core_dir/composer.json"
cp -R "${project_root}/src" "$local_core_dir/src"
cp "${package_dir}/composer.json" "$local_manifest"
composer config repositories.core '{"type":"path","url":"../../var/core-package-openai","options":{"symlink":false,"versions":{"maeandrew/novaposhta-address-resolver":"0.3.0"}}}' --file="$local_manifest"
COMPOSER="$local_manifest" composer update \
    --working-dir="$package_dir" \
    --prefer-dist \
    --no-interaction \
    --no-progress
