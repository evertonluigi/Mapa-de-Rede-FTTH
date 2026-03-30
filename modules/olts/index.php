<?php
$pageTitle = 'OLTs';
$activePage = 'olts';
require_once __DIR__ . '/../../includes/header.php';
$db = Database::getInstance();

handleDelete('olts');

$search = $_GET['q'] ?? '';
$sql = "SELECT o.*,
    (SELECT COUNT(*) FROM olt_pons op WHERE op.olt_id = o.id) as total_pons,
    (SELECT COUNT(*) FROM olt_pons op JOIN clientes cl ON cl.olt_pon_id = op.id WHERE op.olt_id = o.id AND cl.status='ativo') as clientes_ativos
    FROM olts o WHERE 1=1";
$params = [];
if ($search) { $sql .= " AND (o.nome LIKE ? OR o.ip_gerencia LIKE ? OR o.fabricante LIKE ?)"; $params = ["%$search%","%$search%","%$search%"]; }
$sql .= " ORDER BY o.nome ASC";
$olts = $db->fetchAll($sql, $params);
?>
<div class="page-content">
<?php pageHeader('OLTs','fa-server','#ff6600',count($olts),'OLTs cadastradas',
    BASE_URL.'/modules/olts/edit.php','Nova OLT');
flashMessages(); ?>
<?php tableOpen() ?>
    <thead><tr>
        <th>Nome</th><th>IP Gerência</th><th>Fabricante</th><th>Modelo</th>
        <th>PONs</th><th>Clientes Ativos</th><th>Status</th><th>Ações</th>
    </tr></thead>
    <tbody>
    <?php if (empty($olts)): ?>
    <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">
        <i class="fas fa-server" style="font-size:32px;display:block;opacity:0.3;margin-bottom:10px"></i>Nenhuma OLT cadastrada</td></tr>
    <?php endif; ?>
    <?php foreach ($olts as $o): ?>
    <tr>
        <td><strong><?= e($o['nome']) ?></strong></td>
        <td style="font-family:monospace;font-size:13px"><?= e($o['ip_gerencia']?:'—') ?></td>
        <td><?= e($o['fabricante']?:'—') ?></td>
        <td><?= e($o['modelo']?:'—') ?></td>
        <td><?= $o['total_pons'] ?></td>
        <td><?= $o['clientes_ativos'] ?></td>
        <td><?= formatStatus($o['status']) ?></td>
        <td><div style="display:flex;gap:6px">
            <a href="<?= BASE_URL ?>/modules/olts/view.php?id=<?= $o['id'] ?>" class="btn btn-icon btn-secondary"><i class="fas fa-eye"></i></a>
            <a href="<?= BASE_URL ?>/modules/olts/edit.php?id=<?= $o['id'] ?>" class="btn btn-icon btn-primary"><i class="fas fa-edit"></i></a>
            <?php deleteButton($o['id'], 'Remover OLT e todas as suas PONs?') ?>
        </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
<?php tableClose() ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
