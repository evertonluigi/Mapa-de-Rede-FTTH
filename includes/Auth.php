<?php
class Auth {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function login(string $username, string $password): bool {
        $user = $this->db->fetch(
            "SELECT u.*, p.nome as perfil_nome, p.permissoes FROM usuarios u
             JOIN perfis p ON u.perfil_id = p.id
             WHERE (u.username = ? OR u.email = ?) AND u.ativo = 1",
            [$username, $username]
        );

        if (!$user || !password_verify($password, $user['senha'])) {
            return false;
        }

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_nome']  = $user['nome'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_perfil']= $user['perfil_nome'];
        $_SESSION['permissoes'] = json_decode($user['permissoes'], true);
        $_SESSION['login_time'] = time();

        $this->db->query(
            "UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?",
            [$user['id']]
        );

        $this->db->insert('logs_acesso', [
            'usuario_id' => $user['id'],
            'acao'       => 'login',
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);

        AuditLog::log('login', '', $user['id'], 'Login: '.$user['nome']);

        return true;
    }

    public function logout(): void {
        if (isset($_SESSION['user_id'])) {
            $this->db->insert('logs_acesso', [
                'usuario_id' => $_SESSION['user_id'],
                'acao'       => 'logout',
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
            AuditLog::log('logout', '', $_SESSION['user_id'], 'Logout: '.($_SESSION['user_nome'] ?? ''));
        }
        session_destroy();
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }

    public static function check(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
        // Timeout de sessão
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_LIFETIME) {
            session_destroy();
            header('Location: ' . BASE_URL . '/index.php?timeout=1');
            exit;
        }
    }

    public static function user(): array {
        return [
            'id'      => $_SESSION['user_id'] ?? 0,
            'nome'    => $_SESSION['user_nome'] ?? '',
            'perfil'  => $_SESSION['user_perfil'] ?? '',
            'perms'   => $_SESSION['permissoes'] ?? [],
        ];
    }

    public static function can(string $action): bool {
        $perms = $_SESSION['permissoes'] ?? [];
        return isset($perms['all']) || isset($perms[$action]);
    }
}
