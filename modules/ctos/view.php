<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

$id = (int)($_GET['id'] ?? 0);
$cto = $db->fetch(
    "SELECT c.*, p.codigo as poste_codigo,
        op.slot as pon_slot, op.numero_pon as pon_numero,
        o.nome as pon_olt_nome, o.codigo as pon_olt_codigo
     FROM ctos c
     LEFT JOIN postes p ON p.id = c.poste_id
     LEFT JOIN olt_pons op ON op.id = c.olt_pon_id
     LEFT JOIN olts o ON o.id = op.olt_id
     WHERE c.id = ?",
    [$id]
);
if (!$cto) { header('Location: ' . BASE_URL . '/modules/ctos/index.php'); exit; }

$clientes = $db->fetchAll("SELECT cl.*, cl.porta_cto as porta FROM clientes cl WHERE cl.cto_id = ? ORDER BY cl.porta_cto ASC, cl.nome ASC", [$id]);
$manutencoes = $db->fetchAll("SELECT m.*, u.nome as tecnico FROM manutencoes m LEFT JOIN usuarios u ON u.id = m.tecnico_id WHERE m.tipo_elemento = 'cto' AND m.elemento_id = ? ORDER BY m.created_at DESC LIMIT 10", [$id]);

$pageTitle = 'CTO: ' . $cto['codigo'];
$activePage = 'ctos';
require_once __DIR__ . '/../../includes/header.php';

$capacidade = $cto['capacidade_portas'];
$usadas = count(array_filter($clientes, fn($c) => $c['status'] === 'ativo'));
$pct = $capacidade > 0 ? round($usadas / $capacidade * 100) : 0;
?>

<div class="page-content">
    <div class="page-header">
        <div>
            <h2><i class="fas fa-box-open" style="color:#00cc66"></i> <?= e($cto['codigo']) ?></h2>
            <p><?= e($cto['nome'] ?: 'Caixa Terminal Óptica') ?></p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a href="<?= BASE_URL ?>/modules/ctos/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
            <a href="<?= BASE_URL ?>/modules/fusoes/view.php?cto_id=<?= $id ?>" class="btn btn-secondary" style="color:#00cc66;border-color:rgba(0,204,102,.4)"><i class="fas fa-project-diagram"></i> Mapa de Fusão</a>
            <a href="<?= BASE_URL ?>/modules/qrcode/view.php?tipo=cto&id=<?= $id ?>" class="btn btn-secondary" title="QR Code"><i class="fas fa-qrcode"></i> QR Code</a>
            <button type="button" id="btn-detect-pon" onclick="detectarPon()"
                class="btn btn-secondary" style="color:#9933ff;border-color:rgba(153,51,255,.4)">
                <i class="fas fa-magic"></i> Detectar Slot/PON
            </button>
            <a href="<?= BASE_URL ?>/modules/ctos/edit.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Editar</a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
        <!-- Info Card -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
            <h4 style="margin-bottom:16px;color:var(--text-muted);font-size:12px;text-transform:uppercase;letter-spacing:1px">Informações</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <?php
                $ponLabel = $cto['pon_olt_nome']
                    ? '<span style="color:#00ccff;font-weight:600">Slot '.(int)$cto['pon_slot'].' / PON '.(int)$cto['pon_numero'].'</span>'
                      .' <span style="color:var(--text-muted);font-size:12px">— '.e($cto['pon_olt_nome']).'</span>'
                    : '—';
                $infoFields = [
                    'Código'    => e($cto['codigo']),
                    'Status'    => formatStatus($cto['status']),
                    'Slot / PON'=> $ponLabel,
                    'Tipo'      => ucfirst($cto['tipo']),
                    'Capacidade'=> $cto['capacidade_portas'].' portas',
                    'Poste'     => $cto['poste_codigo'] ?: '—',
                    'Fabricante'=> $cto['fabricante'] ?: '—',
                    'Modelo'    => $cto['modelo'] ?: '—',
                ];
                foreach($infoFields as $k=>$v): ?>
                <div>
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px"><?= e($k) ?></div>
                    <div><?= $v ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <!-- Ocupação -->
            <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
                <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                    <span style="font-size:13px">Ocupação</span>
                    <span style="font-size:13px;font-weight:600"><?= $usadas ?>/<?= $capacidade ?> (<?= $pct ?>%)</span>
                </div>
                <div style="background:rgba(255,255,255,0.08);border-radius:6px;height:10px">
                    <?php $bc = $pct>=100?'#ff4455':($pct>=80?'#ffaa00':'#00cc66'); ?>
                    <div style="width:<?= min($pct,100) ?>%;height:100%;background:<?= $bc ?>;border-radius:6px;transition:width 0.5s"></div>
                </div>
            </div>
            <?php if ($cto['observacoes']): ?>
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;margin-bottom:6px">Observações</div>
                <div style="font-size:13px"><?= e($cto['observacoes']) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Map -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden">
            <div id="minimap" style="height:300px"></div>
        </div>
    </div>

    <!-- Portas / Clientes -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px;margin-bottom:24px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h4 style="font-size:15px"><i class="fas fa-plug" style="color:#00cc66"></i> Portas / Clientes</h4>
            <a href="<?= BASE_URL ?>/modules/clientes/edit.php?cto_id=<?= $id ?>" class="btn btn-sm btn-primary">
                <i class="fas fa-user-plus"></i> Novo Cliente
            </a>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px">
            <?php for ($porta = 1; $porta <= $capacidade; $porta++):
                $cliente = null;
                foreach ($clientes as $cl) { if ((int)$cl['porta'] === $porta) { $cliente = $cl; break; } }
                $cor = $cliente ? ($cliente['status']==='ativo'?'#00cc66':'#888') : 'rgba(255,255,255,0.05)';
                $border = $cliente ? "1px solid ".($cliente['status']==='ativo'?'rgba(0,204,102,0.3)':'rgba(136,136,136,0.3)') : '1px solid rgba(255,255,255,0.08)';
            ?>
            <div style="background:rgba(255,255,255,0.03);border:<?= $border ?>;border-radius:10px;padding:14px;position:relative">
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px">PORTA <?= $porta ?></div>
                <?php if ($cliente): ?>
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
                    <div style="width:8px;height:8px;background:<?= $cor ?>;border-radius:50%"></div>
                    <strong style="font-size:13px"><?= e(mb_substr($cliente['nome'],0,20)) ?></strong>
                </div>
                <?php if ($cliente['serial_onu']): ?>
                <div style="font-size:11px;color:var(--text-muted)"><?= e($cliente['serial_onu']) ?></div>
                <?php endif; ?>
                <?php if ($cliente['sinal_dbm']): ?>
                <div style="font-size:12px;color:<?= (float)$cliente['sinal_dbm'] < -27 ? '#ff4455' : '#00cc66' ?>">
                    <?= $cliente['sinal_dbm'] ?> dBm
                </div>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/modules/clientes/view.php?id=<?= $cliente['id'] ?>" style="position:absolute;inset:0;border-radius:10px"></a>
                <?php else: ?>
                <div style="color:var(--text-muted);font-size:13px">Disponível</div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Manutenções -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h4>Histórico de Manutenções</h4>
            <a href="<?= BASE_URL ?>/modules/manutencoes/edit.php?tipo=cto&id=<?= $id ?>" class="btn btn-sm btn-primary">
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

