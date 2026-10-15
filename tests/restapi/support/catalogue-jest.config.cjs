// 20261001 CDX/LH Validate actual catalogue HTTP responses with the mobile schemas.
const path = require('node:path');
const mobile = path.resolve(__dirname, '../../../mobile');

module.exports = {
  ...require(path.join(mobile, 'jest.contract.config.js')),
  rootDir: mobile,
  testEnvironment: 'node',
  modulePaths: [path.join(mobile, 'node_modules')],
  transform: {
    '^.+\\.tsx?$': [require.resolve('babel-jest', { paths: [mobile] }), {
      babelrc: false,
      configFile: false,
      presets: [require.resolve('@babel/preset-typescript', { paths: [mobile] })],
      plugins: [require.resolve('@babel/plugin-transform-modules-commonjs', { paths: [mobile] })],
    }],
  },
  roots: [path.join(mobile, 'src'), path.resolve(__dirname, '..')],
  testMatch: [path.resolve(__dirname, '../CatalogueSchemaContract.test.ts')],
};
