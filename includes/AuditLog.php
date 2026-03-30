<?php
/**
 * AuditLog — Registra todas as ações de criação, edição e exclusão.
 * Uso: AuditLog::log('editar', 'ctos', $id, 'CTO-001 editada', $dadosAntes, $dadosNovos);
 */
class AuditLog {
    public static function log(
        string $acao,
        string $tabela     = '',
        int    $registroId = 0,
        string $descricao  = '',
        array  $dadosAnt   = [],
        array  $dadosNov   = []
    ): void {
        try {
            $db   = Database::getInstance();
            $user = Auth::user();
            // Remover campos sensíveis antes de logar
            unset($dadosAnt['senha'], $dadosNov['senha']);
            $db->insert('audit_logs', [
                'usuario_id'       => $user ? (int)$user['id'] : null,
                'usuario_nome'     => $user ? ($user['nome'] ?? '') : 'Sistema',
                'acao'             => $acao,
                'tabela'           => $tabela ?: null,
                'registro_id'      => $registroId ?: null,
                'descricao'        => $descricao,
                'dados_anteriores' => $dadosAnt ? json_encode($dadosAnt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'dados_novos'      => $dadosNov ? json_encode($dadosNov, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'ip'               => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
        } catch (Throwable $e) {
            // Nunca deixar falha de log quebrar a aplicação
        }
    }
}
