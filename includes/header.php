<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';
Auth::check();
$user = Auth::user();
$db = Database::getInstance();

// Estatísticas rápidas para sidebar
$stats = $db->fetch("SELECT
    (SELECT COUNT(*) FROM postes WHERE status='ativo') as postes,
    (SELECT COUNT(*) FROM ctos WHERE status='ativo') as ctos,
    (SELECT COUNT(*) FROM ceos WHERE status='ativo') as ceos,
    (SELECT COUNT(*) FROM clientes WHERE status='ativo') as clientes,
    (SELECT COUNT(*) FROM manutencoes WHERE status='aberto') as manutencoes
");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Painel') ?> — Gerenciador de Rede FTTH</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body>
<div class="app-layout">

<!-- SIDEBAR BACKDROP (mobile) -->
<div id="sidebar-backdrop" onclick="closeSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo"><i class="fas fa-network-wired" style="color:white"></i></div>
        <div class="sidebar-title"><span>FTTH</span> Gestão</div>
    </div>
    <nav class="sidebar-nav">

        <div class="nav-section">
            <div class="nav-section-title">Principal</div>
            <a href="<?= BASE_URL ?>/dashboard.php" class="nav-item <?= ($activePage??'')=='dashboard'?'active':'' ?>">
                <i class="fas fa-map" style="color:#00b4ff"></i> Mapa da Rede
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Infraestrutura</div>
            <a href="<?= BASE_URL ?>/modules/olts/index.php" class="nav-item <?= ($activePage??'')=='olts'?'active':'' ?>">
                <i class="fas fa-server" style="color:#ff6600"></i> OLTs
            </a>
            <a href="<?= BASE_URL ?>/modules/racks/index.php" class="nav-item <?= ($activePage??'')=='racks'?'active':'' ?>">
                <i class="fas fa-th-large" style="color:#aa6600"></i> Racks / DIOs
            </a>
            <a href="<?= BASE_URL ?>/modules/postes/index.php" class="nav-item <?= ($activePage??'')=='postes'?'active':'' ?>">
                <i class="fas fa-border-all" style="color:#aaaaaa"></i> Postes
                <?php if($stats['postes']>0): ?><span class="nav-badge"><?= $stats['postes'] ?></span><?php endif; ?>
            </a>
            <a href="<?= BASE_URL ?>/modules/cabos/index.php" class="nav-item <?= ($activePage??'')=='cabos'?'active':'' ?>">
                <i class="fas fa-minus" style="color:#3399ff"></i> Cabos
            </a>
            <a href="<?= BASE_URL ?>/modules/ceos/index.php" class="nav-item <?= ($activePage??'')=='ceos'?'active':'' ?>">
                <i class="fas fa-box" style="color:#9933ff"></i> CEOs — Cx. Emenda
            </a>
            <a href="<?= BASE_URL ?>/modules/ctos/index.php" class="nav-item <?= ($activePage??'')=='ctos'?'active':'' ?>">
                <i class="fas fa-box-open" style="color:#00cc66"></i> CTOs — Cx. Terminal
                <?php if($stats['ctos']>0): ?><span class="nav-badge"><?= $stats['ctos'] ?></span><?php endif; ?>
            </a>
            <a href="<?= BASE_URL ?>/modules/splitters/index.php" class="nav-item <?= ($activePage??'')=='splitters'?'active':'' ?>">
                <i class="fas fa-project-diagram" style="color:#ffcc00"></i> Splitters
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Clientes</div>
            <a href="<?= BASE_URL ?>/modules/clientes/index.php" class="nav-item <?= ($activePage??'')=='clientes'?'active':'' ?>">
                <i class="fas fa-users" style="color:#00ccff"></i> Clientes / ONUs
                <?php if($stats['clientes']>0): ?><span class="nav-badge"><?= $stats['clientes'] ?></span><?php endif; ?>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Operação</div>
            <a href="<?= BASE_URL ?>/modules/kpis/index.php" class="nav-item <?= ($activePage??'')=='kpis'?'active':'' ?>">
                <i class="fas fa-tachometer-alt" style="color:#9933ff"></i> Dashboard KPIs
            </a>
            <a href="<?= BASE_URL ?>/modules/importar/index.php" class="nav-item <?= ($activePage??'')=='importar'?'active':'' ?>">
                <i class="fas fa-file-import" style="color:#3399ff"></i> Importar KML/KMZ
            </a>
            <a href="<?= BASE_URL ?>/modules/fusoes/index.php" class="nav-item <?= ($activePage??'')=='fusoes'?'active':'' ?>">
                <i class="fas fa-sitemap" style="color:#ff9900"></i> Mapa de Fusões
            </a>
            <a href="<?= BASE_URL ?>/modules/manutencoes/index.php" class="nav-item <?= ($activePage??'')=='manutencoes'?'active':'' ?>">
                <i class="fas fa-tools" style="color:#ff6655"></i> Manutenções
                <?php if($stats['manutencoes']>0): ?><span class="nav-badge danger"><?= $stats['manutencoes'] ?></span><?php endif; ?>
            </a>
            <a href="<?= BASE_URL ?>/modules/relatorios/index.php" class="nav-item <?= ($activePage??'')=='relatorios'?'active':'' ?>">
                <i class="fas fa-chart-bar" style="color:#33ccaa"></i> Relatórios
            </a>
        </div>

        <?php if(Auth::can('all')): ?>
        <div class="nav-section">
            <div class="nav-section-title">Administração</div>
            <a href="<?= BASE_URL ?>/modules/admin/usuarios.php" class="nav-item <?= ($activePage??'')=='admin'?'active':'' ?>">
                <i class="fas fa-user-cog" style="color:#aaaaff"></i> Usuários
            </a>
            <a href="<?= BASE_URL ?>/modules/admin/audit_log.php" class="nav-item <?= ($activePage??'')=='audit_log'?'active':'' ?>">
                <i class="fas fa-history" style="color:#ff9900"></i> Log de Auditoria
            </a>
        </div>
        <?php endif; ?>

    </nav>
</aside>

<!-- MAIN -->
<div class="main-content">
    <!-- HEADER -->
    <header class="app-header">
        <button class="header-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <span class="header-title"><?= e($pageTitle ?? 'Mapa da Rede') ?></span>
        <div class="header-actions">
            <div class="header-btn" title="Notificações" onclick="window.location='<?= BASE_URL ?>/modules/manutencoes/index.php'">
                <i class="fas fa-bell"></i>
                <?php if($stats['manutencoes']>0): ?>
                <span style="position:absolute;top:6px;right:6px;width:8px;height:8px;background:#ff4455;border-radius:50%"></span>
                <?php endif; ?>
            </div>
            <div class="user-badge" onclick="toggleUserMenu()">
                <div class="user-avatar"><?= strtoupper(substr($user['nome'],0,2)) ?></div>
                <div class="user-info">
                    <div class="name"><?= e($user['nome']) ?></div>
                    <div class="role"><?= e($user['perfil']) ?></div>
                </div>
                <i class="fas fa-chevron-down" style="color:var(--text-muted);font-size:11px"></i>
            </div>
        </div>
    </header>

    <!-- User dropdown -->
    <div id="userMenu" style="display:none;position:fixed;top:64px;right:16px;z-index:500;
        background:var(--bg-panel);border:1px solid var(--border);border-radius:12px;
        min-width:180px;box-shadow:0 8px 30px rgba(0,0,0,0.4);overflow:hidden">
        <a href="<?= BASE_URL ?>/modules/admin/perfil.php" style="display:flex;align-items:center;gap:10px;padding:12px 16px;color:var(--text-muted);text-decoration:none;transition:background 0.2s" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background=''">
            <i class="fas fa-user-circle"></i> Meu Perfil
        </a>
        <div style="height:1px;background:var(--border)"></div>
        <a href="<?= BASE_URL ?>/logout.php" style="display:flex;align-items:center;gap:10px;padding:12px 16px;color:var(--danger);text-decoration:none;transition:background 0.2s" onmouseover="this.style.background='rgba(255,68,85,0.08)'" onmouseout="this.style.background=''">
            <i class="fas fa-sign-out-alt"></i> Sair
        </a>
    </div>

<script>
function toggleSidebar() {
    var sidebar  = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebar-backdrop');
    if (window.innerWidth <= 768) {
        // Mobile: abre/fecha como drawer com backdrop
        var isOpen = sidebar.classList.toggle('open');
        backdrop.style.display = isOpen ? 'block' : 'none';
    } else {
        // Desktop: colapsa/expande normalmente
        sidebar.classList.toggle('collapsed');
    }
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebar-backdrop').style.display = 'none';
}
// Fecha o drawer ao clicar em qualquer item de menu no mobile
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.nav-item').forEach(function (el) {
        el.addEventListener('click', function () {
            if (window.innerWidth <= 768) closeSidebar();
        });
    });
    // Fecha se redimensionar para desktop com drawer aberto
    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) closeSidebar();
    });
});
</script>
