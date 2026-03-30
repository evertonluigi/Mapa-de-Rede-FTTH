<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
Auth::check();

header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();
$type = $_GET['type'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $userId = Auth::user()['id'];

    try {
        switch ($type) {
            case 'poste':
                $id = $db->insert('postes', [
                    'codigo'      => $body['codigo'],
                    'lat'         => (float)$body['lat'],
                    'lng'         => (float)$body['lng'],
                    'tipo'        => $body['tipo'] ?? 'concreto',
                    'altura_m'    => $body['altura_m'] ?: null,
                    'proprietario'=> $body['proprietario'] ?: 'Próprio',
                    'observacoes' => $body['observacoes'] ?? '',
                    'created_by'  => $userId,
                ]);
                $data = $db->fetch("SELECT id, codigo, lat, lng, tipo, status FROM postes WHERE id = ?", [$id]);
                AuditLog::log('criar', 'postes', $id, 'Poste '.$body['codigo'].' criado via mapa', [], $data);
                echo json_encode(['success' => true, 'data' => $data, 'message' => 'Poste salvo!']);
                break;

            case 'cto':
                $id = $db->insert('ctos', [
                    'codigo'           => $body['codigo'],
                    'nome'             => $body['nome'] ?? null,
                    'lat'              => (float)$body['lat'],
                    'lng'              => (float)$body['lng'],
                    'tipo'             => $body['tipo'] ?? 'aerea',
                    'capacidade_portas'=> (int)($body['capacidade_portas'] ?? 8),
                    'fabricante'       => $body['fabricante'] ?? null,
                    'modelo'           => $body['modelo'] ?? null,
                    'observacoes'      => $body['observacoes'] ?? '',
                    'created_by'       => $userId,
                ]);
                $data = $db->fetch("SELECT c.id, c.codigo, c.nome, c.lat, c.lng, c.tipo, c.status, c.capacidade_portas,
                    0 as clientes_ativos FROM ctos c WHERE c.id = ?", [$id]);
                AuditLog::log('criar', 'ctos', $id, 'CTO '.$body['codigo'].' criada via mapa', [], $data);
                echo json_encode(['success' => true, 'data' => $data, 'message' => 'CTO salva!']);
                break;

            case 'ceo':
                $id = $db->insert('ceos', [
                    'codigo'        => $body['codigo'],
                    'nome'          => $body['nome'] ?? null,
                    'lat'           => (float)$body['lat'],
                    'lng'           => (float)$body['lng'],
                    'tipo'          => $body['tipo'] ?? 'aerea',
                    'capacidade_fo' => (int)($body['capacidade_fo'] ?? 24),
                    'observacoes'   => $body['observacoes'] ?? '',
                    'created_by'    => $userId,
                ]);
                $data = $db->fetch("SELECT id, codigo, nome, lat, lng, tipo, status, capacidade_fo FROM ceos WHERE id = ?", [$id]);
                AuditLog::log('criar', 'ceos', $id, 'CEO '.$body['codigo'].' criada via mapa', [], $data);
                echo json_encode(['success' => true, 'data' => $data, 'message' => 'CEO salva!']);
                break;

            case 'cabo':
                $pontos = $body['pontos'] ?? [];
                if (count($pontos) < 2) {
                    echo json_encode(['success' => false, 'error' => 'Trace ao menos 2 pontos.']);
                    break;
                }
                $comprimento = calcPolylineLength($pontos);
                $comprReal = isset($body['comprimento_real']) && $body['comprimento_real'] !== '' ? (float)$body['comprimento_real'] : null;
                $id = $db->insert('cabos', [
                    'codigo'           => $body['codigo'],
                    'tipo'             => $body['tipo'] ?? 'monomodo',
                    'num_fibras'       => (int)($body['num_fibras'] ?? 12),
                    'comprimento_m'    => $comprimento,
                    'comprimento_real' => $comprReal,
                    'status'           => $body['status'] ?? 'ativo',
                    'observacoes'      => $body['observacoes'] ?? '',
                    'created_by'       => $userId,
                ]);
                $allowed_tipos = ['poste','ceo','cto','rack'];
                foreach ($pontos as $i => $pt) {
                    $et = in_array($pt['et'] ?? '', $allowed_tipos) ? $pt['et'] : null;
                    $db->insert('cabo_pontos', [
                        'cabo_id'       => $id,
                        'sequencia'     => $i,
                        'lat'           => (float)$pt['lat'],
                        'lng'           => (float)$pt['lng'],
                        'elemento_tipo' => $et,
                        'elemento_id'   => $et ? (int)$pt['eid'] : null,
                    ]);
                }
                $data = $db->fetch("SELECT id, codigo, tipo, num_fibras, comprimento_m, comprimento_real, status, cor_mapa FROM cabos WHERE id = ?", [$id]);
                $data['pontos'] = $pontos;
                $lenMsg = $comprReal !== null ? round($comprReal).'m (real)' : round($comprimento).'m (mapa)';
                AuditLog::log('criar', 'cabos', $id, 'Cabo '.$body['codigo'].' criado via mapa ('.$lenMsg.')');
                echo json_encode(['success' => true, 'data' => $data, 'message' => 'Cabo salvo! '.$lenMsg]);
                break;

            case 'romper_cabo':
                $caboId    = (int)($body['cabo_id']   ?? 0);
                $segIdx    = (int)($body['seg_idx']    ?? 0);
                $breakLat  = (float)($body['break_lat'] ?? 0);
                $breakLng  = (float)($body['break_lng'] ?? 0);
                $novoCodigo = trim($body['novo_codigo'] ?? '');
                if (!$caboId || !$novoCodigo) {
                    echo json_encode(['success'=>false,'error'=>'Informe o código do novo cabo.']); break;
                }
                $cabo = $db->fetch("SELECT * FROM cabos WHERE id=?", [$caboId]);
                if (!$cabo) { echo json_encode(['success'=>false,'error'=>'Cabo não encontrado.']); break; }

                $allPontos = $db->fetchAll(
                    "SELECT * FROM cabo_pontos WHERE cabo_id=? ORDER BY sequencia ASC", [$caboId]);
                if (count($allPontos) < 2 || $segIdx < 0 || $segIdx >= count($allPontos)-1) {
                    echo json_encode(['success'=>false,'error'=>'Ponto de rompimento inválido.']); break;
                }

                // Calculate lengths for both segments
                $breakPt = ['lat' => $breakLat, 'lng' => $breakLng];
                $firstHalfPts  = array_map(fn($p) => ['lat'=>(float)$p['lat'],'lng'=>(float)$p['lng']],
                                    array_slice($allPontos, 0, $segIdx + 1));
                $firstHalfPts[] = $breakPt;
                $secondHalfPts = [$breakPt];
                foreach (array_slice($allPontos, $segIdx + 1) as $p) {
                    $secondHalfPts[] = ['lat'=>(float)$p['lat'],'lng'=>(float)$p['lng']];
                }
                $comprimento1 = calcPolylineLength($firstHalfPts);
                $comprimento2 = calcPolylineLength($secondHalfPts);

                // Create new cable (second segment), same metadata
                $novoCaboId = $db->insert('cabos', [
                    'codigo'          => $novoCodigo,
                    'tipo'            => $cabo['tipo'],
                    'num_fibras'      => $cabo['num_fibras'],
                    'fibras_por_tubo' => $cabo['fibras_por_tubo'] ?? 12,
                    'comprimento_m'   => $comprimento2,
                    'status'          => $cabo['status'] ?? 'ativo',
                    'cor_mapa'        => $cabo['cor_mapa'],
                    'config_cores'    => $cabo['config_cores'],
                    'created_by'      => $userId,
                ]);

                // Insert pontos for new cable: break point (seq 0) + second half
                $seq = 0;
                $db->insert('cabo_pontos', ['cabo_id'=>$novoCaboId,'sequencia'=>$seq++,
                    'lat'=>$breakLat,'lng'=>$breakLng,'elemento_tipo'=>null,'elemento_id'=>null]);
                $secondHalf = array_slice($allPontos, $segIdx + 1);
                foreach ($secondHalf as $pt) {
                    $db->insert('cabo_pontos', ['cabo_id'=>$novoCaboId,'sequencia'=>$seq++,
                        'lat'=>$pt['lat'],'lng'=>$pt['lng'],
                        'elemento_tipo'=>$pt['elemento_tipo'],'elemento_id'=>$pt['elemento_id']]);
                }

                // Trim original cable: delete points after segIdx, add break point at end
                $db->query("DELETE FROM cabo_pontos WHERE cabo_id=? AND sequencia>?", [$caboId, $segIdx]);
                $db->insert('cabo_pontos', ['cabo_id'=>$caboId,'sequencia'=>$segIdx+1,
                    'lat'=>$breakLat,'lng'=>$breakLng,'elemento_tipo'=>null,'elemento_id'=>null]);
                // Update original cable length
                $db->update('cabos', ['comprimento_m' => $comprimento1], 'id=?', [$caboId]);

                // Reassign fusões belonging to elements in the second half
                $secondElemCeos = [];
                $secondElemCtos = [];
                foreach ($secondHalf as $pt) {
                    if ($pt['elemento_tipo'] === 'ceo' && $pt['elemento_id']) $secondElemCeos[] = (int)$pt['elemento_id'];
                    if ($pt['elemento_tipo'] === 'cto' && $pt['elemento_id']) $secondElemCtos[] = (int)$pt['elemento_id'];
                }
                if ($secondElemCeos) {
                    $ph = implode(',', array_fill(0, count($secondElemCeos), '?'));
                    $db->query("UPDATE fusoes SET cabo_entrada_id=? WHERE cabo_entrada_id=? AND ceo_id IN ($ph)",
                        array_merge([$novoCaboId,$caboId], $secondElemCeos));
                    $db->query("UPDATE fusoes SET cabo_saida_id=? WHERE cabo_saida_id=? AND ceo_id IN ($ph)",
                        array_merge([$novoCaboId,$caboId], $secondElemCeos));
                }
                if ($secondElemCtos) {
                    $ph = implode(',', array_fill(0, count($secondElemCtos), '?'));
                    $db->query("UPDATE fusoes SET cabo_entrada_id=? WHERE cabo_entrada_id=? AND cto_id IN ($ph)",
                        array_merge([$novoCaboId,$caboId], $secondElemCtos));
                    $db->query("UPDATE fusoes SET cabo_saida_id=? WHERE cabo_saida_id=? AND cto_id IN ($ph)",
                        array_merge([$novoCaboId,$caboId], $secondElemCtos));
                }

                // Also reassign rack_conexoes cable fibers for elements in second half
                if ($secondElemCeos || $secondElemCtos) {
                    $db->query("UPDATE rack_conexoes SET cabo_id=? WHERE cabo_id=?", [$novoCaboId, $caboId]);
                }

                // Return both cables for client-side update
                $caboDados   = $db->fetch("SELECT id,codigo,tipo,num_fibras,comprimento_m,comprimento_real,status,cor_mapa FROM cabos WHERE id=?", [$caboId]);
                $novoCaboDados = $db->fetch("SELECT id,codigo,tipo,num_fibras,comprimento_m,comprimento_real,status,cor_mapa FROM cabos WHERE id=?", [$novoCaboId]);
                $reloadPontos = function($cid) use ($db) {
                    $rows = $db->fetchAll("SELECT lat,lng,elemento_tipo as et,elemento_id as eid FROM cabo_pontos WHERE cabo_id=? ORDER BY sequencia", [$cid]);
                    return $rows;
                };
                $caboDados['pontos']     = $reloadPontos($caboId);
                $novoCaboDados['pontos'] = $reloadPontos($novoCaboId);
                AuditLog::log('outro', 'cabos', $caboId, 'Cabo '.$cabo['codigo'].' rompido — novo cabo: '.$novoCodigo);
                echo json_encode(['success'=>true,'cabo'=>$caboDados,'novo_cabo'=>$novoCaboDados]);
                break;

            case 'cliente':
                $id = $db->insert('clientes', [
                    'nome'            => $body['nome'],
                    'login'           => $body['login'] ?? null,
                    'cpf_cnpj'        => $body['cpf_cnpj'] ?? null,
                    'telefone'        => $body['telefone'] ?? null,
                    'numero_contrato' => $body['numero_contrato'] ?? null,
                    'serial_onu'      => $body['serial_onu'] ?? null,
                    'plano'           => $body['plano'] ?? null,
                    'endereco'        => $body['endereco'] ?? null,
                    'lat'             => (float)$body['lat'],
                    'lng'             => (float)$body['lng'],
                    'status'          => 'ativo',
                    'created_by'      => $userId,
                ]);
                $data = $db->fetch("SELECT id, nome, login, lat, lng, status, serial_onu, cto_id, porta_cto FROM clientes WHERE id = ?", [$id]);
                AuditLog::log('criar', 'clientes', $id, 'Cliente '.$body['nome'].' criado via mapa', [], $data);
                echo json_encode(['success' => true, 'data' => $data, 'message' => 'Cliente salvo!']);
                break;

            case 'link_drop':
                $cliId = (int)($body['cliente_id'] ?? 0);
                $ctoId = (int)($body['cto_id'] ?? 0);
                $porta = (int)($body['porta_cto'] ?? 0);
                if (!$cliId || !$ctoId || !$porta) { echo json_encode(['success'=>false,'error'=>'Parâmetros inválidos.']); break; }
                $db->update('clientes', ['cto_id'=>$ctoId, 'porta_cto'=>$porta], 'id=?', [$cliId]);
                AuditLog::log('editar', 'clientes', $cliId, 'Cliente vinculado à CTO #'.$ctoId.' porta '.$porta.' via mapa');
                echo json_encode(['success' => true]);
                break;

            case 'move_poste':
                $pid = (int)($body['id'] ?? 0);
                if (!$pid) { echo json_encode(['success'=>false,'error'=>'ID inválido.']); break; }
                $db->update('postes', ['lat'=>(float)$body['lat'],'lng'=>(float)$body['lng']], 'id=?', [$pid]);
                $db->query("UPDATE cabo_pontos SET lat=?, lng=? WHERE elemento_tipo='poste' AND elemento_id=?",
                    [(float)$body['lat'], (float)$body['lng'], $pid]);
                AuditLog::log('outro', 'postes', $pid, 'Poste movido no mapa para '.round((float)$body['lat'],6).', '.round((float)$body['lng'],6));
                echo json_encode(['success' => true]);
                break;

            case 'move_elem':
                // Move CEO, CTO or Rack and update anchored cable points
                $tipo = in_array($body['tipo'] ?? '', ['ceo','cto','rack']) ? $body['tipo'] : null;
                $eid  = (int)($body['id'] ?? 0);
                if (!$tipo || !$eid) { echo json_encode(['success'=>false,'error'=>'Parâmetros inválidos.']); break; }
                $table = ['ceo'=>'ceos','cto'=>'ctos','rack'=>'racks'][$tipo];
                $db->update($table, ['lat'=>(float)$body['lat'],'lng'=>(float)$body['lng']], 'id=?', [$eid]);
                $db->query("UPDATE cabo_pontos SET lat=?, lng=? WHERE elemento_tipo=? AND elemento_id=?",
                    [(float)$body['lat'], (float)$body['lng'], $tipo, $eid]);
                AuditLog::log('outro', $table, $eid, strtoupper($tipo).' movido no mapa para '.round((float)$body['lat'],6).', '.round((float)$body['lng'],6));
                echo json_encode(['success' => true]);
                break;

            case 'move_cabo_pt':
                // Move a single free cable point by cabo_id + sequencia
                $cid = (int)($body['cabo_id'] ?? 0);
                $seq = (int)($body['sequencia'] ?? -1);
                if (!$cid || $seq < 0) { echo json_encode(['success'=>false,'error'=>'Parâmetros inválidos.']); break; }
                $db->query("UPDATE cabo_pontos SET lat=?, lng=? WHERE cabo_id=? AND sequencia=? AND (elemento_tipo IS NULL OR elemento_tipo='')",
                    [(float)$body['lat'], (float)$body['lng'], $cid, $seq]);
                echo json_encode(['success' => true]);
                break;

            case 'anchor_cabo_pt':
                // Anchor a cable point to an element (poste/ceo/cto/rack)
                $cid = (int)($body['cabo_id'] ?? 0);
                $seq = (int)($body['sequencia'] ?? -1);
                $allowed_et = ['poste','ceo','cto','rack'];
                $et  = in_array($body['elemento_tipo'] ?? '', $allowed_et) ? $body['elemento_tipo'] : null;
                $eid = (int)($body['elemento_id'] ?? 0);
                if (!$cid || $seq < 0 || !$et || !$eid) { echo json_encode(['success'=>false,'error'=>'Parâmetros inválidos.']); break; }
                $db->query("UPDATE cabo_pontos SET lat=?, lng=?, elemento_tipo=?, elemento_id=? WHERE cabo_id=? AND sequencia=?",
                    [(float)$body['lat'], (float)$body['lng'], $et, $eid, $cid, $seq]);
                echo json_encode(['success' => true]);
                break;

            case 'unlink_cabo_pt':
                // Remove anchor from a cable point (set elemento_tipo/id to NULL)
                $cid = (int)($body['cabo_id'] ?? 0);
                $allowed_et2 = ['poste','ceo','cto','rack'];
                $et2  = in_array($body['elemento_tipo'] ?? '', $allowed_et2) ? $body['elemento_tipo'] : null;
                $eid2 = (int)($body['elemento_id'] ?? 0);
                if (!$cid || !$et2 || !$eid2) { echo json_encode(['success'=>false,'error'=>'Parâmetros inválidos.']); break; }
                $db->query("UPDATE cabo_pontos SET elemento_tipo=NULL, elemento_id=NULL WHERE cabo_id=? AND elemento_tipo=? AND elemento_id=?",
                    [$cid, $et2, $eid2]);
                echo json_encode(['success' => true]);
                break;

            case 'detach_move_cabo_pt':
                // Detach anchor AND move a cable point (used when user drags an anchored vertex)
                $cid = (int)($body['cabo_id'] ?? 0);
                $seq = (int)($body['sequencia'] ?? -1);
                if (!$cid || $seq < 0) { echo json_encode(['success'=>false,'error'=>'Parâmetros inválidos.']); break; }
                $db->query("UPDATE cabo_pontos SET lat=?, lng=?, elemento_tipo=NULL, elemento_id=NULL WHERE cabo_id=? AND sequencia=?",
                    [(float)$body['lat'], (float)$body['lng'], $cid, $seq]);
                echo json_encode(['success' => true]);
                break;

            case 'add_cabo_pt':
                // Insert a new intermediate point after after_seq, shifting all later points up
                $cid   = (int)($body['cabo_id'] ?? 0);
                $after = (int)($body['after_seq'] ?? -1);
                $lat   = (float)($body['lat'] ?? 0);
                $lng   = (float)($body['lng'] ?? 0);
                if (!$cid || $after < 0) { echo json_encode(['success'=>false,'error'=>'Parâmetros inválidos.']); break; }
                // Shift all points with sequencia > after by +1
                $db->query("UPDATE cabo_pontos SET sequencia=sequencia+1 WHERE cabo_id=? AND sequencia>?", [$cid, $after]);
                $db->insert('cabo_pontos', ['cabo_id'=>$cid, 'sequencia'=>$after+1, 'lat'=>$lat, 'lng'=>$lng]);
                // Return updated pontos
                $newPts = $db->fetchAll("SELECT sequencia,lat,lng,elemento_tipo,elemento_id FROM cabo_pontos WHERE cabo_id=? ORDER BY sequencia", [$cid]);
                echo json_encode(['success'=>true, 'pontos'=>$newPts, 'new_seq'=>$after+1]);
                break;

            case 'extend_cabo':
                // Append new points to the end of an existing cable, update comprimento_m
                $cid   = (int)($body['cabo_id'] ?? 0);
                $novos = $body['novos_pontos'] ?? [];
                if (!$cid || empty($novos)) { echo json_encode(['success'=>false,'error'=>'Parâmetros inválidos.']); break; }
                $maxSeq = $db->fetch("SELECT MAX(sequencia) mx FROM cabo_pontos WHERE cabo_id=?", [$cid]);
                $seq = (int)($maxSeq['mx'] ?? 0) + 1;
                foreach ($novos as $pt) {
                    $et  = in_array($pt['et']??'', ['poste','ceo','cto','rack']) ? $pt['et'] : null;
                    $eid = $et ? (int)($pt['eid'] ?? 0) : null;
                    $db->insert('cabo_pontos', ['cabo_id'=>$cid, 'sequencia'=>$seq++, 'lat'=>(float)$pt['lat'], 'lng'=>(float)$pt['lng'], 'elemento_tipo'=>$et, 'elemento_id'=>$eid]);
                }
                // Recalculate comprimento_m
                $allPts = $db->fetchAll("SELECT lat,lng FROM cabo_pontos WHERE cabo_id=? ORDER BY sequencia", [$cid]);
                $mapped = array_map(fn($p) => ['lat'=>(float)$p['lat'],'lng'=>(float)$p['lng']], $allPts);
                $newLen = calcPolylineLength($mapped);
                $db->update('cabos', ['comprimento_m'=>$newLen], 'id=?', [$cid]);
                $caboDados = $db->fetch("SELECT id,codigo,tipo,num_fibras,comprimento_m,comprimento_real,status,cor_mapa FROM cabos WHERE id=?", [$cid]);
                $pontos    = $db->fetchAll("SELECT sequencia,lat,lng,elemento_tipo et,elemento_id eid FROM cabo_pontos WHERE cabo_id=? ORDER BY sequencia", [$cid]);
                $caboDados['pontos'] = $pontos;
                AuditLog::log('editar', 'cabos', $cid, 'Cabo #'.$cid.' estendido no mapa');
                echo json_encode(['success'=>true,'data'=>$caboDados]);
                break;

            case 'add_reserva': {
                $cid    = (int)($body['cabo_id'] ?? 0);
                $metros = (float)($body['metros'] ?? 0);
                $lat    = (float)($body['lat'] ?? 0);
                $lng    = (float)($body['lng'] ?? 0);
                $desc   = trim($body['descricao'] ?? '');
                if (!$cid || $metros <= 0) { echo json_encode(['success'=>false,'error'=>'Parâmetros inválidos.']); break; }
                $rid = $db->insert('cabo_reservas', ['cabo_id'=>$cid,'lat'=>$lat,'lng'=>$lng,'metros'=>$metros,'descricao'=>$desc ?: null]);
                $db->query("UPDATE cabos SET comprimento_m = comprimento_m + ? WHERE id=?", [$metros, $cid]);
                $reserva = $db->fetch("SELECT * FROM cabo_reservas WHERE id=?", [$rid]);
                $cabo    = $db->fetch("SELECT id,codigo,comprimento_m FROM cabos WHERE id=?", [$cid]);
                AuditLog::log('criar', 'cabo_reservas', $rid, 'Reserva de '.$metros.'m adicionada ao cabo '.$cabo['codigo']);
                echo json_encode(['success'=>true,'reserva'=>$reserva,'comprimento_m'=>(float)$cabo['comprimento_m']]);
                break;
            }
            case 'update_reserva': {
                $rid        = (int)($body['reserva_id'] ?? 0);
                $newMetros  = (float)($body['metros'] ?? 0);
                $desc       = trim($body['descricao'] ?? '');
                if (!$rid || $newMetros <= 0) { echo json_encode(['success'=>false,'error'=>'Parâmetros inválidos.']); break; }
                $old = $db->fetch("SELECT * FROM cabo_reservas WHERE id=?", [$rid]);
                if (!$old) { echo json_encode(['success'=>false,'error'=>'Reserva não encontrada.']); break; }
                $diff = $newMetros - (float)$old['metros'];
                $db->query("UPDATE cabo_reservas SET metros=?, descricao=? WHERE id=?", [$newMetros, $desc ?: null, $rid]);
                $db->query("UPDATE cabos SET comprimento_m = comprimento_m + ? WHERE id=?", [$diff, $old['cabo_id']]);
                $reserva = $db->fetch("SELECT * FROM cabo_reservas WHERE id=?", [$rid]);
                $cabo    = $db->fetch("SELECT id,codigo,comprimento_m FROM cabos WHERE id=?", [$old['cabo_id']]);
                AuditLog::log('editar', 'cabo_reservas', $rid, 'Reserva do cabo '.$cabo['codigo'].' atualizada para '.$newMetros.'m', $old);
                echo json_encode(['success'=>true,'reserva'=>$reserva,'comprimento_m'=>(float)$cabo['comprimento_m']]);
                break;
            }
            case 'delete_reserva': {
                $rid = (int)($body['reserva_id'] ?? 0);
                if (!$rid) { echo json_encode(['success'=>false,'error'=>'ID inválido.']); break; }
                $old = $db->fetch("SELECT * FROM cabo_reservas WHERE id=?", [$rid]);
                if (!$old) { echo json_encode(['success'=>false,'error'=>'Reserva não encontrada.']); break; }
                $db->query("DELETE FROM cabo_reservas WHERE id=?", [$rid]);
                $db->query("UPDATE cabos SET comprimento_m = comprimento_m - ? WHERE id=?", [(float)$old['metros'], $old['cabo_id']]);
                $cabo = $db->fetch("SELECT id,codigo,comprimento_m FROM cabos WHERE id=?", [$old['cabo_id']]);
                AuditLog::log('deletar', 'cabo_reservas', $rid, 'Reserva de '.$old['metros'].'m removida do cabo '.$cabo['codigo'], $old);
                echo json_encode(['success'=>true,'comprimento_m'=>(float)$cabo['comprimento_m']]);
                break;
            }

            case 'invert_cabo_dir': {
                $cid = (int)($body['id'] ?? 0);
                if (!$cid) { echo json_encode(['success'=>false,'error'=>'ID inválido.']); break; }
                $cabo = $db->fetch("SELECT id, direcao_invertida FROM cabos WHERE id=?", [$cid]);
                if (!$cabo) { echo json_encode(['success'=>false,'error'=>'Cabo não encontrado.']); break; }
                $novo = $cabo['direcao_invertida'] ? 0 : 1;
                $db->query("UPDATE cabos SET direcao_invertida=? WHERE id=?", [$novo, $cid]);
                echo json_encode(['success'=>true,'direcao_invertida'=>$novo]);
                break;
            }

            default:
                echo json_encode(['success' => false, 'error' => 'Tipo inválido.']);
        }
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// GET — buscar elementos por bbox
if ($method === 'GET') {
    $bbox = $_GET['bbox'] ?? null; // swLat,swLng,neLat,neLng
    $elements = [];

    if ($bbox) {
        [$swLat, $swLng, $neLat, $neLng] = array_map('floatval', explode(',', $bbox));
        $where = "lat BETWEEN $swLat AND $neLat AND lng BETWEEN $swLng AND $neLng";
        $elements['postes']   = $db->fetchAll("SELECT id, codigo, lat, lng, tipo, status FROM postes WHERE $where AND status='ativo'");
        $elements['ctos']     = $db->fetchAll("SELECT c.id, c.codigo, c.nome, c.lat, c.lng, c.tipo, c.status, c.capacidade_portas,
            op.slot as pon_slot, op.numero_pon as pon_numero,
            o.nome as pon_olt_nome, o.codigo as pon_olt_codigo
            FROM ctos c
            LEFT JOIN olt_pons op ON op.id = c.olt_pon_id
            LEFT JOIN olts o ON o.id = op.olt_id
            WHERE c.lat BETWEEN $swLat AND $neLat AND c.lng BETWEEN $swLng AND $neLng AND c.status != 'inativo'");
        $elements['ceos']     = $db->fetchAll("SELECT id, codigo, nome, lat, lng, tipo, status FROM ceos WHERE $where AND status!='inativo'");
        $elements['olts']     = $db->fetchAll("SELECT id, codigo, nome, lat, lng, status FROM olts WHERE $where");
        $elements['clientes'] = $db->fetchAll("SELECT id, nome, login, lat, lng, status, serial_onu, cto_id, porta_cto FROM clientes WHERE $where AND status='ativo'");
        $elements['reservas'] = $db->fetchAll("SELECT r.id, r.cabo_id, r.lat, r.lng, r.metros, r.descricao FROM cabo_reservas r WHERE r.lat BETWEEN $swLat AND $neLat AND r.lng BETWEEN $swLng AND $neLng");
    }

    echo json_encode(['success' => true, 'data' => $elements]);
    exit;
}

// DELETE — remover elemento
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>false,'error'=>'ID inválido.']); exit; }
    try {
        switch ($type) {
            case 'poste':
                $old = $db->fetch("SELECT * FROM postes WHERE id=?", [$id]) ?? [];
                $db->query("UPDATE cabo_pontos SET elemento_tipo=NULL, elemento_id=NULL WHERE elemento_tipo='poste' AND elemento_id=?", [$id]);
                $db->query("DELETE FROM postes WHERE id=?", [$id]);
                AuditLog::log('deletar', 'postes', $id, 'Poste '.($old['codigo']??"#$id").' removido do mapa', $old);
                break;
            case 'ceo':
                $old = $db->fetch("SELECT * FROM ceos WHERE id=?", [$id]) ?? [];
                $db->query("UPDATE cabo_pontos SET elemento_tipo=NULL, elemento_id=NULL WHERE elemento_tipo='ceo' AND elemento_id=?", [$id]);
                $db->query("DELETE FROM fusoes WHERE ceo_id=?", [$id]);
                $db->query("DELETE FROM ceos WHERE id=?", [$id]);
                AuditLog::log('deletar', 'ceos', $id, 'CEO '.($old['codigo']??"#$id").' removido do mapa', $old);
                break;
            case 'cto':
                $old = $db->fetch("SELECT * FROM ctos WHERE id=?", [$id]) ?? [];
                $db->query("UPDATE cabo_pontos SET elemento_tipo=NULL, elemento_id=NULL WHERE elemento_tipo='cto' AND elemento_id=?", [$id]);
                $db->query("DELETE FROM ctos WHERE id=?", [$id]);
                AuditLog::log('deletar', 'ctos', $id, 'CTO '.($old['codigo']??"#$id").' removida do mapa', $old);
                break;
            case 'olt':
                $old = $db->fetch("SELECT * FROM olts WHERE id=?", [$id]) ?? [];
                $db->query("DELETE FROM olts WHERE id=?", [$id]);
                AuditLog::log('deletar', 'olts', $id, 'OLT '.($old['codigo']??$old['nome']??"#$id").' removida', $old);
                break;
            case 'cliente':
                $old = $db->fetch("SELECT * FROM clientes WHERE id=?", [$id]) ?? [];
                $db->query("DELETE FROM clientes WHERE id=?", [$id]);
                AuditLog::log('deletar', 'clientes', $id, 'Cliente '.($old['nome']??"#$id").' removido do mapa', $old);
                break;
            case 'cabo':
                // Bloquear remoção se existirem fusões referenciando este cabo
                $fusoesCabo = $db->fetchAll(
                    "SELECT DISTINCT
                        COALESCE(ce.codigo, co.codigo, 'Desconhecida') as caixa_codigo,
                        CASE WHEN ce.id IS NOT NULL THEN 'CEO'
                             WHEN co.id IS NOT NULL THEN 'CTO' ELSE '?' END as caixa_tipo
                     FROM fusoes f
                     LEFT JOIN ceos ce ON ce.id = f.ceo_id
                     LEFT JOIN ctos co ON co.id = f.cto_id
                     WHERE f.cabo_entrada_id = ? OR f.cabo_saida_id = ?",
                    [$id, $id]
                );
                if ($fusoesCabo) {
                    $lista = implode(', ', array_map(fn($r) => $r['caixa_tipo'].' '.$r['caixa_codigo'], $fusoesCabo));
                    echo json_encode(['success'=>false,
                        'error'=>"Cabo possui fusões cadastradas e não pode ser removido.\nCaixas com fusão: {$lista}."]);
                    exit;
                }
                $old = $db->fetch("SELECT * FROM cabos WHERE id=?", [$id]) ?? [];
                $db->query("DELETE FROM rack_conexoes WHERE cabo_id=?", [$id]);
                $db->query("DELETE FROM cabo_pontos WHERE cabo_id=?", [$id]);
                $db->query("DELETE FROM cabos WHERE id=?", [$id]);
                AuditLog::log('deletar', 'cabos', $id, 'Cabo '.($old['codigo']??"#$id").' removido do mapa', $old);
                break;
            default:
                echo json_encode(['success'=>false,'error'=>'Tipo inválido.']); exit;
        }
        echo json_encode(['success' => true]);
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Método não suportado.']);
