<?php
$pageTitle = 'Postes';
$activePage = 'postes';
require_once __DIR__ . '/../../includes/header.php';
$db = Database::getInstance();

handleDelete('postes');

$search = $_GET['q'] ?? '';
$sql = "SELECT p.*, u.nome as criado_por FROM postes p LEFT JOIN usuarios u ON u.id = p.created_by";
$params = [];
if ($search) { $sql .= " WHERE p.codigo LIKE ? OR p.proprietario LIKE ?"; $params = ["%$search%","%$search%"]; }
$sql .= " ORDER BY p.created_at DESC";
$postes = $db->fetchAll($sql, $params);
?>
<div class="page-content">
<?php pageHeader('Postes','fa-border-all','#aaa',count($postes),'postes cadastrados',
    BASE_URL.'/modules/postes/edit.php','Novo Poste','',
    '<a href="'.BASE_URL.'/dashboard.php" class="btn btn-secondary"><i class="fas fa-map"></i> Ver no Mapa</a>');
flashMessages(); ?>
<?php tableOpen() ?>
    <thead><tr>
        <th>Código</th><th>Tipo</th><th>Proprietário</th><th>Altura</th>
        <th>Status</th><th>Coordenadas</th><th>Cadastrado por</th><th style="width:120px">Ações</th>
    </tr></thead>
    <tbody>
    <?php if (empty($postes)): ?>
    <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">
        <i class="fas fa-border-all" style="font-size:32px;margin-bottom:10px;display:block;opacity:0.3"></i>
        Nenhum poste cadastrado</td></tr>
    <?php endif; ?>
    <?php foreach ($postes as $p): ?>
    <tr>
        <td><strong><?= e($p['codigo']) ?></strong></td>
        <td><?= e(ucfirst($p['tipo'])) ?></td>
        <td><?= e($p['proprietario']) ?></td>
        <td><?= $p['altura_m'] ? e($p['altura_m']).'m' : '—' ?></td>
        <td><?= formatStatus($p['status']) ?></td>
        <td style="font-size:12px;color:var(--text-muted)"><?= round((float)$p['lat'],5) ?>, <?= round((float)$p['lng'],5) ?></td>
        <td><?= e($p['criado_por'] ?? '—') ?></td>
        <td><div style="display:flex;gap:6px">
            <a href="<?= BASE_URL ?>/modules/postes/view.php?id=<?= $p['id'] ?>" class="btn btn-icon btn-secondary" title="Ver"><i class="fas fa-eye"></i></a>
            <a href="<?= BASE_URL ?>/modules/postes/edit.php?id=<?= $p['id'] ?>" class="btn btn-icon btn-primary" title="Editar"><i class="fas fa-edit"></i></a>
            <?php deleteButton($p['id'], 'Remover poste '.$p['codigo'].'?') ?>
        </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
<?php tableClose() ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
