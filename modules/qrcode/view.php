<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

$tipo = $_GET['tipo'] ?? '';
$id   = (int)($_GET['id'] ?? 0);

// Mapeamento tipo → tabela, label, ícone, cor, view URL
$mapa = [
    'cto'      => ['tabela'=>'ctos',     'label'=>'CTO',        'icon'=>'fa-box-open',       'cor'=>'#00cc66', 'view'=>'/modules/ctos/view.php'],
    'ceo'      => ['tabela'=>'ceos',     'label'=>'CEO',        'icon'=>'fa-box',             'cor'=>'#9933ff', 'view'=>'/modules/ceos/view.php'],
    'poste'    => ['tabela'=>'postes',   'label'=>'Poste',      'icon'=>'fa-border-all',      'cor'=>'#aaaaaa', 'view'=>'/modules/postes/view.php'],
    'olt'      => ['tabela'=>'olts',     'label'=>'OLT',        'icon'=>'fa-server',          'cor'=>'#ff6600', 'view'=>'/modules/olts/view.php'],
    'rack'     => ['tabela'=>'racks',    'label'=>'Rack',       'icon'=>'fa-th-large',        'cor'=>'#aa6600', 'view'=>'/modules/racks/view.php'],
    'cabo'     => ['tabela'=>'cabos',    'label'=>'Cabo',       'icon'=>'fa-minus',           'cor'=>'#3399ff', 'view'=>'/modules/cabos/view.php'],
    'splitter' => ['tabela'=>'splitters','label'=>'Splitter',   'icon'=>'fa-project-diagram', 'cor'=>'#ffcc00', 'view'=>'/modules/splitters/index.php'],
    'cliente'  => ['tabela'=>'clientes', 'label'=>'Cliente/ONU','icon'=>'fa-user',            'cor'=>'#00ccff', 'view'=>'/modules/clientes/view.php'],
];

if (!isset($mapa[$tipo]) || !$id) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$cfg    = $mapa[$tipo];
$row    = $db->fetch("SELECT * FROM {$cfg['tabela']} WHERE id = ?", [$id]);
if (!$row) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }

$label  = $row['codigo'] ?? $row['nome'] ?? "#$id";
$nome   = $row['nome'] ?? $row['titulo'] ?? '';
$status = $row['status'] ?? '';

// URL absoluta que vai no QR code
$scheme  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$viewUrl = $scheme . '://' . $host . BASE_URL . $cfg['view'] . '?id=' . $id;

// Campos extras a exibir dependendo do tipo
$extras = [];
if ($tipo === 'cto') {
    $usadas = (int)$db->fetch("SELECT COUNT(*) as n FROM clientes WHERE cto_id=? AND status='ativo'", [$id])['n'];
    $extras = ['Capacidade' => ($row['capacidade_portas']??'—').' portas', 'Ocupação' => "$usadas / ".($row['capacidade_portas']??'—')];
}
if ($tipo === 'ceo') {
    $extras = ['Capacidade FO' => ($row['capacidade_fo']??'—').' fibras'];
}
if ($tipo === 'poste') {
    $extras = ['Tipo' => ucfirst($row['tipo']??'—'), 'Altura' => $row['altura_m'] ? $row['altura_m'].'m' : '—'];
}
if ($tipo === 'olt') {
    $extras = ['Fabricante' => $row['fabricante']??'—', 'Modelo' => $row['modelo']??'—', 'IP' => $row['ip_gerencia']??'—'];
}
if ($tipo === 'rack') {
    $extras = ['Localização' => $row['localizacao']??'—'];
}
if ($tipo === 'cabo') {
    $comp = $row['comprimento_real'] ? number_format($row['comprimento_real'],0,',','.').'m (real)' : ($row['comprimento_m'] ? number_format($row['comprimento_m'],0,',','.').'m (mapa)' : '—');
    $extras = ['Fibras' => ($row['num_fibras']??'—').' FO', 'Comprimento' => $comp, 'Tipo' => ucfirst($row['tipo']??'—')];
}
if ($tipo === 'cliente') {
    $extras = ['Login' => $row['login']??'—', 'Contrato' => $row['numero_contrato']??'—', 'Plano' => $row['plano']??'—'];
    if ($row['serial_onu']) $extras['ONU Serial'] = $row['serial_onu'];
}
if ($tipo === 'splitter') {
    $extras = ['Relação' => $row['relacao']??'—', 'Tipo' => ucfirst($row['tipo']??'—')];
}

