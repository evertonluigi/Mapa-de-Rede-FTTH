<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

$id = (int)($_GET['id'] ?? 0);
$cabo = $id ? $db->fetch("SELECT * FROM cabos WHERE id = ?", [$id]) : null;
$isEdit = $cabo !== null;

// Default ABNT NBR 14772 colors
// Padrão Nacional ABNT: Verde, Amarelo, Branco, Azul, Vermelho, Roxo, Marrom, Rosa, Preto, Cinza, Laranja, Acqua
$abnt = ['#2E7D32','#F9A825','#EEEEEE','#1565C0','#C62828','#6A1B9A','#4E342E','#E91E63','#212121','#757575','#E65100','#00838F'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Parse custom colors JSON from hidden field
    $configCores = null;
    $customCores = array_filter($_POST['cores'] ?? []);
    $fpt = (int)($_POST['fibras_por_tubo'] ?? 12);
    if (!empty($customCores)) {
        $configCores = json_encode(['fibras_por_tubo' => $fpt, 'cores' => array_values($customCores)]);
    } elseif ($fpt !== 12) {
        $configCores = json_encode(['fibras_por_tubo' => $fpt, 'cores' => array_slice($abnt, 0, $fpt)]);
    }

    $comprReal = isset($_POST['comprimento_real']) && $_POST['comprimento_real'] !== '' ? (float)$_POST['comprimento_real'] : null;
    $data = [
        'codigo'           => $_POST['codigo'],
        'nome'             => $_POST['nome'] ?: null,
        'tipo'             => $_POST['tipo'],
        'num_fibras'       => (int)$_POST['num_fibras'],
        'comprimento_real' => $comprReal,
        'status'           => $_POST['status'],
        'cor_mapa'         => $_POST['cor_mapa'] ?: null,
        'fibras_por_tubo'  => $fpt,
        'config_cores'     => $configCores,
        'observacoes'      => $_POST['observacoes'] ?? '',
    ];
    if ($isEdit) {
        $db->update('cabos', $data, 'id = ?', [$id]);
        AuditLog::log('editar', 'cabos', $id, 'Cabo '.$data['codigo'].' editado', $cabo, $data);
    } else {
        $data['created_by'] = Auth::user()['id'];
        $newId = $db->insert('cabos', $data);
        AuditLog::log('criar', 'cabos', $newId, 'Cabo '.$data['codigo'].' criado', [], $data);
    }
    header('Location: ' . BASE_URL . '/modules/cabos/index.php?saved=1'); exit;
}

// Parse existing config
$cfgCores = $cabo && $cabo['config_cores'] ? json_decode($cabo['config_cores'], true) : null;
$fpt_atual = $cfgCores['fibras_por_tubo'] ?? (int)($cabo['fibras_por_tubo'] ?? 12);
$cores_atuais = $cfgCores['cores'] ?? $abnt;

