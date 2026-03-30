<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

$id = (int)($_GET['id'] ?? 0);
$olt = $id ? $db->fetch("SELECT * FROM olts WHERE id = ?", [$id]) : null;
$isEdit = $olt !== null;

// Handle PON save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pon'])) {
    $ponData = [
        'olt_id'      => (int)$_POST['olt_id_pon'],
        'numero_pon'  => (int)$_POST['numero_pon'],
        'descricao'   => $_POST['descricao_pon'] ?: null,
        'status'      => $_POST['status_pon'],
    ];
    if ($_POST['pon_id']) {
        $db->update('olt_pons', $ponData, 'id = ?', [(int)$_POST['pon_id']]);
        AuditLog::log('editar', 'olt_pons', (int)$_POST['pon_id'], 'PON '.$ponData['numero_pon'].' editada');
    } else {
        $newPonId = $db->insert('olt_pons', $ponData);
        AuditLog::log('criar', 'olt_pons', $newPonId, 'PON '.$ponData['numero_pon'].' criada', [], $ponData);
    }
    header('Location: ' . BASE_URL . '/modules/olts/view.php?id='.$_POST['olt_id_pon'].'&saved=1'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pon'])) {
    $ponId = (int)$_POST['pon_id'];
    $oldPon = $db->fetch("SELECT * FROM olt_pons WHERE id=?", [$ponId]) ?? [];
    $db->query("DELETE FROM olt_pons WHERE id = ?", [$ponId]);
    AuditLog::log('deletar', 'olt_pons', $ponId, 'PON '.(($oldPon['numero_pon']??$ponId)).' removida', $oldPon);
    header('Location: ' . BASE_URL . '/modules/olts/view.php?id='.$id.'&deleted=1'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nome'])) {
    $data = [
        'codigo'      => $_POST['codigo'],
        'nome'        => $_POST['nome'],
        'ip_gerencia' => $_POST['ip_gerencia'] ?: null,
        'fabricante'  => $_POST['fabricante'] ?: null,
        'modelo'      => $_POST['modelo'] ?: null,
        'firmware'    => $_POST['firmware'] ?: null,
        'localizacao' => $_POST['localizacao'] ?: null,
        'lat'         => $_POST['lat'] ? (float)$_POST['lat'] : null,
        'lng'         => $_POST['lng'] ? (float)$_POST['lng'] : null,
        'status'      => $_POST['status'],
        'observacoes' => $_POST['observacoes'] ?? '',
        'rack_id'     => $_POST['rack_id'] ? (int)$_POST['rack_id'] : null,
    ];
    if ($isEdit) {
        $db->update('olts', $data, 'id = ?', [$id]);
        AuditLog::log('editar', 'olts', $id, 'OLT '.$data['codigo'].' editada', $olt, $data);
    } else {
        $data['created_by'] = Auth::user()['id'];
        $id = $db->insert('olts', $data);
        AuditLog::log('criar', 'olts', $id, 'OLT '.$data['codigo'].' criada', [], $data);
    }
    header('Location: ' . BASE_URL . '/modules/olts/view.php?id='.$id.'&saved=1'); exit;
}

$racks = $db->fetchAll("SELECT id, codigo, nome FROM racks ORDER BY codigo ASC");
$pageTitle = $isEdit ? 'Editar OLT' : 'Nova OLT';
$activePage = 'olts';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-content">
    <div class="page-header">
        <div><h2><i class="fas fa-server" style="color:#ff6600"></i> <?= $isEdit ? 'Editar OLT: '.e($olt['nome']) : 'Nova OLT' ?></h2></div>
        <a href="<?= BASE_URL ?>/modules/olts/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group"><label class="form-label">Código *</label>
                        <input class="form-control" name="codigo" required value="<?= e($olt['codigo'] ?? '') ?>" placeholder="OLT-001"></div>
                    <div class="form-group"><label class="form-label">Nome *</label>
                        <input class="form-control" name="nome" required value="<?= e($olt['nome'] ?? '') ?>" placeholder="OLT Principal"></div>
                    <div class="form-group"><label class="form-label">IP de Gerência</label>
                        <input class="form-control" name="ip_gerencia" value="<?= e($olt['ip_gerencia'] ?? '') ?>" placeholder="192.168.1.1"></div>
                    <div class="form-group"><label class="form-label">Fabricante</label>
                        <select class="form-control" name="fabricante">
                            <option value="">Selecione...</option>
                            <?php foreach(['Huawei','ZTE','Fiberhome','Nokia','Datacom','Intelbras','Outros'] as $f): ?>
                            <option value="<?= $f ?>" <?= ($olt['fabricante']??'')===$f?'selected':'' ?>><?= $f ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Modelo</label>
                        <input class="form-control" name="modelo" value="<?= e($olt['modelo'] ?? '') ?>" placeholder="MA5800-X7"></div>
                    <div class="form-group"><label class="form-label">Firmware</label>
                        <input class="form-control" name="firmware" value="<?= e($olt['firmware'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Localização</label>
                        <input class="form-control" name="localizacao" value="<?= e($olt['localizacao'] ?? '') ?>" placeholder="Data Center / Sala técnica"></div>
                    <div class="form-group"><label class="form-label">Status</label>
                        <select class="form-control" name="status">
                            <?php foreach(['ativo','inativo','manutencao','defeito'] as $s): ?>
                            <option value="<?= $s ?>" <?= ($olt['status']??'ativo')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Latitude</label>
                        <input class="form-control" name="lat" id="lat" value="<?= e($olt['lat'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Longitude</label>
                        <input class="form-control" name="lng" id="lng" value="<?= e($olt['lng'] ?? '') ?>"></div>
                    <div class="form-group full"><label class="form-label">Rack</label>
                        <select class="form-control" name="rack_id">
                            <option value="">— Nenhum —</option>
                            <?php foreach($racks as $rk): ?>
                            <option value="<?= $rk['id'] ?>" <?= ($olt['rack_id']??'')==$rk['id']?'selected':'' ?>><?= e($rk['codigo']) ?><?= $rk['nome'] ? ' — '.$rk['nome'] : '' ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group full"><label class="form-label">Observações</label>
                        <textarea class="form-control" name="observacoes" rows="3"><?= e($olt['observacoes'] ?? '') ?></textarea></div>
                </div>
                <div style="display:flex;gap:10px;margin-top:12px">
                    <a href="<?= BASE_URL ?>/modules/olts/index.php" class="btn btn-secondary">Cancelar</a>
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
const map = L.map('minimap').setView([<?= json_encode($olt['lat'] ?? -27.59) ?>, <?= json_encode($olt['lng'] ?? -48.55) ?>], 14);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
let marker = <?= $isEdit && $olt['lat'] ? 'L.marker(['.e($olt['lat']).','.e($olt['lng']).']).addTo(map)' : 'null' ?>;
map.on('click', function(e) {
    if (marker) map.removeLayer(marker);
    marker = L.marker([e.latlng.lat, e.latlng.lng], {draggable:true}).addTo(map);
    marker.on('dragend', ev => { const p=ev.target.getLatLng(); document.getElementById('lat').value=p.lat.toFixed(8); document.getElementById('lng').value=p.lng.toFixed(8); });
    document.getElementById('lat').value = e.latlng.lat.toFixed(8);
    document.getElementById('lng').value = e.latlng.lng.toFixed(8);
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
