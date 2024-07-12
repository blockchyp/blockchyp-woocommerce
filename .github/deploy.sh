#!/usr/bin/env bash

set -eux

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

main() {
    rm -rf "$build_dir"
    mkdir "$build_dir"
    rm -rf "$staging_dir"

    for f in "${files[@]}"; do
        cp "$f" "$build_dir/"
    done
    for d in "${directories[@]}"; do
        cp -r "$d" "$build_dir/$d"
    done

    case "${1:-}" in
        's3') s3_deploy ;;
        'svn') svn_deploy ;;
    esac
}

svn_deploy() {
    svn checkout http://plugins.svn.wordpress.org/blockchyp-payment-gateway "$staging_dir"

    test -d "$staging_dir/tags/$svn_tag" \
        && echo "Tag already exists: $svn_tag" && exit 1 \
        || :

    rm -rf "$staging_dir/trunk"
    cp -r "$build_dir" "$staging_dir/trunk"
    cp -r "$build_dir" "$staging_dir/tags/$svn_tag"

    rm -rf "$staging_dir/assets"
    cp -r "$build_dir/assets" "$staging_dir/assets"

    pushd "$staging_dir"
    svn stat | grep '^?' | awk '{print $2}' | xargs -I x svn add x@
    svn stat | grep '^!' | awk '{print $2}' | xargs -I x svn rm --force x@

    svn ci --no-auth-cache \
        --username blockchyp \
        --password "$SVN_PASSWORD" \
        -m "Deploy version $svn_tag"
    popd
}

s3_deploy() {
    aws s3 sync --delete \
        "$build_dir/" \
        "s3://$TEST_S3_BUCKET/wordpress/wp-content/plugins/blockchyp-for-woocommerce/"

    aws ecs run-task \
        --cluster "${WP_CLUSTER}" \
        --count 1 \
        --task-definition "${WP_SYNC_TASKDEF}" >/dev/null 2>&1
}

main "$@"
