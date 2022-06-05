
console.log('standard-version-composer-updater initiated');

const version_regex = /const FALLBACK_VERSION = '(.*)';/;

module.exports.readVersion = function (contents) {
  const result = contents.match(version_regex);
  return result[1];
}

module.exports.writeVersion = function (contents, version) {
  const replace = `const FALLBACK_VERSION = '${version}';`;
  const result = contents.replace(version_regex, replace);
  console.log('WRITE version =', result);
  return result;
}
