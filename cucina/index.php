<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cucina - RistoranteMoka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #1a1a2e;
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .header {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            padding: 15px 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        .ordine-card {
            background: white;
            color: #333;
            border-radius: 10px;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transition: transform 0.3s;
            border-left: 5px solid #3498db;
        }
        .ordine-card:hover {
            transform: translateY(-3px);
        }
        .ordine-header {
            background: #2c3e50;
            color: white;
            padding: 12px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ordine-body {
            padding: 15px;
        }
        .piatto-item {
            padding: 10px 0;
            border-bottom: 1px dashed #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .piatto-item:last-child {
            border-bottom: none;
        }
        .stato-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.8em;
        }
        .stato-attesa { background: #f39c12; color: white; }
        .stato-preparazione { background: #3498db; color: white; }
        .stato-pronto { background: #27ae60; color: white; }
        .btn-azione {
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-inizia { background: #3498db; color: white; }
        .btn-inizia:hover { background: #2980b9; }
        .btn-pronto { background: #27ae60; color: white; }
        .btn-pronto:hover { background: #219653; }
        .btn-consegnato { background: #7f8c8d; color: white; }
        .btn-consegnato:hover { background: #5d6d7e; }
        .timer {
            font-family: monospace;
            background: #2c3e50;
            color: #f1c40f;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .stato-colonna {
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            padding: 15px;
            min-height: 500px;
        }
        .colonna-titolo {
            text-align: center;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 8px;
            font-weight: bold;
        }
        #in-attesa-titolo { background: rgba(243, 156, 18, 0.2); color: #f39c12; }
        #in-preparazione-titolo { background: rgba(52, 152, 219, 0.2); color: #3498db; }
        #pronto-titolo { background: rgba(39, 174, 96, 0.2); color: #27ae60; }
        .aggiorna-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #e74c3c;
            color: white;
            border: none;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            font-size: 24px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            cursor: pointer;
            z-index: 1000;
        }
        .aggiorna-btn:hover {
            background: #c0392b;
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-8">
                    <h1 class="h3 mb-1"><i class="fas fa-utensils me-2"></i>CUCINA RISTORANTEMOKA</h1>
                    <p class="mb-0 opacity-75">Monitor ordini in tempo reale</p>
                </div>
                <div class="col-4 text-end">
                    <div class="d-inline-block bg-dark px-3 py-2 rounded">
                        <i class="fas fa-clock me-2"></i>
                        <span id="orario">--:--:--</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenuto principale -->
    <div class="container mt-4">
        <div class="row">
            <!-- Colonna 1: IN ATTESA -->
            <div class="col-md-4">
                <div class="stato-colonna">
                    <h3 class="colonna-titolo" id="in-attesa-titolo">
                        <i class="fas fa-clock me-2"></i>IN ATTESA
                        <span class="badge bg-warning ms-2" id="count-attesa">0</span>
                    </h3>
                    <div id="ordini-attesa">
                        <!-- Ordini in attesa appariranno qui -->
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-check-circle fa-2x mb-3"></i>
                            <p>Nessun ordine in attesa</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Colonna 2: IN PREPARAZIONE -->
            <div class="col-md-4">
                <div class="stato-colonna">
                    <h3 class="colonna-titolo" id="in-preparazione-titolo">
                        <i class="fas fa-blender me-2"></i>IN PREPARAZIONE
                        <span class="badge bg-primary ms-2" id="count-preparazione">0</span>
                    </h3>
                    <div id="ordini-preparazione">
                        <!-- Ordini in preparazione appariranno qui -->
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-utensils fa-2x mb-3"></i>
                            <p>Nessun ordine in preparazione</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Colonna 3: PRONTO -->
            <div class="col-md-4">
                <div class="stato-colonna">
                    <h3 class="colonna-titolo" id="pronto-titolo">
                        <i class="fas fa-bell me-2"></i>PRONTO
                        <span class="badge bg-success ms-2" id="count-pronto">0</span>
                    </h3>
                    <div id="ordini-pronto">
                        <!-- Ordini pronti appariranno qui -->
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-concierge-bell fa-2x mb-3"></i>
                            <p>Nessun ordine pronto</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pulsante aggiorna -->
    <button class="aggiorna-btn" onClick="caricaOrdini()" title="Aggiorna ordini">
        <i class="fas fa-sync-alt"></i>
    </button>

    <!-- Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variabili globali
        const API_BASE = '../api/';
        let tuttiOrdini = [];
        
        // Carica ordini all'avvio e ogni 30 secondi
        window.onload = function() {
            aggiornaOrario();
            caricaOrdini();
            setInterval(aggiornaOrario, 1000);
            setInterval(caricaOrdini, 5000); // Aggiorna ogni 5 secondi
			// Refresh visivo ogni secondo per i timer
            setInterval(aggiornaTimerOrdini, 1000);
        };
        
        // Funzione per aggiornare l'orario
        function aggiornaOrario() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('it-IT');
            document.getElementById('orario').textContent = timeString;
        }
        
        // Funzione per caricare gli ordini dall'API
        async function caricaOrdini() {
            try {
                const response = await fetch(API_BASE + 'ordini/ordini-cucina.php');
                const data = await response.json();
                
                if (data.success) {
                    tuttiOrdini = data.data;
                    mostraOrdiniPerStato(tuttiOrdini);
                    
                    // Aggiorna contatori
                    document.getElementById('count-attesa').textContent = 
                        data.data.filter(o => o.stato === 'attesa').length;
                    document.getElementById('count-preparazione').textContent = 
                        data.data.filter(o => o.stato === 'in_preparazione').length;
                    document.getElementById('count-pronto').textContent = 
                        data.data.filter(o => o.stato === 'pronto').length;
                }
            } catch (error) {
                console.error('Errore nel caricamento ordini:', error);
            }
        }
        
        // Funzione per mostrare gli ordini divisi per stato
        function mostraOrdiniPerStato(ordini) {
            const stati = {
                'attesa': document.getElementById('ordini-attesa'),
                'in_preparazione': document.getElementById('ordini-preparazione'),
                'pronto': document.getElementById('ordini-pronto')
            };
            
            // Svuota tutte le colonne
            Object.values(stati).forEach(colonna => {
                colonna.innerHTML = '';
            });
            
            if (ordini.length === 0) {
                Object.values(stati).forEach(colonna => {
                    colonna.innerHTML = `
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-check-circle fa-2x mb-3"></i>
                            <p>Nessun ordine</p>
                        </div>
                    `;
                });
                return;
            }
            
            // Raggruppa ordini per tavolo e sessione
            const ordiniRaggruppati = {};
            
            ordini.forEach(ordine => {
                const key = `${ordine.tavolo_id}-${ordine.session_id}`;
                if (!ordiniRaggruppati[key]) {
                    ordiniRaggruppati[key] = {
                        tavolo_id: ordine.tavolo_id,
                        session_id: ordine.session_id,
                        created_at: ordine.created_at,
                        stato: ordine.stato,
                        piatti: []
                    };
                }
                ordiniRaggruppati[key].piatti.push({
                    nome: ordine.piatto_nome,
                    quantita: ordine.quantita,
                    note: ordine.note
                });
            });
            
            // Mostra gli ordini raggruppati nelle colonne corrette
            Object.values(ordiniRaggruppati).forEach(ordine => {
                const tempoTrascorso = calcolaTempoTrascorso(ordine.created_at);
                
                const cardHTML = `
                    <div class="ordine-card" id="ordine-${ordine.tavolo_id}-${ordine.session_id}">
                        <div class="ordine-header">
                            <div>
                                <strong><i class="fas fa-table me-1"></i>TAVOLO ${ordine.tavolo_id}</strong>
                                <div class="mt-1">
                                    <span class="timer">${tempoTrascorso}</span>
                                    <span class="stato-badge stato-${ordine.stato.replace('_', '-')} ms-2">
                                        ${statoTestuale(ordine.stato)}
                                    </span>
                                </div>
                            </div>
                            <div class="text-end">
                                <small>${formattaData(ordine.created_at)}</small>
                            </div>
                        </div>
                        <div class="ordine-body">
                            ${ordine.piatti.map(piatto => `
                                <div class="piatto-item">
                                    <div>
                                        <strong>${piatto.nome}</strong>
                                        ${piatto.note ? `<br><small class="text-muted">Nota: ${piatto.note}</small>` : ''}
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-dark">x${piatto.quantita}</span>
                                    </div>
                                </div>
                            `).join('')}
                            <div class="mt-3 pt-2 border-top">
                                ${getPulsantiAzione(ordine.stato, ordine.tavolo_id, ordine.session_id)}
                            </div>
                        </div>
                    </div>
                `;
                
                // Aggiungi alla colonna corretta
                stati[ordine.stato].innerHTML += cardHTML;
            });
        }
        
        // Funzione per calcolare il tempo trascorso
        function calcolaTempoTrascorso(dataString) {
            const ordineTime = new Date(dataString);
            const now = new Date();
            const diffMs = now - ordineTime;
            const diffMins = Math.floor(diffMs / 60000);
            
            if (diffMins < 60) {
                return `${diffMins} min`;
            } else {
                const hours = Math.floor(diffMins / 60);
                const mins = diffMins % 60;
                return `${hours}h ${mins}m`;
            }
        }
        
        // Funzione per formattare la data
        function formattaData(dataString) {
            const date = new Date(dataString);
            return date.toLocaleTimeString('it-IT', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        }
        
        // Funzione per tradurre lo stato
        function statoTestuale(stato) {
            const traduzioni = {
                'attesa': 'IN ATTESA',
                'in_preparazione': 'IN PREPARAZIONE',
                'pronto': 'PRONTO',
                'consegnato': 'CONSEGNATO'
            };
            return traduzioni[stato] || stato;
        }
        
        // Funzione per generare i pulsanti azione
        function getPulsantiAzione(stato, tavoloId, sessionId) {
            switch(stato) {
                case 'attesa':
                    return `<button class="btn-azione btn-inizia w-100" 
                            onclick="cambiaStatoOrdine(${tavoloId}, '${sessionId}', 'in_preparazione')">
                            <i class="fas fa-play me-2"></i>INIZIA PREPARAZIONE
                        </button>`;
                
                case 'in_preparazione':
                    return `<button class="btn-azione btn-pronto w-100" 
                            onclick="cambiaStatoOrdine(${tavoloId}, '${sessionId}', 'pronto')">
                            <i class="fas fa-check me-2"></i>MARCHIA COME PRONTO
                        </button>`;
                
                case 'pronto':
                    return `<button class="btn-azione btn-consegnato w-100" 
                            onclick="cambiaStatoOrdine(${tavoloId}, '${sessionId}', 'consegnato')">
                            <i class="fas fa-check-double me-2"></i>CONSEGNATO AL CLIENTE
                        </button>`;
                
                default:
                    return '';
            }
        }
        
        // Funzione per cambiare stato ordine
        async function cambiaStatoOrdine(tavoloId, sessionId, nuovoStato) {
            if (!confirm(`Confermi di voler cambiare lo stato dell'ordine del Tavolo ${tavoloId} a "${statoTestuale(nuovoStato)}"?`)) {
                return;
            }
            
            try {
                const response = await fetch(API_BASE + 'ordini/cambia-stato-ordine.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        tavolo_id: tavoloId,
                        session_id: sessionId,
                        nuovo_stato: nuovoStato
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(`Stato ordine aggiornato a: ${statoTestuale(nuovoStato)}`);
                    caricaOrdini(); // Ricarica gli ordini
                } else {
                    alert('Errore: ' + data.error);
                }
            } catch (error) {
                alert('Errore di connessione: ' + error.message);
            }
        }
		// Funzione per aggiornare i timer in tempo reale
function aggiornaTimerOrdini() {
    document.querySelectorAll('.ordine-card').forEach(card => {
        const timerElement = card.querySelector('.timer');
        if (timerElement) {
            // Estrai l'ID ordine dal card
            const cardId = card.id.replace('ordine-', '');
            const [tavoloId, sessionId] = cardId.split('-');
            
            // Trova l'ordine corrispondente
            const ordine = tuttiOrdini.find(o => 
                o.tavolo_id == tavoloId && o.session_id == sessionId
            );
            
            if (ordine) {
                timerElement.textContent = calcolaTempoTrascorso(ordine.created_at);
            }
        }
    });
}
    </script>
</body>
</html>