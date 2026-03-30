<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

$id = (int)($_GET['id'] ?? 0);
$olt = $db->fetch("SELECT * FROM olts WHERE id = ?", [$id]);
if (!$olt) { header('Location: ' . BASE_URL . '/modules/olts/index.php'); exit; }

$pons = $db->fetchAll("SELECT op.*,
    (SELECT COUNT(*) FROM clientes cl WHERE cl.olt_pon_id = op.id AND cl.status='ativo') as clientes_ativos
    FROM olt_pons op WHERE op.olt_id = ? ORDER BY op.slot ASC, op.numero_pon ASC", [$id]);

$manutencoes = $db->fetchAll("SELECT m.*, u.nome as tecnico FROM manutencoes m LEFT JOIN usuarios u ON u.id = m.tecnico_id WHERE m.tipo_elemento = 'olt' AND m.elemento_id = ? ORDER BY m.created_at DESC LIMIT 10", [$id]);

// Handle PON delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pon_id'])) {
    $db->query("DELETE FROM olt_pons WHERE id = ? AND olt_id = ?", [(int)$_POST['delete_pon_id'], $id]);
    header('Location: ?id='.$id.'&deleted=1'); exit;
}
// Handle PON save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['numero_pon'])) {
    $ponData = [
        'olt_id'       => $id,
        'slot'         => (int)($_POST['slot'] ?? 1),
        'numero_pon'   => (int)$_POST['numero_pon'],
        'status'       => $_POST['status_pon'],
        'descricao'    => $_POST['descricao_pon'] ?: null,
        'potencia_dbm' => $_POST['potencia_dbm'] !== '' ? (float)$_POST['potencia_dbm'] : 5.00,
    ];
    $ponId = (int)($_POST['pon_edit_id'] ?? 0);
    if ($ponId) { $db->update('olt_pons', $ponData, 'id = ?', [$ponId]); }
    else { $db->insert('olt_pons', $ponData); }
    header('Location: ?id='.$id.'&saved=1'); exit;
}

$pageTitle = 'OLT: ' . $olt['nome'];
$activePage = 'olts';
require_once __DIR__ . '/../../includes/header.php';

$totalClientes = array_sum(array_column($pons, 'clientes_ativos'));
?>

