<?php
// === Processamento antes de qualquer output ===
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
Auth::check();

$db = Database::getInstance();

// Salvar configurações do mapa (ANTES de qualquer output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_map_config'])) {
    $fields = ['mapa_lat','mapa_lng','mapa_zoom','mapa_tile'];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            $db->query("INSERT INTO configuracoes (chave, valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)",
                [$f, trim($_POST[$f])]);
        }
    }
    header('Location: ' . BASE_URL . '/dashboard.php?saved=1'); exit;
}

// Carregar configurações do mapa
$cfg = $db->fetchAll("SELECT chave, valor FROM configuracoes WHERE chave LIKE 'mapa_%'");
$mapaConfig = [];
foreach ($cfg as $c) { $mapaConfig[$c['chave']] = $c['valor']; }
$mapaLat  = (float)($mapaConfig['mapa_lat']  ?? -27.5954);
$mapaLng  = (float)($mapaConfig['mapa_lng']  ?? -48.5480);
$mapaZoom = (int)  ($mapaConfig['mapa_zoom'] ?? 14);
$mapaTile =        ($mapaConfig['mapa_tile'] ?? 'street');

// === Agora pode incluir o header (gera HTML) ===
$pageTitle = 'Mapa da Rede';
$activePage = 'dashboard';
$extraHead = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">';
require_once __DIR__ . '/includes/header.php';

