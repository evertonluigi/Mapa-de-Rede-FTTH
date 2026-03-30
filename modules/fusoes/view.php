<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

$ceo_id = (int)($_GET['ceo_id'] ?? 0);
$cto_id = (int)($_GET['cto_id'] ?? 0);
if (!$ceo_id && !$cto_id) { header('Location: '.BASE_URL.'/modules/fusoes/index.php'); exit; }

if ($ceo_id) {
    $elemento = $db->fetch("SELECT *,'ceo' AS tipo_elem,capacidade_fo AS capacidade FROM ceos WHERE id=?",[$ceo_id]);
    $elem_col='ceo_id'; $elem_id=$ceo_id; $elem_tipo='ceo';
} else {
    $elemento = $db->fetch("SELECT *,'cto' AS tipo_elem,capacidade_portas AS capacidade FROM ctos WHERE id=?",[$cto_id]);
    $elem_col='cto_id'; $elem_id=$cto_id; $elem_tipo='cto';
}
if (!$elemento) { header('Location: '.BASE_URL.'/modules/fusoes/index.php'); exit; }

// Carrega o layout salvo para este elemento
$layoutRow = $db->fetch("SELECT positions_json, orientations_json FROM fusao_layouts WHERE elem_tipo=? AND elem_id=?", [$elem_tipo, $elem_id]);

// ── AJAX ──────────────────────────────────────────────────────────────────────
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if ($_SERVER['REQUEST_METHOD']==='POST' && str_contains($ct,'application/json')) {
    header('Content-Type: application/json');
    $b = json_decode(file_get_contents('php://input'),true)??[];

    // Salvar layout (posições e orientações dos cards)
    if (isset($b['save_layout'])) {
        $posJson = json_encode($b['positions'] ?? []);
        $oriJson = json_encode($b['orientations'] ?? []);
        $db->query(
            "INSERT INTO fusao_layouts (elem_tipo, elem_id, positions_json, orientations_json)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE positions_json=VALUES(positions_json), orientations_json=VALUES(orientations_json), updated_at=NOW()",
            [$elem_tipo, $elem_id, $posJson, $oriJson]
        );
        echo json_encode(['success'=>true]); exit;
    }

    if (!empty($b['delete_id'])) {
        $db->query("DELETE FROM fusoes WHERE id=? AND {$elem_col}=?",[(int)$b['delete_id'],$elem_id]);
        echo json_encode(['success'=>true]); exit;
    }
    if (!empty($b['add_splitter'])) {
        $sid=(int)$b['add_splitter'];
        $subtipo=in_array($b['subtipo']??'',['atendimento','derivacao'])?$b['subtipo']:'derivacao';
        $spl=$db->fetch("SELECT * FROM splitters WHERE id=?",[$sid]);
        if (!$spl){echo json_encode(['success'=>false,'error'=>'Splitter não encontrado']);exit;}
        // No limit on number of splitters per box
        $iid=$db->insert('elemento_splitters',['elem_tipo'=>$elem_tipo,'elem_id'=>$elem_id,'splitter_id'=>$sid,'bandeja'=>1,'subtipo'=>$subtipo]);
        $inst=$db->fetch("SELECT es.*,s.codigo spl_codigo,s.nome spl_nome,s.relacao,s.tipo spl_tipo FROM elemento_splitters es JOIN splitters s ON s.id=es.splitter_id WHERE es.id=?",[$iid]);
        echo json_encode(['success'=>true,'inst'=>$inst]); exit;
    }
    if (!empty($b['delete_splitter'])) {
        $iid=(int)$b['delete_splitter'];
        $db->query("DELETE FROM fusoes WHERE (spl_ent_id=? OR spl_sai_id=?) AND {$elem_col}=?",[$iid,$iid,$elem_id]);
        $db->query("DELETE FROM elemento_splitters WHERE id=? AND elem_tipo=? AND elem_id=?",[$iid,$elem_tipo,$elem_id]);
        echo json_encode(['success'=>true]); exit;
    }

    // Save fusao
    $bandeja=(int)($b['bandeja']??1);
    $mp=$db->fetch("SELECT COALESCE(MAX(posicao),0) mp FROM fusoes WHERE {$elem_col}=? AND bandeja=?",[$elem_id,$bandeja]);
    $ni  = fn($k) => isset($b[$k])&&$b[$k]!==null?(int)$b[$k]:null;
    $nv  = fn($k) => isset($b[$k])&&$b[$k]!==null?(string)$b[$k]:null; // varchar fields
    $fd=[$elem_col=>$elem_id,'bandeja'=>$bandeja,'posicao'=>$mp['mp']+1,
        'tipo'=>$b['tipo']??'emenda','status'=>'ok','updated_by'=>Auth::user()['id'],
        'cabo_entrada_id'=>$ni('cabo_entrada_id'),'fibra_entrada'=>$ni('fibra_entrada'),
        'cabo_saida_id'=>$ni('cabo_saida_id'),'fibra_saida'=>$ni('fibra_saida'),
        'spl_ent_id'=>$ni('spl_ent_id'),'spl_ent_porta'=>$nv('spl_ent_porta'),
        'spl_sai_id'=>$ni('spl_sai_id'),'spl_sai_porta'=>$nv('spl_sai_porta'),
    ];
    $fid=$db->insert('fusoes',$fd);
    $fusao=$db->fetch("SELECT f.*,ce.codigo cabo_e_cod,cs.codigo cabo_s_cod FROM fusoes f LEFT JOIN cabos ce ON ce.id=f.cabo_entrada_id LEFT JOIN cabos cs ON cs.id=f.cabo_saida_id WHERE f.id=?",[$fid]);
    echo json_encode(['success'=>true,'fusao'=>$fusao]); exit;
}

