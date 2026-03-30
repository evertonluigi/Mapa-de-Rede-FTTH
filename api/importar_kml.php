<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
Auth::check();

header('Content-Type: application/json; charset=utf-8');

function jr(array $d): void { echo json_encode($d); exit; }

$acao = $_GET['acao'] ?? '';

// ── PARSE: upload KML/KMZ e extrai pontos ────────────────────────────────────
if ($acao === 'parse') {
    if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        jr(['success' => false, 'error' => 'Nenhum arquivo enviado ou erro no upload.']);
    }

    $tmpPath  = $_FILES['arquivo']['tmp_name'];
    $origName = strtolower($_FILES['arquivo']['name']);
    $ext      = pathinfo($origName, PATHINFO_EXTENSION);

    // KMZ = ZIP contendo doc.kml (ou outro .kml)
    if ($ext === 'kmz') {
        if (!class_exists('ZipArchive')) {
            jr(['success' => false, 'error' => 'Extensão ZipArchive não disponível no servidor.']);
        }
        $zip = new ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            jr(['success' => false, 'error' => 'Não foi possível abrir o arquivo KMZ.']);
        }
        $kmlContent = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_ends_with(strtolower($name), '.kml')) {
                $kmlContent = $zip->getFromIndex($i);
                break;
            }
        }
        $zip->close();
        if ($kmlContent === null) {
            jr(['success' => false, 'error' => 'Nenhum arquivo .kml encontrado dentro do KMZ.']);
        }
    } elseif ($ext === 'kml') {
        $kmlContent = file_get_contents($tmpPath);
    } else {
        jr(['success' => false, 'error' => 'Formato inválido. Envie um arquivo .kml ou .kmz.']);
    }

    // Parse XML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($kmlContent);
    if ($xml === false) {
        jr(['success' => false, 'error' => 'Arquivo KML inválido ou corrompido.']);
    }

    $xml->registerXPathNamespace('kml', 'http://www.opengis.net/kml/2.2');
    $xml->registerXPathNamespace('gx',  'http://www.google.com/kml/ext/2.2');

    // Tenta com namespace e sem
    $placemarks = $xml->xpath('//kml:Placemark') ?: $xml->xpath('//Placemark') ?: [];

    $pontos = [];
    foreach ($placemarks as $pm) {
        $pm->registerXPathNamespace('kml', 'http://www.opengis.net/kml/2.2');

        $nome = trim((string)($pm->name ?? ''));
        $desc = trim(strip_tags((string)($pm->description ?? '')));

        // Pega coordenadas — suporta Point e MultiGeometry/Point
        $coordStr = null;
        $pointNodes = $pm->xpath('.//kml:Point/kml:coordinates') ?: $pm->xpath('.//Point/coordinates') ?: [];
        if (!empty($pointNodes)) {
            $coordStr = trim((string)$pointNodes[0]);
        }
        if (!$coordStr) continue; // ignora linhas, polígonos, etc.

        // Formato KML: lng,lat,alt
        $parts = preg_split('/[\s,]+/', $coordStr);
        $parts = array_values(array_filter($parts, fn($v) => $v !== ''));
        if (count($parts) < 2) continue;

        $lng = (float)$parts[0];
        $lat = (float)$parts[1];

        // Validação básica de coordenadas
        if (abs($lat) > 90 || abs($lng) > 180) continue;

        $pontos[] = [
            'nome' => $nome ?: ('Ponto ' . (count($pontos) + 1)),
            'lat'  => round($lat, 8),
            'lng'  => round($lng, 8),
            'desc' => $desc,
        ];
    }

    if (empty($pontos)) {
        jr(['success' => false, 'error' => 'Nenhum ponto (Placemark) encontrado no arquivo. Verifique se o KML contém marcadores de localização.']);
    }

    jr(['success' => true, 'pontos' => $pontos, 'total' => count($pontos)]);
}

