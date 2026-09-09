//

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';

// Theme management
const themeKey = 'cr_institutional_theme';
const getStoredTheme = () => {
    try {
        return localStorage.getItem(themeKey) || 'dark';
    } catch {
        return 'dark';
    }
};

const applyTheme = (theme) => {
    document.documentElement.setAttribute('data-theme', theme);
    try {
        localStorage.setItem(themeKey, theme);
    } catch {
        // storage disabled
    }
};

// Apply immediately
applyTheme(getStoredTheme());

document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.querySelector('.site-theme-btn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-theme') || 'dark';
            const next = current === 'dark' ? 'light' : 'dark';
            applyTheme(next);
        });
    }
});
