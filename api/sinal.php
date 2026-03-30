<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
Auth::check();

header('Content-Type: application/json; charset=utf-8');
$db   = Database::getInstance();
$tipo = $_GET['tipo'] ?? '';
$id   = (int)($_GET['id'] ?? 0);

function jresp(array $d): void { echo json_encode($d); exit; }

// ── Constants ────────────────────────────────────────────────────────────────
const FIBER_ATTN  = 0.00025; // dBm/m  (0.25 dBm/km)
const LOSS_EMENDA = 0.05;    // dBm per splice
const LOSS_PASS   = 0.00;    // dBm passthrough
const PON_DEFAULT = 5.0;     // dBm default OLT TX

// ── Helpers ──────────────────────────────────────────────────────────────────
function caboLen($db, int $id): float {
    $r = $db->fetch("SELECT comprimento_m, comprimento_real FROM cabos WHERE id=?", [$id]);
    if (!$r) return 0.0;
    // Prefer real field length when available; fall back to map-calculated distance
    $real = $r['comprimento_real'];
    return ($real !== null && (float)$real > 0) ? (float)$real : (float)($r['comprimento_m'] ?? 0);
}

/**
 * Signal (dBm) at the OUTPUT port $porta of splitter instance $splInstId.
 * Handles chains of splitters connected directly without cables.
 * Returns ['sinal'=>float|null, 'aviso'=>string|null, 'comprimento_m'=>float].
 * comprimento_m = total fiber from OLT up to (and including) this splitter.
 */
function splitterOutputSignal($db, int $splInstId, string $porta, int $depth): array {
    if ($depth > 40) return ['sinal'=>null,'aviso'=>'Rastreamento muito profundo','comprimento_m'=>0.0];

    // Find what feeds this splitter's INPUT (cable or upstream splitter output)
    $fin = $db->fetch(
        "SELECT cabo_entrada_id, fibra_entrada,
                spl_ent_id AS up_spl_id, spl_ent_porta AS up_spl_porta,
                perda_db AS perda_in
         FROM fusoes WHERE spl_sai_id=? LIMIT 1",
        [$splInstId]
    );
    if (!$fin) return ['sinal'=>null,'aviso'=>'Entrada do splitter sem conexão','comprimento_m'=>0.0];

    // Trace backward to get signal arriving at this splitter's input port
    if (!empty($fin['cabo_entrada_id'])) {
        $up = traceStart($db, (int)$fin['cabo_entrada_id'], (int)$fin['fibra_entrada'], $depth+1);
        if ($up['sinal'] === null) return $up;
        $inLen      = caboLen($db, (int)$fin['cabo_entrada_id']);
        $sigAtInput = $up['sinal'] - $inLen * FIBER_ATTN;
        $totalLen   = $up['comprimento_m'] + $inLen;
        $upAviso    = $up['aviso'];
    } elseif (!empty($fin['up_spl_id'])) {
        // Upstream splitter output feeds this splitter input directly (no cable)
        $up = splitterOutputSignal($db, (int)$fin['up_spl_id'], $fin['up_spl_porta'] ?? 'o0', $depth+1);
        if ($up['sinal'] === null) return $up;
        $sigAtInput = $up['sinal'];
        $totalLen   = $up['comprimento_m'];
        $upAviso    = $up['aviso'];
    } else {
        return ['sinal'=>null,'aviso'=>'Entrada do splitter sem conexão','comprimento_m'=>0.0];
    }

    $connLoss = ($fin['perda_in'] !== null) ? (float)$fin['perda_in'] : LOSS_EMENDA;

    // Splitting loss: prefer per-port value from output fusão, fallback to splitter model
    $splLoss = null;
    $outF = $db->fetch(
        "SELECT perda_db FROM fusoes WHERE spl_ent_id=? AND spl_ent_porta=? LIMIT 1",
        [$splInstId, $porta]
    );
    if ($outF && $outF['perda_db'] !== null) {
        $splLoss = (float)$outF['perda_db'];
    } else {
        $spl = $db->fetch(
            "SELECT s.perda_insercao_db FROM elemento_splitters es
             JOIN splitters s ON s.id=es.splitter_id WHERE es.id=? LIMIT 1",
            [$splInstId]
        );
        if ($spl && $spl['perda_insercao_db'] !== null) {
            $splLoss = (float)$spl['perda_insercao_db'];
        }
    }

    if ($splLoss === null) {
        return ['sinal' => round($sigAtInput - $connLoss, 3),
                'aviso' => 'Splitter sem perda de inserção configurada',
                'comprimento_m' => round($totalLen, 1)];
    }
    return ['sinal' => round($sigAtInput - $connLoss - $splLoss, 3),
            'aviso' => $upAviso,
            'comprimento_m' => round($totalLen, 1)];
}

