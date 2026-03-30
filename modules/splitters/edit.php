<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

$id = (int)($_GET['id'] ?? 0);
$splitter = $id ? $db->fetch("SELECT * FROM splitters WHERE id = ?", [$id]) : null;
$isEdit = $splitter !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'codigo'          => $_POST['codigo'],
        'nome'            => $_POST['nome'] ?: null,
        'tipo'            => $_POST['tipo'],
        'relacao'         => $_POST['relacao'],
        'nivel'           => $_POST['nivel'] ?: null,
        'poste_id'        => $_POST['poste_id'] ?: null,
        'lat'             => $_POST['lat'] !== '' ? (float)$_POST['lat'] : null,
        'lng'             => $_POST['lng'] !== '' ? (float)$_POST['lng'] : null,
        'perda_insercao_db' => $_POST['perda_insercao_db'] !== '' ? (float)$_POST['perda_insercao_db'] : null,
        'status'          => $_POST['status'],
        'observacoes'     => $_POST['observacoes'] ?? '',
    ];
    if ($isEdit) {
        $db->update('splitters', $data, 'id = ?', [$id]);
        AuditLog::log('editar', 'splitters', $id, 'Splitter '.$data['codigo'].' editado', $splitter, $data);
    } else {
        $data['created_by'] = Auth::user()['id'];
        $newId = $db->insert('splitters', $data);
        AuditLog::log('criar', 'splitters', $newId, 'Splitter '.$data['codigo'].' criado', [], $data);
    }
    header('Location: ' . BASE_URL . '/modules/splitters/index.php?saved=1'); exit;
}

$postes = $db->fetchAll("SELECT id, codigo FROM postes WHERE status='ativo' ORDER BY codigo");
$pageTitle = $isEdit ? 'Editar Splitter' : 'Novo Splitter';
$activePage = 'splitters';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-content">
    <div class="page-header">
        <div><h2><i class="fas fa-project-diagram" style="color:#ffcc00"></i> <?= $isEdit ? 'Editar: '.e($splitter['codigo']) : 'Novo Splitter' ?></h2></div>
        <a href="<?= BASE_URL ?>/modules/splitters/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;max-width:900px">
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
            <h4 style="margin-bottom:16px;font-size:12px;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted)">Dados do Splitter</h4>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Código *</label>
                        <input class="form-control" name="codigo" required value="<?= e($splitter['codigo'] ?? '') ?>" placeholder="SPL-001">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="status">
                            <?php foreach(['ativo','inativo','defeito'] as $s): ?>
                            <option value="<?= $s ?>" <?= ($splitter['status']??'ativo')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Nome / Descrição</label>
                        <input class="form-control" name="nome" value="<?= e($splitter['nome'] ?? '') ?>" placeholder="Ex: Splitter N1 Poste 42">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo</label>
                        <select class="form-control" name="tipo">
                            <?php foreach(['balanceado','desbalanceado'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($splitter['tipo']??'balanceado')===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Relação</label>
                        <select class="form-control" name="relacao">
                            <?php foreach(['1:2','1:4','1:8','1:16','1:32','1:64','2:8','2:16','2:32'] as $r): ?>
                            <option value="<?= $r ?>" <?= ($splitter['relacao']??'1:8')===$r?'selected':'' ?>><?= $r ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nível</label>
                        <select class="form-control" name="nivel">
                            <option value="">Selecione...</option>
                            <?php foreach(['N1','N2','N3'] as $n): ?>
                            <option value="<?= $n ?>" <?= ($splitter['nivel']??'')===$n?'selected':'' ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Perda de inserção (dB)</label>
                        <input class="form-control" type="number" step="0.01" name="perda_insercao_db" value="<?= e($splitter['perda_insercao_db'] ?? '') ?>" placeholder="Ex: 3.5">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Poste Vinculado</label>
                        <select class="form-control" name="poste_id">
                            <option value="">Nenhum</option>
                            <?php foreach($postes as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($splitter['poste_id']??'')==$p['id']?'selected':'' ?>><?= e($p['codigo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Latitude</label>
                        <input class="form-control" name="lat" id="lat" value="<?= e($splitter['lat'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Longitude</label>
                        <input class="form-control" name="lng" id="lng" value="<?= e($splitter['lng'] ?? '') ?>">
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Observações</label>
                        <textarea class="form-control" name="observacoes" rows="2"><?= e($splitter['observacoes'] ?? '') ?></textarea>
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-top:12px">
                    <a href="<?= BASE_URL ?>/modules/splitters/index.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                </div>
            </form>
        </div>

        <!-- Mini map -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden;min-height:420px;display:flex;flex-direction:column">
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);font-size:12px;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted)">
                Localização no Mapa <span style="font-size:11px;color:#444;text-transform:none">(clique para definir)</span>
            </div>
            <div id="minimap" style="flex:1;min-height:360px"></div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('minimap').setView([<?= $splitter['lat'] ?? -27.59 ?>, <?= $splitter['lng'] ?? -48.55 ?>], 15);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'OSM'}).addTo(map);
let marker = <?= ($isEdit && $splitter['lat']) ? 'L.marker(['.((float)$splitter['lat']).','.((float)$splitter['lng']).']).addTo(map)' : 'null' ?>;
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