<!-- Toast de resultado da detecção -->
<div id="detect-toast" style="display:none;position:fixed;bottom:24px;right:24px;z-index:9999;
    max-width:400px;padding:14px 18px;border-radius:12px;font-size:13px;
    box-shadow:0 8px 30px rgba(0,0,0,.5);line-height:1.5"></div>

<script>
async function detectarPon() {
    const btn = document.getElementById('btn-detect-pon');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Detectando...';

    try {
        // Detecta E salva diretamente
        const res  = await fetch('<?= BASE_URL ?>/api/detect_pon.php?cto_id=<?= $id ?>&save=1', { credentials: 'same-origin' });
        const data = await res.json();

        if (data.success) {
            showToastView(
                '<i class="fas fa-check-circle" style="color:#00cc66;margin-right:8px"></i>'
                + '<strong>Slot/PON detectado e salvo!</strong><br>'
                + '<span style="color:#aaa">' + data.label + '</span>',
                'rgba(0,204,102,.12)', 'rgba(0,204,102,.3)'
            );
            // Atualiza a exibição inline sem recarregar
            setTimeout(() => location.reload(), 1800);
        } else {
            showToastView(
                '<i class="fas fa-exclamation-circle" style="color:#ff4455;margin-right:8px"></i>'
                + data.error,
                'rgba(255,68,85,.12)', 'rgba(255,68,85,.3)'
            );
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-magic"></i> Detectar Slot/PON';
        }
    } catch (e) {
        showToastView(
            '<i class="fas fa-exclamation-circle" style="color:#ff4455;margin-right:8px"></i> Erro de comunicação.',
            'rgba(255,68,85,.12)', 'rgba(255,68,85,.3)'
        );
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-magic"></i> Detectar Slot/PON';
    }
}

function showToastView(html, bg, border) {
    const t = document.getElementById('detect-toast');
    t.style.display    = 'block';
    t.style.background = bg;
    t.style.border     = '1px solid ' + border;
    t.style.color      = 'var(--text)';
    t.innerHTML        = html;
    setTimeout(() => { t.style.display = 'none'; }, 5000);
}
</script>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('minimap').setView([<?= $cto['lat'] ?>, <?= $cto['lng'] ?>], 17);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
L.marker([<?= $cto['lat'] ?>, <?= $cto['lng'] ?>]).addTo(map)
    .bindPopup('<b><?= e($cto['codigo']) ?></b><br><?= $usadas ?>/<?= $capacidade ?> portas').openPopup();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
