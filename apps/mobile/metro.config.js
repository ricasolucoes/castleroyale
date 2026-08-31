const { getDefaultConfig } = require('expo/metro-config');

// Expo SDK 57 configures workspace folders and module resolution itself. Keeping
// those defaults is important: nested Expo Router dependencies must remain
// resolvable while the workspace packages stay visible to Metro.
module.exports = getDefaultConfig(__dirname);
