const defaultConfig = {
  content: [
    './resources/views/**/*.blade.php',
    './resources/js/**/*.{js,jsx,ts,tsx,vue}',
    './resources/css/**/*.css',
    './public/**/*.html'
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: '#2563EB',
        secondary: '#9333EA',
        accent: '#F59E0B',
        neutral: '#1F2937'
      },
      spacing: {
        '128': '32rem',
        '144': '36rem'
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', '"Helvetica Neue"', 'Arial', '"Noto Sans"', 'sans-serif'],
        serif: ['ui-serif', 'Georgia', 'Cambria', '"Times New Roman"', 'Times', 'serif'],
        mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', '"Liberation Mono"', '"Courier New"', 'monospace']
      }
    }
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
    require('@tailwindcss/aspect-ratio'),
    require('@tailwindcss/line-clamp')
  ]
};

module.exports = (config = {}) => {
  const content = config.content ?? defaultConfig.content;
  const darkMode = config.darkMode ?? defaultConfig.darkMode;

  const mergedTheme = { ...defaultConfig.theme, ...(config.theme || {}) };
  mergedTheme.extend = {
    ...defaultConfig.theme.extend,
    ...(config.theme?.extend || {})
  };

  const plugins = Array.isArray(config.plugins)
    ? config.plugins
    : defaultConfig.plugins;

  return {
    content,
    darkMode,
    theme: mergedTheme,
    plugins
  };
};