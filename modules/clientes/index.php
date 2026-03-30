<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

handleDelete('clientes');

$pageTitle = 'Clientes';
$activePage = 'clientes';
require_once __DIR__ . '/../../includes/header.php';

$search = $_GET['q'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sql = "SELECT cl.*, c.codigo as cto_codigo FROM clientes cl LEFT JOIN ctos c ON c.id = cl.cto_id WHERE 1=1";
$params = [];
if ($search) { $sql .= " AND (cl.nome LIKE ? OR cl.cpf_cnpj LIKE ? OR cl.serial_onu LIKE ? OR cl.numero_contrato LIKE ?)"; $params = array_fill(0,4,"%$search%"); }
if ($status_filter) { $sql .= " AND cl.status = ?"; $params[] = $status_filter; }
$sql .= " ORDER BY cl.nome ASC";
$clientes = $db->fetchAll($sql, $params);

$statusSelect = '<select class="form-control" name="status" style="width:130px">'
    . '<option value="">Todos status</option>';
foreach (['ativo','suspenso','cancelado','instalacao'] as $s) {
    $statusSelect .= '<option value="'.$s.'" '.($status_filter===$s?'selected':'').'>'.ucfirst($s).'</option>';
}
$statusSelect .= '</select>';
?>
<div class="page-content">
<?php pageHeader('Clientes / ONUs','fa-users','#00ccff',count($clientes),'clientes',
    BASE_URL.'/modules/clientes/edit.php','Novo Cliente', $statusSelect);
flashMessages(); ?>
<?php tableOpen() ?>
    <thead><tr>
        <th>Nome</th><th>Contrato</th><th>Telefone</th><th>CTO</th>
        <th>Porta</th><th>ONU Serial</th><th>Plano</th><th>Status</th><th>Ações</th>
    </tr></thead>
    <tbody>
    <?php if (empty($clientes)): ?>
    <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)">Nenhum cliente cadastrado</td></tr>
    <?php endif; ?>
    <?php foreach ($clientes as $cl): ?>
    <tr>
        <td><strong><?= e($cl['nome']) ?></strong><br><small style="color:var(--text-muted)"><?= e($cl['cpf_cnpj']?:'') ?></small></td>
        <td><?= e($cl['numero_contrato']?:'—') ?></td>
        <td><?= e($cl['telefone']?:'—') ?></td>
        <td><?= e($cl['cto_codigo']?:'—') ?></td>
        <td><?= e($cl['porta_cto']?:'—') ?></td>
        <td style="font-size:12px;font-family:monospace"><?= e($cl['serial_onu']?:'—') ?></td>
        <td><?= e($cl['plano']?:'—') ?></td>
        <td><?= formatStatus($cl['status']) ?></td>
        <td><div style="display:flex;gap:6px">
            <a href="<?= BASE_URL ?>/modules/clientes/view.php?id=<?= $cl['id'] ?>" class="btn btn-icon btn-secondary"><i class="fas fa-eye"></i></a>
            <a href="<?= BASE_URL ?>/modules/clientes/edit.php?id=<?= $cl['id'] ?>" class="btn btn-icon btn-primary"><i class="fas fa-edit"></i></a>
            <?php deleteButton($cl['id'], 'Remover cliente?') ?>
        </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
<?php tableClose() ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
