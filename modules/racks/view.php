<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

$id = (int)($_GET['id'] ?? 0);
$rack = $db->fetch("SELECT * FROM racks WHERE id = ?", [$id]);
if (!$rack) { header('Location: ' . BASE_URL . '/modules/racks/index.php'); exit; }

// Auto-migrate: add 'rack' to cabo_pontos.elemento_tipo enum
try { $db->query("ALTER TABLE cabo_pontos MODIFY elemento_tipo ENUM('poste','ceo','cto','rack')"); } catch(Exception $e){}

// Handle DIO delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_dio_id'])) {
    $dioId = (int)$_POST['delete_dio_id'];
    $db->query("DELETE FROM rack_conexoes WHERE dio_id = ?", [$dioId]);
    $db->query("DELETE FROM dios WHERE id = ? AND rack_id = ?", [$dioId, $id]);
    header('Location: ?id='.$id.'&deleted=1'); exit;
}

// Handle DIO save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo_dio'])) {
    $dioData = [
        'rack_id'         => $id,
        'codigo'          => $_POST['codigo_dio'],
        'nome'            => $_POST['nome_dio'] ?: null,
        'capacidade_portas'=> (int)$_POST['capacidade_portas'],
        'tipo_conector'   => $_POST['tipo_conector'],
        'posicao_u'       => $_POST['posicao_u'] !== '' ? (int)$_POST['posicao_u'] : null,
        'observacoes'     => $_POST['obs_dio'] ?: null,
    ];
    $dioId = (int)($_POST['dio_edit_id'] ?? 0);
    if ($dioId) {
        $db->update('dios', $dioData, 'id = ?', [$dioId]);
    } else {
        $dioData['created_by'] = Auth::user()['id'];
        $db->insert('dios', $dioData);
    }
    header('Location: ?id='.$id.'&saved=1'); exit;
}

