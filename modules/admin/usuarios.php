<?php
$pageTitle = 'Gerenciar Usuários';
$activePage = 'admin';
require_once __DIR__ . '/../../includes/header.php';

if (!Auth::can('all')) { redirect('/dashboard.php'); }

$db = Database::getInstance();

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $senha = password_hash($_POST['senha'], PASSWORD_BCRYPT);
        try {
            $db->insert('usuarios', [
                'nome'      => $_POST['nome'],
                'email'     => $_POST['email'],
                'username'  => $_POST['username'],
                'senha'     => $senha,
                'perfil_id' => (int)$_POST['perfil_id'],
                'ativo'     => 1,
            ]);
            header('Location: ?saved=1'); exit;
        } catch (\Exception $e) {
            $error = 'Erro: usuário ou e-mail já existem.';
        }
    }

    if ($action === 'toggle' && isset($_POST['user_id'])) {
        $uid = (int)$_POST['user_id'];
        if ($uid !== Auth::user()['id']) {
            $u = $db->fetch("SELECT ativo FROM usuarios WHERE id = ?", [$uid]);
            $db->update('usuarios', ['ativo' => $u['ativo'] ? 0 : 1], 'id = ?', [$uid]);
        }
        header('Location: ?saved=1'); exit;
    }

    if ($action === 'reset_pass' && isset($_POST['user_id'])) {
        $uid = (int)$_POST['user_id'];
        $nova = $_POST['nova_senha'] ?? '';
        if (strlen($nova) >= 6) {
            $db->update('usuarios', ['senha' => password_hash($nova, PASSWORD_BCRYPT)], 'id = ?', [$uid]);
        }
        header('Location: ?saved=1'); exit;
    }
}

$usuarios = $db->fetchAll("SELECT u.*, p.nome as perfil_nome FROM usuarios u JOIN perfis p ON p.id = u.perfil_id ORDER BY u.nome");
$perfis   = $db->fetchAll("SELECT * FROM perfis ORDER BY id");
?>

<div class="page-content">
    <div class="page-header">
        <div>
            <h2><i class="fas fa-user-cog" style="color:#aaaaff"></i> Usuários</h2>
            <p><?= count($usuarios) ?> usuários cadastrados</p>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('modal-novo-user').classList.add('show')">
            <i class="fas fa-user-plus"></i> Novo Usuário
        </button>
    </div>

    <?php if (isset($error)): ?>
    <div style="background:rgba(255,68,85,0.1);border:1px solid rgba(255,68,85,0.3);border-radius:10px;padding:14px;margin-bottom:20px;color:#ff8888">
        <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
    </div>
    <?php endif; ?>

    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th><th>Usuário</th><th>E-mail</th><th>Perfil</th>
                    <th>Último Acesso</th><th>Status</th><th>Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div style="width:34px;height:34px;background:linear-gradient(135deg,#00b4ff,#0066cc);border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0">
                            <?= strtoupper(substr($u['nome'],0,2)) ?>
                        </div>
                        <strong><?= e($u['nome']) ?></strong>
                    </div>
                </td>
                <td><code style="background:rgba(255,255,255,0.05);padding:2px 8px;border-radius:4px"><?= e($u['username']) ?></code></td>
                <td><?= e($u['email']) ?></td>
                <td>
                    <span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;
                        background:<?= $u['perfil_id']==1?'rgba(0,180,255,0.15)':($u['perfil_id']==2?'rgba(0,204,102,0.15)':'rgba(150,150,150,0.15)') ?>;
                        color:<?= $u['perfil_id']==1?'#00b4ff':($u['perfil_id']==2?'#00cc66':'#888') ?>">
                        <?= e($u['perfil_nome']) ?>
                    </span>
                </td>
                <td style="color:var(--text-muted);font-size:13px">
                    <?= $u['ultimo_acesso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acesso'])) : 'Nunca' ?>
                </td>
                <td>
                    <?php if ($u['ativo']): ?>
                    <span class="status-badge status-ativo"><i class="fas fa-circle"></i> Ativo</span>
                    <?php else: ?>
                    <span class="status-badge status-inativo"><i class="fas fa-circle"></i> Inativo</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display:flex;gap:6px">
                        <!-- Toggle ativo -->
                        <?php if ($u['id'] !== Auth::user()['id']): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $u['ativo'] ? 'btn-danger' : 'btn-success' ?>"
                                title="<?= $u['ativo'] ? 'Desativar' : 'Ativar' ?>">
                                <i class="fas <?= $u['ativo'] ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <!-- Reset senha -->
                        <button class="btn btn-sm btn-secondary" onclick="showResetPass(<?= $u['id'] ?>, '<?= e($u['nome']) ?>')" title="Redefinir Senha">
                            <i class="fas fa-key"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Novo Usuário -->
<div class="modal-overlay" id="modal-novo-user">
    <div class="modal">
        <div class="modal-header">
            <i class="fas fa-user-plus"></i>
            <h3>Novo Usuário</h3>
            <div class="modal-close" onclick="document.getElementById('modal-novo-user').classList.remove('show')"><i class="fas fa-times"></i></div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group full">
                        <label class="form-label">Nome Completo *</label>
                        <input class="form-control" name="nome" required placeholder="João Silva">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Usuário *</label>
                        <input class="form-control" name="username" required placeholder="joaosilva">
                    </div>
                    <div class="form-group">
                        <label class="form-label">E-mail *</label>
                        <input class="form-control" name="email" type="email" required placeholder="email@exemplo.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Perfil</label>
                        <select class="form-control" name="perfil_id">
                            <?php foreach ($perfis as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Senha *</label>
                        <input class="form-control" name="senha" type="password" required minlength="6" placeholder="Mínimo 6 caracteres">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-novo-user').classList.remove('show')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Criar Usuário</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Reset Senha -->
<div class="modal-overlay" id="modal-reset-pass">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <i class="fas fa-key" style="color:#ffaa00"></i>
            <h3>Redefinir Senha</h3>
            <div class="modal-close" onclick="document.getElementById('modal-reset-pass').classList.remove('show')"><i class="fas fa-times"></i></div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reset_pass">
            <input type="hidden" name="user_id" id="reset-user-id">
            <div class="modal-body">
                <p style="color:var(--text-muted);margin-bottom:16px">Redefinir senha de: <strong id="reset-user-nome"></strong></p>
                <div class="form-group">
                    <label class="form-label">Nova Senha *</label>
                    <input class="form-control" name="nova_senha" type="password" required minlength="6" placeholder="Mínimo 6 caracteres">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-reset-pass').classList.remove('show')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function showResetPass(id, nome) {
    document.getElementById('reset-user-id').value = id;
    document.getElementById('reset-user-nome').textContent = nome;
    document.getElementById('modal-reset-pass').classList.add('show');
}
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('show'); });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
