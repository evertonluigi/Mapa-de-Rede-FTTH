<?php
$pageTitle  = 'Dashboard KPIs';
$activePage = 'kpis';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
/* ── KPI Cards ──────────────────────────────────── */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}
.kpi-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px 18px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    position: relative;
    overflow: hidden;
    transition: border-color .2s;
}
.kpi-card.alert-card { border-color: rgba(255,68,85,.4); }
.kpi-card.warn-card  { border-color: rgba(255,170,0,.4); }
.kpi-card.ok-card    { border-color: rgba(0,204,102,.3); }
.kpi-icon {
    width: 38px; height: 38px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px;
}
.kpi-value {
    font-size: 32px; font-weight: 800; line-height: 1;
    letter-spacing: -1px;
}
.kpi-label {
    font-size: 12px; color: var(--text-muted);
    font-weight: 500; text-transform: uppercase; letter-spacing: .5px;
}
.kpi-sub {
    font-size: 11px; color: var(--text-muted); margin-top: 2px;
}
.kpi-glow {
    position: absolute; top: -20px; right: -20px;
    width: 90px; height: 90px; border-radius: 50%;
    opacity: .07; pointer-events: none;
}

/* ── Sections ───────────────────────────────────── */
.kpi-section-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}
@media (max-width: 900px) {
    .kpi-section-grid { grid-template-columns: 1fr; }
}
.kpi-panel {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
}
.kpi-panel-header {
    padding: 14px 18px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    font-size: 13px; font-weight: 600;
}
.kpi-panel-body { padding: 16px 18px; }

