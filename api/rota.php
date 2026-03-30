<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
Auth::check();

header('Content-Type: application/json; charset=utf-8');
$db   = Database::getInstance();
$tipo = $_GET['tipo'] ?? '';  // fibra | spl_porta
$id   = (int)($_GET['id']   ?? 0);

function jr(array $d): void { echo json_encode($d); exit; }

const FIBER_ATTN_R  = 0.00025;
const LOSS_EMENDA_R = 0.05;
const LOSS_PASS_R   = 0.00;
const PON_DEFAULT_R = 5.0;

function caboLenR($db, int $id): float {
    $r = $db->fetch("SELECT comprimento_m, comprimento_real FROM cabos WHERE id=?", [$id]);
    if (!$r) return 0.0;
    $real = $r['comprimento_real'];
    return ($real !== null && (float)$real > 0) ? (float)$real : (float)($r['comprimento_m'] ?? 0);
}

/**
 * Returns splice nodes for all passante records of a cable+fiber.
 * These are CEOs/CTOs the fiber passes through without being cut.
 */
function getPassanteNodes($db, int $caboId, int $fibraNum): array {
    $rows = $db->fetchAll(
        "SELECT f.perda_db,
                CASE WHEN f.ceo_id IS NOT NULL THEN 'CEO' ELSE 'CTO' END AS elem_tipo,
                COALESCE(ce.codigo, ct.codigo) AS elem_cod
         FROM fusoes f
         LEFT JOIN ceos ce ON ce.id=f.ceo_id
         LEFT JOIN ctos ct ON ct.id=f.cto_id
         WHERE f.cabo_entrada_id=? AND f.fibra_entrada=? AND f.tipo='passante'
           AND f.cabo_entrada_id = f.cabo_saida_id",
        [$caboId, $fibraNum]
    );
    $nodes = [];
    foreach ($rows as $r) {
        $nodes[] = [
            't'        => 'splice',
            'tipo'     => 'passante',
            'elem_tipo'=> $r['elem_tipo'] ?? '',
            'elem_cod' => $r['elem_cod'] ?? '',
            'perda_db' => $r['perda_db'] !== null ? (float)$r['perda_db'] : LOSS_PASS_R,
        ];
    }
    return $nodes;
}

/**
 * Build route segment for splitter $splInstId output port $porta.
 * Handles chains of splitters connected directly without cables.
 * Returns ['rota'=>array, 'sinal'=>float|null, 'aviso'=>string|null].
 * rota ends with the splitter node; sinal = signal at splitter output.
 */
function traceSplitterForRota($db, int $splInstId, string $porta, string $elemTipo, string $elemCod, int $depth): array {
    if ($depth > 40) return ['rota'=>[],'sinal'=>null,'aviso'=>'Rastreamento muito profundo'];

    // Find what feeds this splitter's INPUT
    $fin = $db->fetch(
        "SELECT cabo_entrada_id, fibra_entrada,
                spl_ent_id AS up_spl_id, spl_ent_porta AS up_spl_porta,
                perda_db AS perda_in
         FROM fusoes WHERE spl_sai_id=? LIMIT 1",
        [$splInstId]
    );
    if (!$fin) return ['rota'=>[],'sinal'=>null,'aviso'=>'Entrada do splitter sem conexão'];

    $spl = $db->fetch(
        "SELECT s.perda_insercao_db, s.codigo spl_cod, s.relacao FROM elemento_splitters es
         JOIN splitters s ON s.id=es.splitter_id WHERE es.id=? LIMIT 1",
        [$splInstId]
    );

    // If box context not supplied by caller, look it up from elemento_splitters
    if (empty($elemTipo) || empty($elemCod)) {
        $boxRow = $db->fetch(
            "SELECT UPPER(es.elem_tipo) AS et, COALESCE(ce.codigo, ct.codigo) AS ec
             FROM elemento_splitters es
             LEFT JOIN ceos ce ON ce.id=es.elem_id AND es.elem_tipo='ceo'
             LEFT JOIN ctos ct ON ct.id=es.elem_id AND es.elem_tipo='cto'
             WHERE es.id=? LIMIT 1",
            [$splInstId]
        );
        if ($boxRow) {
            if (empty($elemTipo)) $elemTipo = $boxRow['et'] ?? '';
            if (empty($elemCod))  $elemCod  = $boxRow['ec'] ?? '';
        }
    }

    // Per-port splitting loss (prefer output fusão value, fallback to splitter model)
    $outF = $db->fetch("SELECT perda_db FROM fusoes WHERE spl_ent_id=? AND spl_ent_porta=? LIMIT 1", [$splInstId, $porta]);
    $splLoss = null;
    if ($outF && $outF['perda_db'] !== null) $splLoss = (float)$outF['perda_db'];
    elseif ($spl && $spl['perda_insercao_db'] !== null) $splLoss = (float)$spl['perda_insercao_db'];

    $connLoss = ($fin['perda_in'] !== null) ? (float)$fin['perda_in'] : LOSS_EMENDA_R;

    if (!empty($fin['cabo_entrada_id'])) {
        $up = traceRota($db, (int)$fin['cabo_entrada_id'], (int)$fin['fibra_entrada'], $depth+1);
        if ($up['sinal'] === null) return $up;
        // traceRota returns signal at END of the input cable
        $sigPreSpl = $up['sinal'] - $connLoss;
        $upRota    = $up['rota'];
        $upAviso   = $up['aviso'];
    } elseif (!empty($fin['up_spl_id'])) {
        // Another splitter output feeds this splitter input directly (no cable)
        $up = traceSplitterForRota($db, (int)$fin['up_spl_id'], $fin['up_spl_porta'] ?? 'o0', $elemTipo, $elemCod, $depth+1);
        if ($up['sinal'] === null) return $up;
        $sigPreSpl = $up['sinal'] - $connLoss;
        $upRota    = $up['rota'];
        $upAviso   = $up['aviso'];
    } else {
        return ['rota'=>[],'sinal'=>null,'aviso'=>'Entrada do splitter sem conexão'];
    }

    $sigPostSpl = $splLoss !== null ? $sigPreSpl - $splLoss : $sigPreSpl;
    $splNode = ['t'=>'splitter','codigo'=>($spl['spl_cod']??'SPL'),'relacao'=>($spl['relacao']??'?'),
                'perda_emenda'=>$connLoss,'perda_db'=>$splLoss,'porta'=>$porta,
                'elem_tipo'=>$elemTipo,'elem_cod'=>$elemCod];
    $aviso = $splLoss === null ? 'Splitter sem perda configurada' : $upAviso;

    return ['rota'=>array_merge($upRota, [$splNode]), 'sinal'=>round($sigPostSpl,3), 'aviso'=>$aviso];
}

