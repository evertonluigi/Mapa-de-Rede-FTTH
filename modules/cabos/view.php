<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

$id = (int)($_GET['id'] ?? 0);
$cabo = $db->fetch("SELECT c.*, u.nome as criado_por FROM cabos c LEFT JOIN usuarios u ON u.id = c.created_by WHERE c.id = ?", [$id]);
if (!$cabo) { header('Location: ' . BASE_URL . '/modules/cabos/index.php'); exit; }

$pontos = $db->fetchAll("SELECT * FROM cabo_pontos WHERE cabo_id = ? ORDER BY sequencia", [$id]);

// Elementos ancorados
$postes_ancorados = $db->fetchAll(
    "SELECT DISTINCT p.id, p.codigo FROM postes p
     INNER JOIN cabo_pontos cp ON cp.elemento_tipo='poste' AND cp.elemento_id=p.id
     WHERE cp.cabo_id = ?", [$id]);
$ceos_ancorados = $db->fetchAll(
    "SELECT DISTINCT c.id, c.codigo FROM ceos c
     INNER JOIN cabo_pontos cp ON cp.elemento_tipo='ceo' AND cp.elemento_id=c.id
     WHERE cp.cabo_id = ?", [$id]);
$ctos_ancorados = $db->fetchAll(
    "SELECT DISTINCT c.id, c.codigo FROM ctos c
     INNER JOIN cabo_pontos cp ON cp.elemento_tipo='cto' AND cp.elemento_id=c.id
     WHERE cp.cabo_id = ?", [$id]);

$pageTitle = 'Cabo: ' . $cabo['codigo'];
$activePage = 'cabos';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-content">
    <div class="page-header">
        <div>
            <h2><i class="fas fa-minus" style="color:#3399ff"></i> <?= e($cabo['codigo']) ?></h2>
            <?php if ($cabo['nome']): ?><p><?= e($cabo['nome']) ?></p><?php endif; ?>
        </div>
        <div style="display:flex;gap:10px">
            <a href="<?= BASE_URL ?>/modules/cabos/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
            <a href="<?= BASE_URL ?>/modules/qrcode/view.php?tipo=cabo&id=<?= $id ?>" class="btn btn-secondary" title="QR Code"><i class="fas fa-qrcode"></i> QR Code</a>
            <a href="<?= BASE_URL ?>/modules/cabos/edit.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Editar</a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
        <!-- Detalhes -->
        <div style="display:flex;flex-direction:column;gap:16px">
            <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
                <h4 style="margin-bottom:16px;color:var(--text-muted);font-size:12px;text-transform:uppercase;letter-spacing:1px">Dados do Cabo</h4>
                <div class="form-grid">
                    <?php
                    $rows = [
                        'Código'       => e($cabo['codigo']),
                        'Tipo'         => ucfirst($cabo['tipo']),
                        'Nº Fibras'    => $cabo['num_fibras'].' FO',
                        'Comprimento'  => $cabo['comprimento_m'] ? number_format($cabo['comprimento_m'],0,',','.').' m' : '—',
                        'Status'       => formatStatus($cabo['status']),
                        'Pontos no mapa'=> count($pontos),
                        'Cadastrado por'=> e($cabo['criado_por'] ?? '—'),
                        'Data'         => date('d/m/Y H:i', strtotime($cabo['created_at'])),
                    ];
                    foreach ($rows as $l => $v): ?>
                    <div class="form-group">
                        <label class="form-label"><?= $l ?></label>
                        <div style="padding:8px 0"><?= $v ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($cabo['observacoes']): ?>
                    <div class="form-group full">
                        <label class="form-label">Observações</label>
                        <div style="padding:8px 0;color:var(--text-muted)"><?= e($cabo['observacoes']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Elementos ancorados -->
            <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
                <h4 style="margin-bottom:16px;color:var(--text-muted);font-size:12px;text-transform:uppercase;letter-spacing:1px">Elementos Ancorados</h4>
                <?php if (empty($postes_ancorados) && empty($ceos_ancorados) && empty($ctos_ancorados)): ?>
                <p style="color:var(--text-muted);font-size:13px">Nenhum elemento ancorado. Lance o cabo passando sobre postes/CEOs/CTOs no mapa.</p>
                <?php else: ?>
                    <?php if ($postes_ancorados): ?>
                    <div style="margin-bottom:10px"><strong style="font-size:12px;color:#aaa">POSTES</strong>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">
                        <?php foreach($postes_ancorados as $p): ?>
                        <span style="background:rgba(255,255,255,0.07);padding:3px 10px;border-radius:6px;font-size:12px;display:flex;align-items:center;gap:6px">
                            <?= e($p['codigo']) ?>
                            <button onclick="unlinkElem('poste',<?= $p['id'] ?>,'<?= e($p['codigo']) ?>')" title="Desvincular" style="background:none;border:none;color:#ff4455;cursor:pointer;padding:0;font-size:12px;line-height:1">✕</button>
                        </span>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($ceos_ancorados): ?>
                    <div style="margin-bottom:10px"><strong style="font-size:12px;color:#9933ff">CEOs</strong>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">
                        <?php foreach($ceos_ancorados as $c): ?>
                        <span style="background:rgba(153,51,255,0.12);padding:3px 10px;border-radius:6px;font-size:12px;color:#aa66ff;display:flex;align-items:center;gap:6px">
                            <?= e($c['codigo']) ?>
                            <button onclick="unlinkElem('ceo',<?= $c['id'] ?>,'<?= e($c['codigo']) ?>')" title="Desvincular" style="background:none;border:none;color:#ff4455;cursor:pointer;padding:0;font-size:12px;line-height:1">✕</button>
                        </span>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($ctos_ancorados): ?>
                    <div><strong style="font-size:12px;color:#00cc66">CTOs</strong>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">
                        <?php foreach($ctos_ancorados as $c): ?>
                        <span style="background:rgba(0,204,102,0.1);padding:3px 10px;border-radius:6px;font-size:12px;color:#00cc66;display:flex;align-items:center;gap:6px">
                            <?= e($c['codigo']) ?>
                            <button onclick="unlinkElem('cto',<?= $c['id'] ?>,'<?= e($c['codigo']) ?>')" title="Desvincular" style="background:none;border:none;color:#ff4455;cursor:pointer;padding:0;font-size:12px;line-height:1">✕</button>
                        </span>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mapa do traçado -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden;min-height:480px">
            <div id="minimap" style="height:100%;min-height:480px"></div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
async function unlinkElem(tipo, eid, label) {
    if (!confirm(`Desvincular cabo de ${tipo.toUpperCase()} "${label}"?\nO ponto ficará livre (não ancorado).`)) return;
    const res = await fetch(`<?= BASE_URL ?>/api/elements.php?type=unlink_cabo_pt`, {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ cabo_id: <?= $id ?>, elemento_tipo: tipo, elemento_id: eid })
    });
    const d = await res.json();
    if (d.success) location.reload();
    else alert('Erro: ' + (d.error || 'Falha ao desvincular'));
}
</script>
<script>
const pontos = <?= json_encode(array_map(fn($p) => ['lat'=>(float)$p['lat'],'lng'=>(float)$p['lng']], $pontos)) ?>;
const map = L.map('minimap');
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
if (pontos.length >= 2) {
    const line = L.polyline(pontos.map(p => [p.lat, p.lng]), { color:'#3399ff', weight:4 }).addTo(map);
    map.fitBounds(line.getBounds(), { padding:[20,20] });
    L.circleMarker([pontos[0].lat, pontos[0].lng], { radius:7, color:'#00cc66', fillColor:'#00cc66', fillOpacity:1, weight:2 }).addTo(map).bindTooltip('Início');
    L.circleMarker([pontos[pontos.length-1].lat, pontos[pontos.length-1].lng], { radius:7, color:'#ff4455', fillColor:'#ff4455', fillOpacity:1, weight:2 }).addTo(map).bindTooltip('Fim');
} else {
    map.setView([-27.5954, -48.5480], 14);
}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
