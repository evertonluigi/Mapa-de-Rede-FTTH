<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

$id = (int)($_GET['id'] ?? 0);
$poste = $db->fetch("SELECT p.*, u.nome as criado_por FROM postes p LEFT JOIN usuarios u ON u.id = p.created_by WHERE p.id = ?", [$id]);
if (!$poste) { header('Location: ' . BASE_URL . '/modules/postes/index.php'); exit; }

$manutencoes = $db->fetchAll("SELECT m.*, u.nome as tecnico FROM manutencoes m LEFT JOIN usuarios u ON u.id = m.tecnico_id WHERE m.tipo_elemento = 'poste' AND m.elemento_id = ? ORDER BY m.created_at DESC", [$id]);
$fotos = $db->fetchAll("SELECT * FROM fotos WHERE tipo_elemento = 'poste' AND elemento_id = ?", [$id]);

$pageTitle = 'Poste: ' . $poste['codigo'];
$activePage = 'postes';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-content">
    <div class="page-header">
        <div>
            <h2><i class="fas fa-border-all" style="color:#aaa"></i> <?= e($poste['codigo']) ?></h2>
            <p>Detalhes do poste</p>
        </div>
        <div style="display:flex;gap:10px">
            <a href="<?= BASE_URL ?>/modules/postes/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
            <a href="<?= BASE_URL ?>/modules/qrcode/view.php?tipo=poste&id=<?= $id ?>" class="btn btn-secondary" title="QR Code"><i class="fas fa-qrcode"></i> QR Code</a>
            <a href="<?= BASE_URL ?>/modules/postes/edit.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Editar</a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
        <!-- Info -->
        <div style="display:flex;flex-direction:column;gap:20px">
            <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
                <h4 style="margin-bottom:16px;color:var(--text-muted);font-size:12px;text-transform:uppercase;letter-spacing:1px">Informações</h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <?php
                    $fields = [
                        'Código' => $poste['codigo'],
                        'Status' => formatStatus($poste['status']),
                        'Tipo' => ucfirst($poste['tipo']),
                        'Altura' => $poste['altura_m'] ? $poste['altura_m'].'m' : '—',
                        'Proprietário' => $poste['proprietario'] ?: '—',
                        'Cadastrado por' => $poste['criado_por'] ?: '—',
                        'Latitude' => $poste['lat'],
                        'Longitude' => $poste['lng'],
                        'Cadastrado em' => date('d/m/Y H:i', strtotime($poste['created_at'])),
                    ];
                    foreach ($fields as $label => $value):
                    ?>
                    <div>
                        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px"><?= e($label) ?></div>
                        <div><?= $value ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($poste['observacoes']): ?>
                <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;margin-bottom:8px">Observações</div>
                    <div><?= nl2br(e($poste['observacoes'])) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Manutenções -->
            <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                    <h4 style="font-size:14px">Histórico de Manutenções</h4>
                    <a href="<?= BASE_URL ?>/modules/manutencoes/edit.php?tipo=poste&id=<?= $id ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Registrar
                    </a>
                </div>
                <?php if (empty($manutencoes)): ?>
                <p style="color:var(--text-muted);font-size:13px">Nenhuma manutenção registrada.</p>
                <?php else: ?>
                <?php foreach ($manutencoes as $m): ?>
                <div style="padding:12px;background:rgba(255,255,255,0.03);border-radius:8px;margin-bottom:8px">
                    <div style="display:flex;justify-content:space-between">
                        <strong><?= e(ucfirst($m['tipo_ocorrencia'])) ?></strong>
                        <?= formatStatus($m['status']) ?>
                    </div>
                    <div style="color:var(--text-muted);font-size:12px;margin-top:4px">
                        <?= e($m['tecnico']??'') ?> — <?= date('d/m/Y H:i', strtotime($m['data_ocorrencia'])) ?>
                    </div>
                    <div style="margin-top:6px;font-size:13px"><?= e($m['descricao']) ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Map -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden;min-height:400px">
            <div id="minimap" style="height:100%;min-height:400px"></div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('minimap').setView([<?= $poste['lat'] ?>, <?= $poste['lng'] ?>], 17);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
L.marker([<?= $poste['lat'] ?>, <?= $poste['lng'] ?>]).addTo(map)
    .bindPopup('<b><?= e($poste['codigo']) ?></b><br><?= e(ucfirst($poste['tipo'])) ?>').openPopup();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
