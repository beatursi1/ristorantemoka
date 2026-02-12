<?php
require_once('../includes/auth.php');
require_once('../config/config.php');
checkAccess('admin');

$conn = getDbConnection();
$tavoli = $conn->query("SELECT * FROM tavoli ORDER BY numero ASC");
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Tavoli - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <h2>Gestione Struttura Tavoli</h2>
            <button id="btn-add-tavolo" class="btn btn-success"><i class="fas fa-plus"></i> Aggiungi</button>
        </div>

        <div class="row" id="tavoli-grid">
            <?php while($t = $tavoli->fetch_assoc()): ?>
            <div class="col-md-3 col-6 mb-4" id="tavolo-card-<?php echo $t['id']; ?>">
                <div class="card h-100 shadow-sm border-0 text-center p-3">
                    <i class="fas fa-table fa-3x mb-2 <?php echo $t['stato']=='libero'?'text-success':'text-danger'; ?>"></i>
                    <h5>Tavolo <?php echo $t['numero']; ?></h5>
                    <div class="mt-3">
                        <?php if($t['stato'] == 'libero'): ?>
                            <button class="btn btn-sm btn-outline-danger" onclick="eliminaTavolo(<?php echo $t['id']; ?>, <?php echo $t['numero']; ?>)">
                                <i class="fas fa-trash"></i> Elimina
                            </button>
                        <?php else: ?>
                            <span class="badge bg-danger">Occupato</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script>
    async function eliminaTavolo(id, numero) {
        if(!confirm(`Vuoi davvero eliminare il Tavolo ${numero}?\nL'azione è irreversibile.`)) return;
        
        const resp = await fetch('../api/tavoli/elimina-tavolo.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: id })
        });
        const res = await resp.json();
        
        if(res.success) {
            document.getElementById(`tavolo-row-${id}`)?.remove(); // se fosse tabella
            document.getElementById(`tavolo-card-${id}`)?.remove(); // per la griglia
        } else {
            alert("Errore: " + res.error);
        }
    }

    document.getElementById('btn-add-tavolo').addEventListener('click', async () => {
        const resp = await fetch('../api/tavoli/crea-tavolo.php', { method: 'POST' });
        if(resp.ok) location.reload();
    });
    </script>
</body>
</html>