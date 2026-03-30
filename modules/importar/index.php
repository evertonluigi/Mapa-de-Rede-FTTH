<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$pageTitle  = 'Importar KML / KMZ';
$activePage = 'importar';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.step-box{background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px;margin-bottom:20px}
.step-num{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;background:#3399ff;color:#fff;font-size:12px;font-weight:700;margin-right:8px;flex-shrink:0}
.step-title{font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);display:flex;align-items:center;margin-bottom:16px}
.tipo-card{border:1.5px solid var(--border);border-radius:10px;padding:12px 16px;cursor:pointer;transition:.15s;display:flex;align-items:center;gap:10px}
.tipo-card:hover{border-color:rgba(51,153,255,.5);background:rgba(51,153,255,.05)}
.tipo-card.active{border-color:#3399ff;background:rgba(51,153,255,.1)}
.tipo-card .icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.drop-zone{border:2px dashed rgba(51,153,255,.35);border-radius:12px;padding:40px 24px;text-align:center;cursor:pointer;transition:.2s;background:rgba(51,153,255,.03)}
.drop-zone:hover,.drop-zone.drag-over{border-color:#3399ff;background:rgba(51,153,255,.07)}
.preview-table{width:100%;border-collapse:collapse;font-size:12px}
.preview-table th{background:rgba(255,255,255,.04);padding:7px 10px;text-align:left;font-size:11px;color:var(--text-muted);font-weight:600;border-bottom:1px solid var(--border);position:sticky;top:0}
.preview-table td{padding:6px 10px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle}
.preview-table tr:last-child td{border-bottom:none}
.badge-tipo{display:inline-block;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;text-transform:uppercase}
.defaults-row{display:none}
#result-box{display:none;background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:20px;margin-top:20px}
</style>

<div class="page-content">
    <div class="page-header">
        <div>
            <h2><i class="fas fa-file-import" style="color:#3399ff"></i> Importar KML / KMZ</h2>
            <p style="font-size:13px;color:var(--text-muted);margin-top:4px">Importe marcadores do Google Earth e converta em elementos da rede</p>
        </div>
    </div>

    <div style="max-width:860px">

    <!-- STEP 1: Upload -->
    <div class="step-box" id="step1">
        <div class="step-title"><span class="step-num">1</span> Selecionar Arquivo</div>
        <div class="drop-zone" id="drop-zone" onclick="document.getElementById('file-input').click()">
            <input type="file" id="file-input" accept=".kml,.kmz" style="display:none">
            <i class="fas fa-cloud-upload-alt" style="font-size:32px;color:#3399ff;margin-bottom:10px;display:block"></i>
            <div style="font-size:14px;font-weight:600;margin-bottom:4px">Clique ou arraste o arquivo aqui</div>
            <div style="font-size:12px;color:var(--text-muted)">Formatos suportados: <strong>.kml</strong> e <strong>.kmz</strong> (Google Earth)</div>
        </div>
        <div id="parse-status" style="display:none;margin-top:12px;padding:10px 14px;border-radius:8px;font-size:13px"></div>
    </div>

    <!-- STEP 2: Configurar importação -->
    <div class="step-box" id="step2" style="display:none">
        <div class="step-title"><span class="step-num">2</span> Configurar Importação</div>

        <!-- Tipo de elemento -->
        <div class="form-group" style="margin-bottom:20px">
            <label class="form-label">Tipo de Elemento</label>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-top:8px">
                <div class="tipo-card" data-tipo="poste" onclick="selectTipo('poste')">
                    <div class="icon" style="background:rgba(170,170,170,.12)"><i class="fas fa-border-all" style="color:#aaaaaa"></i></div>
                    <div><div style="font-size:13px;font-weight:700">Poste</div><div style="font-size:11px;color:#555">Postes de rede</div></div>
                </div>
                <div class="tipo-card" data-tipo="ceo" onclick="selectTipo('ceo')">
                    <div class="icon" style="background:rgba(153,51,255,.12)"><i class="fas fa-box" style="color:#9933ff"></i></div>
                    <div><div style="font-size:13px;font-weight:700">CEO</div><div style="font-size:11px;color:#555">Cx. de Emenda</div></div>
                </div>
                <div class="tipo-card" data-tipo="cto" onclick="selectTipo('cto')">
                    <div class="icon" style="background:rgba(0,204,102,.12)"><i class="fas fa-box-open" style="color:#00cc66"></i></div>
                    <div><div style="font-size:13px;font-weight:700">CTO</div><div style="font-size:11px;color:#555">Cx. Terminal</div></div>
                </div>
                <div class="tipo-card" data-tipo="rack" onclick="selectTipo('rack')">
                    <div class="icon" style="background:rgba(170,102,0,.12)"><i class="fas fa-th-large" style="color:#aa6600"></i></div>
                    <div><div style="font-size:13px;font-weight:700">Rack / DIO</div><div style="font-size:11px;color:#555">Rack de fibras</div></div>
                </div>
            </div>
        </div>

        <!-- Código -->
        <div class="form-group" style="margin-bottom:16px">
            <label class="form-label">Geração do Código</label>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px">
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                    <input type="radio" name="cod-mode" value="auto" checked onchange="setCodMode('auto')"> Prefixo automático
                </label>
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                    <input type="radio" name="cod-mode" value="nome" onchange="setCodMode('nome')"> Usar nome do marcador
                </label>
            </div>
            <div id="cod-auto-row" style="display:flex;gap:10px;margin-top:10px;align-items:flex-end">
                <div class="form-group" style="margin:0">
                    <label class="form-label" style="font-size:11px">Prefixo</label>
                    <input class="form-control" id="inp-prefixo" placeholder="PST-" style="width:120px" oninput="updatePreview()">
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label" style="font-size:11px">Nº inicial</label>
                    <input class="form-control" id="inp-seq" type="number" value="1" min="1" style="width:90px" oninput="updatePreview()">
                </div>
                <div style="font-size:12px;color:#555;padding-bottom:8px">
                    Ex: <strong id="cod-preview">PST-001</strong>, <strong id="cod-preview2">PST-002</strong>...
                </div>
            </div>
        </div>

        <!-- Defaults por tipo -->
        <div id="defaults-poste" class="defaults-row">
            <label class="form-label">Tipo de Poste (padrão)</label>
            <select class="form-control" id="def-tipo-poste" style="max-width:200px">
                <option value="concreto">Concreto</option>
                <option value="madeira">Madeira</option>
                <option value="metalico">Metálico</option>
                <option value="outro">Outro</option>
            </select>
        </div>
        <div id="defaults-ceo" class="defaults-row" style="display:flex;gap:16px;flex-wrap:wrap">
            <div class="form-group" style="margin:0">
                <label class="form-label">Tipo CEO (padrão)</label>
                <select class="form-control" id="def-tipo-ceo" style="width:160px">
                    <option value="aerea">Aérea</option>
                    <option value="subterranea">Subterrânea</option>
                    <option value="pedestal">Pedestal</option>
                </select>
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label">Capacidade FO</label>
                <select class="form-control" id="def-cap-fo" style="width:120px">
                    <?php foreach([12,24,48,96,144] as $c): ?>
                    <option value="<?= $c ?>" <?= $c==24?'selected':'' ?>><?= $c ?> FO</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div id="defaults-cto" class="defaults-row" style="display:flex;gap:16px;flex-wrap:wrap">
            <div class="form-group" style="margin:0">
                <label class="form-label">Tipo CTO (padrão)</label>
                <select class="form-control" id="def-tipo-cto" style="width:160px">
                    <option value="aerea">Aérea</option>
                    <option value="subterranea">Subterrânea</option>
                    <option value="pedestal">Pedestal</option>
                </select>
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label">Capacidade (portas)</label>
                <select class="form-control" id="def-cap-portas" style="width:120px">
                    <?php foreach([4,8,16,32] as $c): ?>
                    <option value="<?= $c ?>" <?= $c==8?'selected':'' ?>><?= $c ?> portas</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- STEP 3: Preview e confirmação -->
    <div class="step-box" id="step3" style="display:none">
        <div class="step-title"><span class="step-num">3</span> Revisão — <span id="preview-count" style="color:#3399ff;text-transform:none;letter-spacing:0;font-size:14px"></span></div>

        <div style="max-height:360px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;margin-bottom:16px">
            <table class="preview-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nome (KML)</th>
                        <th>Código gerado</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Descrição</th>
                    </tr>
                </thead>
                <tbody id="preview-tbody"></tbody>
            </table>
        </div>

        <div style="display:flex;gap:10px;align-items:center">
            <button class="btn btn-secondary" onclick="voltarStep2()"><i class="fas fa-arrow-left"></i> Voltar</button>
            <button class="btn btn-primary" id="btn-importar" onclick="confirmarImport()">
                <i class="fas fa-file-import"></i> Importar <span id="btn-count"></span> Elementos
            </button>
            <div id="import-loading" style="display:none;color:#888;font-size:13px"><i class="fas fa-spinner fa-spin"></i> Importando...</div>
        </div>
    </div>

    <!-- Resultado -->
    <div id="result-box">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
            <i class="fas fa-check-circle" style="font-size:24px;color:#00cc66"></i>
            <div>
                <div style="font-size:16px;font-weight:700" id="result-title"></div>
                <div style="font-size:13px;color:var(--text-muted)" id="result-sub"></div>
            </div>
        </div>
        <div id="result-erros" style="display:none;margin-top:10px;padding:10px;background:rgba(255,68,85,.06);border:1px solid rgba(255,68,85,.2);border-radius:8px;font-size:12px;max-height:200px;overflow-y:auto"></div>
        <div style="display:flex;gap:10px;margin-top:14px">
            <button class="btn btn-primary" onclick="location.reload()"><i class="fas fa-redo"></i> Nova Importação</button>
            <a id="result-link" href="#" class="btn btn-secondary"><i class="fas fa-list"></i> Ver Elementos</a>
        </div>
    </div>

    </div><!-- /max-width -->
</div>

<script>
let pontos = [];
let tipoSelecionado = '';
let codMode = 'auto';

// ── Drag & drop ───────────────────────────────────────────────────────────────
const dropZone = document.getElementById('drop-zone');
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => { e.preventDefault(); dropZone.classList.remove('drag-over'); handleFile(e.dataTransfer.files[0]); });
document.getElementById('file-input').addEventListener('change', e => { if(e.target.files[0]) handleFile(e.target.files[0]); });

async function handleFile(file) {
    if (!file) return;
    const ext = file.name.split('.').pop().toLowerCase();
    if (!['kml','kmz'].includes(ext)) { showStatus('Arquivo inválido. Use .kml ou .kmz', 'error'); return; }

    showStatus('<i class="fas fa-spinner fa-spin"></i> Analisando arquivo...', 'loading');
    document.getElementById('step2').style.display = 'none';
    document.getElementById('step3').style.display = 'none';

    const fd = new FormData();
    fd.append('arquivo', file);

    try {
        const r = await fetch(`<?= BASE_URL ?>/api/importar_kml.php?acao=parse`, { method: 'POST', body: fd });
        const d = await r.json();
        if (!d.success) { showStatus(d.error, 'error'); return; }

        pontos = d.pontos;
        showStatus(`<i class="fas fa-check-circle" style="color:#00cc66"></i> <strong>${d.total} marcadores</strong> encontrados em <strong>${escHtml(file.name)}</strong>`, 'ok');
        document.getElementById('step2').style.display = '';
        updatePreview();
    } catch(e) {
        showStatus('Erro de comunicação com o servidor.', 'error');
    }
}

function showStatus(msg, type) {
    const el = document.getElementById('parse-status');
    el.style.display = '';
    el.style.background = type === 'error' ? 'rgba(255,68,85,.08)' : type === 'ok' ? 'rgba(0,204,102,.08)' : 'rgba(51,153,255,.08)';
    el.style.border = '1px solid ' + (type === 'error' ? 'rgba(255,68,85,.3)' : type === 'ok' ? 'rgba(0,204,102,.3)' : 'rgba(51,153,255,.3)');
    el.innerHTML = msg;
}

// ── Tipo de elemento ──────────────────────────────────────────────────────────
function selectTipo(tipo) {
    tipoSelecionado = tipo;
    document.querySelectorAll('.tipo-card').forEach(c => c.classList.toggle('active', c.dataset.tipo === tipo));
    document.querySelectorAll('.defaults-row').forEach(r => r.style.display = 'none');
    const dr = document.getElementById('defaults-' + tipo);
    if (dr) dr.style.display = '';

    // Sugere prefixo padrão
    const prefixos = { poste: 'PST-', ceo: 'CEO-', cto: 'CTO-', rack: 'RK-' };
    document.getElementById('inp-prefixo').value = prefixos[tipo] || tipo.toUpperCase() + '-';
    updatePreview();
    buildPreviewTable();
}

// ── Modo de código ────────────────────────────────────────────────────────────
function setCodMode(mode) {
    codMode = mode;
    document.getElementById('cod-auto-row').style.display = mode === 'auto' ? '' : 'none';
    updatePreview();
    buildPreviewTable();
}

function updatePreview() {
    const pfx = document.getElementById('inp-prefixo').value || '';
    const seq = parseInt(document.getElementById('inp-seq').value) || 1;
    document.getElementById('cod-preview').textContent  = pfx + String(seq).padStart(3,'0');
    document.getElementById('cod-preview2').textContent = pfx + String(seq+1).padStart(3,'0');
}

// ── Preview table ─────────────────────────────────────────────────────────────
function getCodigoPontos() {
    const pfx = document.getElementById('inp-prefixo').value || '';
    let seq = parseInt(document.getElementById('inp-seq').value) || 1;
    return pontos.map(pt => {
        let cod;
        if (codMode === 'nome' && pt.nome) cod = pt.nome.substring(0, 50);
        else { cod = pfx + String(seq).padStart(3, '0'); seq++; }
        return { ...pt, codigo: cod };
    });
}

function buildPreviewTable() {
    if (!tipoSelecionado) return;
    const rows = getCodigoPontos();
    const tbody = document.getElementById('preview-tbody');
    tbody.innerHTML = rows.slice(0, 200).map((pt, i) => `
        <tr>
            <td style="color:#555">${i+1}</td>
            <td style="color:var(--text-muted)">${escHtml(pt.nome)}</td>
            <td><strong style="color:#3399ff">${escHtml(pt.codigo)}</strong></td>
            <td style="font-size:11px;color:#666">${pt.lat}</td>
            <td style="font-size:11px;color:#666">${pt.lng}</td>
            <td style="font-size:11px;color:#555;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escHtml(pt.desc||'')}</td>
        </tr>`).join('') + (rows.length > 200 ? `<tr><td colspan="6" style="text-align:center;color:#555;padding:10px">... e mais ${rows.length-200} pontos</td></tr>` : '');

    document.getElementById('preview-count').textContent = rows.length + ' pontos';
    document.getElementById('btn-count').textContent = rows.length;
    document.getElementById('step3').style.display = '';
}

document.getElementById('inp-prefixo').addEventListener('input', () => { updatePreview(); buildPreviewTable(); });
document.getElementById('inp-seq').addEventListener('input', () => { updatePreview(); buildPreviewTable(); });

function voltarStep2() {
    document.getElementById('step3').style.display = 'none';
}

// ── Confirmar importação ──────────────────────────────────────────────────────
async function confirmarImport() {
    if (!tipoSelecionado) { alert('Selecione o tipo de elemento.'); return; }
    if (!pontos.length) return;

    const rows = getCodigoPontos();

    const defaults = {};
    if (tipoSelecionado === 'poste')     defaults.tipo_poste = document.getElementById('def-tipo-poste').value;
    else if (tipoSelecionado === 'ceo')  { defaults.tipo_ceo = document.getElementById('def-tipo-ceo').value; defaults.capacidade_fo = parseInt(document.getElementById('def-cap-fo').value); }
    else if (tipoSelecionado === 'cto')  { defaults.tipo_cto = document.getElementById('def-tipo-cto').value; defaults.capacidade_portas = parseInt(document.getElementById('def-cap-portas').value); }

    document.getElementById('btn-importar').style.display = 'none';
    document.getElementById('import-loading').style.display = '';

    try {
        const r = await fetch(`<?= BASE_URL ?>/api/importar_kml.php?acao=importar`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tipo: tipoSelecionado,
                pontos: rows,
                usar_nome: codMode === 'nome',
                prefixo: document.getElementById('inp-prefixo').value || '',
                seq_inicio: parseInt(document.getElementById('inp-seq').value) || 1,
                defaults,
            })
        });
        const d = await r.json();

        document.getElementById('import-loading').style.display = 'none';
        document.getElementById('result-box').style.display = '';

        const tipoLabel = { poste: 'Postes', ceo: 'CEOs', cto: 'CTOs', rack: 'Racks' }[tipoSelecionado] || tipoSelecionado;
        const links = { poste: '<?= BASE_URL ?>/modules/postes/index.php', ceo: '<?= BASE_URL ?>/modules/ceos/index.php', cto: '<?= BASE_URL ?>/modules/ctos/index.php', rack: '<?= BASE_URL ?>/modules/racks/index.php' };

        document.getElementById('result-title').textContent = `${d.importados} ${tipoLabel} importados com sucesso!`;
        document.getElementById('result-sub').textContent = d.duplicados > 0 ? `${d.duplicados} código(s) ajustados por duplicata.` : `${d.total} pontos processados.`;
        document.getElementById('result-link').href = links[tipoSelecionado] || '#';
        document.getElementById('result-link').textContent = 'Ver ' + tipoLabel;

        if (d.erros && d.erros.length) {
            const errBox = document.getElementById('result-erros');
            errBox.style.display = '';
            errBox.innerHTML = '<strong style="color:#ff4455">Erros (' + d.erros.length + '):</strong><br>' +
                d.erros.map(e => `Linha ${e.linha}: ${escHtml(e.codigo||'')} — ${escHtml(e.erro)}`).join('<br>');
        }
    } catch(e) {
        document.getElementById('import-loading').style.display = 'none';
        document.getElementById('btn-importar').style.display = '';
        alert('Erro de comunicação com o servidor.');
    }
}

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
