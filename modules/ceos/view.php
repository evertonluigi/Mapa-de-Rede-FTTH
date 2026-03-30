<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

$id = (int)($_GET['id'] ?? 0);
$ceo = $db->fetch("SELECT c.*, p.codigo as poste_codigo FROM ceos c LEFT JOIN postes p ON p.id = c.poste_id WHERE c.id = ?", [$id]);
if (!$ceo) { header('Location: ' . BASE_URL . '/modules/ceos/index.php'); exit; }

$fusoes = $db->fetchAll("SELECT * FROM fusoes WHERE ceo_id = ? ORDER BY bandeja ASC, fibra_entrada ASC", [$id]);
$manutencoes = $db->fetchAll("SELECT m.*, u.nome as tecnico FROM manutencoes m LEFT JOIN usuarios u ON u.id = m.tecnico_id WHERE m.tipo_elemento = 'ceo' AND m.elemento_id = ? ORDER BY m.created_at DESC LIMIT 10", [$id]);

$pageTitle = 'CEO: ' . $ceo['codigo'];
$activePage = 'ceos';
require_once __DIR__ . '/../../includes/header.php';

$totalFusoes = count($fusoes);
$pct = $ceo['capacidade_fo'] > 0 ? round($totalFusoes / $ceo['capacidade_fo'] * 100) : 0;
?>

<div class="page-content">
    <div class="page-header">
        <div>
            <h2><i class="fas fa-box" style="color:#9933ff"></i> <?= e($ceo['codigo']) ?></h2>
            <p><?= e($ceo['nome'] ?: 'Caixa de Emenda Óptica') ?></p>
        </div>
        <div style="display:flex;gap:10px">
            <a href="<?= BASE_URL ?>/modules/ceos/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
            <a href="<?= BASE_URL ?>/modules/fusoes/view.php?ceo_id=<?= $id ?>" class="btn" style="background:rgba(153,51,255,0.15);color:#9933ff;border:1px solid rgba(153,51,255,0.3)"><i class="fas fa-sitemap"></i> Mapa de Fusões</a>
            <a href="<?= BASE_URL ?>/modules/qrcode/view.php?tipo=ceo&id=<?= $id ?>" class="btn btn-secondary" title="QR Code"><i class="fas fa-qrcode"></i> QR Code</a>
            <a href="<?= BASE_URL ?>/modules/ceos/edit.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Editar</a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
        <!-- Info Card -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
            <h4 style="margin-bottom:16px;color:var(--text-muted);font-size:12px;text-transform:uppercase;letter-spacing:1px">Informações</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <?php foreach([
                    'Código'       => $ceo['codigo'],
                    'Status'       => formatStatus($ceo['status']),
                    'Tipo'         => ucfirst($ceo['tipo']),
                    'Cap. FO'      => $ceo['capacidade_fo'].' fibras',
                    'Poste'        => $ceo['poste_codigo']?:'—',
                    'Fabricante'   => $ceo['fabricante']?:'—',
                    'Modelo'       => $ceo['modelo']?:'—',
                    'Fusões reg.'  => $totalFusoes,
                ] as $k=>$v): ?>
                <div>
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px"><?= e($k) ?></div>
                    <div><?= $v ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <!-- Ocupação -->
            <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
                <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                    <span style="font-size:13px">Fusões registradas</span>
                    <span style="font-size:13px;font-weight:600"><?= $totalFusoes ?>/<?= $ceo['capacidade_fo'] ?> (<?= $pct ?>%)</span>
                </div>
                <div style="background:rgba(255,255,255,0.08);border-radius:6px;height:10px">
                    <?php $bc = $pct>=100?'#ff4455':($pct>=80?'#ffaa00':'#9933ff'); ?>
                    <div style="width:<?= min($pct,100) ?>%;height:100%;background:<?= $bc ?>;border-radius:6px;transition:width 0.5s"></div>
                </div>
            </div>
            <?php if ($ceo['observacoes']): ?>
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;margin-bottom:6px">Observações</div>
                <div style="font-size:13px"><?= e($ceo['observacoes']) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Map -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden">
            <div id="minimap" style="height:300px"></div>
        </div>
    </div>

    <!-- Fusões resumidas -->
    <?php if (!empty($fusoes)): ?>
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px;margin-bottom:24px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h4 style="font-size:15px"><i class="fas fa-sitemap" style="color:#9933ff"></i> Fusões</h4>
            <a href="<?= BASE_URL ?>/modules/fusoes/view.php?ceo_id=<?= $id ?>" class="btn btn-sm" style="background:rgba(153,51,255,0.15);color:#9933ff;border:1px solid rgba(153,51,255,0.3)">
                Ver mapa completo
            </a>
        </div>
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead><tr><th>Bandeja</th><th>Fibra Entrada</th><th>Fibra Saída</th><th>Perda</th><th>Observação</th></tr></thead>
                <tbody>
                <?php foreach(array_slice($fusoes, 0, 20) as $f):
                    [$nomeCor, $hexCor] = fiberColor($f['fibra_entrada'] ?? 1);
                ?>
                <tr>
                    <td>Bandeja <?= $f['bandeja'] ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <div style="width:14px;height:14px;border-radius:50%;background:<?= $hexCor ?>;border:1px solid rgba(255,255,255,0.2)"></div>
                            <?= $f['fibra_entrada'] ?> (<?= $nomeCor ?>)
                        </div>
                    </td>
                    <td><?= $f['fibra_saida'] ?></td>
                    <td><?= $f['perda_db'] ? $f['perda_db'].' dB' : '—' ?></td>
                    <td style="color:var(--text-muted);font-size:12px"><?= e($f['observacoes'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($fusoes) > 20): ?>
        <p style="color:var(--text-muted);font-size:12px;margin-top:12px">Mostrando 20 de <?= count($fusoes) ?> fusões.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Manutenções -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h4>Histórico de Manutenções</h4>
            <a href="<?= BASE_URL ?>/modules/manutencoes/edit.php?tipo=ceo&id=<?= $id ?>" class="btn btn-sm btn-primary">
                <i class="fas fa-plus"></i> Registrar
            </a>
        </div>
        <?php if (empty($manutencoes)): ?>
        <p style="color:var(--text-muted);font-size:13px">Nenhuma manutenção registrada.</p>
        <?php else: foreach ($manutencoes as $m): ?>
        <div style="padding:12px;background:rgba(255,255,255,0.03);border-radius:8px;margin-bottom:8px">
            <div style="display:flex;justify-content:space-between">
                <strong><?= e(ucfirst($m['tipo_ocorrencia'])) ?></strong><?= formatStatus($m['status']) ?>
            </div>
            <div style="color:var(--text-muted);font-size:12px;margin-top:4px"><?= e($m['tecnico']??'') ?> — <?= date('d/m/Y H:i',strtotime($m['data_ocorrencia'])) ?></div>
            <div style="margin-top:6px;font-size:13px"><?= e($m['descricao']) ?></div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('minimap').setView([<?= $ceo['lat'] ?>, <?= $ceo['lng'] ?>], 17);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
L.marker([<?= $ceo['lat'] ?>, <?= $ceo['lng'] ?>]).addTo(map)
    .bindPopup('<b><?= e($ceo['codigo']) ?></b>').openPopup();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