// Todos os elementos para o mapa
$postes   = $db->fetchAll("SELECT id, codigo, lat, lng, tipo, status FROM postes WHERE lat IS NOT NULL");
$ceos     = $db->fetchAll("SELECT id, codigo, nome, lat, lng, tipo, status, capacidade_fo FROM ceos WHERE lat IS NOT NULL");
$ctos     = $db->fetchAll("SELECT c.id, c.codigo, c.nome, c.lat, c.lng, c.tipo, c.status, c.capacidade_portas,
    (SELECT COUNT(*) FROM clientes cl WHERE cl.cto_id = c.id AND cl.status='ativo') as clientes_ativos,
    op.slot as pon_slot, op.numero_pon as pon_numero,
    o.nome as pon_olt_nome, o.codigo as pon_olt_codigo
    FROM ctos c
    LEFT JOIN olt_pons op ON op.id = c.olt_pon_id
    LEFT JOIN olts o ON o.id = op.olt_id
    WHERE c.lat IS NOT NULL");
$racks    = $db->fetchAll("SELECT r.id, r.codigo, r.nome, r.lat, r.lng, r.status, r.localizacao, COUNT(o.id) as total_olts FROM racks r LEFT JOIN olts o ON o.rack_id = r.id WHERE r.lat IS NOT NULL GROUP BY r.id ORDER BY r.codigo ASC");
$clientes = $db->fetchAll("SELECT id, nome, login, lat, lng, status, serial_onu, cto_id, porta_cto FROM clientes WHERE lat IS NOT NULL AND status != 'cancelado'");

// Cabos com pontos (incluindo snap de elementos)
$cabos    = $db->fetchAll("SELECT c.id, c.codigo, c.tipo, c.num_fibras, c.comprimento_m, c.comprimento_real, c.status, c.cor_mapa, c.direcao_invertida,
    (SELECT JSON_ARRAYAGG(JSON_OBJECT('lat', p.lat, 'lng', p.lng, 'et', p.elemento_tipo, 'eid', p.elemento_id) ORDER BY p.sequencia)
     FROM cabo_pontos p WHERE p.cabo_id = c.id) as pontos
    FROM cabos c WHERE c.status != 'cortado'");

$reservas = $db->fetchAll("SELECT r.id, r.cabo_id, r.lat, r.lng, r.metros, r.descricao FROM cabo_reservas r");

// CTO port stats
$ctoPortasTotal  = array_sum(array_column($ctos, 'capacidade_portas'));
$ctoPortasUsadas = array_sum(array_column($ctos, 'clientes_ativos'));
$ctoPortasLivres = $ctoPortasTotal - $ctoPortasUsadas;
?>

<div class="map-wrapper">
    <div id="map"></div>

    <!-- Search Bar -->
    <div class="map-controls">
        <div class="search-wrapper">
            <div class="map-search-bar">
                <i class="fas fa-search" style="color:var(--text-muted);font-size:14px"></i>
                <input type="text" id="mapSearch" placeholder="Buscar endereço, código, login, cliente..." autocomplete="off">
                <button class="btn btn-sm btn-primary" onclick="searchMap()" style="padding:5px 12px">
                    <i class="fas fa-search"></i>
                </button>
            </div>
            <div id="searchSuggestions" class="search-suggestions"></div>
        </div>
        <!-- Layers -->
        <div class="map-layers-panel" id="layers-panel">
            <button class="layers-toggle" id="layers-toggle-btn" onclick="toggleLayersPanel()" title="Expandir/Minimizar camadas">
                <i class="fas fa-layer-group"></i>
                <span>Camadas</span>
                <i class="fas fa-chevron-down" id="layers-chevron"></i>
            </button>
            <div id="layers-body" style="display:none;margin-top:6px">
            <label class="layer-item active" id="layer-postes">
                <input type="checkbox" checked onchange="toggleLayer('postes', this.checked)">
                <span class="layer-dot" style="background:#aaaaaa"></span>
                <span class="layer-label">Postes</span>
            </label>
            <label class="layer-item active" id="layer-ceos">
                <input type="checkbox" checked onchange="toggleLayer('ceos', this.checked)">
                <span class="layer-dot" style="background:#9933ff"></span>
                <span class="layer-label">CEOs</span>
            </label>
            <label class="layer-item active" id="layer-ctos">
                <input type="checkbox" checked onchange="toggleLayer('ctos', this.checked)">
                <span class="layer-dot" style="background:#00cc66"></span>
                <span class="layer-label">CTOs</span>
            </label>
            <label class="layer-item active" id="layer-cabos">
                <input type="checkbox" checked onchange="toggleLayer('cabos', this.checked)">
                <span class="layer-dot" style="background:#3399ff"></span>
                <span class="layer-label">Cabos</span>
            </label>
            <div style="margin:4px 0 2px;padding:4px 6px;background:rgba(255,255,255,.04);border-radius:5px;font-size:9px">
                <div style="color:#555;letter-spacing:.5px;margin-bottom:3px">FO por cor</div>
                <div style="display:flex;flex-wrap:wrap;gap:3px 6px">
                    <?php foreach([6=>'#ff8800',12=>'#3399ff',24=>'#00cc66',48=>'#ff4455',96=>'#ff99dd',144=>'#dddddd'] as $fo=>$cor): ?>
                    <span style="display:flex;align-items:center;gap:2px">
                        <span style="width:10px;height:2px;background:<?= $cor ?>;border-radius:1px;display:inline-block"></span>
                        <span style="color:#666"><?= $fo ?></span>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <label class="layer-item active" id="layer-drops">
                <input type="checkbox" checked onchange="toggleLayer('drops', this.checked)">
                <span class="layer-dot" style="background:#222;border:1px solid #555"></span>
                <span class="layer-label">Ramais Drop</span>
            </label>
            <label class="layer-item active" id="layer-clientes">
                <input type="checkbox" checked onchange="toggleLayer('clientes', this.checked)">
                <span class="layer-dot" style="background:#00ccff"></span>
                <span class="layer-label">Clientes</span>
            </label>
            <label class="layer-item active" id="layer-racks">
                <input type="checkbox" checked onchange="toggleLayer('racks', this.checked)">
                <span class="layer-dot" style="background:#cc8800"></span>
                <span class="layer-label">Racks</span>
            </label>
            </div><!-- /layers-body -->
        </div>
    </div>

    <!-- Toolbar -->
    <div class="map-toolbar">
        <div class="tool-btn active" id="tool-select" onclick="setTool('select')" title="">
            <i class="fas fa-mouse-pointer"></i>
            <span class="tool-tooltip">Selecionar</span>
        </div>
        <div class="tool-btn" id="tool-poste" onclick="toggleBatchPoste()" title="">
            <svg width="14" height="24" viewBox="0 0 22 48" style="display:block"><rect x="9" y="6" width="4" height="38" rx="2" fill="currentColor"/><rect x="4" y="6" width="14" height="3" rx="1.5" fill="currentColor"/><circle cx="5" cy="6" r="2.5" fill="#ffee44"/><circle cx="17" cy="6" r="2.5" fill="#ffee44"/></svg>
            <span class="tool-tooltip">Adicionar Postes (clique múltiplo)</span>
        </div>
        <div class="tool-btn" id="tool-ceo" onclick="setTool('ceo')" title="">
            <svg width="18" height="14" viewBox="0 0 34 26" style="display:block"><rect x="1" y="4" width="32" height="20" rx="3" fill="none" stroke="currentColor" stroke-width="2"/><rect x="4" y="8" width="26" height="2" rx="1" fill="currentColor" opacity="0.7"/><rect x="4" y="13" width="26" height="2" rx="1" fill="currentColor" opacity="0.7"/><rect x="4" y="18" width="26" height="2" rx="1" fill="currentColor" opacity="0.7"/></svg>
            <span class="tool-tooltip">Adicionar CEO (Caixa Emenda)</span>
        </div>
        <div class="tool-btn" id="tool-cto" onclick="setTool('cto')" title="">
            <svg width="18" height="16" viewBox="0 0 34 30" style="display:block"><rect x="1" y="3" width="32" height="24" rx="3" fill="none" stroke="currentColor" stroke-width="2"/><rect x="5" y="12" width="5" height="7" rx="1" fill="currentColor" opacity="0.7"/><rect x="14" y="12" width="5" height="7" rx="1" fill="currentColor" opacity="0.7"/><rect x="23" y="12" width="5" height="7" rx="1" fill="currentColor" opacity="0.7"/></svg>
            <span class="tool-tooltip">Adicionar CTO (Caixa Terminal)</span>
        </div>
        <div class="tool-btn" id="tool-cabo" onclick="iniciarDrawCabo()" title="">
            <svg width="22" height="12" viewBox="0 0 36 12" style="display:block"><line x1="2" y1="6" x2="34" y2="6" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><circle cx="2" cy="6" r="3" fill="currentColor"/><circle cx="34" cy="6" r="3" fill="currentColor"/></svg>
            <span class="tool-tooltip">Traçar Cabo (clique → duplo-clique finaliza)</span>
        </div>
        <div class="tool-btn" id="tool-cliente" onclick="setTool('cliente')" title="">
            <svg width="16" height="18" viewBox="0 0 28 32" style="display:block"><polygon points="14,1 1,13 27,13" fill="currentColor"/><rect x="4" y="12" width="20" height="16" rx="1" fill="currentColor" opacity="0.8"/><rect x="11" y="19" width="6" height="9" rx="1" fill="#001122"/></svg>
            <span class="tool-tooltip">Adicionar Cliente / ONU</span>
        </div>
        <div class="tool-btn" id="tool-move" onclick="toggleMoveMode()" title="">
            <i class="fas fa-arrows-alt"></i>
            <span class="tool-tooltip">Modo Mover (arrastar postes, caixas e pontos de cabo)</span>
        </div>
        <div style="height:1px;background:var(--border);margin:4px 0"></div>
        <div class="tool-btn" onclick="goHome()" title="">
            <i class="fas fa-crosshairs"></i>
            <span class="tool-tooltip">Ir para posição inicial do mapa</span>
        </div>
        <div class="tool-btn" id="btn-locate" onclick="locateUser()" title="">
            <i class="fas fa-location-arrow" id="locate-icon"></i>
            <span class="tool-tooltip">Minha localização</span>
        </div>
        <div class="tool-btn" onclick="toggleMapStyle()" title="">
            <i class="fas fa-satellite" id="map-style-icon"></i>
            <span class="tool-tooltip">Alternar satélite / rua</span>
        </div>
        <div style="height:1px;background:var(--border);margin:4px 0"></div>
        <div class="tool-btn" onclick="openModal('modal-mapa-config')" title="">
            <i class="fas fa-map-marker-alt" style="color:#ffaa00"></i>
            <span class="tool-tooltip">Configurar posição inicial</span>
        </div>
    </div>

    <!-- Stats Bar -->
    <div class="stats-bar">
        <div class="stat-card">
            <div class="num" style="color:#aaa;font-size:18px"><?= count($postes) ?></div>
            <div class="lbl">Postes</div>
        </div>
        <div class="stat-card">
            <div class="num" style="color:#9933ff;font-size:18px"><?= count($ceos) ?></div>
            <div class="lbl">CEOs</div>
        </div>
        <div class="stat-card">
            <div class="num" style="color:#00cc66;font-size:18px"><?= count($ctos) ?></div>
            <div class="lbl">CTOs</div>
        </div>
        <div class="stat-card" style="border-color:rgba(0,204,102,.3);padding:6px 10px">
            <div style="font-size:9px;color:#555;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px">Portas CTO</div>
            <div style="display:flex;gap:8px;align-items:flex-end">
                <div style="text-align:center">
                    <div style="font-size:15px;font-weight:700;color:#00cc66;line-height:1"><?= $ctoPortasTotal ?></div>
                    <div style="font-size:9px;color:#555;margin-top:1px">TOTAL</div>
                </div>
                <div style="text-align:center">
                    <div style="font-size:15px;font-weight:700;color:#ffaa00;line-height:1"><?= $ctoPortasUsadas ?></div>
                    <div style="font-size:9px;color:#555;margin-top:1px">USO</div>
                </div>
                <div style="text-align:center">
                    <div style="font-size:15px;font-weight:700;color:#00ff88;line-height:1"><?= $ctoPortasLivres ?></div>
                    <div style="font-size:9px;color:#555;margin-top:1px">LIVRES</div>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="num" style="color:#00ccff;font-size:18px"><?= count($clientes) ?></div>
            <div class="lbl">Clientes</div>
        </div>
    </div>

    <!-- Info Panel (bottom popup) -->
    <div class="info-panel" id="infoPanel"></div>

    <!-- Float Draw Bar -->
    <div class="float-draw-bar" id="float-draw-bar" style="display:none;">
        <div class="float-draw-bar-info">
            <span id="float-draw-icon">🔌</span>
            <span id="float-draw-label">Desenhando cabo...</span>
            <span id="float-draw-pts" class="float-draw-badge">0 pts</span>
            <span id="float-draw-dist" class="float-draw-badge">0 m</span>
        </div>
        <div class="float-draw-bar-actions">
            <button class="btn-float-action" onclick="undoLastPoint()" title="Desfazer último ponto"><i class="fas fa-undo"></i></button>
            <button class="btn-float-action btn-float-ok" onclick="finalizarTracadoCabo()" title="Finalizar traçado"><i class="fas fa-check"></i> Finalizar</button>
            <button class="btn-float-action btn-float-cancel" onclick="cancelarCabo()" title="Cancelar"><i class="fas fa-times"></i></button>
        </div>
    </div>
</div>

<!-- Modal: Adicionar Poste -->
<div class="modal-overlay" id="modal-poste">
    <div class="modal">
        <div class="modal-header">
            <i class="fas fa-border-all"></i>
            <h3>Novo Poste</h3>
            <div class="modal-close" onclick="closeModal('modal-poste')"><i class="fas fa-times"></i></div>
        </div>
        <form id="form-poste" onsubmit="saveElement(event,'poste')">
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Código *</label>
                    <input class="form-control" name="codigo" required placeholder="ex: PST-001">
                </div>
                <div class="form-group">
                    <label class="form-label">Tipo</label>
                    <select class="form-control" name="tipo">
                        <option value="concreto">Concreto</option>
                        <option value="madeira">Madeira</option>
                        <option value="metalico">Metálico</option>
                        <option value="outro">Outro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Altura (metros)</label>
                    <input class="form-control" name="altura_m" type="number" step="0.1" placeholder="11">
                </div>
                <div class="form-group">
                    <label class="form-label">Proprietário</label>
                    <input class="form-control" name="proprietario" placeholder="Próprio / CELESC / TIM...">
                </div>
                <input type="hidden" name="lat" id="poste-lat">
                <input type="hidden" name="lng" id="poste-lng">
                <div class="form-group full">
                    <label class="form-label">Observações</label>
                    <textarea class="form-control" name="observacoes" rows="2"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-poste')">Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
        </div>
        </form>
    </div>
</div>

<!-- Modal: Adicionar CTO -->
<div class="modal-overlay" id="modal-cto">
    <div class="modal">
        <div class="modal-header">
            <i class="fas fa-box-open" style="color:#00cc66"></i>
            <h3>Nova CTO</h3>
            <div class="modal-close" onclick="closeModal('modal-cto')"><i class="fas fa-times"></i></div>
        </div>
        <form id="form-cto" onsubmit="saveElement(event,'cto')">
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Código *</label>
                    <input class="form-control" name="codigo" required placeholder="ex: CTO-001">
                </div>
                <div class="form-group">
                    <label class="form-label">Nome</label>
                    <input class="form-control" name="nome" placeholder="ex: CTO Rua das Flores">
                </div>
                <div class="form-group">
                    <label class="form-label">Capacidade (portas)</label>
                    <select class="form-control" name="capacidade_portas">
                        <option value="4">4 portas</option>
                        <option value="8" selected>8 portas</option>
                        <option value="16">16 portas</option>
                        <option value="32">32 portas</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tipo</label>
                    <select class="form-control" name="tipo">
                        <option value="aerea">Aérea</option>
                        <option value="subterranea">Subterrânea</option>
                        <option value="pedestal">Pedestal</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Fabricante</label>
                    <input class="form-control" name="fabricante" placeholder="Furukawa, Fiberhome...">
                </div>
                <div class="form-group">
                    <label class="form-label">Modelo</label>
                    <input class="form-control" name="modelo" placeholder="">
                </div>
                <input type="hidden" name="lat" id="cto-lat">
                <input type="hidden" name="lng" id="cto-lng">
                <div class="form-group full">
                    <label class="form-label">Observações</label>
                    <textarea class="form-control" name="observacoes" rows="2"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-cto')">Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
        </div>
        </form>
    </div>
</div>

<!-- Modal: Adicionar CEO -->
<div class="modal-overlay" id="modal-ceo">
    <div class="modal">
        <div class="modal-header">
            <i class="fas fa-box" style="color:#9933ff"></i>
            <h3>Nova CEO (Caixa de Emenda)</h3>
            <div class="modal-close" onclick="closeModal('modal-ceo')"><i class="fas fa-times"></i></div>
        </div>
        <form id="form-ceo" onsubmit="saveElement(event,'ceo')">
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Código *</label>
                    <input class="form-control" name="codigo" required placeholder="ex: CEO-001">
                </div>
                <div class="form-group">
                    <label class="form-label">Nome</label>
                    <input class="form-control" name="nome" placeholder="ex: CEO Esquina Norte">
                </div>
                <div class="form-group">
                    <label class="form-label">Capacidade (FO)</label>
                    <select class="form-control" name="capacidade_fo">
                        <option value="12">12 FO</option>
                        <option value="24" selected>24 FO</option>
                        <option value="48">48 FO</option>
                        <option value="72">72 FO</option>
                        <option value="96">96 FO</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tipo</label>
                    <select class="form-control" name="tipo">
                        <option value="aerea">Aérea</option>
                        <option value="subterranea">Subterrânea</option>
                        <option value="pedestal">Pedestal</option>
                    </select>
                </div>
                <input type="hidden" name="lat" id="ceo-lat">
                <input type="hidden" name="lng" id="ceo-lng">
                <div class="form-group full">
                    <label class="form-label">Observações</label>
                    <textarea class="form-control" name="observacoes" rows="2"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-ceo')">Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
        </div>
        </form>
    </div>
</div>

<!-- Modal: Traçar Cabo -->
<div class="modal-overlay" id="modal-cabo">
    <div class="modal">
        <div class="modal-header">
            <i class="fas fa-minus" style="color:#3399ff"></i>
            <h3>Novo Cabo</h3>
            <div class="modal-close" onclick="closeModal('modal-cabo');stopDrawCabo()"><i class="fas fa-times"></i></div>
        </div>
        <form id="form-cabo" onsubmit="saveCabo(event)">
        <div class="modal-body">
            <div style="background:rgba(0,180,255,0.08);border:1px solid rgba(0,180,255,0.2);border-radius:10px;padding:12px;margin-bottom:16px;font-size:13px">
                <i class="fas fa-info-circle" style="color:var(--primary)"></i>
                Clique no mapa para traçar o percurso do cabo. <strong>Clique duplo</strong> para finalizar.
                <br><span id="cabo-pontos-count" style="color:var(--primary)">0 pontos marcados</span>
                — Comprimento: <span id="cabo-length">0 m</span>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Código *</label>
                    <input class="form-control" name="codigo" required placeholder="ex: CAB-001">
                </div>
                <div class="form-group">
                    <label class="form-label">Tipo</label>
                    <select class="form-control" name="tipo">
                        <option value="monomodo">Monomodo</option>
                        <option value="multimodo">Multimodo</option>
                        <option value="drop">Drop</option>
                        <option value="aerial">Aéreo</option>
                        <option value="subterraneo">Subterrâneo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Nº de Fibras</label>
                    <select class="form-control" name="num_fibras">
                        <option value="4">4 FO</option>
                        <option value="8">8 FO</option>
                        <option value="12" selected>12 FO</option>
                        <option value="24">24 FO</option>
                        <option value="36">36 FO</option>
                        <option value="48">48 FO</option>
                        <option value="72">72 FO</option>
                        <option value="96">96 FO</option>
                        <option value="144">144 FO</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-control" name="status">
                        <option value="ativo">Ativo</option>
                        <option value="reserva">Reserva</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" title="Comprimento real medido em campo (diferente do traçado no mapa)">
                        Comprimento Real (m)
                        <span style="font-size:10px;color:var(--text-muted);font-weight:400"> — campo</span>
                    </label>
                    <input class="form-control" name="comprimento_real" type="number" min="0" step="0.1" placeholder="ex: 125.5 (opcional)">
                </div>
                <div class="form-group full">
                    <label class="form-label">Observações</label>
                    <textarea class="form-control" name="observacoes" rows="2"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-cabo');stopDrawCabo()">Cancelar</button>
            <button type="button" class="btn btn-secondary" onclick="undoLastPoint()"><i class="fas fa-undo"></i> Desfazer</button>
            <button type="submit" class="btn btn-primary" id="btn-salvar-cabo" disabled><i class="fas fa-save"></i> Salvar Cabo</button>
        </div>
        </form>
    </div>
</div>

<!-- Context menu: ações sobre cabo (botão direito) -->
<div id="ctx-cabo" onclick="event.stopPropagation()" style="display:none;position:fixed;z-index:9999;background:var(--bg-panel);border:1px solid var(--border);border-radius:10px;padding:6px 0;min-width:190px;box-shadow:0 8px 24px rgba(0,0,0,.5)">
    <div id="ctx-cabo-info" style="padding:6px 14px 4px;font-size:11px;color:var(--text-muted);border-bottom:1px solid var(--border);margin-bottom:4px"></div>
    <div onclick="ctxAdicionarPonto()" style="padding:8px 14px;cursor:pointer;display:flex;align-items:center;gap:8px;font-size:13px" onmouseover="this.style.background='rgba(255,255,255,.06)'" onmouseout="this.style.background=''">
        <i class="fas fa-map-pin" style="width:16px;color:#00ccff"></i> Adicionar Ponto
    </div>
    <div onclick="continuarDesenhoCabo()" style="padding:8px 14px;cursor:pointer;display:flex;align-items:center;gap:8px;font-size:13px" onmouseover="this.style.background='rgba(255,255,255,.06)'" onmouseout="this.style.background=''">
        <i class="fas fa-pencil-alt" style="width:16px;color:#00cc66"></i> Continuar Desenhando
    </div>
    <div onclick="ctxAdicionarReserva()" style="padding:8px 14px;cursor:pointer;display:flex;align-items:center;gap:8px;font-size:13px;color:#ffaa00" onmouseover="this.style.background='rgba(255,170,0,.1)'" onmouseout="this.style.background=''">
        <i class="fas fa-ruler-combined" style="width:16px;color:#ffaa00"></i> Adicionar Reserva Técnica
    </div>
    <div onclick="iniciarRompimento()" style="padding:8px 14px;cursor:pointer;display:flex;align-items:center;gap:8px;font-size:13px;color:#ff6600" onmouseover="this.style.background='rgba(255,102,0,.12)'" onmouseout="this.style.background=''">
        <i class="fas fa-bolt" style="width:16px"></i> Romper Cabo
    </div>
    <div onclick="ctxRemoverCabo()" style="padding:8px 14px;cursor:pointer;display:flex;align-items:center;gap:8px;font-size:13px;color:var(--danger)" onmouseover="this.style.background='rgba(255,68,85,.12)'" onmouseout="this.style.background=''">
        <i class="fas fa-trash" style="width:16px"></i> Remover Cabo
    </div>
</div>

<!-- Modal: Reserva Técnica -->
<div id="modal-reserva" style="display:none;position:fixed;inset:0;z-index:10001;background:rgba(0,0,0,.7);align-items:center;justify-content:center" onclick="if(event.target===this)closeModal('modal-reserva')">
    <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:14px;padding:0;width:min(420px,95vw);position:relative">
        <div style="padding:16px 22px 12px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px">
            <i class="fas fa-ruler-combined" style="color:#ffaa00;font-size:15px"></i>
            <h4 id="reserva-modal-title" style="margin:0;font-size:14px">Reserva Técnica</h4>
            <button onclick="closeModal('modal-reserva')" style="margin-left:auto;background:none;border:none;color:#555;cursor:pointer;font-size:18px">✕</button>
        </div>
        <div style="padding:20px 22px">
            <div id="reserva-cabo-info" style="font-size:12px;color:var(--text-muted);margin-bottom:14px"></div>
            <label style="font-size:12px;color:var(--text-muted);display:block;margin-bottom:4px">Metragem de Reserva (metros)</label>
            <input type="number" id="reserva-metros" min="1" step="0.5" placeholder="Ex: 50" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg-card);color:var(--text);font-size:14px;box-sizing:border-box">
            <label style="font-size:12px;color:var(--text-muted);display:block;margin-top:10px;margin-bottom:4px">Descrição (opcional)</label>
            <input type="text" id="reserva-descricao" placeholder="Ex: Reserva de emenda" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg-card);color:var(--text);font-size:13px;box-sizing:border-box">
            <div style="display:flex;gap:8px;margin-top:18px;justify-content:flex-end">
                <button onclick="closeModal('modal-reserva')" class="btn btn-secondary">Cancelar</button>
                <button id="reserva-salvar-btn" onclick="salvarReserva()" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Reserva</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Editar Reserva Técnica -->
