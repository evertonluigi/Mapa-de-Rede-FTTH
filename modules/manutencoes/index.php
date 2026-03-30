<?php
$pageTitle = 'Manutenções';
$activePage = 'manutencoes';
require_once __DIR__ . '/../../includes/header.php';
$db = Database::getInstance();

handleDelete('manutencoes');

$search   = $_GET['q'] ?? '';
$status_f = $_GET['status'] ?? '';
$tipo_f   = $_GET['tipo'] ?? '';
$sql = "SELECT m.*, u.nome as tecnico_nome FROM manutencoes m LEFT JOIN usuarios u ON u.id = m.tecnico_id WHERE 1=1";
$params = [];
if ($search)   { $sql .= " AND (m.descricao LIKE ? OR m.tipo_ocorrencia LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($status_f) { $sql .= " AND m.status = ?"; $params[] = $status_f; }
if ($tipo_f)   { $sql .= " AND m.tipo_elemento = ?"; $params[] = $tipo_f; }
$sql .= " ORDER BY m.data_ocorrencia DESC";
$manutencoes = $db->fetchAll($sql, $params);

$filterHtml = '<select class="form-control" name="status" style="width:130px">'
    . '<option value="">Todos status</option>';
foreach (['aberto','em_andamento','concluido','cancelado'] as $s) {
    $filterHtml .= '<option value="'.$s.'" '.($status_f===$s?'selected':'').'>'.ucfirst(str_replace('_',' ',$s)).'</option>';
}
$filterHtml .= '</select><select class="form-control" name="tipo" style="width:130px"><option value="">Todos tipos</option>';
foreach (['cto','ceo','olt','poste','cabo','cliente','splitter'] as $t) {
    $filterHtml .= '<option value="'.$t.'" '.($tipo_f===$t?'selected':'').'>'.ucfirst($t).'</option>';
}
$filterHtml .= '</select>';
?>
<div class="page-content">
<?php pageHeader('Manutenções','fa-tools','#ff6655',count($manutencoes),'registros',
    BASE_URL.'/modules/manutencoes/edit.php','Nova', $filterHtml);
flashMessages(); ?>
<?php tableOpen() ?>
    <thead><tr>
        <th>Ocorrência</th><th>Elemento</th><th>Técnico</th>
        <th>Data</th><th>Prioridade</th><th>Status</th><th>Ações</th>
    </tr></thead>
    <tbody>
    <?php if (empty($manutencoes)): ?>
    <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">
        <i class="fas fa-tools" style="font-size:32px;display:block;opacity:0.3;margin-bottom:10px"></i>Nenhuma manutenção registrada</td></tr>
    <?php endif; ?>
    <?php foreach ($manutencoes as $m):
        $prioColors = ['critica'=>'#ff4455','alta'=>'#ffaa00','media'=>'#00ccff','baixa'=>'#888'];
        $prioColor  = $prioColors[$m['prioridade']] ?? '#888';
    ?>
    <tr>
        <td>
            <strong><?= e(ucfirst(str_replace('_',' ',$m['tipo_ocorrencia']))) ?></strong><br>
            <small style="color:var(--text-muted)"><?= e(mb_substr($m['descricao'],0,60)) ?>…</small>
        </td>
        <td>
            <span style="background:rgba(255,255,255,.07);padding:3px 8px;border-radius:4px;font-size:12px;text-transform:uppercase"><?= e($m['tipo_elemento']) ?></span>
            #<?= $m['elemento_id'] ?>
        </td>
        <td><?= e($m['tecnico_nome']?:'—') ?></td>
        <td style="font-size:12px"><?= date('d/m/Y H:i', strtotime($m['data_ocorrencia'])) ?></td>
        <td><span style="color:<?= $prioColor ?>;font-size:12px;font-weight:600;text-transform:uppercase"><?= e($m['prioridade']?:'—') ?></span></td>
        <td><?= formatStatus($m['status']) ?></td>
        <td><div style="display:flex;gap:6px">
            <a href="<?= BASE_URL ?>/modules/manutencoes/edit.php?id=<?= $m['id'] ?>" class="btn btn-icon btn-primary"><i class="fas fa-edit"></i></a>
            <?php deleteButton($m['id'], 'Remover manutenção?') ?>
        </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
<?php tableClose() ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