// ── IMPORTAR: cria elementos no banco ────────────────────────────────────────
if ($acao === 'importar') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $tipo    = $body['tipo']    ?? '';
    $pontos  = $body['pontos']  ?? [];
    $prefixo = trim($body['prefixo'] ?? '');
    $usarNome = (bool)($body['usar_nome'] ?? false);
    $seq      = max(1, (int)($body['seq_inicio'] ?? 1));
    $defaults = $body['defaults'] ?? [];

    $tiposValidos = ['poste', 'ceo', 'cto', 'rack'];
    if (!in_array($tipo, $tiposValidos) || empty($pontos)) {
        jr(['success' => false, 'error' => 'Parâmetros inválidos.']);
    }

    $db = Database::getInstance();
    $userId = Auth::user()['id'];

    $importados = 0;
    $erros = [];
    $duplicados = 0;

    foreach ($pontos as $idx => $pt) {
        $lat = (float)($pt['lat'] ?? 0);
        $lng = (float)($pt['lng'] ?? 0);
        if (!$lat || !$lng) { $erros[] = ['linha' => $idx + 1, 'erro' => 'Coordenadas inválidas']; continue; }

        // Gera código
        if ($usarNome && !empty($pt['nome'])) {
            $codigo = mb_substr(trim($pt['nome']), 0, 50);
        } else {
            $codigo = $prefixo . str_pad($seq, 3, '0', STR_PAD_LEFT);
        }
        $seq++;

        // Verifica duplicata de código
        $exists = false;
        if ($tipo === 'poste')     $exists = (bool)$db->fetch("SELECT id FROM postes WHERE codigo=?", [$codigo]);
        elseif ($tipo === 'ceo')   $exists = (bool)$db->fetch("SELECT id FROM ceos WHERE codigo=?", [$codigo]);
        elseif ($tipo === 'cto')   $exists = (bool)$db->fetch("SELECT id FROM ctos WHERE codigo=?", [$codigo]);
        elseif ($tipo === 'rack')  $exists = (bool)$db->fetch("SELECT id FROM racks WHERE codigo=?", [$codigo]);

        if ($exists) {
            // Adiciona sufixo numérico para tornar único
            $base = $codigo;
            for ($s = 2; $s <= 999; $s++) {
                $codigo = $base . '-' . $s;
                $ck = false;
                if ($tipo === 'poste')    $ck = (bool)$db->fetch("SELECT id FROM postes WHERE codigo=?", [$codigo]);
                elseif ($tipo === 'ceo')  $ck = (bool)$db->fetch("SELECT id FROM ceos WHERE codigo=?", [$codigo]);
                elseif ($tipo === 'cto')  $ck = (bool)$db->fetch("SELECT id FROM ctos WHERE codigo=?", [$codigo]);
                elseif ($tipo === 'rack') $ck = (bool)$db->fetch("SELECT id FROM racks WHERE codigo=?", [$codigo]);
                if (!$ck) break;
            }
            $duplicados++;
        }

        try {
            if ($tipo === 'poste') {
                $db->insert('postes', [
                    'codigo'      => $codigo,
                    'lat'         => $lat,
                    'lng'         => $lng,
                    'tipo'        => $defaults['tipo_poste'] ?? 'concreto',
                    'status'      => 'ativo',
                    'observacoes' => $pt['desc'] ?? null,
                    'created_by'  => $userId,
                ]);
            } elseif ($tipo === 'ceo') {
                $db->insert('ceos', [
                    'codigo'        => $codigo,
                    'nome'          => $pt['nome'] ?? null,
                    'lat'           => $lat,
                    'lng'           => $lng,
                    'tipo'          => $defaults['tipo_ceo'] ?? 'aerea',
                    'capacidade_fo' => (int)($defaults['capacidade_fo'] ?? 24),
                    'status'        => 'ativo',
                    'observacoes'   => $pt['desc'] ?? null,
                    'created_by'    => $userId,
                ]);
            } elseif ($tipo === 'cto') {
                $db->insert('ctos', [
                    'codigo'           => $codigo,
                    'nome'             => $pt['nome'] ?? null,
                    'lat'              => $lat,
                    'lng'              => $lng,
                    'tipo'             => $defaults['tipo_cto'] ?? 'aerea',
                    'capacidade_portas'=> (int)($defaults['capacidade_portas'] ?? 8),
                    'status'           => 'ativo',
                    'observacoes'      => $pt['desc'] ?? null,
                    'created_by'       => $userId,
                ]);
            } elseif ($tipo === 'rack') {
                $db->insert('racks', [
                    'codigo'     => $codigo,
                    'nome'       => $pt['nome'] ?? null,
                    'lat'        => $lat,
                    'lng'        => $lng,
                    'status'     => 'ativo',
                    'observacoes'=> $pt['desc'] ?? null,
                    'created_by' => $userId,
                ]);
            }
            $importados++;
        } catch (Exception $e) {
            $erros[] = ['linha' => $idx + 1, 'codigo' => $codigo, 'erro' => $e->getMessage()];
        }
    }

    jr([
        'success'    => true,
        'importados' => $importados,
        'duplicados' => $duplicados,
        'erros'      => $erros,
        'total'      => count($pontos),
    ]);
}

jr(['success' => false, 'error' => 'Ação inválida.']);