<div id="modal-reserva-edit" style="display:none;position:fixed;inset:0;z-index:10001;background:rgba(0,0,0,.7);align-items:center;justify-content:center" onclick="if(event.target===this)closeModal('modal-reserva-edit')">
    <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:14px;padding:0;width:min(420px,95vw);position:relative">
        <div style="padding:16px 22px 12px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px">
            <i class="fas fa-ruler-combined" style="color:#ffaa00;font-size:15px"></i>
            <h4 style="margin:0;font-size:14px">Editar Reserva Técnica</h4>
            <button onclick="closeModal('modal-reserva-edit')" style="margin-left:auto;background:none;border:none;color:#555;cursor:pointer;font-size:18px">✕</button>
        </div>
        <div style="padding:20px 22px">
            <div id="reserva-edit-info" style="font-size:12px;color:var(--text-muted);margin-bottom:14px"></div>
            <label style="font-size:12px;color:var(--text-muted);display:block;margin-bottom:4px">Metragem de Reserva (metros)</label>
            <input type="number" id="reserva-edit-metros" min="1" step="0.5" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg-card);color:var(--text);font-size:14px;box-sizing:border-box">
            <label style="font-size:12px;color:var(--text-muted);display:block;margin-top:10px;margin-bottom:4px">Descrição (opcional)</label>
            <input type="text" id="reserva-edit-descricao" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg-card);color:var(--text);font-size:13px;box-sizing:border-box">
            <div style="display:flex;gap:8px;margin-top:18px;justify-content:space-between">
                <button onclick="removerReserva()" class="btn" style="background:rgba(255,68,85,.12);color:#ff4455;border:1px solid rgba(255,68,85,.3)"><i class="fas fa-trash"></i> Remover</button>
                <div style="display:flex;gap:8px">
                    <button onclick="closeModal('modal-reserva-edit')" class="btn btn-secondary">Cancelar</button>
                    <button onclick="atualizarReserva()" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Rota da Fibra (mapa de rede) -->
