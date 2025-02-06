// eslint.config.js
module.exports = [
    // Global ignore patterns (replaces .eslintignore)
    {
        ignores: [
            'node_modules/',
            'public/',
            'vendor/'
        ],
    },
    // Configuration for your JavaScript/JSX files in resources/react
    {
        files: ['resources/react/**/*.{js,jsx}'],
        languageOptions: {
            // Define the ECMAScript version and module type.
            ecmaVersion: 2021,
            sourceType: 'module',
            parserOptions: {
                ecmaFeatures: {
                    jsx: true,
                },
            },
        },
        plugins: {
            react: require('eslint-plugin-react'),
            prettier: require('eslint-plugin-prettier'),
        },
        rules: {
            // Run Prettier as an ESLint rule and treat formatting issues as errors.
            'prettier/prettier': 'error',
            // You can add additional rules here.
        },
        settings: {
            react: {
                // Automatically detect the React version.
                version: 'detect',
            },
        },
    },
];
