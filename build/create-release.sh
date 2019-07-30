GH_USER=factorial-io
GH_PATH=`cat ~/.ghtoken`
GH_REPO=phabalicious
GH_TARGET=master
ASSETS_PATH=./
VERSION=`git describe --tags | sed 's/-[0-9]-g[a-z0-9]\{7\}//'`
echo "Releasing ${VERSION} ..."
cd ..
ulimit -Sn 4096
composer install
composer build-phar
cd build

res=`curl --user "$GH_USER:$GH_PATH" -X POST https://api.github.com/repos/${GH_USER}/${GH_REPO}/releases \
-d "
{
  \"tag_name\": \"$VERSION\",
  \"target_commitish\": \"$GH_TARGET\",
  \"name\": \"$VERSION\",
  \"body\": \"new version $VERSION\",
  \"draft\": false,
  \"prerelease\": false
}"`
echo Create release result: ${res}
rel_id=`echo ${res} | python -c 'import json,sys;print(json.load(sys.stdin)["id"])'`
file_name=phabalicious.phar

curl --user "$GH_USER:$GH_PATH" -X POST https://uploads.github.com/repos/${GH_USER}/${GH_REPO}/releases/${rel_id}/assets?name=${file_name}\
 --header 'Content-Type: text/javascript ' --upload-file ${ASSETS_PATH}/phabalicious.phar
