<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

handleDelete('cabos', [
    ['sql' => 'DELETE FROM cabo_pontos  WHERE cabo_id = ?',                        'params' => [':id']],
    ['sql' => 'DELETE FROM cabo_reservas WHERE cabo_id = ?',                       'params' => [':id']],
    ['sql' => 'DELETE FROM fusoes WHERE cabo_entrada_id = ? OR cabo_saida_id = ?', 'params' => [':id', ':id']],
    ['sql' => 'UPDATE rack_conexoes SET cabo_id = NULL, fibra_num = NULL WHERE cabo_id = ?', 'params' => [':id']],
]);

$pageTitle = 'Cabos';
$activePage = 'cabos';
require_once __DIR__ . '/../../includes/header.php';

$search = $_GET['q'] ?? '';
$sql = "SELECT c.*, u.nome as criado_por,
    (SELECT COUNT(*) FROM cabo_pontos cp WHERE cp.cabo_id = c.id) as total_pontos
    FROM cabos c LEFT JOIN usuarios u ON u.id = c.created_by";
$params = [];
if ($search) { $sql .= " WHERE c.codigo LIKE ? OR c.nome LIKE ?"; $params = ["%$search%","%$search%"]; }
$sql .= " ORDER BY c.created_at DESC";
$cabos = $db->fetchAll($sql, $params);
?>
<div class="page-content">
<?php pageHeader('Cabos','fa-minus','#3399ff',count($cabos),'cabos cadastrados',
    BASE_URL.'/modules/cabos/edit.php','Novo Cabo','',
    '<a href="'.BASE_URL.'/dashboard.php" class="btn btn-secondary"><i class="fas fa-map"></i> Ver no Mapa</a>');
flashMessages(); ?>
<?php tableOpen() ?>
    <thead><tr>
        <th>Código</th><th>Tipo</th><th>Fibras</th><th>Comprimento</th>
        <th>Pontos</th><th>Status</th><th>Cadastrado por</th><th style="width:120px">Ações</th>
    </tr></thead>
    <tbody>
    <?php if (empty($cabos)): ?>
    <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">
        <i class="fas fa-minus" style="font-size:32px;margin-bottom:10px;display:block;opacity:0.3"></i>Nenhum cabo cadastrado</td></tr>
    <?php endif; ?>
    <?php foreach ($cabos as $c): ?>
    <tr>
        <td><strong><?= e($c['codigo']) ?></strong><?= $c['nome'] ? '<br><small style="color:var(--text-muted)">'.e($c['nome']).'</small>' : '' ?></td>
        <td><?= e(ucfirst($c['tipo'])) ?></td>
        <td><?= $c['num_fibras'] ?> FO</td>
        <td><?= $c['comprimento_m'] ? number_format($c['comprimento_m'],0,',','.').' m' : '—' ?></td>
        <td><?= $c['total_pontos'] ?></td>
        <td><?= formatStatus($c['status']) ?></td>
        <td><?= e($c['criado_por'] ?? '—') ?></td>
        <td><div style="display:flex;gap:6px">
            <a href="<?= BASE_URL ?>/modules/cabos/view.php?id=<?= $c['id'] ?>" class="btn btn-icon btn-secondary" title="Ver"><i class="fas fa-eye"></i></a>
            <a href="<?= BASE_URL ?>/modules/cabos/edit.php?id=<?= $c['id'] ?>" class="btn btn-icon btn-primary" title="Editar"><i class="fas fa-edit"></i></a>
            <?php deleteButton($c['id'], 'Remover cabo '.$c['codigo'].' e todos os seus pontos?') ?>
        </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
<?php tableClose() ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
