<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
Auth::check();
$db = Database::getInstance();

$id = (int)($_GET['id'] ?? 0);
$rack = $db->fetch("SELECT * FROM racks WHERE id = ?", [$id]);
if (!$rack) { header('Location: ' . BASE_URL . '/modules/racks/index.php'); exit; }

// ── AJAX handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'connect') {
        $dioId = (int)$_POST['dio_id'];
        $porta = (int)$_POST['dio_porta'];
        $lado  = ($_POST['lado'] ?? 'A') === 'B' ? 'B' : 'A';
        $tipo  = $_POST['tipo'] ?? 'pon'; // 'pon' or 'fibra'

        // Remove existing connection on this DIO slot
        $db->query("DELETE FROM rack_conexoes WHERE dio_id = ? AND dio_porta = ? AND lado = ?", [$dioId, $porta, $lado]);

        if ($tipo === 'fibra') {
            $caboId   = (int)$_POST['cabo_id'];
            $fibraNum = (int)$_POST['fibra_num'];
            // Remove any existing connection for this cable fiber
            $db->query("DELETE FROM rack_conexoes WHERE cabo_id = ? AND fibra_num = ?", [$caboId, $fibraNum]);
            $db->insert('rack_conexoes', [
                'rack_id'   => $id, 'olt_pon_id' => null,
                'cabo_id'   => $caboId, 'fibra_num' => $fibraNum,
                'dio_id'    => $dioId, 'dio_porta' => $porta, 'lado' => $lado,
                'created_by'=> Auth::user()['id'],
            ]);
        } else {
            $ponId = (int)$_POST['pon_id'];
            // Remove any existing connection for this PON
            $db->query("DELETE FROM rack_conexoes WHERE olt_pon_id = ?", [$ponId]);
            $db->insert('rack_conexoes', [
                'rack_id'    => $id, 'olt_pon_id' => $ponId,
                'cabo_id'    => null, 'fibra_num' => null,
                'dio_id'     => $dioId, 'dio_porta' => $porta, 'lado' => $lado,
                'created_by' => Auth::user()['id'],
            ]);
        }
        echo json_encode(['ok' => true]); exit;
    }

    if ($_POST['action'] === 'disconnect') {
        if (!empty($_POST['pon_id'])) {
            $db->query("DELETE FROM rack_conexoes WHERE olt_pon_id = ?", [(int)$_POST['pon_id']]);
        } elseif (!empty($_POST['cabo_id'])) {
            $db->query("DELETE FROM rack_conexoes WHERE cabo_id = ? AND fibra_num = ?",
                [(int)$_POST['cabo_id'], (int)$_POST['fibra_num']]);
        } else {
            $dioId = (int)$_POST['dio_id'];
            $porta = (int)$_POST['dio_porta'];
            $lado  = ($_POST['lado'] ?? 'A') === 'B' ? 'B' : 'A';
            $db->query("DELETE FROM rack_conexoes WHERE dio_id = ? AND dio_porta = ? AND lado = ?", [$dioId, $porta, $lado]);
        }
        echo json_encode(['ok' => true]); exit;
    }
}

// ── Load data ─────────────────────────────────────────────────────────────────
$olts = $db->fetchAll("SELECT * FROM olts WHERE rack_id = ? ORDER BY codigo ASC", [$id]);
$oltIds = array_column($olts, 'id');

$pons = [];
if ($oltIds) {
    $ph = implode(',', array_fill(0, count($oltIds), '?'));
    $pons = $db->fetchAll(
        "SELECT op.*, o.nome as olt_nome, o.codigo as olt_codigo, COALESCE(op.potencia_dbm, 5.00) as potencia_dbm
         FROM olt_pons op JOIN olts o ON o.id = op.olt_id
         WHERE op.olt_id IN ($ph) ORDER BY o.codigo ASC, op.slot ASC, op.numero_pon ASC",
        $oltIds
    );
}

$dios = $db->fetchAll("SELECT * FROM dios WHERE rack_id = ? ORDER BY posicao_u ASC, codigo ASC", [$id]);

