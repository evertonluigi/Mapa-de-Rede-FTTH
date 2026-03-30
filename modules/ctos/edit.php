<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

$id = (int)($_GET['id'] ?? 0);
$cto = $id ? $db->fetch("SELECT * FROM ctos WHERE id = ?", [$id]) : null;
$isEdit = $cto !== null;
$postes = $db->fetchAll("SELECT id, codigo FROM postes WHERE status='ativo' ORDER BY codigo");
$olt_pons = $db->fetchAll(
    "SELECT op.id, CONCAT(o.nome,' — Slot ',op.slot,' / PON ',op.numero_pon) as label
     FROM olt_pons op JOIN olts o ON o.id = op.olt_id
     WHERE op.status = 'ativo' ORDER BY o.nome, op.slot, op.numero_pon"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'codigo' => $_POST['codigo'],
        'nome' => $_POST['nome'] ?: null,
        'lat' => (float)$_POST['lat'],
        'lng' => (float)$_POST['lng'],
        'tipo' => $_POST['tipo'],
        'capacidade_portas' => (int)$_POST['capacidade_portas'],
        'olt_pon_id' => $_POST['olt_pon_id'] ?: null,
        'poste_id' => $_POST['poste_id'] ?: null,
        'fabricante' => $_POST['fabricante'] ?: null,
        'modelo' => $_POST['modelo'] ?: null,
        'status' => $_POST['status'],
        'observacoes' => $_POST['observacoes'] ?? '',
    ];
    if ($isEdit) {
        $db->update('ctos', $data, 'id = ?', [$id]);
        AuditLog::log('editar', 'ctos', $id, 'CTO '.$data['codigo'].' editada', $cto, $data);
    } else {
        $data['created_by'] = Auth::user()['id'];
        $newId = $db->insert('ctos', $data);
        AuditLog::log('criar', 'ctos', $newId, 'CTO '.$data['codigo'].' criada', [], $data);
    }
    header('Location: ' . BASE_URL . '/modules/ctos/index.php?saved=1'); exit;
}

