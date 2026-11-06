export default {
    content: ['./resources/**/*.blade.php', './resources/**/*.js'],
    theme: {
        extend: {
            colors: {
                bg: 'rgb(var(--bg-rgb) / <alpha-value>)',
                surface: 'rgb(var(--surface-rgb) / <alpha-value>)',
                'surface-card': 'rgb(var(--surface-card-rgb) / <alpha-value>)',
                border: 'rgb(var(--border-rgb) / <alpha-value>)',
                main: 'rgb(var(--main-rgb) / <alpha-value>)',
                muted: 'rgb(var(--muted-rgb) / <alpha-value>)',
                accent: {
                    DEFAULT: 'rgb(var(--accent-rgb) / <alpha-value>)',
                    hover: 'rgb(var(--accent-hover-rgb) / <alpha-value>)',
                    text: 'var(--accent-text)',
                },
                'accent-sub': {
                    DEFAULT: 'rgb(var(--accent-sub-rgb) / <alpha-value>)',
                    hover: 'rgb(var(--accent-sub-hover-rgb) / <alpha-value>)',
                    text: 'var(--accent-sub-text)',
                },
                success: {
                    DEFAULT: 'rgb(var(--success-rgb) / <alpha-value>)',
                    bg: 'var(--success-bg)',
                    text: 'var(--success-text)',
                },
                warning: {
                    DEFAULT: 'rgb(var(--warning-rgb) / <alpha-value>)',
                    bg: 'var(--warning-bg)',
                    text: 'var(--warning-text)',
                },
                error: {
                    DEFAULT: 'rgb(var(--error-rgb) / <alpha-value>)',
                    bg: 'var(--error-bg)',
                    text: 'var(--error-text)',
                },
            },
            fontFamily: {
                sans: ['"Be Vietnam Pro"', 'sans-serif'],
                serif: ['"Playfair Display"', 'serif'],
                montserrat: ['Montserrat', 'sans-serif'],
            },
            boxShadow: {
                subtle: '0 1px 3px 0 rgba(2, 6, 23, 0.05)',
                card: '0 4px 20px -2px rgba(32, 20, 100, 0.05), 0 2px 6px -1px rgba(2, 6, 23, 0.04)',
                'card-hover': '0 14px 34px -4px rgba(32, 20, 100, 0.12), 0 4px 12px -2px rgba(2, 6, 23, 0.06)',
                elevated: '0 20px 40px -10px rgba(32, 20, 100, 0.15)',
            },
            width: { 160: '40rem' },
        },
    },
    plugins: [],
};
