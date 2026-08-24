// Metro must be told about the monorepo explicitly: by default it only watches
// the project folder, so edits in `packages/*` would not trigger a rebuild and
// hoisted dependencies at the repo root would not resolve.
const { getDefaultConfig } = require('expo/metro-config');
const path = require('node:path');

const projectRoot = __dirname;
const workspaceRoot = path.resolve(projectRoot, '../..');

const config = getDefaultConfig(projectRoot);

config.watchFolders = [workspaceRoot];

config.resolver.nodeModulesPaths = [
  path.resolve(projectRoot, 'node_modules'),
  path.resolve(workspaceRoot, 'node_modules'),
];

// Never resolve a second copy of React or React Native from a nested
// node_modules — two React instances is a whole afternoon of confusing hook
// errors.
config.resolver.disableHierarchicalLookup = true;

// Skia and Reanimated ship .mjs entry points.
config.resolver.sourceExts = [...config.resolver.sourceExts, 'mjs', 'cjs'];

module.exports = config;
