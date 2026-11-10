/**
 * NTA-Pay Global Theme Manager (Light / Dark Mode)
 * Persists user preference via localStorage and applies appropriate class to documentElement
 */
(function() {
    const THEME_STORAGE_KEY = 'nta_pay_theme';

    function getPreferredTheme() {
        const stored = localStorage.getItem(THEME_STORAGE_KEY);
        if (stored) return stored;
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
    }

    function applyTheme(theme) {
        if (theme === 'light') {
            document.documentElement.classList.add('light-theme');
            document.documentElement.classList.remove('dark');
        } else {
            document.documentElement.classList.remove('light-theme');
            document.documentElement.classList.add('dark');
        }
        localStorage.setItem(THEME_STORAGE_KEY, theme);

        // Update any toggle buttons on page
        const toggleButtons = document.querySelectorAll('.theme-toggle-btn');
        toggleButtons.forEach(btn => {
            const iconMoon = btn.querySelector('.theme-icon-moon');
            const iconSun = btn.querySelector('.theme-icon-sun');
            const labelText = btn.querySelector('.theme-text');
            if (theme === 'light') {
                if (iconMoon) iconMoon.classList.remove('hidden');
                if (iconSun) iconSun.classList.add('hidden');
                if (labelText) labelText.textContent = 'Dark';
            } else {
                if (iconMoon) iconMoon.classList.add('hidden');
                if (iconSun) iconSun.classList.remove('hidden');
                if (labelText) labelText.textContent = 'Light';
            }
        });
    }

    // Apply immediately to prevent flash
    const currentTheme = getPreferredTheme();
    applyTheme(currentTheme);

    window.toggleTheme = function() {
        const isLight = document.documentElement.classList.contains('light-theme');
        applyTheme(isLight ? 'dark' : 'light');
    };

    document.addEventListener('DOMContentLoaded', function() {
        applyTheme(localStorage.getItem(THEME_STORAGE_KEY) || currentTheme);
    });
})();

