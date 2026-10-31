(function () {
  'use strict';
  var key = 'linkhop-docs-theme';
  var toggle = document.getElementById('theme-toggle');
  var root = document.documentElement;

  function currentTheme() {
    return root.getAttribute('data-theme') ||
      (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  }

  function apply(theme) {
    root.setAttribute('data-theme', theme);
    try { localStorage.setItem(key, theme); } catch (e) {}
    if (toggle) {
      var next = theme === 'dark' ? 'light' : 'dark';
      toggle.setAttribute('data-action', next);
      toggle.textContent = toggle.getAttribute('data-label-' + next) || next;
    }
  }

  try {
    var stored = localStorage.getItem(key);
    if (stored === 'dark' || stored === 'light') apply(stored);
    else apply(currentTheme());
  } catch (e) {
    apply(currentTheme());
  }

  if (toggle) {
    toggle.addEventListener('click', function () {
      apply(currentTheme() === 'dark' ? 'light' : 'dark');
    });
  }
})();