<div class="page-content">
    <div class="page-header">
        <div>
            <h2><i class="fas fa-server" style="color:#ff6600"></i> <?= e($olt['nome']) ?></h2>
            <p><?= e($olt['localizacao'] ?: '') ?> <?= $olt['ip_gerencia'] ? '— '.$olt['ip_gerencia'] : '' ?></p>
        </div>
        <div style="display:flex;gap:10px">
            <a href="<?= BASE_URL ?>/modules/olts/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
            <a href="<?= BASE_URL ?>/modules/qrcode/view.php?tipo=olt&id=<?= $id ?>" class="btn btn-secondary" title="QR Code"><i class="fas fa-qrcode"></i> QR Code</a>
            <a href="<?= BASE_URL ?>/modules/olts/edit.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Editar</a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
        <!-- Info -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
            <h4 style="margin-bottom:16px;color:var(--text-muted);font-size:12px;text-transform:uppercase;letter-spacing:1px">Informações</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <?php foreach([
                    'Status'       => formatStatus($olt['status']),
                    'IP Gerência'  => $olt['ip_gerencia']?:'—',
                    'Fabricante'   => $olt['fabricante']?:'—',
                    'Modelo'       => $olt['modelo']?:'—',
                    'Firmware'     => $olt['firmware']?:'—',
                    'Localização'  => $olt['localizacao']?:'—',
                    'Total PONs'   => count($pons),
                    'Clientes ativos' => $totalClientes,
                ] as $k=>$v): ?>
                <div>
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px"><?= e($k) ?></div>
                    <div><?= $v ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($olt['observacoes']): ?>
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;margin-bottom:6px">Observações</div>
                <div style="font-size:13px"><?= e($olt['observacoes']) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- PON add form -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
            <h4 style="margin-bottom:16px;font-size:14px"><i class="fas fa-plus-circle" style="color:#ff6600"></i> Adicionar Porta PON</h4>
            <form method="POST" id="form-pon">
                <div class="form-grid">
                    <div class="form-group"><label class="form-label">Slot *</label>
                        <input class="form-control" name="slot" type="number" min="1" required value="1" placeholder="1"></div>
                    <div class="form-group"><label class="form-label">Nº da PON *</label>
                        <input class="form-control" name="numero_pon" type="number" min="1" required placeholder="1"></div>
                    <div class="form-group"><label class="form-label">Status</label>
                        <select class="form-control" name="status_pon">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                            <option value="defeito">Defeito</option>
                        </select></div>
                    <div class="form-group"><label class="form-label">Sinal de Saída (dBm)</label>
                        <input class="form-control" name="potencia_dbm" type="number" step="0.01" value="5" placeholder="+5.00"
                               title="Sinal óptico de saída desta porta PON. Ex: 5 = +5dBm, -1 = -1dBm"></div>
                    <div class="form-group full"><label class="form-label">Descrição</label>
                        <input class="form-control" name="descricao_pon" placeholder="Ex: Bairro Norte"></div>
                </div>
                <input type="hidden" name="pon_edit_id" id="pon_edit_id" value="">
                <div style="display:flex;gap:8px;margin-top:10px">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> <span id="btn-pon-label">Adicionar PON</span></button>
                    <button type="button" class="btn btn-secondary" id="btn-pon-cancel" style="display:none" onclick="cancelEditPon()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- PONs list -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:24px">
        <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px">
            <h4 style="font-size:15px;flex:1"><i class="fas fa-plug" style="color:#ff6600"></i> Portas PON cadastradas (<?= count($pons) ?>)</h4>
        </div>
        <?php if (empty($pons)): ?>
        <p style="color:var(--text-muted);font-size:13px;padding:24px">Nenhuma PON cadastrada. Adicione acima.</p>
        <?php else: ?>
        <table class="data-table">
            <thead><tr>
                <th>Slot</th><th>PON</th><th>Sinal Saída</th><th>Status</th><th>Descrição</th><th>Clientes Ativos</th><th>Ações</th>
            </tr></thead>
            <tbody>
            <?php foreach ($pons as $pon): ?>
            <tr>
                <td><strong style="color:#ff6600"><?= $pon['slot'] ?? 1 ?></strong></td>
                <td><strong>PON <?= $pon['numero_pon'] ?></strong></td>
                <td>
                    <?php $dbm = $pon['potencia_dbm'] ?? 5.00; $dbmColor = $dbm >= 0 ? '#00cc66' : ($dbm >= -3 ? '#ffaa00' : '#ff4455'); ?>
                    <span style="font-family:monospace;color:<?= $dbmColor ?>;font-weight:600"><?= ($dbm >= 0 ? '+' : '') . number_format($dbm, 2) ?> dBm</span>
                </td>
                <td><?= formatStatus($pon['status']) ?></td>
                <td style="color:var(--text-muted)"><?= e($pon['descricao'] ?: '—') ?></td>
                <td><i class="fas fa-users" style="color:#ff6600;margin-right:4px"></i><?= $pon['clientes_ativos'] ?></td>
                <td>
                    <div style="display:flex;gap:6px">
                        <button class="btn btn-icon btn-primary" title="Editar"
                            onclick="editPon(<?= $pon['id'] ?>,<?= $pon['slot']??1 ?>,<?= $pon['numero_pon'] ?>,'<?= $pon['status'] ?>','<?= addslashes($pon['descricao']??'') ?>',<?= $pon['potencia_dbm'] ?? 5 ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if(Auth::can('all')): ?>
                        <form method="POST" onsubmit="return confirm('Remover esta PON?')" style="display:inline">
                            <input type="hidden" name="delete_pon_id" value="<?= $pon['id'] ?>">
                            <button type="submit" class="btn btn-icon btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
<script>
function editPon(id, slot, numero, status, descricao, potencia) {
    document.getElementById('pon_edit_id').value = id;
    document.querySelector('[name=slot]').value = slot;
    document.querySelector('[name=numero_pon]').value = numero;
    document.querySelector('[name=status_pon]').value = status;
    document.querySelector('[name=descricao_pon]').value = descricao;
    document.querySelector('[name=potencia_dbm]').value = potencia ?? 5;
    document.getElementById('btn-pon-label').textContent = 'Salvar Alterações';
    document.getElementById('btn-pon-cancel').style.display = '';
    document.getElementById('form-pon').scrollIntoView({behavior:'smooth'});
}
function cancelEditPon() {
    document.getElementById('pon_edit_id').value = '';
    document.getElementById('form-pon').reset();
    document.querySelector('[name=slot]').value = 1;
    document.getElementById('btn-pon-label').textContent = 'Adicionar PON';
    document.getElementById('btn-pon-cancel').style.display = 'none';
}
</script>

    <!-- Manutenções -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h4>Histórico de Manutenções</h4>
            <a href="<?= BASE_URL ?>/modules/manutencoes/edit.php?tipo=olt&id=<?= $id ?>" class="btn btn-sm btn-primary">
                <i class="fas fa-plus"></i> Registrar
            </a>
        </div>
        <?php if (empty($manutencoes)): ?>
        <p style="color:var(--text-muted);font-size:13px">Nenhuma manutenção registrada.</p>
        <?php else: foreach ($manutencoes as $m): ?>
        <div style="padding:12px;background:rgba(255,255,255,0.03);border-radius:8px;margin-bottom:8px">
            <div style="display:flex;justify-content:space-between">
                <strong><?= e(ucfirst($m['tipo_ocorrencia'])) ?></strong><?= formatStatus($m['status']) ?>
            </div>
            <div style="color:var(--text-muted);font-size:12px;margin-top:4px"><?= e($m['tecnico']??'') ?> — <?= date('d/m/Y H:i',strtotime($m['data_ocorrencia'])) ?></div>
            <div style="margin-top:6px;font-size:13px"><?= e($m['descricao']) ?></div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
