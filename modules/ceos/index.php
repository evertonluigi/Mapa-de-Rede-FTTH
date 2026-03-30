<?php
$pageTitle = 'CEOs — Caixas de Emenda';
$activePage = 'ceos';
require_once __DIR__ . '/../../includes/header.php';
$db = Database::getInstance();

handleDelete('ceos');

$search = $_GET['q'] ?? '';
$sql = "SELECT c.*, p.codigo as poste_codigo FROM ceos c LEFT JOIN postes p ON p.id = c.poste_id WHERE 1=1";
$params = [];
if ($search) { $sql .= " AND (c.codigo LIKE ? OR c.nome LIKE ?)"; $params = ["%$search%","%$search%"]; }
$sql .= " ORDER BY c.created_at DESC";
$ceos = $db->fetchAll($sql, $params);
?>
<div class="page-content">
<?php pageHeader('CEOs — Caixas de Emenda','fa-box','#9933ff',count($ceos),'CEOs cadastradas',
    BASE_URL.'/modules/ceos/edit.php','Nova CEO','',
    '<a href="'.BASE_URL.'/modules/fusoes/index.php" class="btn btn-secondary"><i class="fas fa-sitemap"></i> Fusões</a>');
flashMessages(); ?>
<?php tableOpen() ?>
    <thead><tr>
        <th>Código</th><th>Nome</th><th>Tipo</th><th>Capacidade FO</th>
        <th>Status</th><th>Poste</th><th style="width:150px">Ações</th>
    </tr></thead>
    <tbody>
    <?php if (empty($ceos)): ?>
    <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">
        <i class="fas fa-box" style="font-size:32px;display:block;opacity:0.3;margin-bottom:10px"></i>Nenhuma CEO cadastrada</td></tr>
    <?php endif; ?>
    <?php foreach ($ceos as $c): ?>
    <tr>
        <td><strong><?= e($c['codigo']) ?></strong></td>
        <td><?= e($c['nome'] ?: '—') ?></td>
        <td><?= e(ucfirst($c['tipo'])) ?></td>
        <td><?= $c['capacidade_fo'] ?> fibras</td>
        <td><?= formatStatus($c['status']) ?></td>
        <td><?= e($c['poste_codigo'] ?: '—') ?></td>
        <td><div style="display:flex;gap:6px">
            <a href="<?= BASE_URL ?>/modules/ceos/view.php?id=<?= $c['id'] ?>" class="btn btn-icon btn-secondary" title="Ver"><i class="fas fa-eye"></i></a>
            <a href="<?= BASE_URL ?>/modules/fusoes/view.php?ceo_id=<?= $c['id'] ?>" class="btn btn-icon" style="background:rgba(153,51,255,.15);color:#9933ff;border:1px solid rgba(153,51,255,.3)" title="Mapa de Fusões"><i class="fas fa-sitemap"></i></a>
            <a href="<?= BASE_URL ?>/modules/ceos/edit.php?id=<?= $c['id'] ?>" class="btn btn-icon btn-primary" title="Editar"><i class="fas fa-edit"></i></a>
            <?php deleteButton($c['id'], 'Remover CEO?') ?>
        </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
<?php tableClose() ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
