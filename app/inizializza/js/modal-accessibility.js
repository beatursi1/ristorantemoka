// modal-accessibility.js
// Accessibility helper minimale copiato dalla home del cameriere.
// - memorizza l'opener su show.bs.modal
// - sfoca l'elemento attivo dentro il modal PRIMA che venga nascosto (capture phase)
// - ripristina il focus sull'opener
// Questo file è intenzionalmente semplice e non applica patch invasive su Bootstrap.
(function () {
    "use strict";

    function tryFocus(element) {
        if (!element || typeof element.focus !== 'function') return;
        try {
            element.focus({ preventScroll: true });
        } catch (err) {
            try { element.focus(); } catch (e) { /* ignore */ }
        }
    }

    function focusOpenerFor(modalEl) {
        if (!modalEl) return;
        var opener = modalEl.__modalOpener ||
            document.querySelector('[data-bs-toggle="modal"][data-bs-target="#' + (modalEl.id || '') + '"]') ||
            document.body;
        tryFocus(opener);
    }

    function blurActiveIfIn(modalEl) {
        try {
            var active = document.activeElement;
            if (active && modalEl && modalEl.contains(active)) {
                try { active.blur(); } catch (e) { /* ignore */ }
            }
        } catch (e) {
            /* ignore */
        }
    }

    // Salva l'elemento attivo prima dell'apertura del modal (fallback)
    document.addEventListener('show.bs.modal', function (ev) {
        try {
            var modalEl = ev.target;
            if (modalEl) modalEl.__modalOpener = document.activeElement;
        } catch (err) { /* ignore */ }
    }, true);

    // Intercetta click che possono causare la chiusura (capture phase)
    document.addEventListener('click', function (ev) {
        try {
            var target = ev.target;
            if (!target) return;

            // Pulsanti che chiudono il modal: data-bs-dismiss oppure .btn-close
            var btn = (typeof target.closest === 'function') ? target.closest('[data-bs-dismiss="modal"], .btn-close') : null;
            if (btn) {
                var modalEl = (typeof btn.closest === 'function') ? btn.closest('.modal') : null;
                if (modalEl) {
                    blurActiveIfIn(modalEl);
                    focusOpenerFor(modalEl);
                }
                return;
            }

            // Click sul backdrop: target è l'elemento .modal (non .modal-dialog)
            if (target.classList && target.classList.contains('modal')) {
                var modal = target;
                if (modal) {
                    blurActiveIfIn(modal);
                    focusOpenerFor(modal);
                }
            }
        } catch (err) { /* ignore */ }
    }, true); // capture: true -> esegue prima dei listener di Bootstrap

    // Intercetta ESC prima che Bootstrap lo gestisca (capture)
    document.addEventListener('keydown', function (ev) {
        try {
            if (ev.key !== 'Escape' && ev.key !== 'Esc') return;
            var active = document.activeElement;
            var modalEl = active && (typeof active.closest === 'function') ? active.closest('.modal') : null;
            if (!modalEl) modalEl = document.querySelector('.modal.show');
            if (modalEl) {
                blurActiveIfIn(modalEl);
                focusOpenerFor(modalEl);
            }
        } catch (err) { /* ignore */ }
    }, true);

    // Ulteriore fallback: quando bootstrap emette hide.bs.modal, assicuriamoci che il focus non sia dentro
    document.addEventListener('hide.bs.modal', function (ev) {
        try {
            var modalEl = ev.target;
            if (!modalEl) return;
            blurActiveIfIn(modalEl);
            focusOpenerFor(modalEl);
            if (modalEl.__modalOpener) {
                try { modalEl.__modalOpener = null; } catch (e) { /* ignore */ }
            }
        } catch (err) { /* ignore */ }
    }, true);
})();