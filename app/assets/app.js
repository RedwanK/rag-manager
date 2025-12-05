import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

const THEME_STORAGE_KEY = 'rag-manager-theme';
let currentTheme = null;
const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
let prefersListenerBound = false;

const resolvePreferredTheme = () => {
  const storedTheme = localStorage.getItem(THEME_STORAGE_KEY);

  if (storedTheme === 'dark' || storedTheme === 'light') {
    return storedTheme;
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

const applyTheme = theme => {
  currentTheme = theme;
  const htmlElement = document.documentElement;
  const isDark = theme === 'dark';

  htmlElement.classList.toggle('dark-style', isDark);
  htmlElement.classList.toggle('light-style', !isDark);
  htmlElement.setAttribute('data-theme', isDark ? 'theme-dark' : 'theme-default');

  document.querySelectorAll('[data-theme-toggle]').forEach(toggle => {
    if (toggle.type === 'checkbox') {
      toggle.checked = isDark;
      toggle.setAttribute('aria-checked', isDark);
    } else {
      toggle.setAttribute('aria-pressed', isDark);
    }
  });
};

const setTheme = theme => {
  applyTheme(theme);
  localStorage.setItem(THEME_STORAGE_KEY, theme);
};

const initThemeToggle = () => {
  const toggles = document.querySelectorAll('[data-theme-toggle]');

  if (!currentTheme) {
    currentTheme = resolvePreferredTheme();
  }
  applyTheme(currentTheme);

  toggles.forEach(toggle => {
    if (toggle.dataset.themeToggleBound === 'true') {
      return;
    }

    const updateFromToggle = event => {
      const theme = event.target.checked ? 'dark' : 'light';
      currentTheme = theme;
      setTheme(theme);
    };

    toggle.addEventListener('change', updateFromToggle);
    toggle.dataset.themeToggleBound = 'true';
  });

  if (!prefersListenerBound) {
    prefersDark.addEventListener('change', event => {
      if (localStorage.getItem(THEME_STORAGE_KEY)) {
        return;
      }

      currentTheme = event.matches ? 'dark' : 'light';
      applyTheme(currentTheme);
    });
    prefersListenerBound = true;
  }
};

document.addEventListener('DOMContentLoaded', () => {
  currentTheme = resolvePreferredTheme();
  applyTheme(currentTheme);
  initThemeToggle();
});

document.addEventListener('turbo:load', initThemeToggle);