/**
 * Signal (dBm) at the START of cable $caboId, fiber $fibraNum.
 * Traces backward through fusões + rack_conexoes until OLT is found.
 * Returns ['sinal'=>float|null, 'aviso'=>string|null, 'comprimento_m'=>float].
 * comprimento_m = total fiber distance from OLT to start of this cable.
 */
function traceStart($db, int $caboId, int $fibraNum, int $depth = 0): array {
    if ($depth > 40) return ['sinal'=>null,'aviso'=>'Rastreamento muito profundo','comprimento_m'=>0.0];

    // ── Case A: cable connected to DIO (directly from OLT rack) ──────────────
    $rac = $db->fetch(
        "SELECT op.potencia_dbm
         FROM rack_conexoes rb
         JOIN rack_conexoes ra ON ra.dio_id=rb.dio_id AND ra.dio_porta=rb.dio_porta AND ra.lado='A'
         LEFT JOIN olt_pons op ON op.id=ra.olt_pon_id
         WHERE rb.cabo_id=? AND rb.fibra_num=? AND rb.lado='B' LIMIT 1",
        [$caboId, $fibraNum]
    );
    if ($rac) {
        return ['sinal' => (float)($rac['potencia_dbm'] ?? PON_DEFAULT), 'aviso' => null, 'comprimento_m' => 0.0];
    }

    // ── Case B: cable arrived via fusão ──────────────────────────────────────
    // Exclude only self-referential passante (same cable in=out). Cross-cable passante treated like emenda.
    $f = $db->fetch(
        "SELECT tipo, perda_db, cabo_entrada_id, fibra_entrada, spl_ent_id, spl_ent_porta
         FROM fusoes WHERE cabo_saida_id=? AND fibra_saida=?
           AND NOT (tipo='passante' AND cabo_entrada_id=cabo_saida_id) LIMIT 1",
        [$caboId, $fibraNum]
    );
    if (!$f) return ['sinal'=>null,'aviso'=>null,'comprimento_m'=>0.0];

    // ── Case B1: source is a splitter output ─────────────────────────────────
    if (!empty($f['spl_ent_id'])) {
        // Delegate entirely to splitterOutputSignal which handles chained splitters
        return splitterOutputSignal($db, (int)$f['spl_ent_id'], $f['spl_ent_porta'] ?? 'o0', $depth+1);
    }

    // ── Case B2: source is a cable (emenda / passante) ────────────────────────
    if (empty($f['cabo_entrada_id'])) return ['sinal'=>null,'aviso'=>null,'comprimento_m'=>0.0];

    $up = traceStart($db, (int)$f['cabo_entrada_id'], (int)$f['fibra_entrada'], $depth+1);
    if ($up['sinal'] === null) return $up;

    $inLen = caboLen($db, (int)$f['cabo_entrada_id']);
    $sigAfterCable = $up['sinal'] - $inLen * FIBER_ATTN;
    $totalLen = $up['comprimento_m'] + $inLen;

    $tipo = $f['tipo'];
    $pdb  = $f['perda_db'];
    $aviso = $up['aviso'];

    if ($tipo === 'emenda')   $loss = $pdb !== null ? (float)$pdb : LOSS_EMENDA;
    elseif ($tipo === 'passante') $loss = $pdb !== null ? (float)$pdb : LOSS_PASS;
    elseif ($tipo === 'splitter') {
        if ($pdb !== null) $loss = (float)$pdb;
        else return ['sinal' => $sigAfterCable, 'aviso' => 'Splitter sem perda configurada — resultado parcial', 'comprimento_m' => $totalLen];
    } else $loss = $pdb !== null ? (float)$pdb : 0.0;

    return ['sinal' => $sigAfterCable - $loss, 'aviso' => $aviso, 'comprimento_m' => $totalLen];
}