$pageTitle  = 'QR Code — ' . e($label);
$activePage = '';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
@media print {
    .sidebar, .app-header, .ftoolbar, .no-print { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
    .qr-card { box-shadow: none !important; border: 1px solid #ddd !important; }
    body { background: #fff !important; }
    .qr-wrap { background: #fff !important; color: #000 !important; }
}
.qr-wrap {
    min-height: calc(100vh - 64px);
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg);
    padding: 20px;
}
.qr-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 40px;
    max-width: 480px;
    width: 100%;
    text-align: center;
    box-shadow: 0 12px 40px rgba(0,0,0,.4);
}
.qr-tipo-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 16px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 20px;
}
.qr-code-box {
    background: #fff;
    border-radius: 14px;
    padding: 20px;
    display: inline-block;
    margin: 0 auto 24px;
}
.qr-label {
    font-size: 28px;
    font-weight: 800;
    letter-spacing: 1px;
    margin-bottom: 4px;
}
.qr-nome {
    font-size: 14px;
    color: var(--text-muted);
    margin-bottom: 20px;
}
.qr-info {
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 12px;
    padding: 16px;
    text-align: left;
    margin-bottom: 24px;
}
.qr-info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;
    font-size: 13px;
    border-bottom: 1px solid rgba(255,255,255,.05);
}
.qr-info-row:last-child { border-bottom: none; }
.qr-info-key { color: var(--text-muted); font-size: 12px; }
.qr-url { font-size: 11px; color: var(--text-muted); word-break: break-all; margin-bottom: 20px; font-family: monospace; }
</style>

<div class="qr-wrap">
    <div class="qr-card">
        <div class="qr-tipo-badge" style="background:<?= $cfg['cor'] ?>22;color:<?= $cfg['cor'] ?>;border:1px solid <?= $cfg['cor'] ?>44">
            <i class="fas <?= $cfg['icon'] ?>"></i>
            <?= $cfg['label'] ?>
        </div>

        <div class="qr-label" style="color:<?= $cfg['cor'] ?>"><?= e($label) ?></div>
        <?php if ($nome && $nome !== $label): ?>
        <div class="qr-nome"><?= e($nome) ?></div>
        <?php endif; ?>

        <div class="qr-code-box">
            <div id="qrcode"></div>
        </div>

        <div class="qr-url"><?= e($viewUrl) ?></div>

        <?php if (!empty($extras) || $status): ?>
        <div class="qr-info">
            <?php if ($status): ?>
            <div class="qr-info-row">
                <span class="qr-info-key">Status</span>
                <span><?= formatStatus($status) ?></span>
            </div>
            <?php endif; ?>
            <?php foreach ($extras as $k => $v): ?>
            <div class="qr-info-row">
                <span class="qr-info-key"><?= e($k) ?></span>
                <span style="font-weight:500"><?= e($v) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (!empty($row['lat']) && !empty($row['lng'])): ?>
            <div class="qr-info-row">
                <span class="qr-info-key">Coordenadas</span>
                <span style="font-size:12px;font-family:monospace"><?= round((float)$row['lat'],5) ?>, <?= round((float)$row['lng'],5) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="no-print" style="display:flex;gap:12px;justify-content:center">
            <a href="<?= BASE_URL . $cfg['view'] ?>?id=<?= $id ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('qrcode'), {
    text:   '<?= addslashes($viewUrl) ?>',
    width:  200,
    height: 200,
    colorDark:  '#000000',
    colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.M
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
