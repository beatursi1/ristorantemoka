<?php
// inizializza.php
// ==================== CONFIGURAZIONE ====================
session_start();

// Controllo accesso cameriere
if (!isset($_SESSION['cameriere_id'])) {
    header('Location: login.php');
    exit;
}

// Connessione database
require_once('../config/config.php');
$conn = getDbConnection();
if (!$conn) {
    die("Errore di connessione al database");
}

// Ottieni info cameriere
$cameriere_id = $_SESSION['cameriere_id'];
$stmt = $conn->prepare("SELECT nome, codice FROM camerieri WHERE id = ?");
$stmt->bind_param("i", $cameriere_id);
$stmt->execute();
$cameriere = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Ottieni tavoli
$tavoli = $conn->query("SELECT id, numero, stato FROM tavoli ORDER BY numero");
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inizializza Tavolo - RistoranteMoka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/ristorantemoka/app/inizializza/css/inizializza.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body data-cameriere-id="<?php echo $cameriere_id; ?>">
    <!-- HEADER -->
    <div class="header mb-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-8">
                    <h1 class="h4 mb-0"><i class="fas fa-users-cog me-2"></i>Inizializza Tavolo</h1>
                    <small>Cameriere: <?php echo htmlspecialchars($cameriere['nome']); ?> (<?php echo $cameriere['codice']; ?>)</small>
                </div>
                <div class="col-4 text-end">
                    <button id="btn-new-table" class="btn btn-primary btn-sm me-2" type="button" title="Crea un nuovo tavolo">
                        <i class="fas fa-plus me-1"></i>Nuovo tavolo
                    </button>
                    <a href="logout.php" class="btn btn-light btn-sm">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- FASE 1: SELEZIONE TAVOLO -->
        <div class="card mb-4" id="fase1">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-table me-2"></i>1. Seleziona Tavolo</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Clicca su un tavolo libero per iniziare l'inizializzazione.</p>
                <div class="row" id="tavoli-container">
                    <?php while($tavolo = $tavoli->fetch_assoc()): ?>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="card tavolo-card text-center p-3 
                            <?php echo $tavolo['stato'] == 'libero' ? 'tavolo-libero' : 'tavolo-occupato'; ?> tavolo-selectable"
                            data-id="<?php echo $tavolo['id']; ?>"
                            data-numero="<?php echo $tavolo['numero']; ?>"
                            data-stato="<?php echo $tavolo['stato']; ?>">
                            <i class="fas fa-table fa-3x mb-2"></i>
                            <h5 class="card-title">Tavolo <?php echo $tavolo['numero']; ?></h5>
                            <span class="badge 
                                <?php echo $tavolo['stato'] == 'libero' ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo ucfirst($tavolo['stato']); ?>
                            </span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- FASE 2: QR CODE E MONITOR CLIENTI (nascosta inizialmente) -->
        <div class="card mb-4" id="fase2" style="display: none;">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-qrcode me-2"></i>2. QR Code e Monitor - Tavolo <span id="tavolo-numero-selezionato"></span>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- COLONNA SINISTRA: QR CODE -->
                    <div class="col-md-6">
                        <div class="border rounded p-4 bg-light text-center">
                            <h5><i class="fas fa-qrcode me-2"></i>QR Code del Tavolo</h5>
                            <div id="qr-code-container" class="my-3">
                                <p class="text-muted">Il QR code apparirà qui</p>
                            </div>
                          </div>
                        
                        <!-- MONITOR CLIENTI REGISTRATI -->
                        <div class="monitor-clienti mt-4">
                            <h5><i class="fas fa-users me-2"></i>Clienti Registrati</h5>
                            <div id="clienti-registrati-container">
                                <p class="text-muted"><em>Nessun cliente registrato ancora</em></p>
                            </div>
                            <div class="mt-3">
                                <button class="btn btn-sm btn-primary" id="btn-aggiorna-clienti">
                                    <i class="fas fa-sync-alt me-1"></i>Aggiorna lista
                                </button>
                                <button class="btn btn-sm btn-outline-danger" id="btn-resetta-clienti">
                                    <i class="fas fa-trash-alt me-1"></i>Resetta clienti
                                </button>
                                <button class="btn btn-sm btn-warning" id="btn-libera-tavolo">
                                    <i class="fas fa-door-open me-1"></i>Libera Tavolo
                                </button>
                                <button class="btn btn-sm btn-outline-info" id="btn-ordina-cliente">
                                    <i class="fas fa-utensils me-1"></i>Ordina per il cliente
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- COLONNA DESTRA: CONFIGURAZIONE CLIENTI (per riferimento cameriere) -->
                    <div class="col-md-6">
                        <h5><i class="fas fa-cog me-2"></i>Configurazione Clienti (Riferimento)</h5>
                        <p class="text-muted small mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            Questa sezione mostra la configurazione dei clienti. 
                            Le lettere verranno assegnate automaticamente quando i clienti scansionano il QR code.
                        </p>
                        
                        <!-- Clienti standard A-F (solo visualizzazione) -->
                        <div class="row mb-4" id="clienti-container">
                            <?php 
                            $lettere = ['A', 'B', 'C', 'D', 'E', 'F'];
                            $colori = ['badge-a', 'badge-b', 'badge-c', 'badge-d', 'badge-e', 'badge-f'];
                            foreach($lettere as $index => $lettera): 
                            ?>
                            <div class="col-md-2 col-4 mb-3 text-center">
                                <div class="cliente-badge <?php echo $colori[$index]; ?>" 
                                     data-lettera="<?php echo $lettera; ?>"
                                     id="badge-<?php echo $lettera; ?>">
                                    <?php echo $lettera; ?>
                                </div>
                                <div class="mt-1 small">
                                    <span id="nome-<?php echo $lettera; ?>" class="text-muted">Libero</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pulsante per cliente extra -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <button class="btn btn-outline-secondary btn-sm" id="btn-aggiungi-cliente-extra">
                                    <i class="fas fa-plus me-1"></i>Aggiungi cliente extra (G-Z)
                                </button>
                            </div>
                        </div>
                        
                        <!-- Container per clienti extra -->
                        <div id="clienti-extra-container" class="row mb-4">
                            <!-- Clienti extra aggiunti dinamicamente appariranno qui -->
                        </div>
                    </div>
                </div>
                
                <!-- NAVIGAZIONE ALLA FASE 3 -->
                <div class="text-center mt-4">
                    <button class="btn btn-warning btn-lg" id="btn-vai-fase3">
                        <i class="fas fa-wine-glass-alt me-2"></i>VAI ALLE BEVANDE INIZIALI
                    </button>
                    <p class="text-muted small mt-2">
                        Procedi dopo che i clienti si sono registrati
                    </p>
                </div>
            </div>
        </div>

        <!-- FASE 3: BEVANDE INIZIALI (nascosta) -->
        <div class="card mb-4" id="fase3" style="display: none;">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-wine-glass-alt me-2"></i>3. Bevande Iniziali (Opzionale)</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- ACQUA -->
                    <div class="col-md-6 mb-3">
                        <h6><i class="fas fa-tint me-2 text-primary"></i>Acqua per il tavolo</h6>
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <button class="btn btn-outline-primary btn-lg" id="btn-acqua-meno">
                                <i class="fas fa-minus"></i>
                            </button>
                            <div class="mx-4 text-center">
                                <div class="h2 mb-0" id="contatore-acqua">0</div>
                                <small class="text-muted">bottiglie da 1L</small>
                            </div>
                            <button class="btn btn-outline-primary btn-lg" id="btn-acqua-piu">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <small class="text-muted">
                            Prezzo totale: €<span id="prezzo-acqua">0.00</span> 
                            (€<span id="prezzo-acqua-per-persona">0.00</span> a testa)
                        </small>
                    </div>
                    
                    <!-- ALTRE BEVANDE -->
                    <div class="col-md-6">
                        <h6><i class="fas fa-wine-bottle me-2 text-success"></i>Altre Bevande</h6>
                        <button class="btn btn-outline-success btn-sm mb-3" id="btn-apri-bevanda">
                            <i class="fas fa-plus me-1"></i>Aggiungi bevanda
                        </button>
                        
                        <!-- Lista bevande aggiunte -->
                        <div id="lista-bevande">
                            <p class="text-muted"><em>Nessuna bevanda aggiunta</em></p>
                        </div>
                    </div>
                </div>
                
                <!-- NAVIGAZIONE ALLA FASE 4 -->
                <div class="text-center mt-4">
                    <button class="btn btn-dark btn-lg" id="btn-vai-fase4">
                        <i class="fas fa-check-circle me-2"></i>VAI ALLA CONFERMA FINALE
                    </button>
                </div>
            </div>
        </div>

        <!-- FASE 4: CONFERMA E FINALIZZAZIONE (nascosta) -->
        <div class="card mb-4" id="fase4" style="display: none;">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>4. Conferma e Finalizza</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Riepilogo Configurazione</h5>
                        <div id="riepilogo-clienti" class="mb-3">
                            <p><em>Caricamento clienti...</em></p>
                        </div>
                        <div id="riepilogo-bevande" class="mb-3">
                            <p><em>Nessuna bevanda</em></p>
                        </div>
                        <button class="btn btn-success btn-lg" id="btn-conferma">
                            <i class="fas fa-play-circle me-2"></i>CONFERMA E ATTIVA TAVOLO
                        </button>
                    </div>
                    <div class="col-md-6 text-center">
                        <div id="qr-code-finale" class="border p-4 rounded bg-light">
                            <h5>QR Code Attivo</h5>
                            <div id="qr-code-finale-container">
                                <p class="text-muted"><i class="fas fa-qrcode fa-3x"></i></p>
                                <p class="text-muted">Il QR code è già attivo dalla Fase 2</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal per aggiungere cliente extra -->
    <div class="modal fade" id="modal-cliente-extra" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Aggiungi Cliente Extra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Lettera identificativa</label>
                        <select class="form-select" id="select-lettera-extra">
                            <?php 
                            $lettereExtra = range('G', 'Z');
                            foreach($lettereExtra as $lettera): ?>
                            <option value="<?php echo $lettera; ?>"><?php echo $lettera; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nome (opzionale)</label>
                        <input type="text" class="form-control" id="nome-cliente-extra" 
                               placeholder="Es: Marco, Anna...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" id="btn-salva-cliente-extra">Aggiungi</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal per aggiungere bevanda -->
    <div class="modal fade" id="modal-bevanda" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-wine-bottle me-2"></i>Aggiungi Bevanda</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Seleziona bevanda</label>
                        <select class="form-select" id="select-bevanda">
                            <option value="8" data-prezzo="18.00" data-nome="Vino rosso">Vino rosso (bottiglia) - €18.00</option>
                            <option value="9" data-prezzo="16.00" data-nome="Vino bianco">Vino bianco (bottiglia) - €16.00</option>
                            <option value="7" data-prezzo="5.00" data-nome="Birra artigianale">Birra artigianale 0.5L - €5.00</option>
                            <option value="10" data-prezzo="3.00" data-nome="Coca Cola">Coca Cola 0.33L - €3.00</option>
                            <option value="11" data-prezzo="2.50" data-nome="Acqua frizzante">Acqua frizzante 1L - €2.50</option>
                            <option value="12" data-prezzo="4.00" data-nome="Succo di frutta">Succo di frutta - €4.00</option>
                            <option value="13" data-prezzo="1.50" data-nome="Caffè">Caffè - €1.50</option>
                            <option value="14" data-prezzo="6.00" data-nome="Grappa">Grappa - €6.00</option>
                            <option value="15" data-prezzo="5.00" data-nome="Limoncello">Limoncello - €5.00</option>
                        </select>
                    </div>
                    
                    <!-- CONTATORE QUANTITÀ -->
                    <div class="mb-3">
                        <label class="form-label">Quantità</label>
                        <div class="contatore-quantita">
                            <button class="btn btn-outline-secondary" id="btn-bevanda-meno">
                                <i class="fas fa-minus"></i>
                            </button>
                            <div class="text-center">
                                <span class="h4 mb-0" id="quantita-bevanda">1</span>
                            </div>
                            <button class="btn btn-outline-secondary" id="btn-bevanda-piu">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="text-center mt-2">
                            <small class="text-muted">
                                Totale: €<span id="prezzo-totale-bevanda">18.00</span>
                            </small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Condivisione</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="condivisione-bevanda" id="cond-personale" value="personale" checked>
                            <label class="form-check-label" for="cond-personale">
                                <i class="fas fa-user me-1"></i> Solo per chi ordina
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="condivisione-bevanda" id="cond-tavolo" value="tavolo">
                            <label class="form-check-label" for="cond-tavolo">
                                <i class="fas fa-users me-1"></i> Diviso per tutto il tavolo
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="condivisione-bevanda" id="cond-parziale" value="parziale">
                            <label class="form-check-label" for="cond-parziale">
                                <i class="fas fa-user-friends me-1"></i> Scegli chi partecipa
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="selettore-partecipanti" style="display: none;">
                        <label class="form-label">Seleziona partecipanti</label>
                        <div id="checkbox-partecipanti" class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                            <!-- Checkbox appariranno qui dinamicamente -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" id="btn-salva-bevanda">
                        <i class="fas fa-plus me-1"></i>Aggiungi al tavolo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal per selezionare cliente manuale -->
    <div class="modal fade" id="modal-seleziona-cliente" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-utensils me-2"></i>Ordina per il cliente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Seleziona un cliente per ordinare al posto suo:</p>
                    <div id="lista-clienti-manuali" class="row">
                        <!-- Clienti appariranno qui -->
                    </div>
                    <div id="nessun-cliente-manuale" class="text-center py-4" style="display: none;">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Nessun cliente manuale configurato per questo tavolo.</p>
                        <p class="small">Clicca su un badge (A-F) per aggiungere un cliente manuale.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPT -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
	<script src="/ristorantemoka/app/inizializza/js/modal-accessibility.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

   <!-- Moduli JavaScript separati -->
<script src="/ristorantemoka/app/inizializza/js/api-service.js"></script>
<script src="/ristorantemoka/app/inizializza/js/tavolo-manager.js"></script>
<script src="/ristorantemoka/app/inizializza/js/cliente-manager.js"></script>
<script src="/ristorantemoka/app/inizializza/js/cliente-badge-manager.js"></script>
<!-- Nuovi moduli per refactor -->
<script src="/ristorantemoka/app/inizializza/js/cliente-config-manager.js"></script>
<script src="/ristorantemoka/app/inizializza/js/cliente-monitor-manager.js"></script>
<script src="/ristorantemoka/app/inizializza/js/bevande-manager.js"></script>
<script src="/ristorantemoka/app/inizializza/js/inizializza-main.js"></script>
<script src="/ristorantemoka/app/inizializza/js/nuovo-tavolo.js"></script>

</body>
</html>
<?php $conn->close(); ?>