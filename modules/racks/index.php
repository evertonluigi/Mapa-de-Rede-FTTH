<?php
$pageTitle = 'Racks';
$activePage = 'racks';
require_once __DIR__ . '/../../includes/header.php';
$db = Database::getInstance();

handleDelete('racks', [
    ['sql' => 'DELETE FROM rack_conexoes WHERE rack_id = ?', 'params' => [':id']],
    ['sql' => 'DELETE FROM dios WHERE rack_id = ?',          'params' => [':id']],
]);

$search = $_GET['q'] ?? '';
$sql = "SELECT r.*,
    (SELECT COUNT(*) FROM dios d WHERE d.rack_id = r.id) as total_dios,
    (SELECT COUNT(*) FROM olts o WHERE o.rack_id = r.id) as total_olts
    FROM racks r WHERE 1=1";
$params = [];
if ($search) { $sql .= " AND (r.codigo LIKE ? OR r.nome LIKE ? OR r.localizacao LIKE ?)"; $params = ["%$search%","%$search%","%$search%"]; }
$sql .= " ORDER BY r.codigo ASC";
$racks = $db->fetchAll($sql, $params);
?>
<div class="page-content">
<?php pageHeader('Racks / DIOs','fa-th-large','#aa6600',count($racks),'rack(s) cadastrado(s)',
    BASE_URL.'/modules/racks/edit.php','Novo Rack');
flashMessages(); ?>
<?php tableOpen() ?>
    <thead><tr>
        <th>Código</th><th>Nome</th><th>Localização</th>
        <th>DIOs</th><th>OLTs</th><th>Status</th><th>Ações</th>
    </tr></thead>
    <tbody>
    <?php if (empty($racks)): ?>
    <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">
        <i class="fas fa-server" style="font-size:32px;display:block;opacity:0.3;margin-bottom:10px"></i>Nenhum rack cadastrado</td></tr>
    <?php endif; ?>
    <?php foreach ($racks as $r): ?>
    <tr>
        <td><strong><?= e($r['codigo']) ?></strong></td>
        <td><?= e($r['nome'] ?: '—') ?></td>
        <td style="color:var(--text-muted)"><?= e($r['localizacao'] ?: '—') ?></td>
        <td><?= $r['total_dios'] ?> DIO(s)</td>
        <td><?= $r['total_olts'] ?> OLT(s)</td>
        <td><?= formatStatus($r['status']) ?></td>
        <td><div style="display:flex;gap:6px">
            <a href="<?= BASE_URL ?>/modules/racks/view.php?id=<?= $r['id'] ?>" class="btn btn-icon btn-secondary" title="Visualizar"><i class="fas fa-eye"></i></a>
            <a href="<?= BASE_URL ?>/modules/racks/fusao.php?id=<?= $r['id'] ?>" class="btn btn-icon" style="background:rgba(170,102,0,.2);color:#cc8800;border:1px solid rgba(170,102,0,.3)" title="Mapa de Conexões"><i class="fas fa-project-diagram"></i></a>
            <a href="<?= BASE_URL ?>/modules/racks/edit.php?id=<?= $r['id'] ?>" class="btn btn-icon btn-primary" title="Editar"><i class="fas fa-edit"></i></a>
            <?php deleteButton($r['id'], 'Remover rack "'.$r['codigo'].'" e todos os seus dados?') ?>
        </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
<?php tableClose() ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