<div id="rota-modal-net" style="display:none;position:fixed;inset:0;z-index:10001;background:rgba(0,0,0,.75);align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:14px;padding:0;width:min(720px,96vw);max-height:88vh;display:flex;flex-direction:column;position:relative">
        <div style="padding:16px 22px 12px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px">
            <i class="fas fa-route" style="color:#ffaa00;font-size:15px"></i>
            <h4 style="margin:0;font-size:14px">Rota da Fibra</h4>
            <button onclick="document.getElementById('rota-modal-net').style.display='none'" style="margin-left:auto;background:none;border:none;color:#555;cursor:pointer;font-size:18px">✕</button>
        </div>
        <div id="rota-content-net" style="padding:20px 24px;overflow-y:auto;flex:1;font-size:13px">
            <div style="text-align:center;color:#888"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
    </div>
</div>

<!-- Cabo picker: quando múltiplos cabos se sobrepõem -->
<div id="cabo-picker" onclick="event.stopPropagation()" style="display:none;position:fixed;z-index:9998;background:var(--bg-panel);border:1px solid var(--border);border-radius:10px;padding:6px 0;min-width:220px;max-height:320px;overflow-y:auto;box-shadow:0 8px 24px rgba(0,0,0,.5)">
    <div style="padding:6px 14px 4px;font-size:11px;color:var(--text-muted);border-bottom:1px solid var(--border);margin-bottom:4px;display:flex;align-items:center;gap:6px">
        <i class="fas fa-layer-group"></i> Selecionar cabo
    </div>
    <div id="cabo-picker-list"></div>
