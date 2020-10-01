#!/usr/bin/env bash

set -eu

version="${1:-}"
if test -z "$version"; then
    last=$(git tag -l | sort -V | tail -n 1)
    echo -e "Last version: $last\n"
    read -p "Enter new version: " version
fi
version="${version#v}"

notes="${2:-}"
if test -z "$notes"; then
    read -p "Enter release notes: " notes
fi

sed -i'' "s|Stable tag: .*|Stable tag: $version|" readme.txt
sed -i'' "s|== Changelog ==|== Changelog ==\n\n= $version\n* $notes|" readme.txt
sed -i'' "s|^ \* Version: .*| * Version: $version|" blockchyp-woocommerce.php

git diff

read -p "Make any corrections, then press enter to continue" prompt

git add -u && \
    git commit -m "Bump version to $version" && \
    git tag -am "v$version" "v$version"

echo -e '\nNow run `git push --follow-tags` to publish the release'
