<?php
require_once __DIR__ . '/CRUDHelper.php';
require_once __DIR__ . '/AuditLog.php';

function jsonResponse(mixed $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function redirect(string $path): void {
    header('Location: ' . BASE_URL . $path);
    exit;
}

function e(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function formatStatus(string $status): string {
    $map = [
        'ativo'      => '<span class="status-badge status-ativo"><i class="fas fa-circle"></i> Ativo</span>',
        'inativo'    => '<span class="status-badge status-inativo"><i class="fas fa-circle"></i> Inativo</span>',
        'cheio'      => '<span class="status-badge status-cheio"><i class="fas fa-circle"></i> Cheio</span>',
        'defeito'    => '<span class="status-badge status-defeito"><i class="fas fa-exclamation-triangle"></i> Defeito</span>',
        'manutencao' => '<span class="status-badge status-manutencao"><i class="fas fa-tools"></i> Manutenção</span>',
        'reserva'    => '<span class="status-badge status-ativo"><i class="fas fa-circle"></i> Reserva</span>',
        'cortado'    => '<span class="status-badge status-defeito"><i class="fas fa-scissors"></i> Cortado</span>',
    ];
    return $map[$status] ?? "<span class='status-badge'>$status</span>";
}

function fiberColor(int $n): array {
    // Padrão Nacional ABNT (sequência brasileira)
    $colors = [
        1  => ['Verde',     '#2E7D32'],
        2  => ['Amarelo',   '#F9A825'],
        3  => ['Branco',    '#EEEEEE'],
        4  => ['Azul',      '#1565C0'],
        5  => ['Vermelho',  '#C62828'],
        6  => ['Roxo',      '#6A1B9A'],
        7  => ['Marrom',    '#4E342E'],
        8  => ['Rosa',      '#E91E63'],
        9  => ['Preto',     '#212121'],
        10 => ['Cinza',     '#757575'],
        11 => ['Laranja',   '#E65100'],
        12 => ['Acqua',     '#00838F'],
    ];
    $idx = (($n - 1) % 12) + 1;
    return $colors[$idx] ?? ['Desconhecida', '#666'];
}

function calcPolylineLength(array $points): float {
    $total = 0;
    for ($i = 1; $i < count($points); $i++) {
        $total += haversine(
            $points[$i-1]['lat'], $points[$i-1]['lng'],
            $points[$i]['lat'],   $points[$i]['lng']
        );
    }
    return round($total, 2);
}

function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
}
