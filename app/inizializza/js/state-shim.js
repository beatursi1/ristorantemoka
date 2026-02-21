// state-shim.js
// Shim di compatibilità per AppState / legacy window.carrello e window.parametriUrl
// Estrae lo shim da menu.js in modo da mantenere il file menu.js più leggero e
// rendere possibile il riuso in altre pagine.
// Deve essere caricato prima di menu.js (includi <script src="inizializza/js/state-shim.js"></script> prima di menu.js in menu.html)

(function () {
  'use strict';

  const hasAppState = () => (typeof window !== 'undefined' && window.AppState && typeof window.AppState.getCarrello === 'function');

  if (!window.__legacy_state) {
    window.__legacy_state = {
      carrello: window.carrello || [],
      parametriUrl: window.parametriUrl || {},
      arrivaDaCameriere: !!window.arrivaDaCameriere
    };
  }

  try {
    Object.defineProperty(window, 'carrello', {
      configurable: true,
      enumerable: true,
      get: function () {
        try {
          if (hasAppState()) return window.AppState.getCarrello();
          return Array.isArray(window.__legacy_state.carrello) ? window.__legacy_state.carrello.slice() : [];
        } catch (e) { return []; }
      },
      set: function (val) {
        try {
          if (hasAppState()) return window.AppState.setCarrello(Array.isArray(val) ? val.slice() : []);
          window.__legacy_state.carrello = Array.isArray(val) ? val.slice() : [];
        } catch (e) { /* ignore */ }
      }
    });
  } catch (e) { /* ignore defineProperty failures */ }

  try {
    Object.defineProperty(window, 'parametriUrl', {
      configurable: true,
      enumerable: true,
      get: function () {
        try {
          if (hasAppState()) return window.AppState.getParametriUrl();
          return Object.assign({}, window.__legacy_state.parametriUrl || {});
        } catch (e) { return {}; }
      },
      set: function (val) {
        try {
          if (hasAppState()) return window.AppState.setParametriUrl(Object.assign({}, val || {}));
          window.__legacy_state.parametriUrl = Object.assign({}, val || {});
        } catch (e) { /* ignore */ }
      }
    });
  } catch (e) { /* ignore */ }

  try {
    Object.defineProperty(window, 'arrivaDaCameriere', {
      configurable: true,
      enumerable: true,
      get: function () {
        try {
          if (hasAppState()) return window.AppState.getArrivaDaCameriere();
          return !!window.__legacy_state.arrivaDaCameriere;
        } catch (e) { return false; }
      },
      set: function (val) {
        try {
          if (hasAppState()) return window.AppState.setArrivaDaCameriere(!!val);
          window.__legacy_state.arrivaDaCameriere = !!val;
        } catch (e) { /* ignore */ }
      }
    });
  } catch (e) { /* ignore */ }

  // convenience global functions (backwards compat)
  if (!window.addToCarrello) {
    window.addToCarrello = function (item) {
      try {
        if (hasAppState()) return window.AppState.addToCarrello(item);
        if (!Array.isArray(window.__legacy_state.carrello)) window.__legacy_state.carrello = [];
        window.__legacy_state.carrello.push(item);
      } catch (e) { /* ignore */ }
    };
  }
  if (!window.removeFromCarrello) {
    window.removeFromCarrello = function (idx) {
      try {
        if (hasAppState()) return window.AppState.removeFromCarrello(idx);
        if (Array.isArray(window.__legacy_state.carrello) && typeof idx === 'number') window.__legacy_state.carrello.splice(idx, 1);
      } catch (e) { /* ignore */ }
    };
  }
  if (!window.clearCarrello) {
    window.clearCarrello = function () {
      try {
        if (hasAppState()) return window.AppState.clearCarrello();
        window.__legacy_state.carrello = [];
      } catch (e) { /* ignore */ }
    };
  }

  if (!window.getParametriUrl) {
    window.getParametriUrl = function () {
      try { return hasAppState() ? window.AppState.getParametriUrl() : Object.assign({}, window.__legacy_state.parametriUrl); } catch (e) { return {}; }
    };
  }
  if (!window.setParametriUrl) {
    window.setParametriUrl = function (obj) {
      try { if (hasAppState()) return window.AppState.setParametriUrl(obj); window.__legacy_state.parametriUrl = Object.assign({}, obj || {}); } catch (e) { /* ignore */ }
    };
  }

  if (!window.__AppStateShim) window.__AppStateShim = function () { return { legacy: window.__legacy_state, appStatePresent: hasAppState() }; };
})();