</div>

<!-- Modal: Romper Cabo -->
<div class="modal-overlay" id="modal-romper">
    <div class="modal">
        <div class="modal-header">
            <i class="fas fa-bolt" style="color:#ff6600"></i>
            <h3>Romper Cabo</h3>
            <div class="modal-close" onclick="closeModal('modal-romper');_rompimentoData=null"><i class="fas fa-times"></i></div>
        </div>
        <div class="modal-body">
            <div style="background:rgba(255,102,0,.1);border:1px solid rgba(255,102,0,.3);border-radius:10px;padding:12px;margin-bottom:16px;font-size:13px;color:#ff9944">
                <i class="fas fa-info-circle"></i>
                O cabo será dividido em dois no ponto clicado. As fusões e conexões de cada lado serão mantidas no respectivo trecho.
            </div>
            <div id="romper-cabo-info" style="padding:10px;background:rgba(255,255,255,.04);border-radius:8px;margin-bottom:14px;font-size:13px"></div>
            <div class="form-group">
                <label class="form-label">Código do novo cabo (2º trecho) *</label>
                <input class="form-control" id="romper-novo-codigo" placeholder="ex: CAB-001B" required>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-romper');_rompimentoData=null">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="confirmarRompimento()" style="background:#ff6600;border-color:#ff6600">
                <i class="fas fa-bolt"></i> Confirmar Rompimento
            </button>
        </div>
    </div>
