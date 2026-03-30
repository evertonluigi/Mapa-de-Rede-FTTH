<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
$cl = $db->fetch(
    "SELECT c.*,
        ct.codigo as cto_codigo, ct.nome as cto_nome,
        ct.olt_pon_id as cto_olt_pon_id,
        op.slot as pon_slot, op.numero_pon as pon_numero,
        o.nome as pon_olt_nome, o.codigo as pon_olt_codigo
     FROM clientes c
     LEFT JOIN ctos ct ON ct.id = c.cto_id
     LEFT JOIN olt_pons op ON op.id = ct.olt_pon_id
     LEFT JOIN olts o ON o.id = op.olt_id
     WHERE c.id = ?",
    [$id]
);
if (!$cl) { header('Location: ' . BASE_URL . '/modules/clientes/index.php'); exit; }
$manutencoes = $db->fetchAll("SELECT m.*, u.nome as tecnico FROM manutencoes m LEFT JOIN usuarios u ON u.id = m.tecnico_id WHERE m.tipo_elemento = 'cliente' AND m.elemento_id = ? ORDER BY m.created_at DESC LIMIT 10", [$id]);
$pageTitle = 'Cliente: ' . $cl['nome'];
$activePage = 'clientes';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-content">
    <div class="page-header">
        <div><h2><i class="fas fa-user" style="color:#00ccff"></i> <?= e($cl['nome']) ?></h2>
        <p><?= e($cl['endereco']?:'') ?></p></div>
        <div style="display:flex;gap:10px">
            <a href="<?= BASE_URL ?>/modules/clientes/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
            <a href="<?= BASE_URL ?>/modules/qrcode/view.php?tipo=cliente&id=<?= $id ?>" class="btn btn-secondary" title="QR Code"><i class="fas fa-qrcode"></i> QR Code</a>
            <a href="<?= BASE_URL ?>/modules/clientes/edit.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Editar</a>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
            <h4 style="margin-bottom:16px;color:var(--text-muted);font-size:12px;text-transform:uppercase;letter-spacing:1px">Dados do Cliente</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <?php
                $sinal = $cl['sinal_dbm'];
                $sinalColor = $sinal ? ((float)$sinal < -27 ? '#ff4455' : '#00cc66') : 'inherit';
                $ponLabel = $cl['pon_olt_nome']
                    ? '<span style="color:#00ccff;font-weight:600">Slot '.(int)$cl['pon_slot'].' / PON '.(int)$cl['pon_numero'].'</span>'
                      .' <span style="color:var(--text-muted);font-size:12px">— '.e($cl['pon_olt_nome']).'</span>'
                    : '—';
                $campos = [
                    'Status'    => formatStatus($cl['status']),
                    'CPF/CNPJ'  => $cl['cpf_cnpj']?:'—',
                    'Telefone'  => $cl['telefone']?:'—',
                    'Email'     => $cl['email']?:'—',
                    'Contrato'  => $cl['numero_contrato']?:'—',
                    'Plano'     => $cl['plano']?:'—',
                    'CTO'       => $cl['cto_codigo'] ? ($cl['cto_codigo'].($cl['cto_nome'] ? ' — '.$cl['cto_nome'] : '')) : '—',
                    'Porta CTO' => $cl['porta_cto']?:'—',
                    'Slot / PON'=> $ponLabel,
                    'Serial ONU'=> $cl['serial_onu']?:'—',
                    'Modelo ONU'=> $cl['modelo_onu']?:'—',
                    'Sinal'     => $sinal ? "<span style='color:$sinalColor;font-weight:600'>{$sinal} dBm</span>" : '—',
                ];
                foreach($campos as $k=>$v): ?>
                <div>
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;margin-bottom:3px"><?= e($k) ?></div>
                    <div><?= $v ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($cl['observacoes']): ?>
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;margin-bottom:6px">Observações</div>
                <div style="font-size:13px"><?= e($cl['observacoes']) ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php if($cl['lat']): ?>
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden">
            <div id="minimap" style="height:300px"></div>
        </div>
        <?php else: ?>
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px;display:flex;align-items:center;justify-content:center;color:var(--text-muted)">
            <div style="text-align:center"><i class="fas fa-map-marker-slash" style="font-size:32px;opacity:0.3;margin-bottom:8px;display:block"></i>Localização não definida</div>
        </div>
        <?php endif; ?>
    </div>
    <!-- Manutenções -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h4>Histórico</h4>
            <a href="<?= BASE_URL ?>/modules/manutencoes/edit.php?tipo=cliente&id=<?= $id ?>" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Registrar</a>
        </div>
        <?php if(empty($manutencoes)): ?>
        <p style="color:var(--text-muted);font-size:13px">Nenhum histórico.</p>
        <?php else: foreach($manutencoes as $m): ?>
        <div style="padding:12px;background:rgba(255,255,255,0.03);border-radius:8px;margin-bottom:8px">
            <div style="display:flex;justify-content:space-between"><strong><?= e(ucfirst($m['tipo_ocorrencia'])) ?></strong><?= formatStatus($m['status']) ?></div>
            <div style="color:var(--text-muted);font-size:12px;margin-top:4px"><?= e($m['tecnico']??'') ?> — <?= date('d/m/Y H:i',strtotime($m['data_ocorrencia'])) ?></div>
            <div style="margin-top:6px;font-size:13px"><?= e($m['descricao']) ?></div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>
<?php if($cl['lat']): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('minimap').setView([<?= $cl['lat'] ?>, <?= $cl['lng'] ?>], 17);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
L.marker([<?= $cl['lat'] ?>, <?= $cl['lng'] ?>]).addTo(map).bindPopup('<?= e($cl['nome']) ?>').openPopup();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
