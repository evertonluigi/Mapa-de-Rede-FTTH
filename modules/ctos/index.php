<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

handleDelete('ctos', [
    // Desvincula clientes da CTO (não apaga o cliente, apenas remove o vínculo)
    ['sql' => 'UPDATE clientes SET cto_id = NULL, porta_cto = NULL WHERE cto_id = ?', 'params' => [':id']],
    // Remove splitters associados
    ['sql' => 'DELETE FROM elemento_splitters WHERE elem_tipo = ? AND elem_id = ?', 'params' => ['cto', ':id']],
]);

$pageTitle = 'CTOs — Caixas Terminais';
$activePage = 'ctos';
require_once __DIR__ . '/../../includes/header.php';

$search = $_GET['q'] ?? '';
$sql = "SELECT c.*, p.codigo as poste_codigo,
    (SELECT COUNT(*) FROM clientes cl WHERE cl.cto_id = c.id AND cl.status='ativo') as clientes_ativos
    FROM ctos c LEFT JOIN postes p ON p.id = c.poste_id";
$params = [];
if ($search) { $sql .= " WHERE c.codigo LIKE ? OR c.nome LIKE ?"; $params = ["%$search%","%$search%"]; }
$sql .= " ORDER BY c.created_at DESC";
$ctos = $db->fetchAll($sql, $params);
?>
<div class="page-content">
<?php pageHeader('CTOs — Caixas Terminais','fa-box-open','#00cc66',count($ctos),'CTOs cadastradas',
    BASE_URL.'/modules/ctos/edit.php','Nova CTO','',
    '<a href="'.BASE_URL.'/dashboard.php" class="btn btn-secondary"><i class="fas fa-map"></i> Mapa</a>');
flashMessages(); ?>
<?php tableOpen() ?>
    <thead><tr>
        <th>Código</th><th>Nome</th><th>Tipo</th><th>Capacidade</th>
        <th>Ocupação</th><th>Status</th><th>Poste</th><th style="width:120px">Ações</th>
    </tr></thead>
    <tbody>
    <?php if (empty($ctos)): ?>
    <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">
        <i class="fas fa-box-open" style="font-size:32px;display:block;opacity:0.3;margin-bottom:10px"></i>Nenhuma CTO cadastrada</td></tr>
    <?php endif; ?>
    <?php foreach ($ctos as $c):
        $pct = $c['capacidade_portas'] > 0 ? round(($c['clientes_ativos'] / $c['capacidade_portas']) * 100) : 0;
        $barColor = $pct >= 100 ? '#ff4455' : ($pct >= 80 ? '#ffaa00' : '#00cc66');
    ?>
    <tr>
        <td><strong><?= e($c['codigo']) ?></strong></td>
        <td><?= e($c['nome'] ?: '—') ?></td>
        <td><?= e(ucfirst($c['tipo'])) ?></td>
        <td><?= $c['capacidade_portas'] ?> portas</td>
        <td><div style="display:flex;align-items:center;gap:8px">
            <div style="flex:1;background:rgba(255,255,255,.08);border-radius:3px;height:6px;min-width:60px">
                <div style="width:<?= min($pct,100) ?>%;height:100%;background:<?= $barColor ?>;border-radius:3px"></div>
            </div>
            <span style="font-size:12px;color:var(--text-muted)"><?= $c['clientes_ativos'] ?>/<?= $c['capacidade_portas'] ?></span>
        </div></td>
        <td><?= formatStatus($c['status']) ?></td>
        <td><?= e($c['poste_codigo'] ?: '—') ?></td>
        <td><div style="display:flex;gap:6px">
            <a href="<?= BASE_URL ?>/modules/ctos/view.php?id=<?= $c['id'] ?>" class="btn btn-icon btn-secondary"><i class="fas fa-eye"></i></a>
            <a href="<?= BASE_URL ?>/modules/ctos/edit.php?id=<?= $c['id'] ?>" class="btn btn-icon btn-primary"><i class="fas fa-edit"></i></a>
            <?php deleteButton($c['id'], 'Remover CTO?') ?>
        </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
<?php tableClose() ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
