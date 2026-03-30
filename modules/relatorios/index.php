<?php
$pageTitle = 'Relatórios';
$activePage = 'relatorios';
require_once __DIR__ . '/../../includes/header.php';
$db = Database::getInstance();

// Aggregate stats
$stats = $db->fetch("SELECT
    (SELECT COUNT(*) FROM postes) as total_postes,
    (SELECT COUNT(*) FROM postes WHERE status='ativo') as postes_ativos,
    (SELECT COUNT(*) FROM ctos) as total_ctos,
    (SELECT COUNT(*) FROM ctos WHERE status='ativo') as ctos_ativas,
    (SELECT COUNT(*) FROM ceos) as total_ceos,
    (SELECT COUNT(*) FROM clientes) as total_clientes,
    (SELECT COUNT(*) FROM clientes WHERE status='ativo') as clientes_ativos,
    (SELECT COUNT(*) FROM clientes WHERE status='suspenso') as clientes_suspensos,
    (SELECT COUNT(*) FROM clientes WHERE status='cancelado') as clientes_cancelados,
    (SELECT COUNT(*) FROM olts) as total_olts,
    (SELECT COUNT(*) FROM splitters) as total_splitters,
    (SELECT COUNT(*) FROM manutencoes) as total_manutencoes,
    (SELECT COUNT(*) FROM manutencoes WHERE status='aberto') as manut_abertas,
    (SELECT COUNT(*) FROM manutencoes WHERE status='em_andamento') as manut_andamento,
    (SELECT COUNT(*) FROM fusoes) as total_fusoes
");

// Ocupação média CTOs
$ocupacao = $db->fetch("SELECT
    AVG(CASE WHEN capacidade_portas > 0 THEN
        (SELECT COUNT(*) FROM clientes cl WHERE cl.cto_id = c.id AND cl.status='ativo') * 100.0 / capacidade_portas
    ELSE 0 END) as media_ocupacao
    FROM ctos c WHERE c.status = 'ativo'");

// CTOs mais cheias
$ctosCheias = $db->fetchAll("SELECT c.codigo, c.nome, c.capacidade_portas,
    (SELECT COUNT(*) FROM clientes cl WHERE cl.cto_id = c.id AND cl.status='ativo') as ativos
    FROM ctos c WHERE c.status='ativo'
    ORDER BY ativos DESC LIMIT 10");

// Manutenções por tipo
$manutTipo = $db->fetchAll("SELECT tipo_ocorrencia, COUNT(*) as total FROM manutencoes GROUP BY tipo_ocorrencia ORDER BY total DESC LIMIT 8");

// Clientes por CTO
$clientesCto = $db->fetchAll("SELECT ct.codigo, COUNT(cl.id) as total
    FROM ctos ct LEFT JOIN clientes cl ON cl.cto_id = ct.id AND cl.status='ativo'
    GROUP BY ct.id, ct.codigo ORDER BY total DESC LIMIT 10");

// Manutencoes recentes abertas
$manutRecentes = $db->fetchAll("SELECT m.*, u.nome as tecnico FROM manutencoes m
    LEFT JOIN usuarios u ON u.id = m.tecnico_id
    WHERE m.status IN ('aberto','em_andamento')
    ORDER BY m.data_ocorrencia DESC LIMIT 10");
?>

<div class="page-content">
    <div class="page-header">
        <div><h2><i class="fas fa-chart-bar" style="color:#33ccaa"></i> Relatórios e Estatísticas</h2>
        <p>Visão geral da rede FTTH</p></div>
    </div>

    <!-- Stats cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:28px">
        <?php
        $cards = [
            ['Clientes Ativos',   $stats['clientes_ativos'],   'fas fa-users',          '#00ccff'],
            ['CTOs Ativas',       $stats['ctos_ativas'],       'fas fa-box-open',        '#00cc66'],
            ['CEOs',              $stats['total_ceos'],        'fas fa-box',             '#9933ff'],
            ['OLTs',              $stats['total_olts'],        'fas fa-server',          '#ff6600'],
            ['Postes',            $stats['postes_ativos'],     'fas fa-border-all',      '#aaaaaa'],
            ['Splitters',         $stats['total_splitters'],   'fas fa-project-diagram', '#ffcc00'],
            ['Fusões',            $stats['total_fusoes'],      'fas fa-sitemap',         '#ff9900'],
            ['Manutenções Abertas',$stats['manut_abertas'],   'fas fa-tools',           '#ff4455'],
        ];
        foreach ($cards as [$label, $value, $icon, $color]): ?>
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:20px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                <div style="width:38px;height:38px;background:rgba(255,255,255,0.06);border-radius:10px;display:flex;align-items:center;justify-content:center">
                    <i class="<?= $icon ?>" style="color:<?= $color ?>"></i>
                </div>
            </div>
            <div style="font-size:28px;font-weight:700;color:<?= $color ?>"><?= number_format($value) ?></div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:4px"><?= $label ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">

        <!-- CTOs mais cheias -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
            <h4 style="margin-bottom:20px;font-size:14px"><i class="fas fa-fire" style="color:#ff4455"></i> CTOs com Maior Ocupação</h4>
            <?php if (empty($ctosCheias)): ?>
            <p style="color:var(--text-muted);font-size:13px">Sem dados.</p>
            <?php else: ?>
            <?php foreach ($ctosCheias as $ct):
                $p = $ct['capacidade_portas'] > 0 ? round($ct['ativos'] / $ct['capacidade_portas'] * 100) : 0;
                $bc = $p >= 100 ? '#ff4455' : ($p >= 80 ? '#ffaa00' : '#00cc66');
            ?>
            <div style="margin-bottom:14px">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:13px">
                    <span><strong><?= e($ct['codigo']) ?></strong> <?= $ct['nome'] ? '— '.e(mb_substr($ct['nome'],0,20)) : '' ?></span>
                    <span style="color:<?= $bc ?>;font-weight:600"><?= $ct['ativos'] ?>/<?= $ct['capacidade_portas'] ?> (<?= $p ?>%)</span>
                </div>
                <div style="background:rgba(255,255,255,0.08);border-radius:3px;height:5px">
                    <div style="width:<?= min($p,100) ?>%;height:100%;background:<?= $bc ?>;border-radius:3px"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($ocupacao['media_ocupacao']): ?>
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);font-size:13px;color:var(--text-muted)">
                Ocupação média das CTOs ativas: <strong style="color:var(--text)"><?= round($ocupacao['media_ocupacao'],1) ?>%</strong>
            </div>
            <?php endif; ?>
        </div>

        <!-- Manutenções por tipo -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
            <h4 style="margin-bottom:20px;font-size:14px"><i class="fas fa-chart-pie" style="color:#33ccaa"></i> Manutenções por Tipo</h4>
            <?php if (empty($manutTipo)): ?>
            <p style="color:var(--text-muted);font-size:13px">Sem dados.</p>
            <?php else:
                $maxMT = max(array_column($manutTipo, 'total'));
                foreach ($manutTipo as $mt):
                    $p = $maxMT > 0 ? round($mt['total'] / $maxMT * 100) : 0;
            ?>
            <div style="margin-bottom:12px">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                    <span><?= e(ucfirst(str_replace('_',' ',$mt['tipo_ocorrencia']))) ?></span>
                    <span style="color:var(--text-muted)"><?= $mt['total'] ?></span>
                </div>
                <div style="background:rgba(255,255,255,0.08);border-radius:3px;height:5px">
                    <div style="width:<?= $p ?>%;height:100%;background:#33ccaa;border-radius:3px"></div>
                </div>
            </div>
            <?php endforeach; endif; ?>

            <!-- Status resumo -->
            <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div style="text-align:center;background:rgba(255,68,85,0.1);border-radius:8px;padding:12px">
                        <div style="font-size:22px;font-weight:700;color:#ff4455"><?= $stats['manut_abertas'] ?></div>
                        <div style="font-size:12px;color:var(--text-muted)">Abertas</div>
                    </div>
                    <div style="text-align:center;background:rgba(255,170,0,0.1);border-radius:8px;padding:12px">
                        <div style="font-size:22px;font-weight:700;color:#ffaa00"><?= $stats['manut_andamento'] ?></div>
                        <div style="font-size:12px;color:var(--text-muted)">Em andamento</div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Clientes por status -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
            <h4 style="margin-bottom:20px;font-size:14px"><i class="fas fa-users" style="color:#00ccff"></i> Clientes por Status</h4>
            <?php
            $clienteStatus = [
                'Ativos'     => [$stats['clientes_ativos'],    '#00cc66'],
                'Suspensos'  => [$stats['clientes_suspensos'], '#ffaa00'],
                'Cancelados' => [$stats['clientes_cancelados'],'#ff4455'],
            ];
            $totalCl = $stats['total_clientes'];
            foreach ($clienteStatus as $label => [$count, $color]):
                $p = $totalCl > 0 ? round($count / $totalCl * 100) : 0;
            ?>
            <div style="margin-bottom:14px">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                    <span><?= $label ?></span>
                    <span style="color:<?= $color ?>;font-weight:600"><?= $count ?> (<?= $p ?>%)</span>
                </div>
                <div style="background:rgba(255,255,255,0.08);border-radius:3px;height:7px">
                    <div style="width:<?= $p ?>%;height:100%;background:<?= $color ?>;border-radius:3px"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <div style="margin-top:12px;font-size:13px;color:var(--text-muted)">Total: <?= $totalCl ?> clientes cadastrados</div>
        </div>

        <!-- Links de relatórios exportáveis -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
            <h4 style="margin-bottom:20px;font-size:14px"><i class="fas fa-file-export" style="color:#33ccaa"></i> Exportar / Acessar</h4>
            <div style="display:flex;flex-direction:column;gap:10px">
                <?php
                $links = [
                    [BASE_URL.'/modules/clientes/index.php',   'fas fa-users',          '#00ccff', 'Lista de Clientes',       'Ver todos os clientes cadastrados'],
                    [BASE_URL.'/modules/ctos/index.php',       'fas fa-box-open',        '#00cc66', 'CTOs',                    'Listar todas as CTOs'],
                    [BASE_URL.'/modules/ceos/index.php',       'fas fa-box',             '#9933ff', 'CEOs',                    'Listar todas as CEOs'],
                    [BASE_URL.'/modules/olts/index.php',       'fas fa-server',          '#ff6600', 'OLTs',                    'Gerenciar OLTs e PONs'],
                    [BASE_URL.'/modules/manutencoes/index.php','fas fa-tools',           '#ff6655', 'Manutenções',             'Histórico completo de manutenções'],
                    [BASE_URL.'/modules/fusoes/index.php',     'fas fa-sitemap',         '#ff9900', 'Mapa de Fusões',          'Visualizar diagrama de fusões por CEO'],
                    [BASE_URL.'/dashboard.php',                'fas fa-map',             '#00b4ff', 'Mapa da Rede',            'Ver mapa geográfico da infraestrutura'],
                ];
                foreach ($links as [$url, $icon, $color, $title, $desc]): ?>
                <a href="<?= $url ?>" style="display:flex;align-items:center;gap:12px;padding:12px;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:10px;text-decoration:none;color:inherit;transition:background 0.2s"
                   onmouseover="this.style.background='rgba(255,255,255,0.07)'" onmouseout="this.style.background='rgba(255,255,255,0.03)'">
                    <div style="width:36px;height:36px;background:rgba(255,255,255,0.06);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="<?= $icon ?>" style="color:<?= $color ?>"></i>
                    </div>
                    <div>
                        <div style="font-size:14px;font-weight:600"><?= $title ?></div>
                        <div style="font-size:12px;color:var(--text-muted)"><?= $desc ?></div>
                    </div>
                    <i class="fas fa-chevron-right" style="color:var(--text-muted);margin-left:auto;font-size:12px"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Manutenções abertas -->
    <?php if (!empty($manutRecentes)): ?>
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h4 style="font-size:14px"><i class="fas fa-exclamation-triangle" style="color:#ffaa00"></i> Manutenções Pendentes</h4>
            <a href="<?= BASE_URL ?>/modules/manutencoes/index.php?status=aberto" class="btn btn-sm btn-secondary">Ver todas</a>
        </div>
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead><tr><th>Ocorrência</th><th>Elemento</th><th>Prioridade</th><th>Técnico</th><th>Data</th><th>Status</th><th>Ação</th></tr></thead>
                <tbody>
                <?php foreach ($manutRecentes as $m):
                    $prioColors = ['critica'=>'#ff4455','alta'=>'#ffaa00','media'=>'#00ccff','baixa'=>'#888'];
                    $pc = $prioColors[$m['prioridade']] ?? '#888';
                ?>
                <tr>
                    <td><strong><?= e(ucfirst(str_replace('_',' ',$m['tipo_ocorrencia']))) ?></strong></td>
                    <td><span style="font-size:12px;background:rgba(255,255,255,0.07);padding:2px 7px;border-radius:4px;text-transform:uppercase"><?= e($m['tipo_elemento']) ?></span> #<?= $m['elemento_id'] ?></td>
                    <td><span style="color:<?= $pc ?>;font-weight:600;font-size:12px;text-transform:uppercase"><?= e($m['prioridade']?:'—') ?></span></td>
                    <td><?= e($m['tecnico_nome']?:'—') ?></td>
                    <td style="font-size:12px"><?= date('d/m/Y H:i',strtotime($m['data_ocorrencia'])) ?></td>
                    <td><?= formatStatus($m['status']) ?></td>
                    <td><a href="<?= BASE_URL ?>/modules/manutencoes/edit.php?id=<?= $m['id'] ?>" class="btn btn-icon btn-primary"><i class="fas fa-edit"></i></a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
