<?php
/**
 * Detecta automaticamente qual Slot/PON da OLT alimenta uma CTO,
 * percorrendo o grafo de fusões da mesma forma que api/sinal.php.
 *
 * GET  ?cto_id=X          → retorna olt_pon_id detectado (sem salvar)
 * GET  ?cto_id=X&save=1   → detecta e salva diretamente na CTO
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
Auth::check();
header('Content-Type: application/json; charset=utf-8');

$db     = Database::getInstance();
$cto_id = (int)($_GET['cto_id'] ?? 0);
$save   = !empty($_GET['save']);

if (!$cto_id) {
    echo json_encode(['success' => false, 'error' => 'CTO não informada.']); exit;
}

// ── Rastreia o cabo/fibra de volta até encontrar o olt_pon_id no rack ──────
function traceToOltPon($db, int $caboId, int $fibraNum, int $depth = 0): ?int
{
    if ($depth > 40) return null;

    // Caso A: cabo conectado a um DIO → procura OLT PON no lado A do mesmo pino
    $rac = $db->fetch(
        "SELECT ra.olt_pon_id
         FROM rack_conexoes rb
         JOIN rack_conexoes ra
              ON ra.dio_id = rb.dio_id AND ra.dio_porta = rb.dio_porta AND ra.lado = 'A'
         WHERE rb.cabo_id = ? AND rb.fibra_num = ? AND rb.lado = 'B'
           AND ra.olt_pon_id IS NOT NULL
         LIMIT 1",
        [$caboId, $fibraNum]
    );
    if ($rac && $rac['olt_pon_id']) return (int)$rac['olt_pon_id'];

    // Caso B: cabo chegou via fusão — busca o upstream
    $f = $db->fetch(
        "SELECT cabo_entrada_id, fibra_entrada, spl_ent_id
         FROM fusoes
         WHERE cabo_saida_id = ? AND fibra_saida = ?
           AND NOT (tipo = 'passante' AND cabo_entrada_id = cabo_saida_id)
         LIMIT 1",
        [$caboId, $fibraNum]
    );
    if (!$f) return null;

    // B1: veio de saída de splitter → rastreia a entrada do splitter
    if (!empty($f['spl_ent_id'])) {
        return splitterTraceToOltPon($db, (int)$f['spl_ent_id'], $depth + 1);
    }

    // B2: veio de outro cabo (emenda / passante)
    if (empty($f['cabo_entrada_id'])) return null;
    return traceToOltPon($db, (int)$f['cabo_entrada_id'], (int)$f['fibra_entrada'], $depth + 1);
}

// ── Rastreia a entrada de um splitter até a origem ──────────────────────────
function splitterTraceToOltPon($db, int $splInstId, int $depth): ?int
{
    if ($depth > 40) return null;

    $fin = $db->fetch(
        "SELECT cabo_entrada_id, fibra_entrada, spl_ent_id AS up_spl_id
         FROM fusoes WHERE spl_sai_id = ? LIMIT 1",
        [$splInstId]
    );
    if (!$fin) return null;

    if (!empty($fin['cabo_entrada_id'])) {
        return traceToOltPon($db, (int)$fin['cabo_entrada_id'], (int)$fin['fibra_entrada'], $depth + 1);
    }
    if (!empty($fin['up_spl_id'])) {
        return splitterTraceToOltPon($db, (int)$fin['up_spl_id'], $depth + 1);
    }
    return null;
}

// ── Estratégia 1: via splitter de atendimento da CTO ────────────────────────
$olt_pon_id = null;

$splInst = $db->fetch(
    "SELECT es.id FROM elemento_splitters es
     WHERE es.elem_tipo = 'cto' AND es.elem_id = ? AND es.subtipo = 'atendimento'
     LIMIT 1",
    [$cto_id]
);
if ($splInst) {
    $fin = $db->fetch(
        "SELECT cabo_entrada_id, fibra_entrada FROM fusoes
         WHERE spl_sai_id = ? AND cabo_entrada_id IS NOT NULL LIMIT 1",
        [(int)$splInst['id']]
    );
    if ($fin) {
        $olt_pon_id = traceToOltPon($db, (int)$fin['cabo_entrada_id'], (int)$fin['fibra_entrada']);
    }
}

// ── Estratégia 2: via fusões com cabo de entrada na CTO ─────────────────────
if (!$olt_pon_id) {
    $fins = $db->fetchAll(
        "SELECT DISTINCT cabo_entrada_id, fibra_entrada FROM fusoes
         WHERE cto_id = ? AND cabo_entrada_id IS NOT NULL LIMIT 5",
        [$cto_id]
    );
    foreach ($fins as $fin) {
        $found = traceToOltPon($db, (int)$fin['cabo_entrada_id'], (int)$fin['fibra_entrada']);
        if ($found) { $olt_pon_id = $found; break; }
    }
}

// ── Estratégia 3: via cabos ancorados à CTO no mapa ─────────────────────────
if (!$olt_pon_id) {
    $cables = $db->fetchAll(
        "SELECT DISTINCT f.cabo_saida_id, f.fibra_saida
         FROM fusoes f
         JOIN cabo_pontos cp ON cp.cabo_id = f.cabo_saida_id
         WHERE cp.elemento_tipo = 'cto' AND cp.elemento_id = ?
           AND f.cabo_saida_id IS NOT NULL
         LIMIT 5",
        [$cto_id]
    );
    foreach ($cables as $c) {
        $found = traceToOltPon($db, (int)$c['cabo_saida_id'], (int)$c['fibra_saida']);
        if ($found) { $olt_pon_id = $found; break; }
    }
}

if (!$olt_pon_id) {
    echo json_encode([
        'success' => false,
        'error'   => 'Caminho até a OLT não encontrado. Verifique se as fusões estão cadastradas de ponta a ponta (OLT → CEO → CTO).',
    ]);
    exit;
}

// ── Busca detalhes da PON encontrada ────────────────────────────────────────
$ponInfo = $db->fetch(
    "SELECT op.id, op.slot, op.numero_pon AS pon, o.nome AS olt_nome, o.codigo AS olt_codigo
     FROM olt_pons op JOIN olts o ON o.id = op.olt_id
     WHERE op.id = ?",
    [$olt_pon_id]
);
if (!$ponInfo) {
    echo json_encode(['success' => false, 'error' => 'PON detectada mas não localizada no cadastro.']);
    exit;
}

// ── Salva se solicitado ──────────────────────────────────────────────────────
if ($save) {
    $cto = $db->fetch("SELECT * FROM ctos WHERE id = ?", [$cto_id]);
    $db->update('ctos', ['olt_pon_id' => $olt_pon_id], 'id = ?', [$cto_id]);
    AuditLog::log(
        'editar', 'ctos', $cto_id,
        'Slot/PON detectado automaticamente: Slot '.$ponInfo['slot'].' / PON '.$ponInfo['pon'].' — '.$ponInfo['olt_nome'],
        $cto ?? [], ['olt_pon_id' => $olt_pon_id]
    );
}

echo json_encode([
    'success'    => true,
    'saved'      => $save,
    'olt_pon_id' => (int)$ponInfo['id'],
    'slot'       => (int)$ponInfo['slot'],
    'pon'        => (int)$ponInfo['pon'],
    'olt_nome'   => $ponInfo['olt_nome'],
    'olt_codigo' => $ponInfo['olt_codigo'],
    'label'      => $ponInfo['olt_nome'].' — Slot '.$ponInfo['slot'].' / PON '.$ponInfo['pon'],
]);
