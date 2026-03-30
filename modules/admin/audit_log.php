<?php
$pageTitle  = 'Log de Auditoria';
$activePage = 'audit_log';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
if (!Auth::can('all')) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }
$db = Database::getInstance();

// ── Filtros ────────────────────────────────────────────────────────────────────
$filtAcao    = $_GET['acao']    ?? '';
$filtTabela  = $_GET['tabela']  ?? '';
$filtUsuario = $_GET['usuario'] ?? '';
$filtData    = $_GET['data']    ?? '';
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 50;

$where  = ['1=1'];
$params = [];
if ($filtAcao)    { $where[] = 'acao = ?';           $params[] = $filtAcao; }
if ($filtTabela)  { $where[] = 'tabela = ?';          $params[] = $filtTabela; }
if ($filtUsuario) { $where[] = 'usuario_nome LIKE ?'; $params[] = "%$filtUsuario%"; }
if ($filtData)    { $where[] = 'DATE(created_at) = ?';$params[] = $filtData; }

$cond   = implode(' AND ', $where);
$total  = (int)$db->fetch("SELECT COUNT(*) as n FROM audit_logs WHERE $cond", $params)['n'];
$offset = ($page - 1) * $perPage;
$logs   = $db->fetchAll("SELECT * FROM audit_logs WHERE $cond ORDER BY created_at DESC LIMIT $perPage OFFSET $offset", $params);

$totalPages = (int)ceil($total / $perPage);

// Tabelas disponíveis para filtro
$tabelas = array_column($db->fetchAll("SELECT DISTINCT tabela FROM audit_logs WHERE tabela IS NOT NULL ORDER BY tabela"), 'tabela');

