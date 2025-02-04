module.exports = {
    // Define environments – browser for React and Node for tooling.
    env: {
        browser: true,
        node: true,
        es2021: true,
    },
    // Extend recommended rules for ESLint, React, and Prettier.
    extends: [
        'eslint:recommended',
        'plugin:react/recommended',
        'plugin:prettier/recommended' // Make sure this is always the last configuration in the extends array.
    ],
    // Specify parser options
    parserOptions: {
        ecmaFeatures: {
            jsx: true, // Enable JSX since you are using React.
        },
        ecmaVersion: 12, // or a newer version if needed
        sourceType: 'module',
    },
    // Plugins that are used.
    plugins: ['react', 'prettier'],
    // Custom rules.
    rules: {
        // Run prettier as an ESLint rule and treat it as an error.
        'prettier/prettier': 'error',
        // You can add more custom ESLint rules here if needed.
    },
    // Automatically detect the react version
    settings: {
        react: {
            version: 'detect',
        },
    },
};
