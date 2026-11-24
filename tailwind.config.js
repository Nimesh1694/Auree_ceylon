import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            colors: {
                ivory: '#FDFBF7',
                cream: '#F7F2EA',
                sand: '#EDE4D7',
                espresso: '#1C1917',
                terracotta: '#9A4E22',
                terracottaHover: '#7D3D18',
                sage: '#2E5A44',
                beige: '#F5EFE6',
                footerdark: '#120F0D',
                copper: '#9E471E',
                gold: '#A67C52',
                goldbright: '#D4A843',
                borderwarm: '#EAE2D6',
                emerald: {
                    DEFAULT: '#1E3F2B',
                    50: '#F2F8F4',
                    100: '#E1EFE7',
                    600: '#1E3F2B',
                    700: '#163021',
                    800: '#0F2116',
                    900: '#0A170F',
                },
            },
            fontFamily: {
                sans: ['"DM Sans"', 'system-ui', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'sans-serif', ...defaultTheme.fontFamily.sans],
                serif: ['"Cormorant Garamond"', 'Garamond', 'Georgia', 'serif'],
                display: ['"Cormorant Garamond"', 'Garamond', 'Georgia', 'serif'],
                grotesk: ['"DM Sans"', 'system-ui', 'sans-serif'],
            },
        },
    },

    plugins: [forms],
};