require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.audit-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px}
.audit-criar   {background:rgba(0,204,102,.15);color:#00cc66;border:1px solid rgba(0,204,102,.3)}
.audit-editar  {background:rgba(51,153,255,.15);color:#3399ff;border:1px solid rgba(51,153,255,.3)}
.audit-deletar {background:rgba(255,68,85,.15);color:#ff4455;border:1px solid rgba(255,68,85,.3)}
.audit-login   {background:rgba(255,204,0,.12);color:#ffcc00;border:1px solid rgba(255,204,0,.3)}
.audit-logout  {background:rgba(170,170,170,.15);color:#aaa;border:1px solid rgba(170,170,170,.2)}
.audit-outro   {background:rgba(170,100,0,.15);color:#aa6600;border:1px solid rgba(170,100,0,.3)}

.diff-wrap{display:none;background:#0a0e14;border-top:1px solid rgba(255,255,255,.06);padding:14px 16px;font-size:11px;font-family:monospace}
.diff-wrap.open{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.diff-col h6{font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);margin-bottom:8px}
.diff-col pre{white-space:pre-wrap;word-break:break-all;margin:0;color:#ccc;line-height:1.6}
.diff-del{background:rgba(255,68,85,.12);color:#ff8899}
.diff-add{background:rgba(0,204,102,.1);color:#66ffaa}
.audit-row{cursor:pointer}
.audit-row:hover td{background:rgba(255,255,255,.03)}
</style>

<div class="page-content">
    <div class="page-header">
        <div>
            <h2><i class="fas fa-history" style="color:#ff9900"></i> Log de Auditoria</h2>
            <p><?= number_format($total) ?> registros encontrados</p>
        </div>
        <div style="display:flex;gap:10px">
            <a href="<?= BASE_URL ?>/modules/admin/audit_log.php" class="btn btn-secondary"><i class="fas fa-sync"></i> Limpar filtros</a>
        </div>
    </div>

    <!-- Filtros -->
    <form style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:16px 20px;margin-bottom:20px">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;align-items:end">
            <div>
                <label style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);display:block;margin-bottom:5px">Ação</label>
                <select class="form-control" name="acao">
                    <option value="">Todas</option>
                    <?php foreach(['criar','editar','deletar','login','logout','outro'] as $a): ?>
                    <option value="<?= $a ?>" <?= $filtAcao===$a?'selected':'' ?>><?= ucfirst($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);display:block;margin-bottom:5px">Tabela</label>
                <select class="form-control" name="tabela">
                    <option value="">Todas</option>
                    <?php foreach($tabelas as $t): ?>
                    <option value="<?= e($t) ?>" <?= $filtTabela===$t?'selected':'' ?>><?= e($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);display:block;margin-bottom:5px">Usuário</label>
                <input class="form-control" name="usuario" value="<?= e($filtUsuario) ?>" placeholder="Nome...">
            </div>
            <div>
                <label style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);display:block;margin-bottom:5px">Data</label>
                <input class="form-control" name="data" type="date" value="<?= e($filtData) ?>">
            </div>
            <div>
                <button type="submit" class="btn btn-primary" style="width:100%"><i class="fas fa-filter"></i> Filtrar</button>
            </div>
        </div>
        <?php if ($filtAcao || $filtTabela || $filtUsuario || $filtData): ?>
        <div style="margin-top:10px;font-size:12px;color:var(--text-muted)">
            Filtros ativos:
            <?php if($filtAcao): ?><span style="background:rgba(255,255,255,.08);border-radius:4px;padding:2px 8px;margin-left:4px">ação: <?= e($filtAcao) ?></span><?php endif; ?>
            <?php if($filtTabela): ?><span style="background:rgba(255,255,255,.08);border-radius:4px;padding:2px 8px;margin-left:4px">tabela: <?= e($filtTabela) ?></span><?php endif; ?>
            <?php if($filtUsuario): ?><span style="background:rgba(255,255,255,.08);border-radius:4px;padding:2px 8px;margin-left:4px">usuário: <?= e($filtUsuario) ?></span><?php endif; ?>
            <?php if($filtData): ?><span style="background:rgba(255,255,255,.08);border-radius:4px;padding:2px 8px;margin-left:4px">data: <?= e($filtData) ?></span><?php endif; ?>
        </div>
        <?php endif; ?>
    </form>

    <!-- Tabela de logs -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:150px">Data/Hora</th>
                    <th style="width:130px">Usuário</th>
                    <th style="width:90px">Ação</th>
                    <th style="width:100px">Tabela</th>
                    <th style="width:60px">ID</th>
                    <th>Descrição</th>
                    <th style="width:120px">IP</th>
                    <th style="width:40px"></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
            <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">
                <i class="fas fa-history" style="font-size:28px;display:block;opacity:.3;margin-bottom:10px"></i>
                Nenhum registro encontrado
            </td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $i => $log):
                $hasDiff = $log['dados_anteriores'] || $log['dados_novos'];
            ?>
            <tr class="audit-row <?= $hasDiff ? 'has-diff' : '' ?>" onclick="<?= $hasDiff ? "toggleDiff($i)" : '' ?>">
                <td style="font-size:12px;color:var(--text-muted);white-space:nowrap">
                    <?= date('d/m/Y', strtotime($log['created_at'])) ?>
                    <div style="font-size:11px"><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
                </td>
                <td>
                    <div style="font-size:13px;font-weight:500"><?= e($log['usuario_nome'] ?: '—') ?></div>
                    <?php if ($log['usuario_id']): ?><div style="font-size:10px;color:var(--text-muted)">ID <?= $log['usuario_id'] ?></div><?php endif; ?>
                </td>
                <td>
                    <span class="audit-badge audit-<?= $log['acao'] ?>">
                        <?php $icons=['criar'=>'fa-plus','editar'=>'fa-edit','deletar'=>'fa-trash','login'=>'fa-sign-in-alt','logout'=>'fa-sign-out-alt','outro'=>'fa-circle'];?>
                        <i class="fas <?= $icons[$log['acao']] ?? 'fa-circle' ?>"></i>
                        <?= ucfirst($log['acao']) ?>
                    </span>
                </td>
                <td style="font-size:12px;color:var(--text-muted)"><?= e($log['tabela'] ?? '—') ?></td>
                <td style="font-size:12px;color:var(--text-muted)"><?= $log['registro_id'] ?? '—' ?></td>
                <td style="font-size:13px"><?= e($log['descricao']) ?></td>
                <td style="font-size:11px;color:var(--text-muted)"><?= e($log['ip']) ?></td>
                <td style="text-align:center">
                    <?php if ($hasDiff): ?>
                    <i class="fas fa-chevron-down" id="icon-<?= $i ?>" style="font-size:11px;color:var(--text-muted);transition:transform .2s"></i>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($hasDiff): ?>
            <tr id="diff-<?= $i ?>">
                <td colspan="8" style="padding:0">
                    <div class="diff-wrap" id="diffwrap-<?= $i ?>">
                        <div class="diff-col">
                            <h6><i class="fas fa-history"></i> Antes</h6>
                            <pre id="pre-ant-<?= $i ?>"></pre>
                        </div>
                        <div class="diff-col">
                            <h6><i class="fas fa-check"></i> Depois</h6>
                            <pre id="pre-nov-<?= $i ?>"></pre>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginação -->
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;justify-content:center;gap:6px;margin-top:20px;flex-wrap:wrap">
        <?php
        $qs = array_filter(['acao'=>$filtAcao,'tabela'=>$filtTabela,'usuario'=>$filtUsuario,'data'=>$filtData]);
        $base = '?' . http_build_query($qs) . ($qs ? '&' : '');
        for ($p = max(1,$page-3); $p <= min($totalPages,$page+3); $p++): ?>
        <a href="<?= $base ?>page=<?= $p ?>" class="btn btn-sm <?= $p===$page?'btn-primary':'btn-secondary' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($totalPages > $page+3): ?>
        <span style="align-self:center;color:var(--text-muted)">...</span>
        <a href="<?= $base ?>page=<?= $totalPages ?>" class="btn btn-sm btn-secondary"><?= $totalPages ?></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
const LOGS = <?= json_encode(array_map(fn($l) => [
    'ant' => $l['dados_anteriores'] ? json_decode($l['dados_anteriores'], true) : null,
    'nov' => $l['dados_novos']      ? json_decode($l['dados_novos'],      true) : null,
], $logs)) ?>;

function formatJson(obj) {
    if (!obj) return '—';
    return JSON.stringify(obj, null, 2);
}

function highlightDiff(ant, nov) {
    if (!ant || !nov) return [formatJson(ant), formatJson(nov)];
    const antLines = formatJson(ant).split('\n');
    const novLines = formatJson(nov).split('\n');
    const antHtml = antLines.map(l => {
        const key = l.match(/"(\w+)":/)?.[1];
        if (key && ant[key] !== undefined && nov[key] !== undefined && ant[key] !== nov[key]) {
            return `<span class="diff-del">${l}</span>`;
        }
        return l;
    }).join('\n');
    const novHtml = novLines.map(l => {
        const key = l.match(/"(\w+)":/)?.[1];
        if (key && ant[key] !== undefined && nov[key] !== undefined && ant[key] !== nov[key]) {
            return `<span class="diff-add">${l}</span>`;
        }
        return l;
    }).join('\n');
    return [antHtml, novHtml];
}

function toggleDiff(i) {
    const wrap  = document.getElementById('diffwrap-' + i);
    const icon  = document.getElementById('icon-' + i);
    const data  = LOGS[i];
    if (!wrap) return;
    const isOpen = wrap.classList.toggle('open');
    if (icon) icon.style.transform = isOpen ? 'rotate(180deg)' : '';
    if (isOpen && data) {
        const [antHtml, novHtml] = highlightDiff(data.ant, data.nov);
        document.getElementById('pre-ant-' + i).innerHTML = antHtml;
        document.getElementById('pre-nov-' + i).innerHTML = novHtml;
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