/* ── Progress bars ──────────────────────────────── */
.cto-row {
    display: flex; align-items: center; gap: 10px;
    padding: 6px 0; border-bottom: 1px solid rgba(255,255,255,.04);
    font-size: 12px;
}
.cto-row:last-child { border-bottom: none; }
.cto-name { min-width: 90px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cto-bar-wrap { flex: 1; height: 8px; background: rgba(255,255,255,.07); border-radius: 4px; overflow: hidden; }
.cto-bar { height: 100%; border-radius: 4px; transition: width .6s ease; }
.cto-pct { min-width: 38px; text-align: right; font-weight: 700; font-size: 12px; }

/* ── Priority pills ─────────────────────────────── */
.priority-wrap {
    display: flex; gap: 12px; padding: 12px 0;
}
.priority-block {
    flex: 1; text-align: center; padding: 16px 8px;
    border-radius: 12px; border: 1px solid;
}
.priority-block .pb-num { font-size: 36px; font-weight: 800; line-height: 1; }
.priority-block .pb-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; margin-top: 4px; }
.p-alta  { background: rgba(255,68,85,.1);  border-color: rgba(255,68,85,.3);  color: #ff4455; }
.p-media { background: rgba(255,170,0,.1);  border-color: rgba(255,170,0,.3);  color: #ffaa00; }
.p-baixa { background: rgba(100,200,100,.1);border-color: rgba(100,200,100,.25);color: #66cc66; }

/* ── Chart ──────────────────────────────────────── */
.chart-wrap { position: relative; height: 200px; }

/* ── Table ──────────────────────────────────────── */
.kpi-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.kpi-table th { color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .5px; padding: 6px 8px; text-align: left; border-bottom: 1px solid var(--border); }
.kpi-table td { padding: 7px 8px; border-bottom: 1px solid rgba(255,255,255,.04); vertical-align: middle; }
.kpi-table tr:last-child td { border-bottom: none; }

/* ── Badges ─────────────────────────────────────── */
.badge-alta  { background:rgba(255,68,85,.15);color:#ff4455;border:1px solid rgba(255,68,85,.3);  padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase; }
.badge-media { background:rgba(255,170,0,.15);color:#ffaa00;border:1px solid rgba(255,170,0,.3);  padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase; }
.badge-baixa { background:rgba(100,200,100,.12);color:#66cc66;border:1px solid rgba(100,200,100,.25);padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase; }
.badge-aberto      { background:rgba(255,170,0,.15);color:#ffaa00;padding:2px 7px;border-radius:10px;font-size:10px; }
.badge-em_andamento{ background:rgba(0,180,255,.15);color:#00b4ff;padding:2px 7px;border-radius:10px;font-size:10px; }

/* ── Sinais ─────────────────────────────────────── */
.sinal-bar-wrap { flex:1; height:6px; background:rgba(255,255,255,.07); border-radius:3px; overflow:hidden; }
.sinal-bar { height:100%; border-radius:3px; }

/* ── Refresh indicator ──────────────────────────── */
.refresh-indicator {
    display: flex; align-items: center; gap: 6px;
    font-size: 11px; color: var(--text-muted);
}
.refresh-dot {
    width: 7px; height: 7px; border-radius: 50%;
    background: #00cc66;
    animation: pulse-dot 2s ease-in-out infinite;
}
@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: .4; transform: scale(.7); }
}
</style>

<div class="page-content">

    <div class="page-header">
        <div>
            <h2><i class="fas fa-tachometer-alt" style="color:#9933ff"></i> Dashboard KPIs</h2>
            <p>Indicadores em tempo real da rede FTTH</p>
        </div>
        <div style="display:flex;align-items:center;gap:14px">
            <div class="refresh-indicator">
                <div class="refresh-dot" id="refresh-dot"></div>
                <span>Atualizado às <strong id="last-update">--:--:--</strong></span>
            </div>
            <button class="btn btn-secondary btn-sm" onclick="loadKpis()" id="btn-refresh">
                <i class="fas fa-sync-alt"></i> Atualizar
            </button>
        </div>
    </div>

    <!-- ── KPI Cards ─────────────────────────────── -->
    <div class="kpi-grid" id="kpi-cards">
        <!-- preenchido via JS -->
        <?php for ($i = 0; $i < 6; $i++): ?>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:rgba(255,255,255,.05)"><i class="fas fa-spinner fa-spin" style="color:var(--text-muted)"></i></div>
            <div class="kpi-value" style="color:var(--text-muted)">—</div>
            <div class="kpi-label">Carregando...</div>
        </div>
        <?php endfor; ?>
    </div>

    <!-- ── Linha 1: CTOs + Manutenções ──────────── -->
    <div class="kpi-section-grid">

        <!-- CTOs Ocupação -->
        <div class="kpi-panel">
            <div class="kpi-panel-header">
                <span><i class="fas fa-box-open" style="color:#00cc66;margin-right:7px"></i>Ocupação das CTOs</span>
                <span id="cto-summary" style="font-size:11px;color:var(--text-muted)"></span>
            </div>
            <div class="kpi-panel-body" id="cto-list" style="max-height:300px;overflow-y:auto">
                <div style="color:var(--text-muted);font-size:13px;text-align:center;padding:20px">
                    <i class="fas fa-spinner fa-spin"></i> Carregando...
                </div>
            </div>
        </div>

        <!-- Manutenções por Prioridade + Recentes -->
        <div class="kpi-panel">
            <div class="kpi-panel-header">
                <span><i class="fas fa-tools" style="color:#ff6655;margin-right:7px"></i>Manutenções em Aberto</span>
                <a href="<?= BASE_URL ?>/modules/manutencoes/index.php" style="font-size:11px;color:#00b4ff;text-decoration:none">Ver todas</a>
            </div>
            <div class="kpi-panel-body">
                <div class="priority-wrap" id="priority-blocks">
                    <div class="priority-block p-alta"><div class="pb-num">—</div><div class="pb-label">Alta</div></div>
                    <div class="priority-block p-media"><div class="pb-num">—</div><div class="pb-label">Média</div></div>
                    <div class="priority-block p-baixa"><div class="pb-num">—</div><div class="pb-label">Baixa</div></div>
                </div>
                <div id="manut-list" style="margin-top:8px;max-height:180px;overflow-y:auto">
                    <div style="color:var(--text-muted);font-size:13px;text-align:center;padding:12px">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ── Linha 2: Crescimento + Sinais Críticos ─ -->
    <div class="kpi-section-grid">

        <!-- Crescimento Mensal -->
        <div class="kpi-panel">
            <div class="kpi-panel-header">
                <span><i class="fas fa-chart-line" style="color:#00ccff;margin-right:7px"></i>Crescimento de Clientes (últimos 6 meses)</span>
            </div>
            <div class="kpi-panel-body">
                <div class="chart-wrap">
                    <canvas id="chart-crescimento"></canvas>
                </div>
            </div>
        </div>

        <!-- Piores Sinais -->
        <div class="kpi-panel">
            <div class="kpi-panel-header">
                <span><i class="fas fa-signal" style="color:#ff4455;margin-right:7px"></i>Clientes com Sinal Crítico (&lt; -27 dBm)</span>
                <a href="<?= BASE_URL ?>/modules/clientes/index.php" style="font-size:11px;color:#00b4ff;text-decoration:none">Ver clientes</a>
            </div>
            <div class="kpi-panel-body" id="sinais-list">
                <div style="color:var(--text-muted);font-size:13px;text-align:center;padding:20px">
                    <i class="fas fa-spinner fa-spin"></i> Carregando...
                </div>
            </div>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const BASE_URL = '<?= BASE_URL ?>';
let chartCrescimento = null;
let refreshTimer = null;
const REFRESH_INTERVAL = 120000; // 2 minutos

// Utilitário para cor de ocupação
function corOcupacao(pct) {
    if (pct >= 90) return '#ff4455';
    if (pct >= 80) return '#ff9900';
    if (pct >= 60) return '#ffcc00';
    return '#00cc66';
}

// Utilitário para cor de sinal
function corSinal(dbm) {
    if (dbm >= -20) return '#00cc66';
    if (dbm >= -25) return '#ffcc00';
    if (dbm >= -27) return '#ff9900';
    return '#ff4455';
}

function renderKpiCards(resumo) {
    const cards = [
        {
            icon: 'fas fa-users', color: '#00ccff',
            value: resumo.clientes_ativos,
            label: 'Clientes Ativos',
            sub: resumo.crescimento_mes > 0 ? `+${resumo.crescimento_mes} este mês` : 'Nenhum novo este mês',
            cls: ''
        },
        {
            icon: 'fas fa-box-open', color: resumo.ctos_lotadas_80 > 0 ? '#ff9900' : '#00cc66',
            value: resumo.ctos_lotadas_80,
            label: 'CTOs acima de 80%',
            sub: 'Ocupação crítica',
            cls: resumo.ctos_lotadas_80 > 0 ? 'warn-card' : 'ok-card'
        },
        {
            icon: 'fas fa-signal', color: resumo.sinal_critico > 0 ? '#ff4455' : '#00cc66',
            value: resumo.sinal_critico,
            label: 'Sinal Crítico',
            sub: 'Abaixo de -27 dBm',
            cls: resumo.sinal_critico > 0 ? 'alert-card' : 'ok-card'
        },
        {
            icon: 'fas fa-tools', color: resumo.manut_abertas > 0 ? '#ff6655' : '#00cc66',
            value: resumo.manut_abertas,
            label: 'Manutenções Abertas',
            sub: 'Aberto + Em andamento',
            cls: resumo.manut_abertas > 0 ? 'warn-card' : 'ok-card'
        },
        {
            icon: 'fas fa-wifi', color: resumo.disponibilidade >= 95 ? '#00cc66' : resumo.disponibilidade >= 85 ? '#ffcc00' : '#ff4455',
            value: resumo.disponibilidade + '%',
            label: 'Disponibilidade',
            sub: 'Clientes ativos / total',
            cls: resumo.disponibilidade >= 95 ? 'ok-card' : resumo.disponibilidade >= 85 ? 'warn-card' : 'alert-card'
        },
        {
            icon: 'fas fa-user-plus', color: '#9933ff',
            value: resumo.crescimento_mes,
            label: 'Novos este Mês',
            sub: new Date().toLocaleDateString('pt-BR', {month:'long', year:'numeric'}),
            cls: resumo.crescimento_mes > 0 ? 'ok-card' : ''
        },
    ];

    const container = document.getElementById('kpi-cards');
    container.innerHTML = cards.map(c => `
        <div class="kpi-card ${c.cls}">
            <div class="kpi-glow" style="background:${c.color}"></div>
            <div class="kpi-icon" style="background:${c.color}22">
                <i class="${c.icon}" style="color:${c.color}"></i>
            </div>
            <div class="kpi-value" style="color:${c.color}">${c.value}</div>
            <div class="kpi-label">${c.label}</div>
            <div class="kpi-sub">${c.sub}</div>
        </div>
    `).join('');
}

function renderCtos(ctos) {
    const list = document.getElementById('cto-list');
    const summary = document.getElementById('cto-summary');

    const lotadas = ctos.filter(c => parseFloat(c.pct) >= 80).length;
    summary.textContent = lotadas > 0 ? `${lotadas} acima de 80%` : 'Todas abaixo de 80%';

    if (!ctos.length) {
        list.innerHTML = '<div style="color:var(--text-muted);text-align:center;padding:20px;font-size:13px">Nenhuma CTO cadastrada</div>';
        return;
    }

    list.innerHTML = ctos.map(c => {
        const pct  = parseFloat(c.pct) || 0;
        const cor  = corOcupacao(pct);
        const nome = c.nome ? `${c.codigo} — ${c.nome}` : c.codigo;
        return `
        <div class="cto-row">
            <div class="cto-name" title="${nome}">${nome}</div>
            <div class="cto-bar-wrap">
                <div class="cto-bar" style="width:${Math.min(pct,100)}%;background:${cor}"></div>
            </div>
            <div class="cto-pct" style="color:${cor}">${pct}%</div>
            <div style="min-width:60px;text-align:right;font-size:11px;color:var(--text-muted)">${c.usadas}/${c.capacidade_portas}</div>
        </div>`;
    }).join('');
}

function renderManutencoes(prioridade, recentes) {
    // Blocos de prioridade
    document.getElementById('priority-blocks').innerHTML = `
        <div class="priority-block p-alta">
            <div class="pb-num">${prioridade.alta}</div>
            <div class="pb-label">Alta</div>
        </div>
        <div class="priority-block p-media">
            <div class="pb-num">${prioridade.media}</div>
            <div class="pb-label">Média</div>
        </div>
        <div class="priority-block p-baixa">
            <div class="pb-num">${prioridade.baixa}</div>
            <div class="pb-label">Baixa</div>
        </div>
    `;

    const list = document.getElementById('manut-list');
    if (!recentes.length) {
        list.innerHTML = '<div style="color:#00cc66;font-size:12px;text-align:center;padding:12px"><i class="fas fa-check-circle"></i> Nenhuma manutenção em aberto</div>';
        return;
    }

    list.innerHTML = `
        <table class="kpi-table">
            <thead><tr>
                <th>Ocorrência</th>
                <th>Prioridade</th>
                <th>Status</th>
                <th>Data</th>
            </tr></thead>
            <tbody>
                ${recentes.map(m => {
                    const dt = m.data_ocorrencia ? m.data_ocorrencia.split(' ')[0].split('-').reverse().join('/') : '—';
                    return `<tr>
                        <td><a href="${BASE_URL}/modules/manutencoes/index.php" style="color:var(--text);text-decoration:none">${m.tipo_ocorrencia || '—'}</a></td>
                        <td><span class="badge-${m.prioridade}">${m.prioridade}</span></td>
                        <td><span class="badge-${m.status}">${m.status.replace('_',' ')}</span></td>
                        <td style="color:var(--text-muted)">${dt}</td>
                    </tr>`;
                }).join('')}
            </tbody>
        </table>`;
}

function renderCrescimento(dados) {
    const labels = dados.map(d => d.mes_label);
    const values = dados.map(d => parseInt(d.total));

    if (chartCrescimento) {
        chartCrescimento.data.labels = labels;
        chartCrescimento.data.datasets[0].data = values;
        chartCrescimento.update('none');
        return;
    }

    const ctx = document.getElementById('chart-crescimento').getContext('2d');
    chartCrescimento = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Clientes',
                data: values,
                backgroundColor: 'rgba(0,204,255,0.25)',
                borderColor: '#00ccff',
                borderWidth: 2,
                borderRadius: 6,
                hoverBackgroundColor: 'rgba(0,204,255,0.45)',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1a1a2e',
                    borderColor: 'rgba(255,255,255,0.1)',
                    borderWidth: 1,
                    titleColor: '#fff',
                    bodyColor: '#aaa',
                    callbacks: {
                        label: ctx => `${ctx.parsed.y} clientes`
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255,255,255,0.04)' },
                    ticks: { color: '#888', font: { size: 11 } }
                },
                y: {
                    grid: { color: 'rgba(255,255,255,0.04)' },
                    ticks: { color: '#888', font: { size: 11 }, stepSize: 1 },
                    beginAtZero: true
                }
            }
        }
    });
}

function renderSinais(piores) {
    const list = document.getElementById('sinais-list');
    if (!piores.length) {
        list.innerHTML = '<div style="color:#00cc66;font-size:13px;text-align:center;padding:20px"><i class="fas fa-check-circle"></i> Nenhum cliente com sinal crítico</div>';
        return;
    }

    list.innerHTML = `
        <table class="kpi-table">
            <thead><tr>
                <th>Cliente</th>
                <th>Login</th>
                <th style="width:120px">Sinal</th>
            </tr></thead>
            <tbody>
                ${piores.map(c => {
                    const dbm = parseFloat(c.sinal_dbm);
                    const cor = corSinal(dbm);
                    // Barra: escala de -35 a -15 dBm => 0 a 100%
                    const pct = Math.max(0, Math.min(100, ((dbm + 35) / 20) * 100));
                    return `<tr>
                        <td><a href="${BASE_URL}/modules/clientes/view.php?id=${c.id}" style="color:var(--text);text-decoration:none">${c.nome}</a></td>
                        <td style="color:var(--text-muted)">${c.login || '—'}</td>
                        <td>
                            <div style="display:flex;align-items:center;gap:7px">
                                <div class="sinal-bar-wrap">
                                    <div class="sinal-bar" style="width:${pct}%;background:${cor}"></div>
                                </div>
                                <span style="color:${cor};font-weight:700;font-size:12px;min-width:52px">${dbm} dBm</span>
                            </div>
                        </td>
                    </tr>`;
                }).join('')}
            </tbody>
        </table>
        ${piores.length >= 5 ? `<div style="text-align:center;margin-top:10px"><a href="${BASE_URL}/modules/clientes/index.php" style="font-size:11px;color:#00b4ff;text-decoration:none">Ver todos os clientes</a></div>` : ''}
    `;
}

function animateRefreshDot() {
    const dot = document.getElementById('refresh-dot');
    dot.style.background = '#00ccff';
    setTimeout(() => { dot.style.background = '#00cc66'; }, 800);
}

async function loadKpis() {
    const btn = document.getElementById('btn-refresh');
    btn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Atualizando...';
    btn.disabled = true;

    try {
        const res  = await fetch(`${BASE_URL}/api/kpis.php`, { credentials: 'same-origin' });
        const data = await res.json();

        renderKpiCards(data.resumo);
        renderCtos(data.ctos_ocupacao);
        renderManutencoes(data.manut_prioridade, data.manut_recentes);
        renderCrescimento(data.crescimento_mensal);
        renderSinais(data.piores_sinais);

        document.getElementById('last-update').textContent = data.updated_at;
        animateRefreshDot();
    } catch (e) {
        console.error('Erro ao carregar KPIs:', e);
    } finally {
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Atualizar';
        btn.disabled = false;
    }
}

// Carga inicial + auto-refresh
document.addEventListener('DOMContentLoaded', function() {
    loadKpis();
    refreshTimer = setInterval(loadKpis, <?= 120000 ?>);
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
