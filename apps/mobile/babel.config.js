module.exports = function (api) {
  api.cache(true);

  return {
    presets: [['babel-preset-expo', { jsxImportSource: 'react' }]],
    plugins: [
      'react-native-reanimated/plugin',
      // Must stay last: Reanimated's worklet transform rewrites function
      // bodies and has to see the final AST.
      'react-native-worklets/plugin',
    ],
  };
};