// ── Data ──────────────────────────────────────────────────────────────────────
$cabos=$db->fetchAll("SELECT DISTINCT c.id,c.codigo,c.num_fibras,c.tipo,c.fibras_por_tubo,c.config_cores,c.cor_mapa FROM cabos c INNER JOIN cabo_pontos cp ON cp.cabo_id=c.id WHERE cp.elemento_tipo=? AND cp.elemento_id=? AND c.status!='cortado' ORDER BY c.codigo",[$elem_tipo,$elem_id]);
$fusoes=$db->fetchAll("SELECT f.*,ce.codigo cabo_e_cod,cs.codigo cabo_s_cod FROM fusoes f LEFT JOIN cabos ce ON ce.id=f.cabo_entrada_id LEFT JOIN cabos cs ON cs.id=f.cabo_saida_id WHERE f.{$elem_col}=? ORDER BY f.bandeja,f.posicao",[$elem_id]);
$instSplitters=$db->fetchAll("SELECT es.*,s.codigo spl_codigo,s.nome spl_nome,s.relacao,s.tipo spl_tipo FROM elemento_splitters es JOIN splitters s ON s.id=es.splitter_id WHERE es.elem_tipo=? AND es.elem_id=? ORDER BY es.id",[$elem_tipo,$elem_id]);
$allSplitters=$db->fetchAll("SELECT id,codigo,nome,relacao,tipo FROM splitters WHERE status='ativo' ORDER BY codigo");
$fcPhp=[];for($i=1;$i<=12;$i++){[$n,$h]=fiberColor($i);$fcPhp[$i]=['nome'=>$n,'hex'=>$h];}
// Clients connected to this box by CTO port (1-indexed)
$clientesPorPorta = [];
if ($elem_tipo === 'cto') {
    $rows = $db->fetchAll("SELECT id, nome, login, porta_cto FROM clientes WHERE cto_id=? AND status='ativo' AND porta_cto IS NOT NULL", [$elem_id]);
    foreach ($rows as $r) { $clientesPorPorta[(int)$r['porta_cto']] = ['login'=>$r['login']??'','nome'=>$r['nome']??'','id'=>(int)$r['id']]; }
}
$labelTipo=$elem_tipo==='ceo'?'CEO':'CTO';
$pageTitle="Fusões — {$elemento['codigo']}";
$activePage='fusoes';
require_once __DIR__.'/../../includes/header.php';
?>
<style>
/* ── Canvas ── */
#fcanvas{position:relative;overflow:auto;background:#080b0f;border:1px solid var(--border);border-radius:12px;min-height:600px;cursor:default}
#fsvg{position:absolute;top:0;left:0;pointer-events:none;z-index:2;overflow:visible}
#fsvg .conn{pointer-events:stroke;cursor:pointer}

