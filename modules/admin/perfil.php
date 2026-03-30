<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();
$user = Auth::user();
$u = $db->fetch("SELECT * FROM usuarios WHERE id = ?", [$user['id']]);

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'info';

    if ($action === 'info') {
        $db->update('usuarios', [
            'nome'  => $_POST['nome'],
            'email' => $_POST['email'],
        ], 'id = ?', [$user['id']]);
        $_SESSION['user_nome'] = $_POST['nome'];
        $msg = 'Dados atualizados com sucesso!';
        $u = $db->fetch("SELECT * FROM usuarios WHERE id = ?", [$user['id']]);
    }

    if ($action === 'senha') {
        $atual = $_POST['senha_atual'] ?? '';
        $nova  = $_POST['senha_nova'] ?? '';
        $conf  = $_POST['senha_conf'] ?? '';

        if (!password_verify($atual, $u['senha'])) {
            $error = 'Senha atual incorreta.';
        } elseif (strlen($nova) < 6) {
            $error = 'Nova senha deve ter ao menos 6 caracteres.';
        } elseif ($nova !== $conf) {
            $error = 'A confirmação não coincide com a nova senha.';
        } else {
            $db->update('usuarios', ['senha' => password_hash($nova, PASSWORD_BCRYPT)], 'id = ?', [$user['id']]);
            $msg = 'Senha alterada com sucesso!';
        }
    }
}

$pageTitle = 'Meu Perfil';
$activePage = '';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-content">
    <div class="page-header">
        <div>
            <h2><i class="fas fa-user-circle" style="color:#00b4ff"></i> Meu Perfil</h2>
            <p>Gerencie suas informações e senha de acesso</p>
        </div>
    </div>

    <?php if ($msg): ?>
    <div style="background:rgba(0,204,102,0.1);border:1px solid rgba(0,204,102,0.3);border-radius:10px;padding:14px;margin-bottom:20px;color:#00cc66">
        <i class="fas fa-check-circle"></i> <?= e($msg) ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div style="background:rgba(255,68,85,0.1);border:1px solid rgba(255,68,85,0.3);border-radius:10px;padding:14px;margin-bottom:20px;color:#ff8888">
        <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
        <!-- Dados Pessoais -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:28px">
            <div style="display:flex;align-items:center;gap:16px;margin-bottom:28px">
                <div style="width:64px;height:64px;background:linear-gradient(135deg,#00b4ff,#0066cc);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:700">
                    <?= strtoupper(substr($u['nome'],0,2)) ?>
                </div>
                <div>
                    <div style="font-size:18px;font-weight:700"><?= e($u['nome']) ?></div>
                    <div style="color:var(--text-muted);font-size:13px"><?= e($user['perfil']) ?> — @<?= e($u['username']) ?></div>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="info">
                <div class="form-group" style="margin-bottom:16px">
                    <label class="form-label">Nome Completo</label>
                    <input class="form-control" name="nome" required value="<?= e($u['nome']) ?>">
                </div>
                <div class="form-group" style="margin-bottom:16px">
                    <label class="form-label">E-mail</label>
                    <input class="form-control" name="email" type="email" required value="<?= e($u['email']) ?>">
                </div>
                <div class="form-group" style="margin-bottom:20px">
                    <label class="form-label">Usuário</label>
                    <input class="form-control" value="<?= e($u['username']) ?>" disabled style="opacity:0.5">
                    <div style="font-size:12px;color:var(--text-muted);margin-top:4px">O usuário não pode ser alterado.</div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Dados</button>
            </form>
        </div>

        <!-- Alterar Senha -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:28px">
            <h3 style="font-size:16px;margin-bottom:24px"><i class="fas fa-lock" style="color:#ffaa00"></i> Alterar Senha</h3>
            <form method="POST">
                <input type="hidden" name="action" value="senha">
                <div class="form-group" style="margin-bottom:16px">
                    <label class="form-label">Senha Atual</label>
                    <input class="form-control" name="senha_atual" type="password" required placeholder="••••••••">
                </div>
                <div class="form-group" style="margin-bottom:16px">
                    <label class="form-label">Nova Senha</label>
                    <input class="form-control" name="senha_nova" type="password" required minlength="6" placeholder="Mínimo 6 caracteres">
                </div>
                <div class="form-group" style="margin-bottom:24px">
                    <label class="form-label">Confirmar Nova Senha</label>
                    <input class="form-control" name="senha_conf" type="password" required minlength="6" placeholder="Repita a nova senha">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Alterar Senha</button>
            </form>

            <div style="margin-top:32px;padding-top:24px;border-top:1px solid var(--border)">
                <div style="color:var(--text-muted);font-size:12px;margin-bottom:12px;text-transform:uppercase;letter-spacing:0.5px">Informações da Conta</div>
                <div style="display:grid;gap:8px;font-size:13px">
                    <div style="display:flex;justify-content:space-between">
                        <span style="color:var(--text-muted)">Último acesso</span>
                        <span><?= $u['ultimo_acesso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acesso'])) : 'N/A' ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between">
                        <span style="color:var(--text-muted)">Membro desde</span>
                        <span><?= date('d/m/Y', strtotime($u['created_at'])) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
