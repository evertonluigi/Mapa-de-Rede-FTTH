<?php
$db_local = null;
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

$id = (int)($_GET['id'] ?? 0);
$poste = $id ? $db->fetch("SELECT * FROM postes WHERE id = ?", [$id]) : null;
$isEdit = $poste !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'codigo'       => $_POST['codigo'],
        'lat'          => (float)$_POST['lat'],
        'lng'          => (float)$_POST['lng'],
        'tipo'         => $_POST['tipo'],
        'altura_m'     => $_POST['altura_m'] ?: null,
        'proprietario' => $_POST['proprietario'] ?: 'Próprio',
        'status'       => $_POST['status'],
        'observacoes'  => $_POST['observacoes'] ?? '',
    ];

    if ($isEdit) {
        $db->update('postes', $data, 'id = ?', [$id]);
        AuditLog::log('editar', 'postes', $id, 'Poste '.$data['codigo'].' editado', $poste, $data);
    } else {
        $data['created_by'] = Auth::user()['id'];
        $newId = $db->insert('postes', $data);
        AuditLog::log('criar', 'postes', $newId, 'Poste '.$data['codigo'].' criado', [], $data);
    }
    header('Location: ' . BASE_URL . '/modules/postes/index.php?saved=1'); exit;
}

$pageTitle = $isEdit ? 'Editar Poste' : 'Novo Poste';
$activePage = 'postes';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-content">
    <div class="page-header">
        <div>
            <h2><i class="fas fa-border-all" style="color:#aaa"></i> <?= $isEdit ? 'Editar Poste: '.e($poste['codigo']) : 'Novo Poste' ?></h2>
        </div>
        <a href="<?= BASE_URL ?>/modules/postes/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
        <!-- Form -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Código *</label>
                        <input class="form-control" name="codigo" required value="<?= e($poste['codigo'] ?? '') ?>" placeholder="PST-001">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="status">
                            <?php foreach(['ativo','inativo','danificado'] as $s): ?>
                            <option value="<?= $s ?>" <?= ($poste['status']??'ativo')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo</label>
                        <select class="form-control" name="tipo">
                            <?php foreach(['concreto','madeira','metalico','outro'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($poste['tipo']??'concreto')===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Altura (m)</label>
                        <input class="form-control" name="altura_m" type="number" step="0.1" value="<?= e($poste['altura_m'] ?? '') ?>" placeholder="11">
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Proprietário</label>
                        <input class="form-control" name="proprietario" value="<?= e($poste['proprietario'] ?? 'Próprio') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Latitude *</label>
                        <input class="form-control" name="lat" id="lat" required value="<?= e($poste['lat'] ?? '') ?>" placeholder="-27.59">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Longitude *</label>
                        <input class="form-control" name="lng" id="lng" required value="<?= e($poste['lng'] ?? '') ?>" placeholder="-48.55">
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Observações</label>
                        <textarea class="form-control" name="observacoes" rows="3"><?= e($poste['observacoes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div style="margin-top:12px;font-size:13px;color:var(--text-muted);margin-bottom:16px">
                    <i class="fas fa-info-circle" style="color:var(--primary)"></i>
                    Clique no mapa ao lado para definir as coordenadas automaticamente.
                </div>

                <div style="display:flex;gap:10px">
                    <a href="<?= BASE_URL ?>/modules/postes/index.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                </div>
            </form>
        </div>

        <!-- Mini Map -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden;min-height:420px">
            <div id="minimap" style="height:100%;min-height:420px"></div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const initLat = <?= json_encode($poste['lat'] ?? -27.59) ?>;
const initLng = <?= json_encode($poste['lng'] ?? -48.55) ?>;

const map = L.map('minimap').setView([initLat, initLng], 16);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

let marker = null;
<?php if ($isEdit): ?>
marker = L.marker([initLat, initLng], { draggable: true }).addTo(map);
marker.on('dragend', updateCoords);
<?php endif; ?>

map.on('click', function(e) {
    const { lat, lng } = e.latlng;
    if (marker) map.removeLayer(marker);
    marker = L.marker([lat, lng], { draggable: true }).addTo(map);
    marker.on('dragend', updateCoords);
    document.getElementById('lat').value = lat.toFixed(8);
    document.getElementById('lng').value = lng.toFixed(8);
});

function updateCoords(e) {
    const pos = e.target.getLatLng();
    document.getElementById('lat').value = pos.lat.toFixed(8);
    document.getElementById('lng').value = pos.lng.toFixed(8);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