/**
 * Signal at a specific fiber's near end at a CEO/CTO.
 * Returns ['sinal_local'=>float|null, 'aviso'=>string|null, 'direcao'=>string, 'comprimento_m'=>float].
 */
function signalAtFiberInBox($db, int $caboId, int $fibraNum, string $elemTipo, int $elemId): array {
    // Which end of cable is at this box?
    $pts = $db->fetch(
        "SELECT MIN(sequencia) mn, MAX(sequencia) mx FROM cabo_pontos WHERE cabo_id=?",
        [$caboId]
    );
    $mid = $pts ? (((float)$pts['mn'] + (float)$pts['mx']) / 2.0) : 0;
    $anchor = $db->fetch(
        "SELECT sequencia FROM cabo_pontos
         WHERE cabo_id=? AND elemento_tipo=? AND elemento_id=? LIMIT 1",
        [$caboId, $elemTipo, $elemId]
    );
    $boxAtStart = $anchor ? ((float)$anchor['sequencia'] <= $mid) : true;

    // Trace signal at start of cable
    $t = traceStart($db, $caboId, $fibraNum);
    if ($t['sinal'] === null) {
        return ['sinal_local'=>null,'aviso'=>$t['aviso']??'Sinal não rastreável','direcao'=>$boxAtStart?'inicio_para_fim':'fim_para_inicio','comprimento_m'=>0.0];
    }
    $len = caboLen($db, $caboId);
    $sigInicio = $t['sinal'];
    $sigFim    = $sigInicio - $len * FIBER_ATTN;

    // If box is at last point: signal arriving at box = sigFim, total length includes this cable
    // If box is at first point: this cable's start IS the box → signal = sigInicio, no extra cable
    $sinalLocal  = $boxAtStart ? $sigInicio : $sigFim;
    $comprimento = $boxAtStart ? $t['comprimento_m'] : ($t['comprimento_m'] + $len);
    $direcao     = $boxAtStart ? 'inicio_para_fim' : 'fim_para_inicio';
    return ['sinal_local'=>round($sinalLocal,3),'aviso'=>$t['aviso'],'direcao'=>$direcao,'comprimento_m'=>round($comprimento,1)];
}

// ── Endpoints ─────────────────────────────────────────────────────────────────

// GET ?tipo=fibra&id={caboId}&fibra={fn}&elem_tipo={ceo|cto}&elem_id={id}
// → signal at near end of this fiber in this box
if ($tipo === 'fibra') {
    $fibraNum = (int)($_GET['fibra'] ?? 0);
    $elemTipo = in_array($_GET['elem_tipo']??'', ['ceo','cto']) ? $_GET['elem_tipo'] : 'ceo';
    $elemId   = (int)($_GET['elem_id'] ?? 0);
    if (!$id || !$fibraNum || !$elemId) jresp(['success'=>false,'error'=>'Parâmetros inválidos']);

    $r = signalAtFiberInBox($db, $id, $fibraNum, $elemTipo, $elemId);
    jresp(['success'=>true,'sinal'=>$r['sinal_local'],'aviso'=>$r['aviso'],'direcao'=>$r['direcao'],'comprimento_m'=>$r['comprimento_m']]);
}