// Cables linked to this rack
$cabos = $db->fetchAll(
    "SELECT c.id, c.codigo, c.nome, c.num_fibras, c.fibras_por_tubo, c.config_cores, c.tipo
     FROM cabos c INNER JOIN cabo_pontos cp ON cp.cabo_id = c.id
     WHERE cp.elemento_tipo = 'rack' AND cp.elemento_id = ?
     ORDER BY c.codigo ASC", [$id]);

// All connections (PON or cable fiber)
$conexoesPon = $db->fetchAll(
    "SELECT rc.*, COALESCE(rc.lado,'A') as lado,
            op.slot, op.numero_pon, op.olt_id, op.potencia_dbm as pon_potencia,
            o.codigo as olt_codigo, d.codigo as dio_codigo
     FROM rack_conexoes rc
     JOIN olt_pons op ON op.id = rc.olt_pon_id
     JOIN olts o ON o.id = op.olt_id
     JOIN dios d ON d.id = rc.dio_id
     WHERE rc.rack_id = ? AND rc.olt_pon_id IS NOT NULL", [$id]);

$conexoesFibra = $db->fetchAll(
    "SELECT rc.*, COALESCE(rc.lado,'A') as lado,
            c.codigo as cabo_codigo, d.codigo as dio_codigo
     FROM rack_conexoes rc
     JOIN cabos c ON c.id = rc.cabo_id
     JOIN dios d ON d.id = rc.dio_id
     WHERE rc.rack_id = ? AND rc.cabo_id IS NOT NULL", [$id]);

$pageTitle  = 'Mapa de Conexões — ' . $rack['codigo'];
$activePage = 'racks';
$extraHead  = '<style>
/* ── Canvas ── */
#canvas-wrap{position:relative;overflow:auto;height:calc(100vh - 64px);background:#080b0f}
#canvas{position:relative;min-width:100%;min-height:100%}
#fsvg{position:absolute;top:0;left:0;pointer-events:none;z-index:2;overflow:visible}
#fsvg path{pointer-events:stroke;cursor:pointer}

/* ── Cards ── */
.fcard{position:absolute;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:12px;z-index:5;min-width:200px;box-shadow:0 4px 16px rgba(0,0,0,.4)}
.fcard:hover{border-color:rgba(255,255,255,.2)}
.fcard-hdr{padding:8px 12px;cursor:move;display:flex;align-items:center;gap:7px;user-select:none;border-bottom:1px solid rgba(255,255,255,.06);border-radius:12px 12px 0 0;background:rgba(255,255,255,.05)}
.fcard-title{font-size:12px;font-weight:700;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fcard-sub{font-size:10px;color:#555;margin-top:1px;line-height:1}
.fcard-ports{padding:6px 0}

/* ── Ports ── */
.fport{display:flex;align-items:center;padding:3px 10px;gap:4px;font-size:12px;cursor:default;transition:background .15s;position:relative;min-height:28px}
.fport:hover{background:rgba(255,255,255,.05)}
.fport-info{font-size:10px;color:rgba(255,255,255,.55);white-space:nowrap}

/* ── Dots ── */
.fport-dot{width:14px;height:14px;border-radius:50%;border:2px solid rgba(255,255,255,.25);background:rgba(255,255,255,.05);cursor:crosshair;flex-shrink:0;transition:transform .12s,box-shadow .12s;position:relative}
.fport-dot:hover{transform:scale(1.3);box-shadow:0 0 0 3px rgba(255,255,255,.25)}
.fport-dot.connected{border-color:#00cc66;background:rgba(0,204,102,.25)}
.fport-dot.selected{box-shadow:0 0 0 3px #ffcc00,0 0 10px rgba(255,204,0,.5)!important;transform:scale(1.2);border-color:#ffcc00}
.fport-dot.busy{border-color:#ff4455;background:rgba(255,68,85,.15)}

/* ── DIO separator ── */
.fport-sep{width:1px;background:rgba(255,255,255,.1);align-self:stretch;margin:2px 0}

/* ── Connection line ── */
.conn-line{stroke-width:2;fill:none;opacity:.75}
.conn-line:hover{stroke-width:3;opacity:1}

/* ── Toolbar & Legend ── */
.ftoolbar{position:fixed;top:80px;right:20px;display:flex;flex-direction:column;gap:8px;z-index:100}
.flegend{position:fixed;bottom:20px;left:20px;background:var(--bg-panel);border:1px solid var(--border);border-radius:10px;padding:12px 16px;font-size:12px;color:var(--text-muted);z-index:100;max-width:260px}
.flegend-dot{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:6px;vertical-align:middle}
</style>';
require_once __DIR__ . '/../../includes/header.php';
?>

<div id="canvas-wrap">
    <div id="canvas">
        <svg id="fsvg"></svg>
    </div>
</div>

<!-- Toolbar -->
<div class="ftoolbar">
    <a href="<?= BASE_URL ?>/modules/racks/view.php?id=<?= $id ?>" class="btn btn-secondary btn-sm" title="Voltar"><i class="fas fa-arrow-left"></i></a>
    <button class="btn btn-secondary btn-sm" onclick="autoLayout()" title="Auto-layout"><i class="fas fa-magic"></i></button>
    <button class="btn btn-secondary btn-sm" onclick="resetPositions()" title="Resetar posições"><i class="fas fa-redo"></i></button>
</div>

<!-- Legend -->
<div class="flegend">
    <div style="font-weight:700;font-size:11px;color:var(--text);margin-bottom:8px;text-transform:uppercase;letter-spacing:.6px">
        <?= e($rack['codigo']) ?> — Mapa de Conexões
    </div>
    <div style="margin-bottom:4px"><span class="flegend-dot" style="background:#00cc66"></span>Conectado</div>
    <div style="margin-bottom:4px"><span class="flegend-dot" style="border:2px solid rgba(255,255,255,.25);background:transparent"></span>Livre</div>
    <div style="margin-bottom:4px"><span class="flegend-dot" style="background:#ffcc00"></span>Selecionado (aguardando DIO)</div>
    <div style="margin-top:8px;font-size:10px;opacity:.6;line-height:1.5">
        Clique em uma PON ou fibra → clique no ponto A ou B do DIO para conectar.<br>
        Clique direito na linha para remover.
    </div>
    <div style="margin-top:6px;font-size:10px;color:#aa6600">
        <i class="fas fa-circle" style="font-size:8px"></i> A = lado interno &nbsp;
        <i class="fas fa-circle" style="font-size:8px"></i> B = lado externo
    </div>
    <div style="margin-top:4px;font-size:10px;color:#3399ff">
        <i class="fas fa-minus" style="font-size:8px"></i> Cabos vinculados aparecem à direita
    </div>
</div>

<script>
const BASE_URL         = '<?= BASE_URL ?>';
const RACK_ID          = <?= $id ?>;
const PONS_DATA        = <?= json_encode(array_values($pons)) ?>;
const DIOS_DATA        = <?= json_encode(array_values($dios)) ?>;
const CABOS_DATA       = <?= json_encode(array_values($cabos)) ?>;
const CONEXOES_PON     = <?= json_encode(array_values($conexoesPon)) ?>;
const CONEXOES_FIBRA   = <?= json_encode(array_values($conexoesFibra)) ?>;

const canvasWrap = document.getElementById('canvas-wrap');
const canvas     = document.getElementById('canvas');
const svg        = document.getElementById('fsvg');
const storageKey = 'rack_fusao_pos_' + RACK_ID;

// ── State ─────────────────────────────────────────────────────────────────────
// selectedItem: null | {type:'pon', id:ponId, dotEl} | {type:'fibra', caboId, fibraNum, dotEl}
let selectedItem  = null;
let cardPositions = JSON.parse(localStorage.getItem(storageKey) || '{}');
let cards         = {};

// ── OLT colors ────────────────────────────────────────────────────────────────
const OLT_COLORS  = ['#ff6600','#3399ff','#00cc99','#cc33ff','#ffcc00','#ff3366','#00ccff','#ff9933'];
const oltColorMap = {};
PONS_DATA.forEach(p => {
    if (!oltColorMap[p.olt_id]) {
        oltColorMap[p.olt_id] = OLT_COLORS[Object.keys(oltColorMap).length % OLT_COLORS.length];
    }
});


</script>
<script src="<?= BASE_URL ?>/assets/js/racks.js"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
