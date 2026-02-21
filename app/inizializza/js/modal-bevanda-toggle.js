// modal-bevanda-toggle.js
// Utility per spostare #modal-bevanda dentro/da un <template> per rimozione sicura e ripristino facile.

(function () {
  "use strict";

  window.modalBevandaToggle = {
    moveModalToTemplate: function () {
      try {
        var modal = document.getElementById('modal-bevanda');
        if (!modal) return false;
        // se già esiste template, non duplicare
        if (document.getElementById('tmpl-modal-bevanda')) return true;

        var template = document.createElement('template');
        template.id = 'tmpl-modal-bevanda';
        template.innerHTML = modal.outerHTML;
        // inserisci template nello stesso punto del DOM (prima del body end)
        document.body.appendChild(template);
        // rimuovi il modal dal DOM
        modal.parentNode.removeChild(modal);
        // rimuovi eventuale backdrop residuo
        var backdrop = document.querySelector('.modal-backdrop');
        if (backdrop && backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
        return true;
      } catch (e) {
        console.error('moveModalToTemplate error', e);
        return false;
      }
    },

    restoreModalFromTemplate: function () {
      try {
        var template = document.getElementById('tmpl-modal-bevanda');
        if (!template) return false;
        // parse template content and append to body
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = template.innerHTML.trim();
        var modalEl = tempDiv.firstElementChild;
        if (!modalEl) return false;
        document.body.appendChild(modalEl);
        // optionally remove template or keep it for future toggles
        // template.parentNode.removeChild(template);
        return true;
      } catch (e) {
        console.error('restoreModalFromTemplate error', e);
        return false;
      }
    }
  };
})();