$cabosVinculados = $db->fetchAll(
    "SELECT c.id, c.codigo, c.nome, c.num_fibras, c.tipo, c.status FROM cabos c
     INNER JOIN cabo_pontos cp ON cp.cabo_id = c.id
     WHERE cp.elemento_tipo = 'rack' AND cp.elemento_id = ?
     ORDER BY c.codigo ASC", [$id]);

$dios = $db->fetchAll("SELECT d.*,
    (SELECT COUNT(*) FROM rack_conexoes rc WHERE rc.dio_id = d.id) as total_conexoes
    FROM dios d WHERE d.rack_id = ? ORDER BY d.posicao_u ASC, d.codigo ASC", [$id]);

$olts = $db->fetchAll("SELECT o.*, COUNT(op.id) as total_pons FROM olts o
    LEFT JOIN olt_pons op ON op.olt_id = o.id
    WHERE o.rack_id = ? GROUP BY o.id ORDER BY o.codigo ASC", [$id]);

$pageTitle = 'Rack: ' . $rack['codigo'];
$activePage = 'racks';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-content">
    <div class="page-header">
        <div>
            <h2><i class="fas fa-server" style="color:#aa6600"></i> <?= e($rack['codigo']) ?></h2>
            <p><?= e($rack['nome'] ?: '') ?><?= $rack['localizacao'] ? ' — '.$rack['localizacao'] : '' ?></p>
        </div>
        <div style="display:flex;gap:10px">
            <a href="<?= BASE_URL ?>/modules/racks/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
            <a href="<?= BASE_URL ?>/modules/racks/fusao.php?id=<?= $id ?>" class="btn" style="background:rgba(170,102,0,.2);color:#cc8800;border:1px solid rgba(170,102,0,.3)"><i class="fas fa-project-diagram"></i> Mapa de Conexões</a>
            <a href="<?= BASE_URL ?>/modules/qrcode/view.php?tipo=rack&id=<?= $id ?>" class="btn btn-secondary" title="QR Code"><i class="fas fa-qrcode"></i> QR Code</a>
            <a href="<?= BASE_URL ?>/modules/racks/edit.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Editar</a>
        </div>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success" style="margin-bottom:16px">Item removido.</div>
    <?php endif; ?>
    <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success" style="margin-bottom:16px">Salvo com sucesso.</div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
        <!-- Rack info -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
            <h4 style="margin-bottom:16px;color:var(--text-muted);font-size:12px;text-transform:uppercase;letter-spacing:1px">Informações do Rack</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <?php foreach([
                    'Código'      => e($rack['codigo']),
                    'Status'      => formatStatus($rack['status']),
                    'Nome'        => e($rack['nome'] ?: '—'),
                    'Localização' => e($rack['localizacao'] ?: '—'),
                    'DIOs'        => count($dios),
                    'OLTs'        => count($olts),
                ] as $k => $v): ?>
                <div>
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px"><?= $k ?></div>
                    <div><?= $v ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($rack['observacoes']): ?>
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;margin-bottom:6px">Observações</div>
                <div style="font-size:13px"><?= e($rack['observacoes']) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Add DIO form -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
            <h4 style="margin-bottom:16px;font-size:14px"><i class="fas fa-plus-circle" style="color:#aa6600"></i> Adicionar DIO</h4>
            <form method="POST" id="form-dio">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Código *</label>
                        <input class="form-control" name="codigo_dio" required placeholder="DIO-01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nome</label>
                        <input class="form-control" name="nome_dio" placeholder="DIO Principal">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Capacidade (portas) *</label>
                        <input class="form-control" name="capacidade_portas" type="number" min="1" required value="12" placeholder="12">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo Conector</label>
                        <select class="form-control" name="tipo_conector">
                            <?php foreach(['SC/APC','SC/UPC','LC/UPC','LC/APC','FC/UPC','FC/APC'] as $t): ?>
                            <option value="<?= $t ?>"><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Posição U</label>
                        <input class="form-control" name="posicao_u" type="number" min="1" placeholder="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Observações</label>
                        <input class="form-control" name="obs_dio" placeholder="">
                    </div>
                </div>
                <input type="hidden" name="dio_edit_id" id="dio_edit_id" value="">
                <div style="display:flex;gap:8px;margin-top:10px">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> <span id="btn-dio-label">Adicionar DIO</span></button>
                    <button type="button" class="btn btn-secondary" id="btn-dio-cancel" style="display:none" onclick="cancelEditDio()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- DIOs list -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:24px">
        <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px">
            <h4 style="font-size:15px;flex:1"><i class="fas fa-th-large" style="color:#aa6600"></i> DIOs cadastrados (<?= count($dios) ?>)</h4>
        </div>
        <?php if (empty($dios)): ?>
        <p style="color:var(--text-muted);font-size:13px;padding:24px">Nenhum DIO cadastrado. Adicione acima.</p>
        <?php else: ?>
        <table class="data-table">
            <thead><tr>
                <th>Código</th><th>Nome</th><th>Capacidade</th><th>Tipo Conector</th><th>Posição U</th><th>Conexões</th><th>Ações</th>
            </tr></thead>
            <tbody>
            <?php foreach ($dios as $dio): ?>
            <tr>
                <td><strong style="color:#cc8800"><?= e($dio['codigo']) ?></strong></td>
                <td><?= e($dio['nome'] ?: '—') ?></td>
                <td><?= $dio['capacidade_portas'] ?> portas</td>
                <td style="color:var(--text-muted)"><?= e($dio['tipo_conector']) ?></td>
                <td><?= $dio['posicao_u'] ? 'U'.$dio['posicao_u'] : '—' ?></td>
                <td><?= $dio['total_conexoes'] ?> / <?= $dio['capacidade_portas'] ?></td>
                <td>
                    <div style="display:flex;gap:6px">
                        <button class="btn btn-icon btn-primary" title="Editar"
                            onclick="editDio(<?= $dio['id'] ?>,'<?= addslashes($dio['codigo']) ?>','<?= addslashes($dio['nome']??'') ?>',<?= $dio['capacidade_portas'] ?>,'<?= $dio['tipo_conector'] ?>',<?= $dio['posicao_u'] ?? 'null' ?>,'<?= addslashes($dio['observacoes']??'') ?>')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if(Auth::can('all')): ?>
                        <form method="POST" onsubmit="return confirm('Remover DIO e todas as suas conexões?')" style="display:inline">
                            <input type="hidden" name="delete_dio_id" value="<?= $dio['id'] ?>">
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

    <!-- Cables linked to this rack -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:24px">
        <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px">
            <h4 style="font-size:15px;flex:1"><i class="fas fa-minus" style="color:#3399ff"></i> Cabos vinculados (<?= count($cabosVinculados) ?>)</h4>
            <span style="font-size:12px;color:var(--text-muted)">Vincule cabos arrastando-os até este rack no mapa de rede</span>
        </div>
        <?php if (empty($cabosVinculados)): ?>
        <p style="color:var(--text-muted);font-size:13px;padding:24px">Nenhum cabo vinculado. No mapa de rede, arraste a extremidade de um cabo até este rack para vinculá-lo.</p>
        <?php else: ?>
        <table class="data-table">
            <thead><tr>
                <th>Código</th><th>Nome</th><th>Tipo</th><th>Fibras</th><th>Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ($cabosVinculados as $cb): ?>
            <tr>
                <td><strong style="color:#3399ff"><?= e($cb['codigo']) ?></strong></td>
                <td><?= e($cb['nome'] ?: '—') ?></td>
                <td style="color:var(--text-muted)"><?= e($cb['tipo']) ?></td>
                <td><?= $cb['num_fibras'] ?> FO</td>
                <td><?= formatStatus($cb['status']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- OLTs in this rack -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden">
        <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px">
            <h4 style="font-size:15px;flex:1"><i class="fas fa-server" style="color:#ff6600"></i> OLTs neste Rack (<?= count($olts) ?>)</h4>
            <a href="<?= BASE_URL ?>/modules/olts/edit.php" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Nova OLT</a>
        </div>
        <?php if (empty($olts)): ?>
        <p style="color:var(--text-muted);font-size:13px;padding:24px">Nenhuma OLT associada a este rack. Ao criar/editar uma OLT, selecione este rack.</p>
        <?php else: ?>
        <table class="data-table">
            <thead><tr>
                <th>Código</th><th>Nome</th><th>IP Gerência</th><th>Fabricante / Modelo</th><th>PONs</th><th>Status</th><th>Ações</th>
            </tr></thead>
            <tbody>
            <?php foreach ($olts as $olt): ?>
            <tr>
                <td><strong><?= e($olt['codigo']) ?></strong></td>
                <td><?= e($olt['nome']) ?></td>
                <td style="color:var(--text-muted);font-family:monospace"><?= e($olt['ip_gerencia'] ?: '—') ?></td>
                <td><?= e(($olt['fabricante'] ? $olt['fabricante'].' ' : '') . ($olt['modelo'] ?: '')) ?: '—' ?></td>
                <td><?= $olt['total_pons'] ?> PON(s)</td>
                <td><?= formatStatus($olt['status']) ?></td>
                <td>
                    <div style="display:flex;gap:6px">
                        <a href="<?= BASE_URL ?>/modules/olts/view.php?id=<?= $olt['id'] ?>" class="btn btn-icon btn-secondary" title="Visualizar"><i class="fas fa-eye"></i></a>
                        <a href="<?= BASE_URL ?>/modules/olts/edit.php?id=<?= $olt['id'] ?>" class="btn btn-icon btn-primary" title="Editar"><i class="fas fa-edit"></i></a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
function editDio(id, codigo, nome, cap, conector, posU, obs) {
    document.getElementById('dio_edit_id').value = id;
    document.querySelector('[name=codigo_dio]').value = codigo;
    document.querySelector('[name=nome_dio]').value = nome;
    document.querySelector('[name=capacidade_portas]').value = cap;
    document.querySelector('[name=tipo_conector]').value = conector;
    document.querySelector('[name=posicao_u]').value = posU || '';
    document.querySelector('[name=obs_dio]').value = obs;
    document.getElementById('btn-dio-label').textContent = 'Salvar Alterações';
    document.getElementById('btn-dio-cancel').style.display = '';
    document.getElementById('form-dio').scrollIntoView({behavior:'smooth'});
}
function cancelEditDio() {
    document.getElementById('dio_edit_id').value = '';
    document.getElementById('form-dio').reset();
    document.querySelector('[name=capacidade_portas]').value = 12;
    document.getElementById('btn-dio-label').textContent = 'Adicionar DIO';
    document.getElementById('btn-dio-cancel').style.display = 'none';
}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
