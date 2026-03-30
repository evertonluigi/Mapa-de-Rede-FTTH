<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

$id = (int)($_GET['id'] ?? 0);
$ceo = $id ? $db->fetch("SELECT * FROM ceos WHERE id = ?", [$id]) : null;
$isEdit = $ceo !== null;
$postes = $db->fetchAll("SELECT id, codigo FROM postes WHERE status='ativo' ORDER BY codigo");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'codigo'       => $_POST['codigo'],
        'nome'         => $_POST['nome'] ?: null,
        'lat'          => (float)$_POST['lat'],
        'lng'          => (float)$_POST['lng'],
        'tipo'         => $_POST['tipo'],
        'capacidade_fo'=> (int)$_POST['capacidade_fo'],
        'poste_id'     => $_POST['poste_id'] ?: null,
        'fabricante'   => $_POST['fabricante'] ?: null,
        'modelo'       => $_POST['modelo'] ?: null,
        'status'       => $_POST['status'],
        'observacoes'  => $_POST['observacoes'] ?? '',
    ];
    if ($isEdit) {
        $db->update('ceos', $data, 'id = ?', [$id]);
        AuditLog::log('editar', 'ceos', $id, 'CEO '.$data['codigo'].' editada', $ceo, $data);
    } else {
        $data['created_by'] = Auth::user()['id'];
        $newId = $db->insert('ceos', $data);
        AuditLog::log('criar', 'ceos', $newId, 'CEO '.$data['codigo'].' criada', [], $data);
    }
    header('Location: ' . BASE_URL . '/modules/ceos/index.php?saved=1'); exit;
}

$pageTitle = $isEdit ? 'Editar CEO' : 'Nova CEO';
$activePage = 'ceos';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-content">
    <div class="page-header">
        <div><h2><i class="fas fa-box" style="color:#9933ff"></i> <?= $isEdit ? 'Editar CEO: '.e($ceo['codigo']) : 'Nova CEO' ?></h2></div>
        <a href="<?= BASE_URL ?>/modules/ceos/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group"><label class="form-label">Código *</label>
                        <input class="form-control" name="codigo" required value="<?= e($ceo['codigo'] ?? '') ?>" placeholder="CEO-001"></div>
                    <div class="form-group"><label class="form-label">Nome</label>
                        <input class="form-control" name="nome" value="<?= e($ceo['nome'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Capacidade FO</label>
                        <select class="form-control" name="capacidade_fo">
                            <?php foreach([12,24,36,48,72,96,144,288] as $cap): ?>
                            <option value="<?= $cap ?>" <?= ($ceo['capacidade_fo']??24)==$cap?'selected':'' ?>><?= $cap ?> fibras</option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Tipo</label>
                        <select class="form-control" name="tipo">
                            <?php foreach(['aerea','subterranea','pedestal','interna'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($ceo['tipo']??'aerea')===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Poste Vinculado</label>
                        <select class="form-control" name="poste_id">
                            <option value="">Nenhum</option>
                            <?php foreach($postes as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($ceo['poste_id']??'')==$p['id']?'selected':'' ?>><?= e($p['codigo']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Status</label>
                        <select class="form-control" name="status">
                            <?php foreach(['ativo','inativo','manutencao'] as $s): ?>
                            <option value="<?= $s ?>" <?= ($ceo['status']??'ativo')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Fabricante</label>
                        <input class="form-control" name="fabricante" value="<?= e($ceo['fabricante'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Modelo</label>
                        <input class="form-control" name="modelo" value="<?= e($ceo['modelo'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Latitude *</label>
                        <input class="form-control" name="lat" id="lat" required value="<?= e($ceo['lat'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Longitude *</label>
                        <input class="form-control" name="lng" id="lng" required value="<?= e($ceo['lng'] ?? '') ?>"></div>
                    <div class="form-group full"><label class="form-label">Observações</label>
                        <textarea class="form-control" name="observacoes" rows="3"><?= e($ceo['observacoes'] ?? '') ?></textarea></div>
                </div>
                <div style="display:flex;gap:10px;margin-top:12px">
                    <a href="<?= BASE_URL ?>/modules/ceos/index.php" class="btn btn-secondary">Cancelar</a>
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
const map = L.map('minimap').setView([<?= json_encode($ceo['lat'] ?? -27.59) ?>, <?= json_encode($ceo['lng'] ?? -48.55) ?>], 16);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
let marker = <?= $isEdit ? 'L.marker(['.e($ceo['lat']).','.e($ceo['lng']).']).addTo(map)' : 'null' ?>;
<?php if ($isEdit): ?>marker.on('dragend', e => { const p=e.target.getLatLng(); document.getElementById('lat').value=p.lat.toFixed(8); document.getElementById('lng').value=p.lng.toFixed(8); });<?php endif; ?>
map.on('click', function(e) {
    if (marker) map.removeLayer(marker);
    marker = L.marker([e.latlng.lat, e.latlng.lng], {draggable:true}).addTo(map);
    marker.on('dragend', ev => { const p=ev.target.getLatLng(); document.getElementById('lat').value=p.lat.toFixed(8); document.getElementById('lng').value=p.lng.toFixed(8); });
    document.getElementById('lat').value = e.latlng.lat.toFixed(8);
    document.getElementById('lng').value = e.latlng.lng.toFixed(8);
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
