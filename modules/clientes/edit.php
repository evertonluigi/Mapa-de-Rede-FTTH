<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

$id = (int)($_GET['id'] ?? 0);
$cliente = $id ? $db->fetch("SELECT * FROM clientes WHERE id = ?", [$id]) : null;
$isEdit = $cliente !== null;
$ctos = $db->fetchAll("SELECT id, codigo, nome, capacidade_portas FROM ctos WHERE status != 'inativo' ORDER BY codigo");
$olt_pons = $db->fetchAll("SELECT op.id, CONCAT(o.nome,' - PON ',op.numero_pon) as label FROM olt_pons op JOIN olts o ON o.id = op.olt_id WHERE op.status='ativo'");
$cto_id_default = (int)($_GET['cto_id'] ?? $cliente['cto_id'] ?? 0);

// Portas ocupadas por CTO: {cto_id: {porta: nome_cliente}}
$portasOcupadas = [];
$ocupadas = $db->fetchAll(
    "SELECT cto_id, porta_cto, nome, id FROM clientes
     WHERE cto_id IS NOT NULL AND porta_cto IS NOT NULL
     AND status != 'cancelado'" . ($isEdit ? " AND id != $id" : "")
);
foreach ($ocupadas as $oc) {
    $portasOcupadas[(int)$oc['cto_id']][(int)$oc['porta_cto']] = $oc['nome'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nome' => $_POST['nome'],
        'login' => $_POST['login'] ?: null,
        'cpf_cnpj' => $_POST['cpf_cnpj'] ?: null,
        'telefone' => $_POST['telefone'] ?: null,
        'email' => $_POST['email'] ?: null,
        'endereco' => $_POST['endereco'] ?: null,
        'numero_contrato' => $_POST['numero_contrato'] ?: null,
        'cto_id' => $_POST['cto_id'] ?: null,
        'porta_cto' => $_POST['porta_cto'] ?: null,
        'olt_pon_id' => $_POST['olt_pon_id'] ?: null,
        'serial_onu' => $_POST['serial_onu'] ?: null,
        'modelo_onu' => $_POST['modelo_onu'] ?: null,
        'sinal_dbm' => $_POST['sinal_dbm'] !== '' ? (float)$_POST['sinal_dbm'] : null,
        'plano' => $_POST['plano'] ?: null,
        'status' => $_POST['status'],
        'lat' => $_POST['lat'] ? (float)$_POST['lat'] : null,
        'lng' => $_POST['lng'] ? (float)$_POST['lng'] : null,
        'observacoes' => $_POST['observacoes'] ?? '',
    ];
    if ($isEdit) {
        $db->update('clientes', $data, 'id = ?', [$id]);
        AuditLog::log('editar', 'clientes', $id, 'Cliente '.$data['nome'].' editado', $cliente, $data);
    } else {
        $data['created_by'] = Auth::user()['id'];
        $newId = $db->insert('clientes', $data);
        AuditLog::log('criar', 'clientes', $newId, 'Cliente '.$data['nome'].' criado', [], $data);
    }
    header('Location: ' . BASE_URL . '/modules/clientes/index.php?saved=1'); exit;
}

