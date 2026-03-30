<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

$id = (int)($_GET['id'] ?? 0);
$manutencao = $id ? $db->fetch("SELECT * FROM manutencoes WHERE id = ?", [$id]) : null;
$isEdit = $manutencao !== null;

// Pre-fill from GET params (called from view pages)
$tipo_pre  = $_GET['tipo'] ?? $manutencao['tipo_elemento'] ?? '';
$elem_pre  = (int)($_GET['id'] ?? $manutencao['elemento_id'] ?? 0);
// Reparse: if called as edit?id=X that's the manutenção id; if called as edit?tipo=cto&id=5 it's pre-fill
if (!$isEdit && isset($_GET['tipo'])) {
    $elem_pre = (int)($_GET['id'] ?? 0);
}

$tecnicos = $db->fetchAll("SELECT id, nome FROM usuarios WHERE ativo = 1 ORDER BY nome");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'tipo_elemento'    => $_POST['tipo_elemento'],
        'elemento_id'      => (int)$_POST['elemento_id'],
        'tipo_ocorrencia'  => $_POST['tipo_ocorrencia'],
        'descricao'        => $_POST['descricao'],
        'prioridade'       => $_POST['prioridade'],
        'status'           => $_POST['status'],
        'tecnico_id'       => $_POST['tecnico_id'] ?: null,
        'data_ocorrencia'  => $_POST['data_ocorrencia'],
        'data_resolucao'   => $_POST['data_resolucao'] ?: null,
        'observacoes'      => $_POST['observacoes'] ?? '',
    ];
    if ($isEdit) {
        $db->update('manutencoes', $data, 'id = ?', [$id]);
        AuditLog::log('editar', 'manutencoes', $id, 'Manutenção #'.$id.' editada', $manutencao, $data);
    } else {
        $data['created_by'] = Auth::user()['id'];
        $newId = $db->insert('manutencoes', $data);
        AuditLog::log('criar', 'manutencoes', $newId, 'Manutenção registrada: '.$data['tipo_ocorrencia'], [], $data);
    }
    // Redirect back to origin element view if we know it
    $tipo_el = $data['tipo_elemento'];
    $elem_id = $data['elemento_id'];
    $urls = [
        'cto'      => BASE_URL.'/modules/ctos/view.php?id='.$elem_id,
        'ceo'      => BASE_URL.'/modules/ceos/view.php?id='.$elem_id,
        'olt'      => BASE_URL.'/modules/olts/view.php?id='.$elem_id,
        'cliente'  => BASE_URL.'/modules/clientes/view.php?id='.$elem_id,
        'poste'    => BASE_URL.'/modules/postes/view.php?id='.$elem_id,
    ];
    $redirect = $urls[$tipo_el] ?? BASE_URL.'/modules/manutencoes/index.php';
    header('Location: '.$redirect.'?saved=1'); exit;
}

$pageTitle = $isEdit ? 'Editar Manutenção' : 'Nova Manutenção';
$activePage = 'manutencoes';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-content">
    <div class="page-header">
        <div><h2><i class="fas fa-tools" style="color:#ff6655"></i> <?= $isEdit ? 'Editar Manutenção #'.$id : 'Registrar Manutenção' ?></h2></div>
        <a href="<?= BASE_URL ?>/modules/manutencoes/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>

    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:28px;max-width:860px">
        <form method="POST">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

                <div class="form-group"><label class="form-label">Tipo de Elemento *</label>
                    <select class="form-control" name="tipo_elemento" required>
                        <option value="">Selecione...</option>
                        <?php foreach(['cto'=>'CTO','ceo'=>'CEO','olt'=>'OLT','poste'=>'Poste','cabo'=>'Cabo','splitter'=>'Splitter','cliente'=>'Cliente'] as $val=>$lbl): ?>
                        <option value="<?= $val ?>" <?= ($manutencao['tipo_elemento']??$tipo_pre)===$val?'selected':'' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group"><label class="form-label">ID do Elemento *</label>
                    <input class="form-control" name="elemento_id" type="number" required value="<?= $manutencao['elemento_id'] ?? $elem_pre ?>" placeholder="ID do registro">
                </div>

                <div class="form-group"><label class="form-label">Tipo de Ocorrência *</label>
                    <select class="form-control" name="tipo_ocorrencia" required>
                        <option value="">Selecione...</option>
                        <?php foreach([
                            'corte'       => 'Corte de fibra',
                            'fusao'       => 'Fusão',
                            'substituicao'=> 'Substituição de equipamento',
                            'instalacao'  => 'Instalação',
                            'medicao'     => 'Medição de sinal',
                            'visita'      => 'Visita técnica',
                            'outros'      => 'Outros',
                        ] as $val=>$lbl): ?>
                        <option value="<?= $val ?>" <?= ($manutencao['tipo_ocorrencia']??'')===$val?'selected':'' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group"><label class="form-label">Prioridade</label>
                    <select class="form-control" name="prioridade">
                        <?php foreach(['baixa'=>'Baixa','media'=>'Média','alta'=>'Alta','critica'=>'Crítica'] as $val=>$lbl): ?>
                        <option value="<?= $val ?>" <?= ($manutencao['prioridade']??'media')===$val?'selected':'' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group"><label class="form-label">Status</label>
                    <select class="form-control" name="status">
                        <?php foreach(['aberto'=>'Aberto','em_andamento'=>'Em andamento','resolvido'=>'Resolvido'] as $val=>$lbl): ?>
                        <option value="<?= $val ?>" <?= ($manutencao['status']??'aberto')===$val?'selected':'' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group"><label class="form-label">Técnico Responsável</label>
                    <select class="form-control" name="tecnico_id">
                        <option value="">Nenhum / A definir</option>
                        <?php foreach($tecnicos as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= ($manutencao['tecnico_id']??'')==$t['id']?'selected':'' ?>><?= e($t['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group"><label class="form-label">Data da Ocorrência *</label>
                    <input class="form-control" name="data_ocorrencia" type="datetime-local" required
                        value="<?= $manutencao ? date('Y-m-d\TH:i', strtotime($manutencao['data_ocorrencia'])) : date('Y-m-d\TH:i') ?>">
                </div>

                <div class="form-group"><label class="form-label">Data de Resolução</label>
                    <input class="form-control" name="data_resolucao" type="datetime-local"
                        value="<?= $manutencao && $manutencao['data_resolucao'] ? date('Y-m-d\TH:i', strtotime($manutencao['data_resolucao'])) : '' ?>">
                </div>

                <div class="form-group full"><label class="form-label">Descrição *</label>
                    <textarea class="form-control" name="descricao" rows="4" required><?= e($manutencao['descricao'] ?? '') ?></textarea>
                </div>

                <div class="form-group full"><label class="form-label">Observações</label>
                    <textarea class="form-control" name="observacoes" rows="3"><?= e($manutencao['observacoes'] ?? '') ?></textarea>
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:20px">
                <a href="<?= BASE_URL ?>/modules/manutencoes/index.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