/* ── Cards ── */
.fcard{position:absolute;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:12px;z-index:5;min-width:160px;box-shadow:0 4px 16px rgba(0,0,0,.4)}
.fcard:hover{border-color:rgba(255,255,255,.2)}
.fcard-hdr{background:rgba(255,255,255,.06);border-radius:12px 12px 0 0;padding:7px 10px;cursor:move;display:flex;align-items:center;gap:6px;user-select:none;border-bottom:1px solid rgba(255,255,255,.06)}
.fcard-title{font-size:12px;font-weight:700;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fcard-sub{font-size:10px;color:#555;margin-top:1px}
.fcard-btn{background:transparent;border:none;color:#555;cursor:pointer;padding:2px 4px;border-radius:4px;font-size:11px;line-height:1}
.fcard-btn:hover{color:#fff;background:rgba(255,255,255,.1)}
.fcard-body{padding:10px}

/* ── Cable fibers ── */
.tube-row{display:flex;align-items:center;gap:3px;margin-bottom:4px;flex-wrap:nowrap}
.tube-lbl{font-size:9px;color:#444;min-width:22px;text-align:right;flex-shrink:0}
.fo-item{display:flex;flex-direction:column;align-items:center;gap:1px}
.fo-dot{width:26px;height:26px;border-radius:50%;border:2px solid rgba(255,255,255,.15);cursor:crosshair;display:flex;align-items:center;justify-content:center;transition:transform .1s,box-shadow .1s;user-select:none;position:relative;flex-shrink:0}
.fo-dot:hover{transform:scale(1.2);box-shadow:0 0 0 3px rgba(255,255,255,.3)}
.fo-dot.selected{box-shadow:0 0 0 3px #ffcc00,0 0 10px rgba(255,204,0,.5)!important;transform:scale(1.15)}
.fo-dot.fused{box-shadow:0 0 0 2px #00cc66}
/* Passagem: pulsing 3-color animation — cyan, lime, electric-purple */
@keyframes passagem-pulse{
    0%  {box-shadow:0 0 0 3px #00E5FF,0 0 10px rgba(0,229,255,.5)}
    33% {box-shadow:0 0 0 3px #AEEA00,0 0 10px rgba(174,234,0,.5)}
    66% {box-shadow:0 0 0 3px #D500F9,0 0 10px rgba(213,0,249,.5)}
    100%{box-shadow:0 0 0 3px #00E5FF,0 0 10px rgba(0,229,255,.5)}
}
.fo-dot.passante{border-style:dashed;animation:passagem-pulse 1.8s ease-in-out infinite}
.fo-num{font-size:8px;color:#555;line-height:1}

/* V orientation */
.fiber-v .fo-item{flex-direction:row;gap:6px;margin-bottom:3px;align-items:center}
.fiber-v .fo-num{font-size:10px;color:#666;min-width:32px}

/* ── Splitter card ── */
.spl-body{display:flex;align-items:stretch;min-height:60px}
.spl-ports{display:flex;flex-direction:column;gap:6px;padding:8px 6px;justify-content:center}
.spl-mid{flex:1;display:flex;align-items:center;justify-content:center;padding:4px 10px;font-size:18px;font-weight:700;color:#ffcc00;background:rgba(255,204,0,.06);border-left:1px solid rgba(255,204,0,.15);border-right:1px solid rgba(255,204,0,.15)}
.spl-port{width:28px;min-height:22px;border-radius:11px;border:2px solid rgba(255,204,0,.5);background:rgba(255,204,0,.12);cursor:crosshair;display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:8px;color:#ffcc00;user-select:none;transition:transform .1s,box-shadow .1s;padding:2px 1px;box-sizing:border-box}
.spl-port:hover{transform:scale(1.1);box-shadow:0 0 0 3px rgba(255,204,0,.4)}
.spl-port.fused{border-color:#00cc66;background:rgba(0,204,102,.2);color:#00cc66}
.spl-port.selected{box-shadow:0 0 0 3px #ffcc00,0 0 8px rgba(255,204,0,.5)!important}
.spl-in-lbl{font-size:8px;color:#666;text-align:center;margin-bottom:2px}

/* ── Bandeja tabs ── */
.b-tab{padding:3px 10px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--text-muted);cursor:pointer;font-size:12px;transition:all .15s}
.b-tab.active{background:rgba(51,153,255,.2);border-color:#3399ff;color:#3399ff}

/* ── Context menu ── */
#dot-menu{position:fixed;z-index:9999;background:var(--bg-card);border:1px solid rgba(255,255,255,.15);border-radius:8px;padding:4px;box-shadow:0 8px 24px rgba(0,0,0,.6);display:none;min-width:160px}
.dot-menu-item{padding:7px 12px;font-size:12px;cursor:pointer;border-radius:5px;color:var(--text-primary);display:flex;align-items:center;gap:8px}
.dot-menu-item:hover{background:rgba(255,255,255,.08)}
.dot-menu-item.danger{color:#ff4455}
.dot-menu-item.danger:hover{background:rgba(255,68,85,.15)}

/* ── Bandeja dialog ── */
#bd-dialog{display:none;position:fixed;z-index:9999;background:var(--bg-card);border:1px solid rgba(255,204,0,.4);border-radius:12px;padding:16px;width:240px;box-shadow:0 8px 32px rgba(0,0,0,.6)}
</style>

<div class="page-content" style="max-width:none">
<div class="page-header">
    <div>
        <h2><i class="fas fa-sitemap" style="color:#ff9900"></i> Mapa de Fusões</h2>
        <p><?= $labelTipo ?>: <strong><?= e($elemento['codigo']) ?></strong><?= $elemento['nome'] ? ' — '.e($elemento['nome']) : '' ?>
           &nbsp;|&nbsp; <?= $elemento['capacidade'] ?> <?= $elem_tipo==='ceo'?'FO':'portas' ?></p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <a href="<?= BASE_URL ?>/modules/fusoes/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
        <?php if (!empty($allSplitters)): ?>
        <button class="btn btn-secondary" onclick="showAddSplitter()"><i class="fas fa-project-diagram" style="color:#ffcc00"></i> + Splitter</button>
        <?php endif; ?>
        <button class="btn btn-secondary" onclick="resetLayout()"><i class="fas fa-th"></i> Layout</button>
        <button class="btn btn-secondary" onclick="exportarPNG()"><i class="fas fa-image"></i> PNG</button>
        <button class="btn btn-secondary" onclick="exportarPDF()"><i class="fas fa-file-pdf"></i> PDF</button>
    </div>
</div>

<!-- Bandeja toolbar -->
<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap">
    <span style="font-size:12px;color:#555">Bandejas:</span>
    <div id="bandeja-tabs" style="display:flex;gap:6px;flex-wrap:wrap"></div>
    <div style="margin-left:auto;font-size:11px;color:#444">
        <i class="fas fa-crosshairs" style="color:#3399ff"></i> Arraste fibra→fibra para fusionar &nbsp;·&nbsp;
        <i class="fas fa-mouse-pointer" style="color:#ffaa00"></i> Clique direito na fibra para opções &nbsp;·&nbsp;
        <i class="fas fa-arrows-alt" style="color:#555"></i> Arraste cabeçalho do cabo para mover
    </div>
</div>

<!-- Legenda ABNT -->
<div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:10px" id="legend-wrap"></div>

<!-- Canvas -->
<div id="fcanvas">
    <svg id="fsvg"><defs id="svg-defs"></defs></svg>
</div>
</div>

<!-- Context menu -->
<div id="dot-menu"></div>

<!-- Bandeja dialog -->
<div id="bd-dialog">
    <div style="font-weight:700;margin-bottom:10px;font-size:13px"><i class="fas fa-sitemap" style="color:#ff9900"></i> <span id="bd-title">Nova Fusão</span></div>
    <div id="bd-info" style="font-size:12px;color:var(--text-muted);margin-bottom:10px;line-height:1.7;background:rgba(255,255,255,.04);padding:8px;border-radius:6px"></div>
    <div id="bd-tipo-row" style="display:none;margin-bottom:12px">
        <label class="form-label" style="font-size:11px;margin-bottom:6px;display:block">Tipo de Conexão</label>
        <div style="display:flex;gap:6px">
            <label style="flex:1;display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:7px;border:1px solid var(--border);cursor:pointer;font-size:12px" id="bd-tipo-emenda-lbl">
                <input type="radio" name="bd-tipo" id="bd-tipo-emenda" value="emenda" checked style="margin:0">
                <i class="fas fa-cut" style="color:#ff8800;font-size:11px"></i> Emenda
            </label>
            <label style="flex:1;display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:7px;border:1px solid var(--border);cursor:pointer;font-size:12px" id="bd-tipo-passante-lbl">
                <input type="radio" name="bd-tipo" id="bd-tipo-passante" value="passante" style="margin:0">
                <i class="fas fa-arrows-alt-h" style="color:#ffaa00;font-size:11px"></i> Passante
            </label>
        </div>
        <div id="bd-tipo-hint" style="display:none;font-size:10px;color:#ffaa00;margin-top:5px;padding:4px 6px;background:rgba(255,170,0,.07);border-radius:5px">
            <i class="fas fa-info-circle"></i> Passante: fibra física sem corte, sem perda de emenda
        </div>
    </div>
    <div id="bd-bandeja-row" class="form-group" style="margin-bottom:12px">
        <label class="form-label" style="font-size:11px">Bandeja</label>
        <input type="number" id="bd-bandeja" class="form-control" value="1" min="1">
    </div>
    <div style="display:flex;gap:8px">
        <button id="bd-cancel" class="btn btn-secondary" style="flex:1">Cancelar</button>
        <button id="bd-ok" class="btn btn-primary" style="flex:1"><i class="fas fa-check"></i> Salvar</button>
    </div>
</div>

<!-- Add Splitter modal -->
<div id="spl-modal" style="display:none;position:fixed;inset:0;z-index:9998;background:rgba(0,0,0,.7);display:none;align-items:center;justify-content:center">
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:24px;width:360px;max-height:80vh;overflow-y:auto">
        <h4 style="margin-bottom:16px;font-size:14px"><i class="fas fa-project-diagram" style="color:#ffcc00"></i> Adicionar Splitter</h4>
        <div id="spl-list" style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px"></div>
        <button class="btn btn-secondary" style="width:100%" onclick="document.getElementById('spl-modal').style.display='none'">Fechar</button>
    </div>
</div>

<!-- Popup: Ver Sinal -->
<div id="sinal-popup" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:28px 32px;min-width:280px;text-align:center;position:relative">
        <button onclick="document.getElementById('sinal-popup').style.display='none'" style="position:absolute;top:10px;right:12px;background:none;border:none;color:#555;cursor:pointer;font-size:16px">✕</button>
        <div style="font-size:12px;color:#888;margin-bottom:12px;text-transform:uppercase;letter-spacing:.6px"><i class="fas fa-signal"></i> Nível de Sinal</div>
        <div id="sinal-popup-content" style="font-size:14px;color:var(--text)">Calculando...</div>
    </div>
</div>

<!-- Modal: Ver Rota da Fibra -->
<div id="rota-modal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.75);align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:0;width:min(1400px,98vw);height:min(820px,96vh);display:flex;flex-direction:column;position:relative">
        <div style="padding:16px 24px 13px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-shrink:0">
            <i class="fas fa-route" style="color:#ffaa00;font-size:16px"></i>
            <h4 style="margin:0;font-size:15px">Rota da Fibra</h4>
            <button onclick="document.getElementById('rota-modal').style.display='none'" style="margin-left:auto;background:none;border:none;color:#555;cursor:pointer;font-size:18px">✕</button>
        </div>
        <div id="rota-content" style="padding:20px 24px;overflow:hidden;flex:1">
            <div style="text-align:center;color:#888"><i class="fas fa-spinner fa-spin"></i> Carregando rota...</div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
const CABLES        = <?= json_encode($cabos) ?>;
const INST_SPLITTERS= <?= json_encode($instSplitters) ?>;
const ALL_SPLITTERS = <?= json_encode($allSplitters) ?>;
const ELEM_TIPO     = '<?= $elem_tipo ?>';
const ELEM_ID       = <?= $elem_id ?>;
const BASE_URL      = '<?= BASE_URL ?>';
const FC_ABNT       = <?= json_encode($fcPhp) ?>;
// Layout salvo no banco (cross-device). Fallback para localStorage se vazio.
const DB_POSITIONS    = <?= json_encode($layoutRow ? json_decode($layoutRow['positions_json'] ?? '{}', true) : new stdClass()) ?>;
const DB_ORIENTATIONS = <?= json_encode($layoutRow ? json_decode($layoutRow['orientations_json'] ?? '{}', true) : new stdClass()) ?>;
const POS_KEY  = 'ftth_pos_<?= $elem_tipo ?>_<?= $elem_id ?>';
const ORI_KEY  = 'ftth_ori_<?= $elem_tipo ?>_<?= $elem_id ?>';
// Clients by CTO port (1-indexed): { 1: {login,nome,id}, 2: {...}, ... }
const CLIENTES_PORTA = <?= json_encode($clientesPorPorta) ?>;
// Usadas pelas funções de exportar PNG/PDF (nome do arquivo e texto)
const ELEM_CODIGO   = '<?= e($elemento['codigo']) ?>';
const ELEM_LABEL    = '<?= $labelTipo ?>';
const EXPORT_DATE   = '<?= date('d/m/Y') ?>';
const EXPORT_DATE_YMD = '<?= date('Ymd') ?>';

let fusoes      = <?= json_encode($fusoes) ?>.map(f=>({...f}));
let activeBandeja = 'all';
let positions   = {};   // { "c-5": {x,y}, "s-3": {x,y} }
let orientations= {};   // { "c-5": "H"|"V" }
let pendingFusion= null;
let dragSrc     = null; // { dotId, cableId?, fiberNum?, splId?, splPorta?, side:null }
let tempLine    = null;
let cardDrag    = null; // { el, startX, startY, startLeft, startTop }


</script>
<script src="<?= BASE_URL ?>/assets/js/fusoes.js"></script>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