$pageTitle = $isEdit ? 'Editar Cliente' : 'Novo Cliente';
$activePage = 'clientes';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-content">
    <div class="page-header">
        <div><h2><i class="fas fa-user-plus" style="color:#00ccff"></i> <?= $isEdit ? 'Editar: '.e($cliente['nome']) : 'Novo Cliente' ?></h2></div>
        <a href="<?= BASE_URL ?>/modules/clientes/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
            <form method="POST">
                <div style="margin-bottom:16px;color:var(--text-muted);font-size:12px;text-transform:uppercase;letter-spacing:1px;font-weight:600">Dados Pessoais</div>
                <div class="form-grid">
                    <div class="form-group"><label class="form-label">Nome *</label>
                        <input class="form-control" name="nome" required value="<?= e($cliente['nome']??'') ?>"></div>
                    <div class="form-group"><label class="form-label">Login *</label>
                        <input class="form-control" name="login" required value="<?= e($cliente['login']??'') ?>" placeholder="ex: joao.silva"></div>
                    <div class="form-group"><label class="form-label">CPF / CNPJ</label>
                        <input class="form-control" name="cpf_cnpj" value="<?= e($cliente['cpf_cnpj']??'') ?>"></div>
                    <div class="form-group"><label class="form-label">Telefone</label>
                        <input class="form-control" name="telefone" value="<?= e($cliente['telefone']??'') ?>"></div>
                    <div class="form-group full"><label class="form-label">Email</label>
                        <input class="form-control" name="email" type="email" value="<?= e($cliente['email']??'') ?>"></div>
                    <div class="form-group full"><label class="form-label">Endereço</label>
                        <input class="form-control" name="endereco" value="<?= e($cliente['endereco']??'') ?>"></div>
                </div>
                <div style="margin:20px 0 16px;color:var(--text-muted);font-size:12px;text-transform:uppercase;letter-spacing:1px;font-weight:600;padding-top:16px;border-top:1px solid var(--border)">Dados Técnicos</div>
                <div class="form-grid">
                    <div class="form-group"><label class="form-label">Nº Contrato</label>
                        <input class="form-control" name="numero_contrato" value="<?= e($cliente['numero_contrato']??'') ?>"></div>
                    <div class="form-group"><label class="form-label">Plano</label>
                        <input class="form-control" name="plano" value="<?= e($cliente['plano']??'') ?>" placeholder="300 Mbps"></div>
                    <div class="form-group"><label class="form-label">CTO</label>
                        <select class="form-control" name="cto_id" id="cto_id" onchange="loadPortas()">
                            <option value="">Selecione...</option>
                            <?php foreach($ctos as $c): ?>
                            <option value="<?= $c['id'] ?>" data-cap="<?= $c['capacidade_portas'] ?>" <?= ($cto_id_default==$c['id'])?'selected':'' ?>><?= e($c['codigo'].' - '.$c['nome']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Porta CTO</label>
                        <select class="form-control" name="porta_cto" id="porta_cto">
                            <option value="">Selecione CTO primeiro</option>
                            <?php if($cliente && $cliente['porta_cto']): ?>
                            <option value="<?= $cliente['porta_cto'] ?>" selected>Porta <?= $cliente['porta_cto'] ?></option>
                            <?php endif; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">PON / OLT</label>
                        <select class="form-control" name="olt_pon_id">
                            <option value="">Selecione...</option>
                            <?php foreach($olt_pons as $op): ?>
                            <option value="<?= $op['id'] ?>" <?= ($cliente['olt_pon_id']??'')==$op['id']?'selected':'' ?>><?= e($op['label']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Serial ONU</label>
                        <input class="form-control" name="serial_onu" value="<?= e($cliente['serial_onu']??'') ?>"></div>
                    <div class="form-group"><label class="form-label">Modelo ONU</label>
                        <input class="form-control" name="modelo_onu" value="<?= e($cliente['modelo_onu']??'') ?>"></div>
                    <div class="form-group"><label class="form-label">Sinal (dBm)</label>
                        <input class="form-control" name="sinal_dbm" type="number" step="0.01" value="<?= e($cliente['sinal_dbm']??'') ?>" placeholder="-20.5"></div>
                    <div class="form-group"><label class="form-label">Status</label>
                        <select class="form-control" name="status">
                            <?php foreach(['ativo','suspenso','cancelado','instalacao'] as $s): ?>
                            <option value="<?= $s ?>" <?= ($cliente['status']??'ativo')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Latitude</label>
                        <input class="form-control" name="lat" id="lat" value="<?= e($cliente['lat']??'') ?>"></div>
                    <div class="form-group"><label class="form-label">Longitude</label>
                        <input class="form-control" name="lng" id="lng" value="<?= e($cliente['lng']??'') ?>"></div>
                    <div class="form-group full"><label class="form-label">Observações</label>
                        <textarea class="form-control" name="observacoes" rows="3"><?= e($cliente['observacoes']??'') ?></textarea></div>
                </div>
                <div style="display:flex;gap:10px;margin-top:12px">
                    <a href="<?= BASE_URL ?>/modules/clientes/index.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                </div>
            </form>
        </div>
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden;min-height:420px;display:flex;flex-direction:column">
            <div style="padding:10px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px">
                <span style="font-size:12px;color:var(--text-muted)">Clique no mapa ou use sua localização</span>
                <button type="button" id="btn-minha-loc" onclick="usarMinhaLocalizacao()" class="btn btn-sm btn-secondary">
                    <i class="fas fa-crosshairs"></i> Minha Localização
                </button>
            </div>
            <div id="minimap" style="flex:1;min-height:380px"></div>
            <div id="loc-status" style="display:none;padding:8px 14px;font-size:12px;border-top:1px solid var(--border)"></div>
        </div>
    </div>
</div>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('minimap').setView([<?= json_encode($cliente['lat'] ?? -27.59) ?>, <?= json_encode($cliente['lng'] ?? -48.55) ?>], 16);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
let marker = null;
<?php if ($isEdit && $cliente['lat']): ?>
marker = L.marker([<?= e($cliente['lat']) ?>, <?= e($cliente['lng']) ?>], {draggable:true}).addTo(map);
marker.on('dragend', function(e) {
    const p = e.target.getLatLng();
    document.getElementById('lat').value = p.lat.toFixed(8);
    document.getElementById('lng').value = p.lng.toFixed(8);
});
<?php endif; ?>
map.on('click', function(e) {
    setMarker(e.latlng.lat, e.latlng.lng);
});

function setMarker(lat, lng) {
    if (marker) map.removeLayer(marker);
    marker = L.marker([lat, lng], {draggable:true}).addTo(map);
    marker.on('dragend', function(e) {
        const p = e.target.getLatLng();
        document.getElementById('lat').value = p.lat.toFixed(8);
        document.getElementById('lng').value = p.lng.toFixed(8);
    });
    document.getElementById('lat').value = lat.toFixed(8);
    document.getElementById('lng').value = lng.toFixed(8);
}

function usarMinhaLocalizacao() {
    if (!navigator.geolocation) {
        showLocStatus('Geolocalização não suportada neste navegador.', 'error');
        return;
    }
    const btn = document.getElementById('btn-minha-loc');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Obtendo...';
    showLocStatus('Aguardando permissão de localização...', 'info');

    navigator.geolocation.getCurrentPosition(
        function(pos) {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            const acc = Math.round(pos.coords.accuracy);
            setMarker(lat, lng);
            map.setView([lat, lng], 18);
            showLocStatus(`<i class="fas fa-check-circle" style="color:#00cc66"></i> Localização obtida — precisão ~${acc}m`, 'ok');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-crosshairs"></i> Minha Localização';
        },
        function(err) {
            const msgs = {1:'Permissão negada. Permita o acesso à localização no navegador.', 2:'Localização indisponível.', 3:'Tempo esgotado.'};
            showLocStatus('<i class="fas fa-exclamation-circle" style="color:#ff4455"></i> ' + (msgs[err.code] || 'Erro ao obter localização.'), 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-crosshairs"></i> Minha Localização';
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
}

function showLocStatus(msg, type) {
    const el = document.getElementById('loc-status');
    el.style.display = 'block';
    el.style.color = type === 'error' ? '#ff4455' : type === 'ok' ? '#00cc66' : 'var(--text-muted)';
    el.innerHTML = msg;
    if (type === 'ok') setTimeout(() => { el.style.display = 'none'; }, 4000);
}

const PORTAS_OCUPADAS = <?= json_encode($portasOcupadas) ?>;

function loadPortas(keepVal) {
    const sel    = document.getElementById('cto_id');
    const ctoId  = parseInt(sel.value) || 0;
    const opt    = sel.options[sel.selectedIndex];
    const cap    = parseInt(opt ? opt.getAttribute('data-cap') : 0) || 8;
    const portaSel = document.getElementById('porta_cto');
    const prev   = keepVal !== undefined ? keepVal : portaSel.value;
    const ocupadas = PORTAS_OCUPADAS[ctoId] || {};

    portaSel.innerHTML = '<option value="">Selecione a porta</option>';
    for (let i = 1; i <= cap; i++) {
        const opt = document.createElement('option');
        opt.value = i;
        if (ocupadas[i]) {
            opt.textContent = `Porta ${i} — em uso (${ocupadas[i]})`;
            opt.disabled = true;
            opt.style.color = '#ff6666';
        } else {
            opt.textContent = `Porta ${i} — livre`;
            opt.style.color = '#00cc66';
        }
        portaSel.appendChild(opt);
    }
    if (prev) portaSel.value = prev;
}
// Inicializa portas ao carregar em modo edição
(function(){
    const ctoSel = document.getElementById('cto_id');
    if (ctoSel && ctoSel.value) {
        loadPortas(<?= json_encode((string)($cliente['porta_cto'] ?? '')) ?>);
    }
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