$pageTitle = $isEdit ? 'Editar Cabo' : 'Novo Cabo';
$activePage = 'cabos';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.color-dot{width:28px;height:28px;border-radius:50%;border:2px solid rgba(255,255,255,.2);cursor:pointer;position:relative}
.color-dot input[type=color]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;border-radius:50%}
.fiber-preview{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.fpt-btn{padding:4px 12px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--text-muted);cursor:pointer;font-size:13px}
.fpt-btn.active{background:rgba(51,153,255,.2);border-color:#3399ff;color:#3399ff}
</style>
<div class="page-content">
    <div class="page-header">
        <div>
            <h2><i class="fas fa-minus" style="color:#3399ff"></i> <?= $isEdit ? 'Editar Cabo: '.e($cabo['codigo']) : 'Novo Cabo' ?></h2>
        </div>
        <a href="<?= BASE_URL ?>/modules/cabos/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;max-width:900px">

        <!-- Dados básicos -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
            <h4 style="margin-bottom:16px;font-size:12px;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted)">Dados do Cabo</h4>
            <form method="POST" id="form-cabo">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Código *</label>
                        <input class="form-control" name="codigo" required value="<?= e($cabo['codigo'] ?? '') ?>" placeholder="CBF-001">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="status">
                            <?php foreach(['ativo','reserva','cortado','defeito'] as $s): ?>
                            <option value="<?= $s ?>" <?= ($cabo['status']??'ativo')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Nome / Descrição</label>
                        <input class="form-control" name="nome" value="<?= e($cabo['nome'] ?? '') ?>" placeholder="Ex: Cabo Rua Principal">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo</label>
                        <select class="form-control" name="tipo">
                            <?php foreach(['monomodo','multimodo','drop','aerial','subterraneo'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($cabo['tipo']??'monomodo')===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nº de Fibras</label>
                        <select class="form-control" name="num_fibras" id="sel-fibras">
                            <?php foreach([2,4,6,8,12,24,48,96,144] as $n): ?>
                            <option value="<?= $n ?>" <?= ((int)($cabo['num_fibras']??12))===$n?'selected':'' ?>><?= $n ?> FO</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Observações</label>
                        <textarea class="form-control" name="observacoes" rows="2"><?= e($cabo['observacoes'] ?? '') ?></textarea>
                    </div>
                </div>
                <?php if ($isEdit): ?>
                <div style="margin-bottom:16px;padding:10px 12px;background:rgba(51,153,255,.08);border-radius:8px;font-size:12px;color:var(--text-muted)">
                    <i class="fas fa-ruler" style="color:#3399ff"></i>
                    Comprimento no mapa: <strong><?= $cabo['comprimento_m'] ? number_format($cabo['comprimento_m'],0,',','.').' m' : '—' ?></strong>
                    <?php if (!empty($cabo['comprimento_real'])): ?>
                    &nbsp;·&nbsp; Real: <strong style="color:var(--success)"><?= number_format($cabo['comprimento_real'],1,',','.').' m' ?></strong>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label">Comprimento Real (m)
                        <span style="font-size:10px;color:var(--text-muted);font-weight:400"> — medido em campo</span>
                    </label>
                    <input class="form-control" name="comprimento_real" type="number" min="0" step="0.1"
                           value="<?= e($cabo['comprimento_real'] ?? '') ?>"
                           placeholder="ex: 125.5 (opcional — deixe vazio para usar o do mapa)">
                </div>
                <!-- Hidden fields preenchidos pelo painel de configuração -->
                <input type="hidden" name="cor_mapa" id="hid-cor-mapa" value="<?= e($cabo['cor_mapa'] ?? '') ?>">
                <input type="hidden" name="fibras_por_tubo" id="hid-fpt" value="<?= $fpt_atual ?>">
                <!-- cores[] preenchidos por JS -->
                <div id="hid-cores-wrap"></div>
                <div style="display:flex;gap:10px;margin-top:4px">
                    <a href="<?= BASE_URL ?>/modules/cabos/index.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                </div>
            </form>
        </div>

        <!-- Configurações de cor -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px">
            <h4 style="margin-bottom:16px;font-size:12px;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted)">Configurações de Cor</h4>

            <!-- Cor no mapa -->
            <div class="form-group" style="margin-bottom:20px">
                <label class="form-label">Cor no Mapa</label>
                <div style="display:flex;align-items:center;gap:12px">
                    <div id="mapa-color-preview" style="width:40px;height:40px;border-radius:8px;border:2px solid rgba(255,255,255,.2);cursor:pointer;position:relative">
                        <input type="color" id="inp-cor-mapa" value="<?= e($cabo['cor_mapa'] ?? '#3399ff') ?>"
                               style="position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%"
                               oninput="document.getElementById('mapa-color-preview').style.background=this.value;document.getElementById('hid-cor-mapa').value=this.value">
                    </div>
                    <div>
                        <div style="font-size:13px" id="mapa-cor-label"><?= $cabo['cor_mapa'] ?? 'Padrão (azul)' ?></div>
                        <div style="font-size:11px;color:#555">Cor exibida no mapa de rede</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('inp-cor-mapa').value='';document.getElementById('hid-cor-mapa').value='';document.getElementById('mapa-color-preview').style.background='';document.getElementById('mapa-cor-label').textContent='Padrão (azul)'">Padrão</button>
                </div>
            </div>

            <!-- Fibras por tubo -->
            <div class="form-group" style="margin-bottom:16px">
                <label class="form-label">Fibras por Tubo</label>
                <div style="display:flex;gap:8px;flex-wrap:wrap" id="fpt-btns">
                    <?php foreach([4,6,8,12] as $n): ?>
                    <button type="button" class="fpt-btn <?= $fpt_atual==$n?'active':'' ?>"
                            onclick="setFpt(<?= $n ?>)"><?= $n ?> FO/tubo</button>
                    <?php endforeach; ?>
                </div>
                <div style="font-size:11px;color:#555;margin-top:4px" id="fpt-info">
                    <?= (int)($cabo['num_fibras']??12) ?> fibras ÷ <?= $fpt_atual ?> = <?= ceil((int)($cabo['num_fibras']??12)/$fpt_atual) ?> tubo(s)
                </div>
            </div>

            <!-- Sequência de cores -->
            <div class="form-group">
                <label class="form-label" style="display:flex;align-items:center;justify-content:space-between">
                    Sequência de Cores (por tubo)
                    <button type="button" class="btn btn-sm btn-secondary" onclick="resetCoresAbnt()">Padrão ABNT</button>
                </label>
                <div id="cores-editor" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px"></div>
            </div>

            <!-- Preview do cabo -->
            <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
                <label class="form-label">Preview (1 tubo)</label>
                <div id="preview-tube" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px"></div>
            </div>
        </div>
    </div>
</div>

<script>
const ABNT = <?= json_encode($abnt) ?>;
const ABNT_NOMES = ['Verde','Amarelo','Branco','Azul','Vermelho','Roxo','Marrom','Rosa','Preto','Cinza','Laranja','Acqua'];
let fpt = <?= $fpt_atual ?>;
let cores = <?= json_encode($cores_atuais) ?>;

// Init cor mapa preview
document.getElementById('mapa-color-preview').style.background = '<?= e($cabo['cor_mapa'] ?? '#3399ff') ?>';

function isDark(hex) {
    if (!hex || hex.length < 7) return false;
    const r=parseInt(hex.slice(1,3),16),g=parseInt(hex.slice(3,5),16),b=parseInt(hex.slice(5,7),16);
    return (r*299+g*587+b*114)/1000<128;
}

function setFpt(n) {
    fpt = n;
    document.getElementById('hid-fpt').value = n;
    document.querySelectorAll('.fpt-btn').forEach(b => b.classList.toggle('active', parseInt(b.textContent)==n));
    // Ajusta array de cores
    while (cores.length < n) cores.push(ABNT[(cores.length) % 12]);
    cores = cores.slice(0, n);
    updateInfo();
    renderCoresEditor();
    renderPreview();
}

function updateInfo() {
    const total = parseInt(document.getElementById('sel-fibras').value) || 12;
    document.getElementById('fpt-info').textContent =
        `${total} fibras ÷ ${fpt} = ${Math.ceil(total/fpt)} tubo(s)`;
}

function renderCoresEditor() {
    const el = document.getElementById('cores-editor');
    el.innerHTML = '';
    cores.forEach((c, i) => {
        const wrap = document.createElement('div');
        wrap.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:3px';
        const dotW = document.createElement('div');
        dotW.className = 'color-dot';
        dotW.style.background = c;
        const inp = document.createElement('input');
        inp.type = 'color';
        inp.value = c;
        inp.addEventListener('input', function() {
            cores[i] = this.value;
            dotW.style.background = this.value;
            renderPreview();
            syncHiddenCores();
        });
        dotW.appendChild(inp);
        const lbl = document.createElement('span');
        lbl.style.cssText = 'font-size:9px;color:#666';
        lbl.textContent = `T1-F${String(i+1).padStart(2,'0')}`;
        wrap.appendChild(dotW); wrap.appendChild(lbl);
        el.appendChild(wrap);
    });
}

function renderPreview() {
    const el = document.getElementById('preview-tube');
    el.innerHTML = '';
    cores.forEach((c, i) => {
        const bd = c === '#EEEEEE' ? '#999' : c;
        el.innerHTML += `<div style="display:flex;flex-direction:column;align-items:center;gap:2px">
            <div style="width:24px;height:24px;border-radius:50%;background:${c};border:2px solid ${bd};display:flex;align-items:center;justify-content:center">
                <span style="font-size:8px;color:${isDark(c)?'#fff':'#000'};font-weight:700">${i+1}</span>
            </div>
            <span style="font-size:9px;color:#666">T1-F${String(i+1).padStart(2,'0')}</span>
        </div>`;
    });
}

function resetCoresAbnt() {
    cores = ABNT.slice(0, fpt);
    renderCoresEditor();
    renderPreview();
    syncHiddenCores();
}

function syncHiddenCores() {
    const wrap = document.getElementById('hid-cores-wrap');
    wrap.innerHTML = '';
    cores.forEach((c, i) => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'cores[]';
        inp.value = c;
        wrap.appendChild(inp);
    });
}

document.getElementById('sel-fibras').addEventListener('change', updateInfo);

// Init
renderCoresEditor();
renderPreview();
syncHiddenCores();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
