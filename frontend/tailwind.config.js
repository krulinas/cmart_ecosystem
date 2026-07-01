/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        // CMart Brand Blue palette
        brand: {
          50:  '#e1f5fe',
          100: '#b3e5fc',
          200: '#81d4fa',
          300: '#4fc3f7',
          400: '#29B6F6',
          500: '#29B6F6', // Primary — buttons, highlights, main brand
          600: '#0277BD', // Secondary — hover, active, accents
          700: '#0269a8',
          800: '#015a91',
          900: '#014a7a',
        },
        cmart: {
          primary:   '#29B6F6',
          secondary: '#0277BD',
          accent:    '#000000',
          muted:     '#757575',
        },
        ink: {
          50:  '#f8fafc',
          100: '#f1f5f9',
          200: '#e2e8f0',
          300: '#cbd5e1',
          500: '#757575', // aligned with CMart muted text
          700: '#334155',
          900: '#0f172a',
        },
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      maxWidth: {
        page: '1200px',
      },
      fontSize: {
        'readable-sm': ['0.9375rem', { lineHeight: '1.55' }],
        'readable-base': ['1rem', { lineHeight: '1.6' }],
      },
    },
  },
  plugins: [],
}
