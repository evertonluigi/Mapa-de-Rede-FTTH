<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

handleDelete('splitters');

$pageTitle = 'Splitters';
$activePage = 'splitters';
require_once __DIR__ . '/../../includes/header.php';

$search = $_GET['q'] ?? '';
$sql = "SELECT s.*, p.codigo as poste_codigo FROM splitters s LEFT JOIN postes p ON p.id = s.poste_id WHERE 1=1";
$params = [];
if ($search) { $sql .= " AND (s.codigo LIKE ? OR s.nome LIKE ?)"; $params = ["%$search%","%$search%"]; }
$sql .= " ORDER BY s.created_at DESC";
$splitters = $db->fetchAll($sql, $params);
?>
<div class="page-content">
<?php pageHeader('Splitters','fa-project-diagram','#ffcc00',count($splitters),'splitters cadastrados',
    BASE_URL.'/modules/splitters/edit.php','Novo Splitter');
flashMessages(); ?>
<?php tableOpen() ?>
    <thead><tr>
        <th>Código</th><th>Nome</th><th>Tipo</th><th>Relação</th>
        <th>Nível</th><th>Status</th><th>Poste</th><th>Ações</th>
    </tr></thead>
    <tbody>
    <?php if (empty($splitters)): ?>
    <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">
        <i class="fas fa-project-diagram" style="font-size:32px;display:block;opacity:0.3;margin-bottom:10px"></i>Nenhum splitter cadastrado</td></tr>
    <?php endif; ?>
    <?php foreach ($splitters as $s): ?>
    <tr>
        <td><strong><?= e($s['codigo']) ?></strong></td>
        <td><?= e($s['nome']?:'—') ?></td>
        <td><?= e(ucfirst($s['tipo']??'')) ?></td>
        <td><?= e($s['relacao']?:'—') ?></td>
        <td><?= e($s['nivel']?:'—') ?></td>
        <td><?= formatStatus($s['status']) ?></td>
        <td><?= e($s['poste_codigo']?:'—') ?></td>
        <td><div style="display:flex;gap:6px">
            <a href="<?= BASE_URL ?>/modules/splitters/edit.php?id=<?= $s['id'] ?>" class="btn btn-icon btn-primary"><i class="fas fa-edit"></i></a>
            <?php deleteButton($s['id'], 'Remover splitter?') ?>
        </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
<?php tableClose() ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
