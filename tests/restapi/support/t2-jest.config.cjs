// 20261001 CDX/LH Run app schema and rendered navigation contracts against exported real PHP responses.
const path = require('node:path');
const mobile = path.resolve(__dirname, '../../../mobile');
module.exports = {
  ...require(path.join(mobile, 'jest.config.js')),
  rootDir: mobile,
  modulePaths: [path.join(mobile, 'node_modules')],
  roots: [path.join(mobile, 'src'), path.join(mobile, '__tests__'), path.resolve(__dirname, '..')],
  testMatch: [path.resolve(__dirname, '../T2SchemaContract.test.tsx')],
  testTimeout: 30000,
};