/**
 * Trace backward from the START of cable $caboId / fiber $fibraNum.
 * Builds a route array (from OLT to current position), returned in forward order.
 * Returns ['rota'=>array, 'sinal'=>float|null, 'aviso'=>string|null].
 *
 * rota elements (front→back, so OLT first):
 *   {t:'olt',  nome, pon, potencia_dbm}
 *   {t:'cabo', id, codigo, comprimento_m, fibra_num, perda_cabo}
 *   {t:'splice', tipo, codigo_elem, perda_db}   (CEO/CTO with emenda/passante)
 *   {t:'splitter', codigo, relacao, perda_db, porta}
 */
function traceRota($db, int $caboId, int $fibraNum, int $depth = 0): array {
    if ($depth > 40) return ['rota'=>[],'sinal'=>null,'aviso'=>'Rastreamento muito profundo'];

    $caboInfo = $db->fetch("SELECT c.id,c.codigo,c.comprimento_m,
        f.ceo_id, f.cto_id,
        ce.codigo ceo_cod, ct.codigo cto_cod
        FROM cabos c
        LEFT JOIN fusoes f ON f.cabo_saida_id=c.id AND f.fibra_saida=?
        LEFT JOIN ceos ce ON ce.id=f.ceo_id
        LEFT JOIN ctos ct ON ct.id=f.cto_id
        WHERE c.id=? LIMIT 1", [$fibraNum, $caboId]);
    $caboCodigo = $caboInfo ? $caboInfo['codigo'] : "Cabo #$caboId";
    $caboLen    = caboLenR($db, $caboId);
    $perdaCabo  = round($caboLen * FIBER_ATTN_R, 4);

    // ── Case A: cable from OLT rack ──────────────────────────────────────────
    $rac = $db->fetch(
        "SELECT op.potencia_dbm, o.codigo olt_cod, o.nome olt_nome,
                CONCAT('PON ',op.slot,'/',op.numero_pon) pon_id
         FROM rack_conexoes rb
         JOIN rack_conexoes ra ON ra.dio_id=rb.dio_id AND ra.dio_porta=rb.dio_porta AND ra.lado='A'
         LEFT JOIN olt_pons op ON op.id=ra.olt_pon_id
         LEFT JOIN olts o ON o.id=op.olt_id
         WHERE rb.cabo_id=? AND rb.fibra_num=? AND rb.lado='B' LIMIT 1",
        [$caboId, $fibraNum]
    );
    if ($rac) {
        $potencia = (float)($rac['potencia_dbm'] ?? PON_DEFAULT_R);
        $sinalFim = $potencia - $caboLen * FIBER_ATTN_R;
        $passanteNodes = getPassanteNodes($db, $caboId, $fibraNum);
        $rota = array_merge(
            [
                ['t'=>'olt', 'nome'=>($rac['olt_nome']??$rac['olt_cod']??'OLT'), 'pon'=>($rac['pon_id']??'PON'), 'potencia_dbm'=>$potencia],
                ['t'=>'cabo', 'id'=>$caboId, 'codigo'=>$caboCodigo, 'comprimento_m'=>$caboLen, 'fibra_num'=>$fibraNum, 'perda_cabo'=>$perdaCabo],
            ],
            $passanteNodes
        );
        return ['rota'=>$rota, 'sinal'=>round($sinalFim,3), 'aviso'=>null];
    }

    // ── Case B: cable arrived via fusão ──────────────────────────────────────
    // Exclude only self-referential passante (same cable in=out). Cross-cable passante is handled like emenda.
    $f = $db->fetch(
        "SELECT f.*, ce.codigo ceo_cod, ct.codigo cto_cod
         FROM fusoes f
         LEFT JOIN ceos ce ON ce.id=f.ceo_id
         LEFT JOIN ctos ct ON ct.id=f.cto_id
         WHERE f.cabo_saida_id=? AND f.fibra_saida=?
           AND NOT (f.tipo='passante' AND f.cabo_entrada_id=f.cabo_saida_id) LIMIT 1",
        [$caboId, $fibraNum]
    );
    if (!$f) return ['rota'=>[],'sinal'=>null,'aviso'=>null];

    $elemCod  = $f['ceo_cod'] ?? $f['cto_cod'] ?? null;
    $elemTipo = $f['ceo_id'] ? 'CEO' : ($f['cto_id'] ? 'CTO' : '');

    // ── Case B1: source is a splitter output ─────────────────────────────────
    if (!empty($f['spl_ent_id'])) {
        $splResult = traceSplitterForRota(
            $db, (int)$f['spl_ent_id'], $f['spl_ent_porta'] ?? 'o0',
            $elemTipo, $elemCod, $depth+1
        );
        if ($splResult['sinal'] === null) return $splResult;

        $sigFim        = $splResult['sinal'] - $caboLen * FIBER_ATTN_R;
        $caboNode      = ['t'=>'cabo','id'=>$caboId,'codigo'=>$caboCodigo,'comprimento_m'=>$caboLen,'fibra_num'=>$fibraNum,'perda_cabo'=>$perdaCabo];
        $passanteNodes = getPassanteNodes($db, $caboId, $fibraNum);

        return ['rota'=>array_merge($splResult['rota'], [$caboNode], $passanteNodes),
                'sinal'=>round($sigFim,3),
                'aviso'=>$splResult['aviso']];
    }

    // ── Case B2: source is a cable (emenda / passante) ────────────────────────
    if (empty($f['cabo_entrada_id'])) return ['rota'=>[],'sinal'=>null,'aviso'=>null];

    $up = traceRota($db, (int)$f['cabo_entrada_id'], (int)$f['fibra_entrada'], $depth+1);
    if ($up['sinal'] === null) return $up;

    $pdb  = $f['perda_db'];
    $tipo = $f['tipo'];
    if ($tipo === 'emenda')   $loss = $pdb !== null ? (float)$pdb : LOSS_EMENDA_R;
    elseif ($tipo === 'passante') $loss = $pdb !== null ? (float)$pdb : LOSS_PASS_R;
    elseif ($tipo === 'splitter') $loss = $pdb !== null ? (float)$pdb : 0.0;
    else $loss = $pdb !== null ? (float)$pdb : 0.0;

    $sigAfterFusao = $up['sinal'] - $loss;
    $sigFim = $sigAfterFusao - $caboLen * FIBER_ATTN_R;

    $spliceNode    = ['t'=>'splice','tipo'=>$tipo,'elem_tipo'=>$elemTipo,'elem_cod'=>$elemCod,'perda_db'=>$loss];
    $caboNode      = ['t'=>'cabo','id'=>$caboId,'codigo'=>$caboCodigo,'comprimento_m'=>$caboLen,'fibra_num'=>$fibraNum,'perda_cabo'=>$perdaCabo];
    $passanteNodes = getPassanteNodes($db, $caboId, $fibraNum);

    return ['rota'=>array_merge($up['rota'],[$spliceNode,$caboNode],$passanteNodes),
            'sinal'=>round($sigFim,3), 'aviso'=>$up['aviso']];
}

// ── Endpoint: ?tipo=fibra&id={caboId}&fibra={fn}&elem_tipo={ceo|cto}&elem_id={id}
if ($tipo === 'fibra') {
    $fibraNum = (int)($_GET['fibra'] ?? 0);
    $elemTipo = in_array($_GET['elem_tipo']??'', ['ceo','cto']) ? $_GET['elem_tipo'] : 'ceo';
    $elemId   = (int)($_GET['elem_id'] ?? 0);
    if (!$id || !$fibraNum || !$elemId) jr(['success'=>false,'error'=>'Parâmetros inválidos']);

    // Determine which end of the cable is at this box to decide direction
    $pts = $db->fetch("SELECT MIN(sequencia) mn, MAX(sequencia) mx FROM cabo_pontos WHERE cabo_id=?", [$id]);
    $mid = $pts ? (((float)$pts['mn'] + (float)$pts['mx']) / 2.0) : 0;
    $anchor = $db->fetch("SELECT sequencia FROM cabo_pontos WHERE cabo_id=? AND elemento_tipo=? AND elemento_id=? LIMIT 1", [$id, $elemTipo, $elemId]);
    $boxAtStart = $anchor ? ((float)$anchor['sequencia'] <= $mid) : true;

    $result = traceRota($db, $id, $fibraNum);

    // Append a "box" node at the end
    if ($result['sinal'] === null) {
        jr(['success'=>true,'rota'=>$result['rota'],'sinal'=>null,'aviso'=>$result['aviso']??'Sinal não rastreável até a OLT']);
    }

    if ($boxAtStart) {
        // traceRota returns signal at END of cable; box is at START, so add back cable loss
        $caboLen = caboLenR($db, $id);
        $sinalBox = round($result['sinal'] + $caboLen * FIBER_ATTN_R, 3);
    } else {
        $sinalBox = $result['sinal'];
    }

    jr(['success'=>true,'rota'=>$result['rota'],'sinal'=>$sinalBox,'aviso'=>$result['aviso']]);
}

// ── Endpoint: ?tipo=spl&id={instId}&porta={portaId}
// Signal at a specific splitter port output (or input)
if ($tipo === 'spl') {
    $porta = $_GET['porta'] ?? '';
    if (!$id) jr(['success'=>false,'error'=>'ID inválido']);

    // Determine the cable connected to this port
    $isOutput = str_starts_with($porta, 'o'); // 'o0','o1',...
    if ($isOutput) {
        // Output port: find fusão where spl_ent_id=id AND spl_ent_porta=porta
        $fu = $db->fetch("SELECT cabo_saida_id, fibra_saida, spl_sai_id FROM fusoes WHERE spl_ent_id=? AND spl_ent_porta=? LIMIT 1", [$id, $porta]);
        if (!$fu) {
            // No downstream cable — still calculate signal at this splitter output port
            $splResult = traceSplitterForRota($db, $id, $porta, '', '', 0);
            jr(['success'=>true,'rota'=>$splResult['rota'],'sinal'=>$splResult['sinal'],'aviso'=>$splResult['aviso']]);
        }

        if (!empty($fu['cabo_saida_id'])) {
            // Output goes to a cable: trace from that cable and add back its loss to get signal at port
            $result = traceRota($db, (int)$fu['cabo_saida_id'], (int)$fu['fibra_saida']);
            if ($result['sinal'] === null) {
                jr(['success'=>true,'rota'=>$result['rota'],'sinal'=>null,'aviso'=>$result['aviso']??'Sinal não rastreável']);
            }
            $caboLen = caboLenR($db, (int)$fu['cabo_saida_id']);
            $sinalPorta = round($result['sinal'] + $caboLen * FIBER_ATTN_R, 3);
            jr(['success'=>true,'rota'=>$result['rota'],'sinal'=>$sinalPorta,'aviso'=>$result['aviso']]);
        } else {
            // Output goes directly to another splitter (no cable) — return signal at this port
            $splResult = traceSplitterForRota($db, $id, $porta, '', '', 0);
            jr(['success'=>true,'rota'=>$splResult['rota'],'sinal'=>$splResult['sinal'],'aviso'=>$splResult['aviso']]);
        }
    } else {
        // Input port: find fusão where spl_sai_id=id AND spl_sai_porta=porta → get cabo_entrada_id, fibra_entrada
        $fu = $db->fetch("SELECT cabo_entrada_id, fibra_entrada FROM fusoes WHERE spl_sai_id=? AND spl_sai_porta=? AND cabo_entrada_id IS NOT NULL LIMIT 1", [$id, $porta]);
        if (!$fu) jr(['success'=>true,'rota'=>[],'sinal'=>null,'aviso'=>'Porta sem cabo conectado']);
        $result = traceRota($db, (int)$fu['cabo_entrada_id'], (int)$fu['fibra_entrada']);
        jr(['success'=>true,'rota'=>$result['rota'],'sinal'=>$result['sinal'],'aviso'=>$result['aviso']]);
    }
}

jr(['success'=>false,'error'=>'Tipo não suportado']);
