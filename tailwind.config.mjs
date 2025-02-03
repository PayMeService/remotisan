module.exports = {
  content: [
    './resources/views/**/*.blade.php',
    './resources/react/**/*.{js,jsx,ts,tsx}',
    './resources/css/**/*.css',
  ],
    safelist: [
        // List any classes that are generated dynamically
        'bg-red-500',
        'bg-gray-50',
        'bg-gray-200',
        'text-lg',
    ],
  theme: {
    extend: {},
  },
  plugins: [],
};
