<?php
/**
 * CRUDHelper — Funções reutilizáveis para módulos de listagem/gestão.
 * Elimina código duplicado dos módulos: delete handler, flash messages,
 * page header, search form e botão de exclusão.
 */

/**
 * Processa DELETE via POST e redireciona. Aceita queries extras antes do delete principal.
 * @param string $table       Tabela principal
 * @param array  $extraQueries [['sql'=>'...','params'=>[...]], ...]  executadas antes
 */
function handleDelete(string $table, array $extraQueries = []): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['delete_id'])) return;
    $id = (int)$_POST['delete_id'];
    $db = Database::getInstance();
    $old = $db->fetch("SELECT * FROM $table WHERE id = ?", [$id]) ?? [];
    $label = $old['codigo'] ?? $old['nome'] ?? $old['titulo'] ?? "#$id";
    foreach ($extraQueries as $q) {
        $db->query($q['sql'], array_map(fn($v) => $v === ':id' ? $id : $v, $q['params']));
    }
    $db->query("DELETE FROM $table WHERE id = ?", [$id]);
    AuditLog::log('deletar', $table, $id, ucfirst($table).' '.$label.' removido', $old);
    header('Location: ?deleted=1');
    exit;
}

/**
 * Exibe banners de feedback (saved/deleted) se presentes na URL.
 */
function flashMessages(): void
{
    if (isset($_GET['deleted'])) {
        echo '<div style="background:rgba(255,68,85,.1);border:1px solid rgba(255,68,85,.3);border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#ff4455">'
           . '<i class="fas fa-check-circle"></i> Registro removido com sucesso.</div>';
    }
    if (isset($_GET['saved'])) {
        echo '<div style="background:rgba(0,204,102,.1);border:1px solid rgba(0,204,102,.3);border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#00cc66">'
           . '<i class="fas fa-check-circle"></i> Registro salvo com sucesso.</div>';
    }
}

/**
 * Renderiza o cabeçalho padrão da página (título, contagem, busca e botões).
 * @param string $title      Título da página (ex: "Postes")
 * @param string $icon       Classe do ícone FontAwesome (ex: "fa-border-all")
 * @param string $color      Cor hex do ícone (ex: "#aaa")
 * @param int    $count      Número de registros
 * @param string $noun       Substantivo para a contagem (ex: "postes cadastrados")
 * @param string $createUrl  URL do botão "Novo" (vazio = não exibe botão)
 * @param string $createLabel Label do botão "Novo" (ex: "Novo Poste")
 * @param string $searchHtml HTML extra dentro do form de busca (ex: select de filtro)
 * @param string $extraBtns  HTML de botões extras após o botão de criar
 */
function pageHeader(
    string $title,
    string $icon,
    string $color,
    int    $count,
    string $noun,
    string $createUrl  = '',
    string $createLabel= '',
    string $searchHtml = '',
    string $extraBtns  = ''
): void {
    $search = e($_GET['q'] ?? '');
    echo '<div class="page-header">';
    echo '<div>';
    echo '<h2><i class="fas ' . $icon . '" style="color:' . $color . '"></i> ' . $title . '</h2>';
    echo '<p>' . $count . ' ' . $noun . '</p>';
    echo '</div>';
    echo '<div style="display:flex;gap:10px;align-items:center">';
    echo '<form style="display:flex;gap:8px">';
    echo '<input class="form-control" name="q" value="' . $search . '" placeholder="Buscar..." style="width:200px">';
    echo $searchHtml;
    echo '<button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i></button>';
    echo '</form>';
    if ($createUrl) {
        echo '<a href="' . $createUrl . '" class="btn btn-primary"><i class="fas fa-plus"></i> ' . $createLabel . '</a>';
    }
    echo $extraBtns;
    echo '</div></div>';
}

/**
 * Renderiza o botão de exclusão (formulário POST) para uso dentro de tabelas.
 * @param int    $id       ID do registro
 * @param string $confirm  Mensagem de confirmação
 */
function deleteButton(int $id, string $confirm = 'Remover este registro?'): void
{
    if (!Auth::can('all')) return;
    $safeConfirm = htmlspecialchars($confirm, ENT_QUOTES);
    echo '<form method="POST" onsubmit="return confirm(\'' . $safeConfirm . '\')" style="display:inline">';
    echo '<input type="hidden" name="delete_id" value="' . $id . '">';
    echo '<button type="submit" class="btn btn-icon btn-danger" title="Remover"><i class="fas fa-trash"></i></button>';
    echo '</form>';
}

/**
 * Wrap padrão para o conteúdo de uma tabela de listagem.
 * Abre a div — o chamador fecha com tableClose().
 */
function tableOpen(): void
{
    echo '<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden">';
    echo '<table class="data-table">';
}

function tableClose(): void
{
    echo '</table></div>';
}