$pageTitle = $isEdit ? 'Editar CTO' : 'Nova CTO';
$activePage = 'ctos';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-content">
    <div class="page-header">
        <div><h2><i class="fas fa-box-open" style="color:#00cc66"></i> <?= $isEdit ? 'Editar CTO: '.e($cto['codigo']) : 'Nova CTO' ?></h2></div>
        <a href="<?= BASE_URL ?>/modules/ctos/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group"><label class="form-label">Código *</label>
                        <input class="form-control" name="codigo" required value="<?= e($cto['codigo'] ?? '') ?>" placeholder="CTO-001"></div>
                    <div class="form-group"><label class="form-label">Nome</label>
                        <input class="form-control" name="nome" value="<?= e($cto['nome'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Capacidade</label>
                        <select class="form-control" name="capacidade_portas">
                            <?php foreach([4,8,16,32] as $cap): ?>
                            <option value="<?= $cap ?>" <?= ($cto['capacidade_portas']??8)==$cap?'selected':'' ?>><?= $cap ?> portas</option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Tipo</label>
                        <select class="form-control" name="tipo">
                            <?php foreach(['aerea','subterranea','pedestal'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($cto['tipo']??'aerea')===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Poste Vinculado</label>
                        <select class="form-control" name="poste_id">
                            <option value="">Nenhum</option>
                            <?php foreach($postes as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($cto['poste_id']??'')==$p['id']?'selected':'' ?>><?= e($p['codigo']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group full">
                        <label class="form-label" style="display:flex;align-items:center;justify-content:space-between">
                            <span>Slot / PON da OLT <span style="color:var(--text-muted);font-weight:400">(entrada de sinal)</span></span>
                            <?php if ($isEdit): ?>
                            <button type="button" id="btn-detect-pon" onclick="detectarPon()"
                                class="btn btn-sm btn-secondary" style="font-size:11px;padding:3px 10px;margin-left:8px">
                                <i class="fas fa-magic"></i> Detectar automaticamente
                            </button>
                            <?php endif; ?>
                        </label>
                        <select class="form-control" name="olt_pon_id" id="olt_pon_id">
                            <option value="">Não vinculado</option>
                            <?php foreach($olt_pons as $op): ?>
                            <option value="<?= $op['id'] ?>" <?= ($cto['olt_pon_id']??'')==$op['id']?'selected':'' ?>><?= e($op['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="detect-status" style="display:none;margin-top:6px;font-size:12px;padding:6px 10px;border-radius:6px"></div>
                    </div>
                    <div class="form-group"><label class="form-label">Status</label>
                        <select class="form-control" name="status">
                            <?php foreach(['ativo','inativo','cheio','manutencao'] as $s): ?>
                            <option value="<?= $s ?>" <?= ($cto['status']??'ativo')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Fabricante</label>
                        <input class="form-control" name="fabricante" value="<?= e($cto['fabricante'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Modelo</label>
                        <input class="form-control" name="modelo" value="<?= e($cto['modelo'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Latitude *</label>
                        <input class="form-control" name="lat" id="lat" required value="<?= e($cto['lat'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Longitude *</label>
                        <input class="form-control" name="lng" id="lng" required value="<?= e($cto['lng'] ?? '') ?>"></div>
                    <div class="form-group full"><label class="form-label">Observações</label>
                        <textarea class="form-control" name="observacoes" rows="3"><?= e($cto['observacoes'] ?? '') ?></textarea></div>
                </div>
                <div style="display:flex;gap:10px;margin-top:12px">
                    <a href="<?= BASE_URL ?>/modules/ctos/index.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                </div>
            </form>
        </div>
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden;min-height:420px">
            <div id="minimap" style="height:100%;min-height:420px"></div>
        </div>
    </div>
</div>
<?php if ($isEdit): ?>
<script>
async function detectarPon() {
    const btn    = document.getElementById('btn-detect-pon');
    const status = document.getElementById('detect-status');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Detectando...';
    status.style.display = 'none';

    try {
        const res  = await fetch('<?= BASE_URL ?>/api/detect_pon.php?cto_id=<?= $id ?>', { credentials: 'same-origin' });
        const data = await res.json();

        if (data.success) {
            const sel = document.getElementById('olt_pon_id');
            sel.value = data.olt_pon_id;
            // Se a opção não existe no select (não deveria acontecer), adiciona
            if (sel.value != data.olt_pon_id) {
                const opt = document.createElement('option');
                opt.value = data.olt_pon_id;
                opt.textContent = data.label;
                opt.selected = true;
                sel.appendChild(opt);
            }
            status.style.display  = 'block';
            status.style.background = 'rgba(0,204,102,.12)';
            status.style.border   = '1px solid rgba(0,204,102,.3)';
            status.style.color    = '#00cc66';
            status.innerHTML = '<i class="fas fa-check-circle"></i> Detectado: <strong>' + data.label + '</strong> — clique em Salvar para confirmar.';
        } else {
            status.style.display  = 'block';
            status.style.background = 'rgba(255,68,85,.1)';
            status.style.border   = '1px solid rgba(255,68,85,.3)';
            status.style.color    = '#ff4455';
            status.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.error;
        }
    } catch (e) {
        status.style.display  = 'block';
        status.style.background = 'rgba(255,68,85,.1)';
        status.style.border   = '1px solid rgba(255,68,85,.3)';
        status.style.color    = '#ff4455';
        status.innerHTML = '<i class="fas fa-exclamation-circle"></i> Erro de comunicação com o servidor.';
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-magic"></i> Detectar automaticamente';
}
</script>
<?php endif; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('minimap').setView([<?= json_encode($cto['lat'] ?? -27.59) ?>, <?= json_encode($cto['lng'] ?? -48.55) ?>], 16);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
let marker = <?= $isEdit ? 'L.marker(['.e($cto['lat']).','.e($cto['lng']).']).addTo(map)' : 'null' ?>;
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
