// state.js
(function (window, document) {
  'use strict';

  // Stato applicazione centralizzato e minimale.
  // Espone API sincrone semplici: get/set per parametriUrl, carrello, flag.
  // Eventi: subscribe(callback) per cambi sul carrello o parametriUrl.
  const _state = {
    carrello: [], // array di oggetti {id,nome,prezzo,cliente,...}
    parametriUrl: {}, // oggetto con valori letti dalla query
    arrivaDaCameriere: false
  };

  const _subs = new Set();

  function _notify(type, payload) {
    try {
      _subs.forEach(fn => {
        try { fn(type, payload); } catch (e) { console.error('AppState subscriber error', e); }
      });
    } catch (e) { /* ignore */ }
  }

  // Carrello API
  function getCarrello() { return _state.carrello.slice(); } // ritorna copia
  function setCarrello(arr) {
    _state.carrello = Array.isArray(arr) ? arr.slice() : [];
    _notify('carrello:changed', getCarrello());
  }
  function addToCarrello(item) {
    _state.carrello.push(item);
    _notify('carrello:changed', getCarrello());
  }
  function removeFromCarrello(index) {
    if (typeof index === 'number' && index >= 0 && index < _state.carrello.length) {
      _state.carrello.splice(index, 1);
      _notify('carrello:changed', getCarrello());
      return true;
    }
    return false;
  }
  function clearCarrello() {
    _state.carrello = [];
    _notify('carrello:changed', getCarrello());
  }

  // Parametri URL API
  function getParametriUrl() { return Object.assign({}, _state.parametriUrl); }
  function setParametriUrl(obj) {
    _state.parametriUrl = Object.assign({}, obj || {});
    _notify('parametriUrl:changed', getParametriUrl());
  }

  // Flag cameriere
  function getArrivaDaCameriere() { return !!_state.arrivaDaCameriere; }
  function setArrivaDaCameriere(v) {
    _state.arrivaDaCameriere = !!v;
    _notify('arrivaDaCameriere:changed', _state.arrivaDaCameriere);
  }

  // Persistenza semplice (opzionale): salva e carica carrello in localStorage
  function persistCarrello(key) {
    try {
      if (!key) return;
      localStorage.setItem(key, JSON.stringify(_state.carrello));
    } catch (e) { /* ignore */ }
  }
  function loadCarrello(key) {
    try {
      if (!key) return;
      const raw = localStorage.getItem(key);
      if (raw) {
        const arr = JSON.parse(raw);
        if (Array.isArray(arr)) {
          _state.carrello = arr.slice();
          _notify('carrello:changed', getCarrello());
        }
      }
    } catch (e) { /* ignore */ }
  }

  // Subscriptions
  function subscribe(fn) {
    if (typeof fn !== 'function') return function () {};
    _subs.add(fn);
    // return unsubscribe
    return function () { _subs.delete(fn); };
  }

  // Espongo l'API come window.AppState per compatibilità con codice esistente
  const AppState = {
    // cart
    getCarrello, setCarrello, addToCarrello, removeFromCarrello, clearCarrello,
    // params
    getParametriUrl, setParametriUrl,
    // flag
    getArrivaDaCameriere, setArrivaDaCameriere,
    // persistence
    persistCarrello, loadCarrello,
    // events
    subscribe,
    // helper per debug
    _internal: function () { return JSON.parse(JSON.stringify(_state)); }
  };

  // Evitiamo di sovrascrivere se già presente (non dovrebbero esserci conflitti)
  if (!window.AppState) {
    window.AppState = AppState;
  } else {
    // Se esiste già, arricchiamo con eventuali metodi mancanti (safeguard)
    try {
      Object.keys(AppState).forEach(k => { if (!window.AppState[k]) window.AppState[k] = AppState[k]; });
    } catch (e) { /* ignore */ }
  }
})(window, document);