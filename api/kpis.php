<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
Auth::check();
header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();

// Resumo geral
$resumo = $db->fetch("SELECT
    (SELECT COUNT(*) FROM clientes WHERE status='ativo') as clientes_ativos,
    (SELECT COUNT(*) FROM clientes WHERE status != 'cancelado') as clientes_total,
    (SELECT COUNT(*) FROM clientes WHERE status='ativo' AND sinal_dbm IS NOT NULL AND sinal_dbm < -27) as sinal_critico,
    (SELECT COUNT(*) FROM manutencoes WHERE status IN ('aberto','em_andamento')) as manut_abertas,
    (SELECT COUNT(*) FROM clientes WHERE status='ativo'
        AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())) as crescimento_mes,
    (SELECT COUNT(*) FROM ctos WHERE status != 'inativo') as total_ctos
");

// Ocupação de todas as CTOs ativas
$ctos = $db->fetchAll(
    "SELECT c.id, c.codigo, c.nome, c.capacidade_portas,
        COUNT(cl.id) as usadas,
        ROUND(COUNT(cl.id) * 100.0 / NULLIF(c.capacidade_portas, 0), 1) as pct
     FROM ctos c
     LEFT JOIN clientes cl ON cl.cto_id = c.id AND cl.status = 'ativo'
     WHERE c.status != 'inativo'
     GROUP BY c.id, c.codigo, c.nome, c.capacidade_portas
     ORDER BY pct DESC, usadas DESC"
);

// CTOs acima de 80%
$ctos_lotadas_80 = count(array_filter($ctos, fn($c) => (float)$c['pct'] >= 80));

// Disponibilidade: clientes ativos / total não cancelados
$disponibilidade = (int)$resumo['clientes_total'] > 0
    ? round((int)$resumo['clientes_ativos'] * 100.0 / (int)$resumo['clientes_total'], 1)
    : 100.0;

// Manutenções abertas por prioridade
$manutPrioridade = $db->fetchAll(
    "SELECT prioridade, COUNT(*) as total
     FROM manutencoes
     WHERE status IN ('aberto','em_andamento')
     GROUP BY prioridade"
);
$manutPorPrioridade = ['alta' => 0, 'media' => 0, 'baixa' => 0];
foreach ($manutPrioridade as $m) {
    $key = $m['prioridade'];
    if (isset($manutPorPrioridade[$key])) {
        $manutPorPrioridade[$key] = (int)$m['total'];
    }
}

// Crescimento mensal — últimos 6 meses
$crescimento = $db->fetchAll(
    "SELECT
        DATE_FORMAT(created_at, '%Y-%m') as mes_key,
        DATE_FORMAT(created_at, '%b/%y') as mes_label,
        COUNT(*) as total
     FROM clientes
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
       AND status != 'cancelado'
     GROUP BY mes_key, mes_label
     ORDER BY mes_key ASC"
);

// Manutenções abertas recentes (ordenadas por prioridade e data)
$manutRecentes = $db->fetchAll(
    "SELECT m.id, m.tipo_ocorrencia, m.prioridade, m.status, m.data_ocorrencia,
        m.tipo_elemento, m.elemento_id, u.nome as tecnico
     FROM manutencoes m
     LEFT JOIN usuarios u ON u.id = m.tecnico_id
     WHERE m.status IN ('aberto','em_andamento')
     ORDER BY FIELD(m.prioridade, 'alta', 'media', 'baixa'), m.data_ocorrencia ASC
     LIMIT 10"
);

// Top 5 clientes com pior sinal
$pioresSinais = $db->fetchAll(
    "SELECT id, nome, login, sinal_dbm, cto_id
     FROM clientes
     WHERE status = 'ativo' AND sinal_dbm IS NOT NULL
     ORDER BY sinal_dbm ASC
     LIMIT 5"
);

echo json_encode([
    'resumo' => [
        'clientes_ativos'  => (int)$resumo['clientes_ativos'],
        'ctos_lotadas_80'  => $ctos_lotadas_80,
        'sinal_critico'    => (int)$resumo['sinal_critico'],
        'manut_abertas'    => (int)$resumo['manut_abertas'],
        'disponibilidade'  => $disponibilidade,
        'crescimento_mes'  => (int)$resumo['crescimento_mes'],
    ],
    'ctos_ocupacao'      => $ctos,
    'manut_prioridade'   => $manutPorPrioridade,
    'crescimento_mensal' => $crescimento,
    'manut_recentes'     => $manutRecentes,
    'piores_sinais'      => $pioresSinais,
    'updated_at'         => date('H:i:s'),
], JSON_UNESCAPED_UNICODE);
