#!/usr/bin/env bash

set -eu

build_dir="$GITHUB_WORKSPACE/build"
staging_dir="$GITHUB_WORKSPACE/staging"
git_tag="${GITHUB_REF##*/}"
svn_tag="${git_tag#v}"
files=(
    LICENSE
    blockchyp-woocommerce.php
    readme.txt
)
directories=(
    assets
    vendor
)

rm -rf "$build_dir"
mkdir "$build_dir"
rm -rf "$staging_dir"

for f in "${files[@]}"; do
    cp "$f" "$build_dir/"
done
for d in "${directories[@]}"; do
    cp -r "$d" "$build_dir/$d"
done

svn checkout http://svn.wp-plugins.org/blockchyp-for-woocommerce "$staging_dir"

test -d "$staging_dir/tags/$svn_tag" && echo "Tag already exists: $svn_tag" && exit 1 || :

rm -rf "$staging_dir/trunk"
cp -r "$build_dir" "$staging_dir/trunk"
cp -r "$build_dir" "$staging_dir/tags/$svn_tag"

pushd "$staging_dir"
svn stat | grep '^?' | awk '{print $2}' | xargs -I x svn add x@
svn stat | grep '^!' | awk '{print $2}' | xargs -I x svn rm --force x@

svn ci --no-auth-cache --username blockchyp --password "$SVN_PASSWORD" -m "Deploy version $svn_tag"
popd