</div>

<!-- Modal: Adicionar Cliente -->
<div class="modal-overlay" id="modal-cliente">
    <div class="modal">
        <div class="modal-header">
            <i class="fas fa-user-plus" style="color:#00ccff"></i>
            <h3>Novo Cliente</h3>
            <div class="modal-close" onclick="closeModal('modal-cliente')"><i class="fas fa-times"></i></div>
        </div>
        <form id="form-cliente" onsubmit="saveElement(event,'cliente')">
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Nome *</label>
                    <input class="form-control" name="nome" required placeholder="Nome completo">
                </div>
                <div class="form-group">
                    <label class="form-label">Login *</label>
                    <input class="form-control" name="login" required placeholder="ex: joao.silva">
                </div>
                <div class="form-group">
                    <label class="form-label">CPF / CNPJ</label>
                    <input class="form-control" name="cpf_cnpj" placeholder="">
                </div>
                <div class="form-group">
                    <label class="form-label">Telefone</label>
                    <input class="form-control" name="telefone" placeholder="(48) 99999-9999">
                </div>
                <div class="form-group">
                    <label class="form-label">Nº Contrato</label>
                    <input class="form-control" name="numero_contrato" placeholder="">
                </div>
                <div class="form-group">
                    <label class="form-label">Serial ONU</label>
                    <input class="form-control" name="serial_onu" placeholder="">
                </div>
                <div class="form-group">
                    <label class="form-label">Plano</label>
                    <input class="form-control" name="plano" placeholder="ex: 300 Mbps">
                </div>
                <input type="hidden" name="lat" id="cliente-lat">
                <input type="hidden" name="lng" id="cliente-lng">
                <div class="form-group full">
                    <label class="form-label">Endereço</label>
                    <input class="form-control" name="endereco" placeholder="">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-cliente')">Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
        </div>
        </form>
    </div>
