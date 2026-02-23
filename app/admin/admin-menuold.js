(function () {
  let _menuData = null; // ultimo JSON caricato da menu.php

  // --- UTILI DI BASE --------------------------------------------------------

  async function fetchMenu() {
    try {
      const resp = await fetch('../../api/menu/menu.php');
      const data = await resp.json();
      if (!data || !data.success || !Array.isArray(data.data)) {
        showError('Errore nel caricamento dei dati del menu.');
        return null;
      }
      return data;
    } catch (e) {
      console.error(e);
      showError('Errore di connessione con il server (menu.php).');
      return null;
    }
  }

  function showError(msg) {
    const cont = document.getElementById('categorie-container');
    if (!cont) return;
    cont.innerHTML =
      '<div class="alert alert-danger" role="alert">' + msg + '</div>';
  }

  function getCategoriaById(catId) {
    if (!_menuData || !Array.isArray(_menuData.data)) return null;
    return _menuData.data.find(c => String(c.id) === String(catId)) || null;
  }

  // --- SELECT CATEGORIA / SOTTOCATEGORIA (FORM AGGIUNTA) --------------------

  function populateCategoriaSelect() {
    const sel = document.getElementById('input-categoria');
    if (!sel || !_menuData || !Array.isArray(_menuData.data)) return;

    sel.innerHTML = '';
    (_menuData.data || []).forEach(cat => {
      const opt = document.createElement('option');
      opt.value = cat.id || '';
      opt.textContent =
        (cat.nome || 'Categoria senza nome') +
        (cat.ordine ? ' (ordine ' + cat.ordine + ')' : '');
      sel.appendChild(opt);
    });
  }

  function populateSottocategoriaSelect() {
    const selCat = document.getElementById('input-categoria');
    const selSub = document.getElementById('input-sottocategoria');
    if (!selCat || !selSub || !_menuData || !Array.isArray(_menuData.data)) return;

    const catId = selCat.value;
    selSub.innerHTML = '';

    const optNone = document.createElement('option');
    optNone.value = '';
    optNone.textContent = 'Nessuna sottocategoria';
    selSub.appendChild(optNone);

    if (!catId) return;

    const cat = _menuData.data.find(
      c => String(c.id) === String(catId)
    );
    if (!cat || !Array.isArray(cat.sottocategorie) || !cat.sottocategorie.length) {
      return;
    }

    cat.sottocategorie
      .slice()
      .sort(
        (a, b) =>
          (parseInt(a.ordine || 0, 10) || 0) -
          (parseInt(b.ordine || 0, 10) || 0)
      )
      .forEach(sub => {
        if (sub.visibile === 0 || sub.visibile === '0') return;
        const opt = document.createElement('option');
        opt.value = sub.id || '';
        opt.textContent =
          sub.nome +
          (sub.ordine ? ' (ordine ' + sub.ordine + ')' : '');
        selSub.appendChild(opt);
      });
  }

  // --- SELECT SOTTOCATEGORIA (MODALE MODIFICA PIATTO) -----------------------

  function fillSottocategorieSelectForCategoria(selectEl, categoriaId, selectedSubId) {
    if (!selectEl) return;

    selectEl.innerHTML = '';

    const optNone = document.createElement('option');
    optNone.value = '';
    optNone.textContent = 'Nessuna sottocategoria';
    selectEl.appendChild(optNone);

    if (!categoriaId) return;

    const cat = getCategoriaById(categoriaId);
    if (!cat || !Array.isArray(cat.sottocategorie) || !cat.sottocategorie.length) {
      return;
    }

    cat.sottocategorie
      .slice()
      .sort((a, b) => {
        const oa = parseInt(a.ordine || 0, 10) || 0;
        const ob = parseInt(b.ordine || 0, 10) || 0;
        if (oa !== ob) return oa - ob;
        return (a.nome || '').localeCompare(b.nome || '');
      })
      .forEach(sub => {
        if (sub.visibile === 0 || sub.visibile === '0') return;
        const opt = document.createElement('option');
        opt.value = sub.id || '';
        opt.textContent =
          sub.nome + (sub.ordine ? ' (ordine ' + sub.ordine + ')' : '');
        if (selectedSubId && String(sub.id) === String(selectedSubId)) {
          opt.selected = true;
        }
        selectEl.appendChild(opt);
      });
  }

  // --- ORDINE CATEGORIE (SU / GIÙ) ------------------------------------------

  async function spostaCategoria(catId, delta) {
    if (!_menuData || !Array.isArray(_menuData.data)) return;

    const arr = _menuData.data.slice();
    const index = arr.findIndex(c => String(c.id) === String(catId));
    if (index === -1) return;

    const newIndex = index + delta;
    if (newIndex < 0 || newIndex >= arr.length) {
      return;
    }

    const tmp = arr[index];
    arr[index] = arr[newIndex];
    arr[newIndex] = tmp;

    const ordineIds = arr.map(c => c.id);

    try {
      const bodyObj = ordineIds.reduce((acc, id, idx) => {
        acc['ordine_categorie[' + idx + ']'] = String(id);
        return acc;
      }, {});

      const resp = await fetch('../../api/menu/riordina-categorie.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
        },
        body: new URLSearchParams(bodyObj)
      });

      const data = await resp.json();
      if (!data || !data.success) {
        alert(
          (data && data.error)
            ? 'Errore nel salvataggio ordine categorie: ' + data.error
            : 'Errore nel salvataggio ordine categorie.'
        );
        return;
      }

      _menuData.data = arr;
      renderMenu(_menuData);
    } catch (err) {
      console.error(err);
      alert('Errore di connessione durante il riordino categorie.');
    }
  }

  // --- ORDINE SOTTOCATEGORIE (SU / GIÙ) ------------------------------------

  async function spostaSottocategoria(categoriaId, sottocategoriaId, delta) {
    const cat = getCategoriaById(categoriaId);
    if (!cat || !Array.isArray(cat.sottocategorie)) return;

    const arr = cat.sottocategorie.slice();
    const index = arr.findIndex(s => String(s.id) === String(sottocategoriaId));
    if (index === -1) return;

    const newIndex = index + delta;
    if (newIndex < 0 || newIndex >= arr.length) {
      return;
    }

    const tmp = arr[index];
    arr[index] = arr[newIndex];
    arr[newIndex] = tmp;

    const ordineIds = arr.map(s => s.id);

    try {
      const bodyObj = { categoria_id: String(categoriaId) };
      ordineIds.forEach((id, idx) => {
        bodyObj['ordine_sottocategorie[' + idx + ']'] = String(id);
      });

      const resp = await fetch('../../api/menu/riordina-sottocategorie.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
        },
        body: new URLSearchParams(bodyObj)
      });

      const data = await resp.json();
      if (!data || !data.success) {
        alert(
          (data && data.error)
            ? 'Errore nel salvataggio ordine sottocategorie: ' + data.error
            : 'Errore nel salvataggio ordine sottocategorie.'
        );
        return;
      }

      cat.sottocategorie = arr;
      renderMenu(_menuData);
    } catch (err) {
      console.error(err);
      alert('Errore di connessione durante il riordino sottocategorie.');
    }
  }

  // --- ORDINE PIATTI (SU / GIÙ) --------------------------------------------

  async function spostaPiatto(categoriaId, sottocategoriaId, piattoId, delta) {
    const cat = getCategoriaById(categoriaId);
    if (!cat) return;

    const isInSub = !!sottocategoriaId;
    let arr;

    if (isInSub) {
      if (!Array.isArray(cat.sottocategorie)) return;
      const sub = cat.sottocategorie.find(
        s => String(s.id) === String(sottocategoriaId)
      );
      if (!sub || !Array.isArray(sub.piatti)) return;
      arr = sub.piatti.slice();
    } else {
      if (!Array.isArray(cat.piatti)) return;
      arr = cat.piatti.slice();
    }

    const index = arr.findIndex(p => String(p.id) === String(piattoId));
    if (index === -1) return;

    const newIndex = index + delta;
    if (newIndex < 0 || newIndex >= arr.length) {
      return;
    }

    const tmp = arr[index];
    arr[index] = arr[newIndex];
    arr[newIndex] = tmp;

    const ordineIds = arr.map(p => p.id);

    try {
      const bodyObj = {
        categoria_id: String(categoriaId),
        sottocategoria_id: isInSub ? String(sottocategoriaId) : ''
      };
      ordineIds.forEach((id, idx) => {
        bodyObj['ordine_piatti[' + idx + ']'] = String(id);
      });

      const resp = await fetch('../../api/menu/riordina-piatti.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
        },
        body: new URLSearchParams(bodyObj)
      });

      const data = await resp.json();
      if (!data || !data.success) {
        alert(
          (data && data.error)
            ? 'Errore nel salvataggio ordine piatti: ' + data.error
            : 'Errore nel salvataggio ordine piatti.'
        );
        return;
      }

      if (isInSub) {
        const sub = cat.sottocategorie.find(
          s => String(s.id) === String(sottocategoriaId)
        );
        if (sub) sub.piatti = arr;
      } else {
        cat.piatti = arr;
      }

      renderMenu(_menuData);
    } catch (err) {
      console.error(err);
      alert('Errore di connessione durante il riordino piatti.');
    }
  }

  // --- MODIFICA CATEGORIA ---------------------------------------------------

  function setupModificaCategoriaHandlers() {
    const form = document.getElementById('form-modifica-categoria');
    if (!form) return;

    form.addEventListener('submit', async function (e) {
      e.preventDefault();

      const inputId = document.getElementById('mod-cat-id');
      const inputNome = document.getElementById('mod-cat-nome');
      const inputVisibile = document.getElementById('mod-cat-visibile');
      const msgEl = document.getElementById('mod-cat-msg');
      const modalEl = document.getElementById('modalModificaCategoria');

      if (msgEl) {
        msgEl.textContent = '';
        msgEl.className = 'small mt-1';
      }

      if (!inputId || !inputNome || !inputVisibile) return;

      const id = parseInt(inputId.value, 10);
      if (!id || isNaN(id)) {
        if (msgEl) {
          msgEl.textContent = 'ID categoria non valido.';
          msgEl.classList.add('text-danger');
        }
        return;
      }

      const nome = inputNome.value.trim();
      if (!nome) {
        if (msgEl) {
          msgEl.textContent = 'Il nome della categoria è obbligatorio.';
          msgEl.classList.add('text-danger');
        }
        return;
      }

      const visibile = inputVisibile.checked ? 1 : 0;

      try {
        const params = new URLSearchParams({
          id: String(id),
          nome: nome,
          visibile: String(visibile)
        });

        const resp = await fetch('../../api/menu/update-categoria.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
          },
          body: params
        });

        const data = await resp.json();

        if (!data || !data.success) {
          if (msgEl) {
            msgEl.textContent =
              data && data.error
                ? 'Errore: ' + data.error
                : 'Errore sconosciuto durante il salvataggio.';
            msgEl.classList.add('text-danger');
          }
          return;
        }

        if (msgEl) {
          msgEl.textContent = 'Categoria aggiornata correttamente.';
          msgEl.classList.add('text-success');
        }

        const nuovoMenu = await fetchMenu();
        if (nuovoMenu) {
          _menuData = nuovoMenu;
          renderMenu(_menuData);
          populateCategoriaSelect();
          populateSottocategoriaSelect();
        }

        if (modalEl) {
          const bsModal =
            bootstrap.Modal.getInstance(modalEl) ||
            new bootstrap.Modal(modalEl);
          setTimeout(() => {
            bsModal.hide();
          }, 600);
        }
      } catch (err) {
        console.error(err);
        if (msgEl) {
          msgEl.textContent =
            'Errore di connessione con il server.';
          msgEl.classList.add('text-danger');
        }
      }
    });
  }

  // --- NUOVA SOTTOCATEGORIA -------------------------------------------------

  function setupNuovaSottocategoriaHandlers() {
    const form = document.getElementById('form-nuova-sottocategoria');
    if (!form) return;

    form.addEventListener('submit', async function (e) {
      e.preventDefault();

      const inputCatId = document.getElementById('new-sub-cat-id');
      const inputNome = document.getElementById('new-sub-nome');
      const inputVisibile = document.getElementById('new-sub-visibile');
      const msgEl = document.getElementById('new-sub-msg');
      const modalEl = document.getElementById('modalNuovaSottocategoria');

      if (msgEl) {
        msgEl.textContent = '';
        msgEl.className = 'small mt-1';
      }

      if (!inputCatId || !inputNome || !inputVisibile) return;

      const categoriaId = parseInt(inputCatId.value, 10);
      if (!categoriaId || isNaN(categoriaId)) {
        if (msgEl) {
          msgEl.textContent = 'ID categoria non valido.';
          msgEl.classList.add('text-danger');
        }
        return;
      }

      const nome = inputNome.value.trim();
      if (!nome) {
        if (msgEl) {
          msgEl.textContent = 'Il nome della sottocategoria è obbligatorio.';
          msgEl.classList.add('text-danger');
        }
        return;
      }

      const visibile = inputVisibile.checked ? 1 : 0;

      try {
        const params = new URLSearchParams({
          categoria_id: String(categoriaId),
          nome: nome,
          visibile: String(visibile)
        });

        const resp = await fetch('../../api/menu/crea-sottocategoria.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
          },
          body: params
        });

        const data = await resp.json();

        if (!data || !data.success) {
          if (msgEl) {
            msgEl.textContent =
              data && data.error
                ? 'Errore: ' + data.error
                : 'Errore sconosciuto durante il salvataggio.';
            msgEl.classList.add('text-danger');
          }
          return;
        }

        if (msgEl) {
          msgEl.textContent = 'Sottocategoria creata correttamente.';
          msgEl.classList.add('text-success');
        }

        const nuovoMenu = await fetchMenu();
        if (nuovoMenu) {
          _menuData = nuovoMenu;
          renderMenu(_menuData);
          populateCategoriaSelect();
          populateSottocategoriaSelect();
        }

        if (modalEl) {
          const bsModal =
            bootstrap.Modal.getInstance(modalEl) ||
            new bootstrap.Modal(modalEl);
          setTimeout(() => {
            bsModal.hide();
          }, 600);
        }
      } catch (err) {
        console.error(err);
        if (msgEl) {
          msgEl.textContent =
            'Errore di connessione con il server.';
          msgEl.classList.add('text-danger');
        }
      }
    });
  }

  // --- RIGA PIATTO ----------------------------------------------------------

  function creaRigaPiatto(cat, sub, p) {
    const riga = document.createElement('div');
    riga.className = 'piatto-riga d-flex justify-content-between align-items-start';

    const colLeft = document.createElement('div');
    const nomePiatto = document.createElement('div');
    nomePiatto.className = 'piatto-nome';
    nomePiatto.textContent = p.nome || 'Piatto senza nome';
    colLeft.appendChild(nomePiatto);

    if (p.descrizione) {
      const desc = document.createElement('div');
      desc.className = 'piatto-descrizione';
      desc.textContent = p.descrizione;
      colLeft.appendChild(desc);
    }

    const meta = document.createElement('div');
    meta.className = 'piatto-meta';

    const tempo =
      typeof p.tempo_preparazione === 'number'
        ? p.tempo_preparazione + ' min'
        : '';
    const punti =
      typeof p.punti_fedelta === 'number'
        ? p.punti_fedelta + ' punti fedeltà'
        : '';

    const parts = [tempo, punti].filter(Boolean);
    if (parts.length) {
      meta.textContent = parts.join(' • ');
      colLeft.appendChild(meta);
    }

    if (p.allergeni) {
      const allergeniRow = document.createElement('div');
      allergeniRow.className = 'piatto-meta';
      allergeniRow.innerHTML =
        '<strong>Allergeni:</strong> ' + p.allergeni;
      colLeft.appendChild(allergeniRow);
    }

    riga.appendChild(colLeft);

    const colRight = document.createElement('div');
    colRight.className =
      'text-end d-flex flex-column align-items-end gap-1';

    const prezzo = document.createElement('div');
    prezzo.className = 'piatto-prezzo';
    prezzo.textContent =
      p.prezzo_formattato ||
      (p.prezzo != null ? '€' + Number(p.prezzo).toFixed(2) : '');
    colRight.appendChild(prezzo);

    if (typeof p.punti_fedelta === 'number') {
      const puntiBadge = document.createElement('div');
      puntiBadge.className = 'piatto-punti';
      puntiBadge.textContent =
        p.punti_fedelta + ' punti (1 punto ogni €)';
      colRight.appendChild(puntiBadge);
    }

    const toolsRow = document.createElement('div');
    toolsRow.className = 'd-flex align-items-center gap-1 mt-1';

    const groupMove = document.createElement('div');
    groupMove.className = 'btn-group btn-group-sm';
    groupMove.setAttribute('role', 'group');

    const btnUp = document.createElement('button');
    btnUp.type = 'button';
    btnUp.className = 'btn btn-sm btn-move';
    btnUp.innerHTML = '<i class="fas fa-arrow-up"></i>';
    groupMove.appendChild(btnUp);

    const btnDown = document.createElement('button');
    btnDown.type = 'button';
    btnDown.className = 'btn btn-sm btn-move';
    btnDown.innerHTML = '<i class="fas fa-arrow-down"></i>';
    groupMove.appendChild(btnDown);

    const tools = document.createElement('div');
    tools.className = 'btn-group btn-group-sm';
    tools.setAttribute('role', 'group');

    const btnEditPiatto = document.createElement('button');
    btnEditPiatto.type = 'button';
    btnEditPiatto.className = 'btn btn-outline-secondary';
    btnEditPiatto.innerHTML =
      '<i class="fas fa-pen me-1"></i>Modifica';
    tools.appendChild(btnEditPiatto);

    const btnDeletePiatto = document.createElement('button');
    btnDeletePiatto.type = 'button';
    btnDeletePiatto.className = 'btn btn-outline-danger';
    btnDeletePiatto.innerHTML =
      '<i class="fas fa-trash me-1"></i>Elimina';
    tools.appendChild(btnDeletePiatto);

    // Handler frecce su/giù per piatto
    btnUp.addEventListener('click', async () => {
      await spostaPiatto(cat.id, sub ? sub.id : null, p.id, -1);
    });
    btnDown.addEventListener('click', async () => {
      await spostaPiatto(cat.id, sub ? sub.id : null, p.id, +1);
    });

    toolsRow.appendChild(groupMove);
    toolsRow.appendChild(tools);
    colRight.appendChild(toolsRow);

    // Click su "Modifica" piatto
    btnEditPiatto.addEventListener('click', function () {
      const modalEl = document.getElementById(
        'modalModificaPiatto'
      );
      if (!modalEl) return;

      const bsModal = new bootstrap.Modal(modalEl);

      const inputId = document.getElementById('mod-piatto-id');
      const inputNome =
        document.getElementById('mod-piatto-nome');
      const inputDescr = document.getElementById(
        'mod-piatto-descrizione'
      );
      const inputPrezzo = document.getElementById(
        'mod-piatto-prezzo'
      );
      const inputTempo =
        document.getElementById('mod-piatto-tempo');
      const inputPunti =
        document.getElementById('mod-piatto-punti');
      const inputAllergeni = document.getElementById(
        'mod-piatto-allergeni'
      );
      const inputSottocategoria = document.getElementById(
        'mod-piatto-sottocategoria'
      );
      const msgEl =
        document.getElementById('mod-piatto-msg');

      if (msgEl) {
        msgEl.textContent = '';
        msgEl.className = 'small mt-1';
      }

      if (inputId) inputId.value = p.id || '';
      if (inputNome) inputNome.value = p.nome || '';
      if (inputDescr) inputDescr.value = p.descrizione || '';

      if (inputPrezzo) {
        const val =
          typeof p.prezzo === 'number'
            ? p.prezzo
            : parseFloat(
                (p.prezzo || '')
                  .toString()
                  .replace(',', '.')
              );
        inputPrezzo.value = !isNaN(val) ? val : '';
      }

      if (inputTempo) {
        inputTempo.value =
          typeof p.tempo_preparazione === 'number' &&
          !isNaN(p.tempo_preparazione)
            ? p.tempo_preparazione
            : '';
      }

      if (inputPunti) {
        inputPunti.value =
          typeof p.punti_fedelta === 'number' &&
          !isNaN(p.punti_fedelta)
            ? p.punti_fedelta
            : '';
      }

      if (inputAllergeni)
        inputAllergeni.value = p.allergeni || '';

      if (inputSottocategoria) {
        const categoriaId = cat.id;
        const currentSubId =
          (p.sottocategoria_id != null ? p.sottocategoria_id : '') ||
          (sub && sub.id) ||
          '';
        fillSottocategorieSelectForCategoria(
          inputSottocategoria,
          categoriaId,
          currentSubId
        );
      }

      bsModal.show();
    });

    riga.appendChild(colRight);

    return riga;
  }

  // --- RENDER CATEGORIE + PIATTI -------------------------------------------

  function renderMenu(data) {
    const cont = document.getElementById('categorie-container');
    if (!cont) return;
    cont.innerHTML = '';

    if (!data || !Array.isArray(data.data) || data.data.length === 0) {
      cont.innerHTML =
        '<div class="alert alert-warning">Nessuna categoria presente nel menu.</div>';
      return;
    }

    (data.data || []).forEach(cat => {
      const card = document.createElement('div');
      card.className = 'categoria-card bg-white';

      const header = document.createElement('div');
      header.className = 'categoria-header';

      const left = document.createElement('div');
      const nome = document.createElement('div');
      nome.innerHTML =
        '<span class="badge badge-categoria me-2">' +
        (cat.id || '?') +
        '</span> ' +
        (cat.nome || 'Categoria senza nome');
      left.appendChild(nome);

      const info = document.createElement('small');
      const countPiattiSenzaSub = Array.isArray(cat.piatti) ? cat.piatti.length : 0;
      let countPiattiSub = 0;
      if (Array.isArray(cat.sottocategorie)) {
        cat.sottocategorie.forEach(sub => {
          if (Array.isArray(sub.piatti)) {
            countPiattiSub += sub.piatti.length;
          }
        });
      }
      const totPiattiCat = countPiattiSenzaSub + countPiattiSub;

      info.textContent =
        'Ordine: ' +
        (cat.ordine || '-') +
        ' • Piatti totali: ' + totPiattiCat;
      left.appendChild(info);
      header.appendChild(left);

      const right = document.createElement('div');
      right.className = 'd-flex flex-column align-items-end gap-1';

      const idLine = document.createElement('div');
      idLine.className = 'small';
      idLine.textContent = 'ID categoria: ' + (cat.id || '-');
      right.appendChild(idLine);

      const btnBar = document.createElement('div');
      btnBar.className = 'btn-group btn-group-sm';
      btnBar.setAttribute('role', 'group');

      // frecce categoria
      const btnUp = document.createElement('button');
      btnUp.type = 'button';
      btnUp.className = 'btn btn-sm btn-move';
      btnUp.innerHTML = '<i class="fas fa-arrow-up"></i>';
      btnBar.appendChild(btnUp);

      const btnDown = document.createElement('button');
      btnDown.type = 'button';
      btnDown.className = 'btn btn-sm btn-move';
      btnDown.innerHTML = '<i class="fas fa-arrow-down"></i>';
      btnBar.appendChild(btnDown);

      // modifica categoria
      const btnEdit = document.createElement('button');
      btnEdit.type = 'button';
      btnEdit.className = 'btn btn-sm btn-cat-action';
      btnEdit.innerHTML = '<i class="fas fa-pen me-1"></i>Modifica';
      btnBar.appendChild(btnEdit);

      // aggiungi sottocategoria
      const btnAddSub = document.createElement('button');
      btnAddSub.type = 'button';
      btnAddSub.className = 'btn btn-sm btn-cat-action';
      btnAddSub.innerHTML =
        '<i class="fas fa-folder-plus me-1"></i>Aggiungi sottocategoria';
      btnBar.appendChild(btnAddSub);

      // elimina categoria (non implementato a DB per ora)
      const btnDelete = document.createElement('button');
      btnDelete.type = 'button';
      btnDelete.className = 'btn btn-sm btn-outline-danger';
      btnDelete.innerHTML = '<i class="fas fa-trash me-1"></i>Elimina';
      btnBar.appendChild(btnDelete);

      // handler sposta categoria
      btnUp.addEventListener('click', async () => {
        await spostaCategoria(cat.id, -1);
      });
      btnDown.addEventListener('click', async () => {
        await spostaCategoria(cat.id, +1);
      });

      // handler Modifica categoria
      btnEdit.addEventListener('click', () => {
        const modalEl = document.getElementById('modalModificaCategoria');
        if (!modalEl) return;

        const inputId = document.getElementById('mod-cat-id');
        const inputNome = document.getElementById('mod-cat-nome');
        const inputVisibile = document.getElementById('mod-cat-visibile');
        const msgEl = document.getElementById('mod-cat-msg');

        if (msgEl) {
          msgEl.textContent = '';
          msgEl.className = 'small mt-1';
        }

        if (inputId) inputId.value = cat.id || '';
        if (inputNome) inputNome.value = cat.nome || '';
        if (inputVisibile) {
          inputVisibile.checked = cat.visibile === undefined
            ? true
            : !!cat.visibile;
        }

        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
      });

      // handler Aggiungi sottocategoria
      btnAddSub.addEventListener('click', () => {
        const modalEl = document.getElementById('modalNuovaSottocategoria');
        if (!modalEl) return;

        const inputCatId = document.getElementById('new-sub-cat-id');
        const inputNome = document.getElementById('new-sub-nome');
        const inputVisibile = document.getElementById('new-sub-visibile');
        const msgEl = document.getElementById('new-sub-msg');

        if (msgEl) {
          msgEl.textContent = '';
          msgEl.className = 'small mt-1';
        }

        if (inputCatId) inputCatId.value = cat.id || '';
        if (inputNome) inputNome.value = '';
        if (inputVisibile) inputVisibile.checked = true;

        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
      });

      right.appendChild(btnBar);
      header.appendChild(right);

      card.appendChild(header);

      const piattiWrapper = document.createElement('div');

      // Piatti senza sottocategoria
      if (Array.isArray(cat.piatti) && cat.piatti.length) {
        const bloccoNoSub = document.createElement('div');
        bloccoNoSub.className = 'mb-3';

        const titoloNoSub = document.createElement('div');
        titoloNoSub.className = 'd-flex justify-content-between align-items-center mb-2';

        const labelNoSub = document.createElement('h6');
        labelNoSub.className = 'text-muted mb-0';
        labelNoSub.textContent = 'Piatti senza sottocategoria';
        titoloNoSub.appendChild(labelNoSub);

        bloccoNoSub.appendChild(titoloNoSub);

        cat.piatti.forEach(p => {
          const riga = creaRigaPiatto(cat, null, p);
          bloccoNoSub.appendChild(riga);
        });

        piattiWrapper.appendChild(bloccoNoSub);
      }

      // Piatti con sottocategoria
      if (Array.isArray(cat.sottocategorie) && cat.sottocategorie.length) {
        cat.sottocategorie.forEach(sub => {
          const bloccoSub = document.createElement('div');
          bloccoSub.className = 'mb-3';

          const headerSub = document.createElement('div');
          headerSub.className = 'd-flex justify-content-between align-items-center mb-2';

          const titoloSub = document.createElement('h6');
          titoloSub.className = 'mb-0';
          titoloSub.textContent =
            (sub.nome || 'Sottocategoria') +
            (sub.ordine ? ' (ordine ' + sub.ordine + ')' : '');
          headerSub.appendChild(titoloSub);

          const toolsSub = document.createElement('div');
          toolsSub.className = 'btn-group btn-group-sm';

          const btnSubUp = document.createElement('button');
          btnSubUp.type = 'button';
          btnSubUp.className = 'btn btn-sm btn-move';
          btnSubUp.innerHTML = '<i class="fas fa-arrow-up"></i>';
          toolsSub.appendChild(btnSubUp);

          const btnSubDown = document.createElement('button');
          btnSubDown.type = 'button';
          btnSubDown.className = 'btn btn-sm btn-move';
          btnSubDown.innerHTML = '<i class="fas fa-arrow-down"></i>';
          toolsSub.appendChild(btnSubDown);

          btnSubUp.addEventListener('click', async () => {
            await spostaSottocategoria(cat.id, sub.id, -1);
          });
          btnSubDown.addEventListener('click', async () => {
            await spostaSottocategoria(cat.id, sub.id, +1);
          });

          headerSub.appendChild(toolsSub);
          bloccoSub.appendChild(headerSub);

          if (Array.isArray(sub.piatti) && sub.piatti.length) {
            sub.piatti.forEach(p => {
              const riga = creaRigaPiatto(cat, sub, p);
              bloccoSub.appendChild(riga);
            });
          } else {
            const vuotoSub = document.createElement('div');
            vuotoSub.className = 'p-2 text-muted small';
            vuotoSub.textContent = 'Nessun piatto in questa sottocategoria.';
            bloccoSub.appendChild(vuotoSub);
          }

          piattiWrapper.appendChild(bloccoSub);
        });
      }

      if (!totPiattiCat) {
        const vuoto = document.createElement('div');
        vuoto.className = 'p-3 text-muted';
        vuoto.textContent =
          'Nessun piatto presente in questa categoria.';
        piattiWrapper.appendChild(vuoto);
      }

      card.appendChild(piattiWrapper);
      cont.appendChild(card);
    });

    if (data.timestamp) {
      const el = document.getElementById('admin-refresh-time');
      if (el) {
        el.textContent = 'Ultimo aggiornamento: ' + data.timestamp;
      }
    }
  }

  // --- FORM AGGIUNGI PIATTO -------------------------------------------------

  function setupForm() {
    const form = document.getElementById('form-aggiungi-piatto');
    if (!form) return;

    const selCatChange = document.getElementById('input-categoria');
    if (selCatChange) {
      selCatChange.addEventListener('change', function () {
        populateSottocategoriaSelect();
      });
    }

    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      const msg = document.getElementById('form-aggiungi-msg');
      if (msg) {
        msg.textContent = '';
        msg.className = 'small';
      }

      const selCat = document.getElementById('input-categoria');
      const selSub = document.getElementById('input-sottocategoria');
      const nome = document.getElementById('input-nome');
      const desc = document.getElementById('input-descrizione');
      const prezzo = document.getElementById('input-prezzo');
      const tempo = document.getElementById('input-tempo');
      const punti = document.getElementById('input-punti');
      const allergeni = document.getElementById('input-allergeni');
      if (!selCat || !nome || !prezzo) return;

      const catId = selCat.value;
      if (!catId) {
        if (msg) {
          msg.textContent = 'Seleziona una categoria.';
          msg.classList.add('text-danger');
        }
        return;
      }

      const nomeVal = nome.value.trim();
      if (!nomeVal) {
        if (msg) {
          msg.textContent = 'Il nome della pietanza è obbligatorio.';
          msg.classList.add('text-danger');
        }
        return;
      }

      const prezzoVal = parseFloat(prezzo.value.replace(',', '.'));
      if (isNaN(prezzoVal) || prezzoVal < 0) {
        if (msg) {
          msg.textContent = 'Inserisci un prezzo valido.';
          msg.classList.add('text-danger');
        }
        return;
      }

      const tempoVal = tempo.value ? tempo.value.trim() : '';
      const puntiVal = punti.value ? punti.value.trim() : '';
      const allergeniVal = allergeni && allergeni.value ? allergeni.value.trim() : '';
      const descrVal = desc ? desc.value.trim() : '';
      const sottocategoriaId = selSub ? selSub.value : '';

      try {
        const params = new URLSearchParams({
          categoria_id: String(catId),
          sottocategoria_id: sottocategoriaId || '',
          nome: nomeVal,
          descrizione: descrVal,
          prezzo: String(prezzoVal),
          tempo_preparazione: tempoVal,
          punti_fedelta: puntiVal,
          allergeni: allergeniVal
        });

        const resp = await fetch('../../api/menu/crea-piatto.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
          },
          body: params
        });

        const data = await resp.json();

        if (!data || !data.success) {
          if (msg) {
            msg.textContent =
              data && data.error
                ? 'Errore: ' + data.error
                : 'Errore sconosciuto durante il salvataggio.';
            msg.classList.add('text-danger');
          }
          return;
        }

        const nuovoMenu = await fetchMenu();
        if (nuovoMenu) {
          _menuData = nuovoMenu;
          renderMenu(_menuData);
          populateCategoriaSelect();
          populateSottocategoriaSelect();
        }

        if (msg) {
          msg.textContent = 'Piatto creato correttamente.';
          msg.classList.add('text-success');
        }

        nome.value = '';
        if (desc) desc.value = '';
        tempo.value = '';
        punti.value = '';
        prezzo.value = '';
        if (allergeni) allergeni.value = '';
        if (selSub) selSub.value = '';
      } catch (err) {
        console.error(err);
        if (msg) {
          msg.textContent = 'Errore di connessione con il server.';
          msg.classList.add('text-danger');
        }
      }
    });
  }

  // --- MODALE MODIFICA PIATTO -----------------------------------------------

  function setupModificaPiatto() {
    const form = document.getElementById(
      'form-modifica-piatto'
    );
    if (!form) return;

    form.addEventListener('submit', async function (e) {
      e.preventDefault();

      const inputId =
        document.getElementById('mod-piatto-id');
      const inputNome =
        document.getElementById('mod-piatto-nome');
      const inputDescr = document.getElementById(
        'mod-piatto-descrizione'
      );
      const inputPrezzo = document.getElementById(
        'mod-piatto-prezzo'
      );
      const inputTempo =
        document.getElementById('mod-piatto-tempo');
      const inputPunti =
        document.getElementById('mod-piatto-punti');
      const inputAllergeni = document.getElementById(
        'mod-piatto-allergeni'
      );
      const inputSottocategoria = document.getElementById(
        'mod-piatto-sottocategoria'
      );
      const msgEl =
        document.getElementById('mod-piatto-msg');
      const modalEl =
        document.getElementById('modalModificaPiatto');

      if (msgEl) {
        msgEl.textContent = '';
        msgEl.className = 'small mt-1';
      }

      if (!inputId || !inputNome || !inputPrezzo) return;

      const idPiatto = parseInt(inputId.value, 10);
      if (!idPiatto || isNaN(idPiatto)) {
        if (msgEl) {
          msgEl.textContent = 'ID piatto non valido.';
          msgEl.classList.add('text-danger');
        }
        return;
      }

      const nome = inputNome.value.trim();
      if (!nome) {
        if (msgEl) {
          msgEl.textContent =
            'Il nome del piatto è obbligatorio.';
          msgEl.classList.add('text-danger');
        }
        return;
      }

      const prezzoStr = inputPrezzo.value.replace(',', '.');
      const prezzoVal = parseFloat(prezzoStr);
      if (isNaN(prezzoVal) || prezzoVal < 0) {
        if (msgEl) {
          msgEl.textContent =
            'Inserisci un prezzo valido.';
          msgEl.classList.add('text-danger');
        }
        return;
      }

      const descr = inputDescr
        ? inputDescr.value.trim()
        : '';
      const tempoStr = inputTempo
        ? inputTempo.value.trim()
        : '';
      const puntiStr = inputPunti
        ? inputPunti.value.trim()
        : '';
      const allergeni = inputAllergeni
        ? inputAllergeni.value.trim()
        : '';
      const sottocategoriaId = inputSottocategoria
        ? inputSottocategoria.value
        : '';

      try {
        const params = new URLSearchParams({
          id_piatto: String(idPiatto),
          nome: nome,
          descrizione: descr,
          prezzo: String(prezzoVal),
          tempo_preparazione: tempoStr,
          punti_fedelta: puntiStr,
          allergeni: allergeni,
          sottocategoria_id: sottocategoriaId || ''
        });

        const resp = await fetch(
          '../../api/menu/update-piatto.php',
          {
            method: 'POST',
            headers: {
              'Content-Type':
                'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: params
          }
        );

        const data = await resp.json();

        if (!data || !data.success) {
          if (msgEl) {
            msgEl.textContent =
              data && data.error
                ? 'Errore: ' + data.error
                : 'Errore sconosciuto durante il salvataggio.';
            msgEl.classList.add('text-danger');
          }
          return;
        }

        if (msgEl) {
          msgEl.textContent =
            'Piatto aggiornato correttamente.';
          msgEl.classList.add('text-success');
        }

        const nuovoMenu = await fetchMenu();
        if (nuovoMenu) {
          _menuData = nuovoMenu;
          renderMenu(_menuData);
          populateCategoriaSelect();
          populateSottocategoriaSelect();
        }

        if (modalEl) {
          const bsModal =
            bootstrap.Modal.getInstance(modalEl) ||
            new bootstrap.Modal(modalEl);
          setTimeout(() => {
            bsModal.hide();
          }, 600);
        }
      } catch (err) {
        console.error(err);
        if (msgEl) {
          msgEl.textContent =
            'Errore di connessione con il server.';
          msgEl.classList.add('text-danger');
        }
      }
    });
  }

  // --- INIT -----------------------------------------------------------------

  async function init() {
    const data = await fetchMenu();
    if (!data) return;
    _menuData = data;
    populateCategoriaSelect();
    populateSottocategoriaSelect();
    renderMenu(_menuData);
    setupForm();
    setupModificaPiatto();
    setupModificaCategoriaHandlers();
    setupNuovaSottocategoriaHandlers();
  }

  window.addEventListener('load', init);
})();