// GET ?tipo=cto&id=X  or  ?tipo=ceo&id=X
// → signal at output of the "atendimento" splitter in this box
if ($tipo === 'cto' || $tipo === 'ceo') {
    $elemTipo = $tipo;
    $elemId   = $id;
    if (!$elemId) jresp(['success'=>false,'error'=>'ID inválido']);

    // Find attendance splitter in this box
    $splInst = $db->fetch(
        "SELECT es.id, s.perda_insercao_db, s.relacao, s.codigo spl_codigo
         FROM elemento_splitters es JOIN splitters s ON s.id=es.splitter_id
         WHERE es.elem_tipo=? AND es.elem_id=? AND es.subtipo='atendimento' LIMIT 1",
        [$elemTipo, $elemId]
    );
    if (!$splInst) {
        jresp(['success'=>true,'sinal'=>null,'aviso'=>'Nenhum splitter de atendimento cadastrado']);
    }

    // Find a cable feeding this splitter's input
    $fin = $db->fetch(
        "SELECT cabo_entrada_id, fibra_entrada, perda_db FROM fusoes
         WHERE spl_sai_id=? AND cabo_entrada_id IS NOT NULL LIMIT 1",
        [(int)$splInst['id']]
    );
    if (!$fin) {
        jresp(['success'=>true,'sinal'=>null,'aviso'=>'Splitter de atendimento sem cabo de entrada conectado']);
    }

    $up = traceStart($db, (int)$fin['cabo_entrada_id'], (int)$fin['fibra_entrada']);
    if ($up['sinal'] === null) {
        jresp(['success'=>true,'sinal'=>null,'aviso'=>$up['aviso']??'Sinal não rastreável até a OLT']);
    }

    $inLen = caboLen($db, (int)$fin['cabo_entrada_id']);
    $sigAfterCable = $up['sinal'] - $inLen * FIBER_ATTN;
    $totalLen = round($up['comprimento_m'] + $inLen, 1);
    $connLoss = ($fin['perda_db'] !== null) ? (float)$fin['perda_db'] : LOSS_EMENDA;

    $splLoss = $splInst['perda_insercao_db'] !== null ? (float)$splInst['perda_insercao_db'] : null;
    if ($splLoss === null) {
        jresp(['success'=>true,'sinal'=>round($sigAfterCable - $connLoss, 3),
               'aviso'=>'Splitter sem perda de inserção — resultado parcial',
               'spl_codigo'=>$splInst['spl_codigo'],'spl_relacao'=>$splInst['relacao'],'comprimento_m'=>$totalLen]);
    }

    $sinalSaida = $sigAfterCable - $connLoss - $splLoss;
    jresp(['success'=>true,'sinal'=>round($sinalSaida,3),'aviso'=>$up['aviso'],
           'spl_codigo'=>$splInst['spl_codigo'],'spl_relacao'=>$splInst['relacao'],'comprimento_m'=>$totalLen]);
}