</div>

<!-- Modal: Conectar Drop (selecionar porta CTO) -->
<div class="modal-overlay" id="modal-drop-port">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <i class="fas fa-plug" style="color:#00cc66"></i>
            <h3 id="drop-modal-title">Conectar Ramal</h3>
            <div class="modal-close" onclick="cancelDropMode()"><i class="fas fa-times"></i></div>
        </div>
        <div class="modal-body">
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">Selecione a porta disponível na CTO:</p>
            <div id="drop-ports-grid" style="display:flex;flex-wrap:wrap;gap:8px"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="cancelDropMode()">Cancelar</button>
        </div>
    </div>
</div>

<!-- Modal: Configurar Posição Inicial do Mapa -->
<div class="modal-overlay" id="modal-mapa-config">
    <div class="modal" style="max-width:560px">
        <div class="modal-header">
            <i class="fas fa-map-marker-alt" style="color:#ffaa00"></i>
            <h3>Configurar Posição Inicial do Mapa</h3>
            <div class="modal-close" onclick="closeModal('modal-mapa-config')"><i class="fas fa-times"></i></div>
        </div>
        <form method="POST">
            <input type="hidden" name="save_map_config" value="1">
            <div class="modal-body">
                <div style="background:rgba(255,170,0,0.08);border:1px solid rgba(255,170,0,0.2);border-radius:10px;padding:12px;margin-bottom:20px;font-size:13px;color:rgba(255,255,255,0.7)">
                    <i class="fas fa-info-circle" style="color:#ffaa00"></i>
                    Clique no mini-mapa abaixo para definir o ponto inicial, ou preencha as coordenadas manualmente.
                    Você também pode clicar em <strong>"Usar posição atual do mapa"</strong> para salvar onde está agora.
                </div>

                <!-- Mini-mapa para escolher ponto -->
                <div id="config-map" style="height:220px;border-radius:10px;overflow:hidden;border:1px solid var(--border);margin-bottom:20px"></div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Latitude *</label>
                        <input class="form-control" name="mapa_lat" id="cfg-lat" type="number" step="0.000001" required
                               value="<?= $mapaLat ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Longitude *</label>
                        <input class="form-control" name="mapa_lng" id="cfg-lng" type="number" step="0.000001" required
                               value="<?= $mapaLng ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Zoom inicial (1–20)</label>
                        <input class="form-control" name="mapa_zoom" id="cfg-zoom" type="number" min="1" max="20"
                               value="<?= $mapaZoom ?>">
                        <div style="font-size:11px;color:var(--text-muted);margin-top:4px">
                            1 = mundo inteiro &nbsp;|&nbsp; 14 = bairro &nbsp;|&nbsp; 18 = rua
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estilo padrão</label>
                        <select class="form-control" name="mapa_tile">
                            <option value="street"    <?= $mapaTile==='street'   ?'selected':'' ?>>Mapa de Rua (Google)</option>
                            <option value="satellite" <?= $mapaTile==='satellite'?'selected':'' ?>>Satélite (Google)</option>
                            <option value="dark"      <?= $mapaTile==='dark'     ?'selected':'' ?>>Escuro sem POIs (CartoDB)</option>
                        </select>
                    </div>
                </div>

                <button type="button" class="btn btn-secondary" style="margin-top:8px;width:100%" onclick="usarPosicaoAtual()">
                    <i class="fas fa-crosshairs"></i> Usar posição atual do mapa principal
                </button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-mapa-config')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Configuração</button>
            </div>
        </form>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_MAPS_KEY ?>"></script>
<script src="https://unpkg.com/leaflet.gridlayer.googlemutant@latest/dist/Leaflet.GoogleMutant.js"></script>
<script>
// ===================================================
// FTTH Network Manager — Dados injetados pelo PHP
// ===================================================

const BASE_URL = '<?= BASE_URL ?>';

const ELEMENTS = {
    postes:   <?= json_encode($postes) ?>,
    ceos:     <?= json_encode($ceos) ?>,
    ctos:     <?= json_encode($ctos) ?>,
    racks:    <?= json_encode($racks) ?>,
    clientes: <?= json_encode($clientes) ?>,
    cabos:    <?= json_encode($cabos) ?>,
    reservas: <?= json_encode($reservas) ?>
};

// Configuração inicial salva no servidor
const MAP_CONFIG = {
    lat:  <?= $mapaLat ?>,
    lng:  <?= $mapaLng ?>,
    zoom: <?= $mapaZoom ?>,
    tile: '<?= $mapaTile ?>'
};

</script>
<script src="<?= BASE_URL ?>/assets/js/map.js?v=<?= filemtime(__DIR__.'/assets/js/map.js') ?>"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
