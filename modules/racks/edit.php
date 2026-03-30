<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

$id = (int)($_GET['id'] ?? 0);
$rack = $id ? $db->fetch("SELECT * FROM racks WHERE id = ?", [$id]) : null;
$isEdit = $rack !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo'])) {
    $data = [
        'codigo'      => $_POST['codigo'],
        'nome'        => $_POST['nome'] ?: null,
        'localizacao' => $_POST['localizacao'] ?: null,
        'lat'         => $_POST['lat'] !== '' ? (float)$_POST['lat'] : null,
        'lng'         => $_POST['lng'] !== '' ? (float)$_POST['lng'] : null,
        'status'      => $_POST['status'],
        'observacoes' => $_POST['observacoes'] ?: null,
    ];
    if ($isEdit) {
        $db->update('racks', $data, 'id = ?', [$id]);
        AuditLog::log('editar', 'racks', $id, 'Rack '.$data['codigo'].' editado', $rack, $data);
    } else {
        $data['created_by'] = Auth::user()['id'];
        $id = $db->insert('racks', $data);
        AuditLog::log('criar', 'racks', $id, 'Rack '.$data['codigo'].' criado', [], $data);
    }
    header('Location: ' . BASE_URL . '/modules/racks/view.php?id=' . $id . '&saved=1'); exit;
}

$pageTitle = $isEdit ? 'Editar Rack' : 'Novo Rack';
$activePage = 'racks';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-content">
    <div class="page-header">
        <div>
            <h2><i class="fas fa-server" style="color:#aa6600"></i> <?= $isEdit ? 'Editar Rack: '.e($rack['codigo']) : 'Novo Rack' ?></h2>
        </div>
        <a href="<?= BASE_URL ?>/modules/racks/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Código *</label>
                        <input class="form-control" name="codigo" required value="<?= e($rack['codigo'] ?? '') ?>" placeholder="RACK-01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nome</label>
                        <input class="form-control" name="nome" value="<?= e($rack['nome'] ?? '') ?>" placeholder="Rack Principal">
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Localização</label>
                        <input class="form-control" name="localizacao" value="<?= e($rack['localizacao'] ?? '') ?>" placeholder="Data Center / Sala técnica">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="status">
                            <?php foreach(['ativo'=>'Ativo','inativo'=>'Inativo','manutencao'=>'Manutenção'] as $v=>$l): ?>
                            <option value="<?= $v ?>" <?= ($rack['status']??'ativo')===$v?'selected':'' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Latitude</label>
                        <input class="form-control" name="lat" id="lat" value="<?= e($rack['lat'] ?? '') ?>" placeholder="-27.5954">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Longitude</label>
                        <input class="form-control" name="lng" id="lng" value="<?= e($rack['lng'] ?? '') ?>" placeholder="-48.5480">
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Observações</label>
                        <textarea class="form-control" name="observacoes" rows="3"><?= e($rack['observacoes'] ?? '') ?></textarea>
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-top:12px">
                    <a href="<?= BASE_URL ?>/modules/racks/index.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                </div>
            </form>
        </div>

        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden;min-height:420px">
            <div id="minimap" style="height:100%;min-height:420px"></div>
        </div>
    </div>
</div>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('minimap').setView([<?= json_encode($rack['lat'] ?? -27.59) ?>, <?= json_encode($rack['lng'] ?? -48.55) ?>], 14);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
let marker = <?= $isEdit && $rack['lat'] ? 'L.marker(['.(float)$rack['lat'].','.(float)$rack['lng'].']).addTo(map)' : 'null' ?>;
map.on('click', function(e) {
    if (marker) map.removeLayer(marker);
    marker = L.marker([e.latlng.lat, e.latlng.lng], {draggable:true}).addTo(map);
    marker.on('dragend', ev => {
        const p = ev.target.getLatLng();
        document.getElementById('lat').value = p.lat.toFixed(8);
        document.getElementById('lng').value = p.lng.toFixed(8);
    });
    document.getElementById('lat').value = e.latlng.lat.toFixed(8);
    document.getElementById('lng').value = e.latlng.lng.toFixed(8);
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