// GET ?tipo=cabo&id=X  → signal + direction for map arrows
if ($tipo === 'cabo' && $id) {
    $fiberRow = $db->fetch(
        "SELECT fibra FROM (
            SELECT fibra_saida AS fibra FROM fusoes WHERE cabo_saida_id=?
            UNION SELECT fibra_num AS fibra FROM rack_conexoes WHERE cabo_id=? AND lado='B'
         ) t ORDER BY fibra LIMIT 1",
        [$id, $id]
    );
    if (!$fiberRow) {
        jresp(['success'=>true,'sinal_entrada'=>null,'sinal_saida'=>null,
               'direcao'=>'inicio_para_fim','fibra'=>null,'perda_cabo'=>null,'aviso'=>null]);
    }
    $fibra = (int)$fiberRow['fibra'];
    $t     = traceStart($db, $id, $fibra);
    $len   = caboLen($db, $id);
    $dir   = 'inicio_para_fim';

    // Determine direction from anchor points
    $pts = $db->fetch("SELECT MIN(sequencia) mn, MAX(sequencia) mx FROM cabo_pontos WHERE cabo_id=?",[$id]);
    $mid = $pts ? (((float)$pts['mn']+(float)$pts['mx'])/2) : 0;
    $ra = $db->fetch("SELECT 1 FROM rack_conexoes WHERE cabo_id=? AND fibra_num=? AND lado='B' LIMIT 1",[$id,$fibra]);
    if ($ra) {
        $rackAnchor = $db->fetch("SELECT cp.sequencia FROM rack_conexoes rb JOIN rack_conexoes ra ON ra.dio_id=rb.dio_id AND ra.dio_porta=rb.dio_porta AND ra.lado='A' JOIN cabo_pontos cp ON cp.cabo_id=rb.cabo_id AND cp.elemento_tipo='rack' WHERE rb.cabo_id=? AND rb.fibra_num=? AND rb.lado='B' LIMIT 1",[$id,$fibra]);
        if ($rackAnchor) $dir = ((float)$rackAnchor['sequencia']<=$mid)?'inicio_para_fim':'fim_para_inicio';
    } else {
        $fu = $db->fetch("SELECT ceo_id,cto_id FROM fusoes WHERE cabo_saida_id=? AND fibra_saida=? AND NOT (tipo='passante' AND cabo_entrada_id=cabo_saida_id) LIMIT 1",[$id,$fibra]);
        if ($fu) {
            $et = $fu['ceo_id']?'ceo':'cto'; $eid=(int)($fu['ceo_id']??$fu['cto_id']);
            $an = $db->fetch("SELECT sequencia FROM cabo_pontos WHERE cabo_id=? AND elemento_tipo=? AND elemento_id=? LIMIT 1",[$id,$et,$eid]);
            if ($an) $dir=((float)$an['sequencia']<=$mid)?'inicio_para_fim':'fim_para_inicio';
        }
    }

    // Apply manual direction override
    $caboRow = $db->fetch("SELECT direcao_invertida FROM cabos WHERE id=?", [$id]);
    if ($caboRow && (int)$caboRow['direcao_invertida'] === 1) {
        $dir = ($dir === 'inicio_para_fim') ? 'fim_para_inicio' : 'inicio_para_fim';
    }

    $sig  = $t['sinal'];
    $perda = round($len * FIBER_ATTN, 4);
    jresp(['success'=>true,'sinal_entrada'=>$sig?round($sig,3):null,
           'sinal_saida'=>$sig?round($sig-$perda,3):null,'direcao'=>$dir,
           'fibra'=>$fibra,'perda_cabo'=>$perda,'aviso'=>$t['aviso']]);
}

// GET ?tipo=spl_ports&id={instId}
// → signal at ALL output ports of a splitter instance (based on relacao, not just connected ones)
if ($tipo === 'spl_ports' && $id) {
    $splMeta = $db->fetch(
        "SELECT s.relacao FROM elemento_splitters es
         JOIN splitters s ON s.id=es.splitter_id WHERE es.id=? LIMIT 1",
        [$id]
    );
    if (!$splMeta) jresp(['success'=>true,'ports'=>[]]);

    $relParts = explode(':', $splMeta['relacao'] ?? '1:8');
    $numOut   = max(1, (int)($relParts[1] ?? 8));

    // Calculate signal at port o0 once; all ports of a standard splitter get the same signal
    $refSig = splitterOutputSignal($db, $id, 'o0', 0);
    $result = [];
    for ($i = 0; $i < $numOut; $i++) {
        $porta = "o$i";
        // Check if this port has a custom perda_db override
        $outF = $db->fetch(
            "SELECT perda_db FROM fusoes WHERE spl_ent_id=? AND spl_ent_porta=? LIMIT 1",
            [$id, $porta]
        );
        if ($outF && $outF['perda_db'] !== null) {
            $sig = splitterOutputSignal($db, $id, $porta, 0);
        } else {
            $sig = $refSig;
        }
        $result[$porta] = [
            'sinal' => $sig['sinal'] !== null ? round($sig['sinal'], 3) : null,
            'aviso' => $sig['aviso'],
        ];
    }
    jresp(['success'=>true,'ports'=>$result]);
}

jresp(['success'=>false,'error'=>'Tipo não suportado']);
