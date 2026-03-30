<?php
require_once __DIR__ . '/config/config.php';

// Se já logado, redireciona
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new Auth();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($auth->login($username, $password)) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    } else {
        $error = 'Usuário ou senha inválidos.';
    }
}

$timeout = isset($_GET['timeout']) ? 'Sua sessão expirou. Faça login novamente.' : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — FTTH Network Manager</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div class="login-bg">
        <div class="login-particles" id="particles"></div>
        <div class="login-box">
            <div class="login-logo">
                <div class="logo-icon">
                    <i class="fas fa-network-wired"></i>
                </div>
                <h1>FTTH Network</h1>
                <p>Gestão de Redes de Fibra Óptica</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($timeout): ?>
                <div class="alert alert-warning"><i class="fas fa-clock"></i> <?= htmlspecialchars($timeout) ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Usuário</label>
                    <input type="text" id="username" name="username" placeholder="Digite seu usuário"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Senha</label>
                    <div class="input-pass">
                        <input type="password" id="password" name="password" placeholder="Digite sua senha" required>
                        <button type="button" class="toggle-pass" onclick="togglePassword()">
                            <i class="fas fa-eye" id="eye-icon"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Entrar
                </button>
            </form>

            <div class="login-footer">
                <span><i class="fas fa-shield-alt"></i> Conexão segura</span>
                <span>v<?= APP_VERSION ?></span>
            </div>
        </div>
    </div>

    <script>
    function togglePassword() {
        const input = document.getElementById('password');
        const icon = document.getElementById('eye-icon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }

    // Partículas de fundo
    const container = document.getElementById('particles');
    for (let i = 0; i < 20; i++) {
        const p = document.createElement('div');
        p.className = 'particle';
        p.style.cssText = `
            left: ${Math.random()*100}%;
            top: ${Math.random()*100}%;
            width: ${Math.random()*4+2}px;
            height: ${Math.random()*4+2}px;
            animation-delay: ${Math.random()*5}s;
            animation-duration: ${Math.random()*10+10}s;
        `;
        container.appendChild(p);
    }
    </script>
</body>
</html>
