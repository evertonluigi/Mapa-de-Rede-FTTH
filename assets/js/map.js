// FTTH Network Manager — Map Engine
// Extracted from dashboard.php — PHP data injected via dashboard.php inline <script>
// Globals available: BASE_URL, ELEMENTS, MAP_CONFIG (set by dashboard.php)


// Map Init — usa configuração salva
const map = L.map('map', {
    center: [MAP_CONFIG.lat, MAP_CONFIG.lng],
    zoom: MAP_CONFIG.zoom,
    zoomControl: false   // Vamos posicionar o zoom abaixo da toolbar
});

// Move zoom control para não colidir com toolbar
L.control.zoom({ position: 'bottomright' }).addTo(map);

// Estilos Google Maps — oculta POIs, comércios e transporte público
const NO_POI_STYLES = [
    { featureType: 'poi',     stylers: [{ visibility: 'off' }] },
    { featureType: 'transit', stylers: [{ visibility: 'off' }] },
];

// Tile layers
const tiles = {
    street:    L.gridLayer.googleMutant({ type: 'roadmap', maxZoom: 22, styles: NO_POI_STYLES }),
    satellite: L.gridLayer.googleMutant({ type: 'hybrid',  maxZoom: 22, styles: NO_POI_STYLES }),
    dark:      L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                   maxZoom: 20, attribution: '&copy; OpenStreetMap &copy; CARTO'
               }),
};

const TILE_CYCLE = ['street', 'satellite', 'dark'];
const TILE_ICONS = { street: 'fas fa-satellite', satellite: 'fas fa-map', dark: 'fas fa-road' };
const TILE_LABELS = { street: 'Rua', satellite: 'Satélite', dark: 'Escuro' };

let currentTile = (MAP_CONFIG.tile && tiles[MAP_CONFIG.tile]) ? MAP_CONFIG.tile : 'street';
tiles[currentTile].addTo(map);
document.getElementById('map-style-icon').className = TILE_ICONS[currentTile];

function toggleMapStyle() {
    const idx  = TILE_CYCLE.indexOf(currentTile);
    const next = TILE_CYCLE[(idx + 1) % TILE_CYCLE.length];
    map.removeLayer(tiles[currentTile]);
    tiles[next].addTo(map);
    currentTile = next;
    document.getElementById('map-style-icon').className = TILE_ICONS[next];
}

function goHome() {
    map.setView([MAP_CONFIG.lat, MAP_CONFIG.lng], MAP_CONFIG.zoom, { animate: true });
}

// ── Minha Localização ─────────────────────────────────────────────────────────
let _locating     = false;
let _locateWatch  = null;
let _locateDot    = null;   // marcador ponto azul
let _locateRing   = null;   // círculo de precisão
let _locateFirst  = true;   // primeira posição → centraliza mapa

function locateUser() {
    // Geolocalização exige HTTPS (exceto localhost)
    const isSecure = location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
    if (!isSecure) {
        showToast('Localização requer HTTPS. Acesse o sistema via https://.', 'error');
        return;
    }
    if (!navigator.geolocation) {
        showToast('Geolocalização não suportada neste dispositivo.', 'error');
        return;
    }

    // Se já está rastreando → desliga
    if (_locating) {
        _locateStop();
        return;
    }

    // Inicia
    _locating = true;
    _locateFirst = true;
    const btn  = document.getElementById('btn-locate');
    const icon = document.getElementById('locate-icon');
    if (btn)  btn.classList.add('active');
    if (icon) icon.className = 'fas fa-spinner fa-spin';

    // watchPosition: atualiza continuamente (importante no mobile)
    _locateWatch = navigator.geolocation.watchPosition(
        _locateSuccess,
        _locateError,
        {
            enableHighAccuracy: true,   // força GPS no mobile
            timeout: 15000,
            maximumAge: 3000
        }
    );
}

function _locateSuccess(pos) {
    const lat  = pos.coords.latitude;
    const lng  = pos.coords.longitude;
    const acc  = pos.coords.accuracy;          // metros
    const ll   = L.latLng(lat, lng);
    const icon = document.getElementById('locate-icon');

    // Para o spinner
    if (icon) icon.className = 'fas fa-location-arrow';

    // Remove marcadores antigos e recria
    if (_locateRing)  { map.removeLayer(_locateRing);  _locateRing  = null; }
    if (_locateDot)   { map.removeLayer(_locateDot);   _locateDot   = null; }

    // Círculo de precisão
    _locateRing = L.circle(ll, {
        radius: acc,
        color: '#00b4ff',
        fillColor: '#00b4ff',
        fillOpacity: 0.12,
        weight: 1.5,
        interactive: false
    }).addTo(map);

    // Ponto central (circleMarker não escala com zoom)
    _locateDot = L.circleMarker(ll, {
        radius: 8,
        fillColor: '#00b4ff',
        color: '#fff',
        weight: 2.5,
        fillOpacity: 1
    }).addTo(map);

    const accStr = acc < 10 ? 'Alta (GPS)' : acc < 50 ? 'Média (~'+Math.round(acc)+' m)' : 'Baixa (~'+Math.round(acc)+' m)';
    _locateDot.bindPopup(
        `<div style="font-size:13px;line-height:1.7">
            <strong style="color:#00b4ff">📍 Você está aqui</strong><br>
            Lat: ${lat.toFixed(6)}<br>
            Lng: ${lng.toFixed(6)}<br>
            Precisão: ${accStr}
        </div>`,
        { maxWidth: 220 }
    );

    // Primeira posição → centraliza mapa no zoom adequado
    if (_locateFirst) {
        _locateFirst = false;
        map.setView(ll, Math.max(map.getZoom(), 17), { animate: true });
        _locateDot.openPopup();
    }
}

function _locateError(err) {
    _locateStop();
    const msgs = {
        1: 'Permissão negada. Ative a localização nas configurações do navegador/celular.',
        2: 'Localização indisponível. Verifique se o GPS está ativado.',
        3: 'Tempo esgotado. Verifique o sinal de GPS e tente novamente.'
    };
    showToast(msgs[err.code] || 'Erro ao obter localização.', 'error');
}

function _locateStop() {
    _locating = false;
    if (_locateWatch !== null) {
        navigator.geolocation.clearWatch(_locateWatch);
        _locateWatch = null;
    }
    if (_locateRing) { map.removeLayer(_locateRing); _locateRing = null; }
    if (_locateDot)  { map.removeLayer(_locateDot);  _locateDot  = null; }
    const btn  = document.getElementById('btn-locate');
    const icon = document.getElementById('locate-icon');
    if (btn)  btn.classList.remove('active');
    if (icon) icon.className = 'fas fa-location-arrow';
}

// ---- Mini-mapa de configuração ----
let configMap = null;
let configMarker = null;

function initConfigMap() {
    if (configMap) { configMap.invalidateSize(); return; }
    configMap = L.map('config-map', { zoomControl: true }).setView(
        [parseFloat(document.getElementById('cfg-lat').value) || MAP_CONFIG.lat,
         parseFloat(document.getElementById('cfg-lng').value) || MAP_CONFIG.lng],
        parseInt(document.getElementById('cfg-zoom').value) || MAP_CONFIG.zoom
    );
    L.gridLayer.googleMutant({ type: 'roadmap', maxZoom: 22 }).addTo(configMap);

    // Marca ponto inicial
    const lat = parseFloat(document.getElementById('cfg-lat').value);
    const lng = parseFloat(document.getElementById('cfg-lng').value);
    if (lat && lng) {
        configMarker = L.marker([lat, lng], { draggable: true }).addTo(configMap);
        configMarker.on('dragend', updateConfigFields);
    }

    configMap.on('click', function(e) {
        if (configMarker) configMap.removeLayer(configMarker);
        configMarker = L.marker([e.latlng.lat, e.latlng.lng], { draggable: true }).addTo(configMap);
        configMarker.on('dragend', updateConfigFields);
        document.getElementById('cfg-lat').value = e.latlng.lat.toFixed(6);
        document.getElementById('cfg-lng').value = e.latlng.lng.toFixed(6);
    });

    // Atualiza zoom quando mudar o campo
    document.getElementById('cfg-zoom').addEventListener('input', function() {
        configMap.setZoom(parseInt(this.value) || 14);
    });

    // Atualiza campos ao mover o mapa
    configMap.on('moveend', function() {
        document.getElementById('cfg-zoom').value = configMap.getZoom();
    });
}

function updateConfigFields(e) {
    const pos = e.target.getLatLng();
    document.getElementById('cfg-lat').value = pos.lat.toFixed(6);
    document.getElementById('cfg-lng').value = pos.lng.toFixed(6);
}

function usarPosicaoAtual() {
    const center = map.getCenter();
    const zoom   = map.getZoom();
    document.getElementById('cfg-lat').value  = center.lat.toFixed(6);
    document.getElementById('cfg-lng').value  = center.lng.toFixed(6);
    document.getElementById('cfg-zoom').value = zoom;
    if (configMap) {
        configMap.setView([center.lat, center.lng], zoom);
        if (configMarker) configMap.removeLayer(configMarker);
        configMarker = L.marker([center.lat, center.lng], { draggable: true }).addTo(configMap);
        configMarker.on('dragend', updateConfigFields);
    }
    showToast('Posição atual do mapa carregada!', 'success');
}

// Layer groups
const layers = {
    postes:    L.layerGroup().addTo(map),
    ceos:      L.layerGroup().addTo(map),
    ctos:      L.layerGroup().addTo(map),
    cabos:     L.layerGroup().addTo(map),
    drops:     L.layerGroup().addTo(map),
    clientes:  L.layerGroup().addTo(map),
    racks:     L.layerGroup().addTo(map),
    reservas:  L.layerGroup().addTo(map),
    selection: L.layerGroup().addTo(map),  // always rendered last (on top)
};

// ── Selection state ────────────────────────────────────────────────────────────
let _selEl = null; // { type, id, data }
const _cablePolyMap = new Map(); // cabo id → Leaflet polyline (rebuilt on renderCabos)

function _clearCableGlow() {
    _cablePolyMap.forEach(p => {
        const el = p.getElement ? p.getElement() : p._path;
        if (el) el.classList.remove('cabo-selecionado');
    });
}
function setSelection(type, data) {
    _selEl = { type, id: data.id, data };
    updateSelectionRing();
}
function clearSelection() {
    _selEl = null;
    _clearCableGlow();
    layers.selection.clearLayers();
}
function updateSelectionRing() {
    _clearCableGlow();
    layers.selection.clearLayers();
    if (!_selEl) return;
    const { type, data } = _selEl;
    if (type === 'cabo') {
        // Apply CSS glow directly to the cable's SVG path — no extra rectangle layer
        const poly = _cablePolyMap.get(data.id);
        if (poly) {
            const el = poly.getElement ? poly.getElement() : poly._path;
            if (el) el.classList.add('cabo-selecionado');
        }
    } else if (['poste','ceo','cto','rack','cliente'].includes(type)) {
        const lat = data.lat, lng = data.lng;
        if (lat && lng) {
            L.circleMarker([lat, lng], {
                radius: 18, color: '#ffffff', weight: 2.5,
                fill: false, opacity: 0.7, interactive: false
            }).addTo(layers.selection);
        }
    }
}
map.on('zoomend', () => { updateSelectionRing(); });

function toggleLayersPanel() {
    const panel = document.getElementById('layers-panel');
    const body  = document.getElementById('layers-body');
    const open  = panel.classList.toggle('open');
    body.style.display = open ? '' : 'none';
}

function toggleLayer(name, visible) {
    if (visible) { layers[name].addTo(map); }
    else { map.removeLayer(layers[name]); }
    document.getElementById('layer-'+name).classList.toggle('active', visible);
}

// ============================================================
// ---- SVG ICON FACTORIES (ícones realistas por tipo) ----
// ============================================================

function svgIcon(svgHtml, w, h, anchorX, anchorY) {
    return L.divIcon({
        className: '',
        html: `<div style="filter:drop-shadow(0 2px 4px rgba(0,0,0,0.6))">${svgHtml}</div>`,
        iconSize: [w, h],
        iconAnchor: [anchorX ?? w/2, anchorY ?? h],
        popupAnchor: [0, -(anchorY ?? h)]
    });
}

// POSTE — poste de luz com braço e isolador
function iconPoste(status) {
    const opacity = status === 'inativo' ? '0.45' : (status === 'danificado' ? '0.75' : '1');
    return L.icon({
        iconUrl: `${BASE_URL}/assets/icons/poste.png`,
        iconSize:   [40, 40],
        iconAnchor: [20, 40],
        popupAnchor:[0, -40],
        className: 'icon-poste-img',
    });
}

// CEO — Caixa de Emenda Óptica (caixa retangular com entradas de cabo)
function iconCeo(status) {
    return L.icon({
        iconUrl:     `${BASE_URL}/assets/icons/ceo.png`,
        iconSize:    [40, 40],
        iconAnchor:  [20, 20],
        popupAnchor: [0, -20],
    });
}

// CTO — Caixa Terminal Óptica
function iconCto(pct, status) {
    return L.icon({
        iconUrl:     `${BASE_URL}/assets/icons/cto.png`,
        iconSize:    [40, 40],
        iconAnchor:  [20, 20],
        popupAnchor: [0, -20],
    });
}

// OLT — Equipment rack / servidor
function iconOlt(status) {
    const bc = status==='inativo' ? '#333' : '#7a2800';
    const lc = status==='inativo' ? '#666' : '#ff6600';
    const s = `<svg width="38" height="32" viewBox="0 0 38 32" xmlns="http://www.w3.org/2000/svg">
        <rect x="1" y="1" width="36" height="30" rx="3" fill="${bc}" stroke="${lc}" stroke-width="1.5"/>
        <rect x="3" y="4"  width="32" height="7" rx="1" fill="${lc}" opacity="0.25"/>
        <rect x="3" y="13" width="32" height="7" rx="1" fill="${lc}" opacity="0.25"/>
        <rect x="3" y="22" width="32" height="7" rx="1" fill="${lc}" opacity="0.25"/>
        <circle cx="31" cy="7.5"  r="2" fill="#00ff88" opacity="0.8"/>
        <circle cx="31" cy="16.5" r="2" fill="#00ff88" opacity="0.8"/>
        <circle cx="31" cy="25.5" r="2" fill="#ffaa00" opacity="0.8"/>
        <rect x="5" y="6"  width="5" height="3" rx="0.5" fill="${lc}" opacity="0.5"/>
        <rect x="12" y="6"  width="5" height="3" rx="0.5" fill="${lc}" opacity="0.5"/>
        <rect x="19" y="6"  width="5" height="3" rx="0.5" fill="${lc}" opacity="0.5"/>
        <text x="19" y="2.5" text-anchor="middle" font-size="0" fill="none">OLT</text>
    </svg>`;
    return svgIcon(s, 38, 32, 19, 16);
}

// RACK — armário de telecomunicações
function iconRack(status) {
    const bc = status==='inativo' ? '#333' : '#2a1800';
    const lc = status==='inativo' ? '#666' : '#cc8800';
    const s = `<svg width="38" height="32" viewBox="0 0 38 32" xmlns="http://www.w3.org/2000/svg">
        <rect x="1" y="1" width="36" height="30" rx="3" fill="${bc}" stroke="${lc}" stroke-width="1.5"/>
        <rect x="3" y="4"  width="32" height="5" rx="1" fill="${lc}" opacity="0.3"/>
        <rect x="3" y="11" width="32" height="5" rx="1" fill="${lc}" opacity="0.3"/>
        <rect x="3" y="18" width="32" height="5" rx="1" fill="${lc}" opacity="0.3"/>
        <rect x="3" y="25" width="32" height="4" rx="1" fill="${lc}" opacity="0.3"/>
        <circle cx="33" cy="6.5"  r="1.5" fill="#00ff88" opacity="0.9"/>
        <circle cx="33" cy="13.5" r="1.5" fill="#00ff88" opacity="0.9"/>
        <circle cx="33" cy="20.5" r="1.5" fill="${lc}"   opacity="0.9"/>
        <circle cx="33" cy="27"   r="1.5" fill="#444"    opacity="0.9"/>
        <rect x="5" y="5.5"  width="20" height="2" rx="0.5" fill="${lc}" opacity="0.4"/>
        <rect x="5" y="12.5" width="20" height="2" rx="0.5" fill="${lc}" opacity="0.4"/>
        <rect x="5" y="19.5" width="20" height="2" rx="0.5" fill="${lc}" opacity="0.4"/>
    </svg>`;
    return svgIcon(s, 38, 32, 19, 16);
}

// CLIENTE / ONU — casa com sinal
function iconCliente(status) {
    const ativo = status === 'ativo';
    const fill  = ativo ? '#005588' : '#333';
    const stroke= ativo ? '#00ccff' : '#666';
    const dot   = ativo ? '#00ff88' : '#888';
    const s = `<svg width="28" height="32" viewBox="0 0 28 32" xmlns="http://www.w3.org/2000/svg">
        <polygon points="14,1 1,13 27,13" fill="${fill}" stroke="${stroke}" stroke-width="1.5" stroke-linejoin="round"/>
        <rect x="4" y="12" width="20" height="16" rx="1" fill="${fill}" stroke="${stroke}" stroke-width="1.5"/>
        <rect x="11" y="19" width="6" height="9" rx="1" fill="${ativo?'#003366':'#222'}"/>
        <circle cx="22" cy="5" r="3" fill="${dot}" opacity="0.9"/>
    </svg>`;
    return svgIcon(s, 28, 32, 14, 32);
}

// SPLITTER — ícone de divisor de sinal
function iconSplitter() {
    const s = `<svg width="28" height="24" viewBox="0 0 28 24" xmlns="http://www.w3.org/2000/svg">
        <line x1="2" y1="12" x2="10" y2="12" stroke="#ffcc00" stroke-width="2.5" stroke-linecap="round"/>
        <rect x="9" y="7" width="8" height="10" rx="2" fill="#996600" stroke="#ffcc00" stroke-width="1.5"/>
        <line x1="17" y1="9"  x2="26" y2="5"  stroke="#ffcc00" stroke-width="2" stroke-linecap="round"/>
        <line x1="17" y1="12" x2="26" y2="12" stroke="#ffcc00" stroke-width="2" stroke-linecap="round"/>
        <line x1="17" y1="15" x2="26" y2="19" stroke="#ffcc00" stroke-width="2" stroke-linecap="round"/>
    </svg>`;
    return svgIcon(s, 28, 24, 14, 12);
}

// Ícone temporário para postes em modo batch (amarelo pulsante)
function iconPosteBatch() {
    const s = `<svg width="22" height="48" viewBox="0 0 22 48" xmlns="http://www.w3.org/2000/svg">
        <rect x="9" y="6" width="4" height="38" rx="2" fill="#ffee44"/>
        <rect x="4" y="6" width="14" height="3" rx="1.5" fill="#ffee44"/>
        <circle cx="5"  cy="6" r="2.5" fill="#fff176" stroke="#ffee44" stroke-width="1"/>
        <circle cx="17" cy="6" r="2.5" fill="#fff176" stroke="#ffee44" stroke-width="1"/>
        <rect x="8" y="44" width="6" height="4" rx="2" fill="#ffee44" opacity="0.7"/>
    </svg>`;
    return svgIcon(s, 22, 48, 11, 48);
}

// ---- Render Elements ----
function renderAll() {
    renderPostes();
    renderCeos();
    renderCtos();
    renderRacks();
    renderDropLines();
    renderClientes();
    renderCabos();
    renderReservas();
}

function renderDropLines() {
    layers.drops.clearLayers();
    ELEMENTS.clientes.forEach(cl => {
        if (!cl.cto_id || !cl.lat || !cl.lng) return;
        const cto = ELEMENTS.ctos.find(c => c.id == cl.cto_id);
        if (!cto || !cto.lat || !cto.lng) return;
        L.polyline([[cto.lat, cto.lng], [cl.lat, cl.lng]], {
            color: '#111111', weight: 1.5, opacity: 0.65, dashArray: null, interactive: false
        }).addTo(layers.drops);
    });
}

// Adiciona ponto ao cabo em desenho (ancorado ou livre)
function addCaboPoint(lat, lng, tipo, eid, label) {
    caboPoints.push({ lat, lng, et: tipo || null, eid: eid || null });
    const snapColor = tipo === 'poste' ? '#ffcc00' : (tipo === 'ceo' ? '#aa44ff' : (tipo === 'cto' ? '#00cc66' : '#ffffff'));
    const m = L.circleMarker([lat, lng], {
        radius: tipo ? 7 : 5, color: '#00b4ff', fillColor: snapColor,
        fillOpacity: 1, weight: 2, interactive: false
    }).addTo(map);
    tempMarkers.push(m);
    if (tipo) showToast(`Ancorado em ${tipo.toUpperCase()}: ${label}`, 'success');
    if (snapRing) { map.removeLayer(snapRing); snapRing = null; }
    updateCaboLine();
    updateCaboBarInfo();
}

function renderPostes() {
    layers.postes.clearLayers();
    ELEMENTS.postes.forEach(p => {
        const mk = L.marker([p.lat, p.lng], { icon: iconPoste(p.status), draggable: moveMode });
        mk.addTo(layers.postes);
        mk.on('click', function(e) {
            if (drawingCabo && currentTool === 'cabo') {
                L.DomEvent.stopPropagation(e);
                addCaboPoint(p.lat, p.lng, 'poste', p.id, p.codigo);
            } else if (!moveMode) { showInfo('poste', p); }
        });
        mk.on('dragend', async function(e) {
            if (!moveMode) return;
            const { lat, lng } = e.target.getLatLng();
            p.lat = lat; p.lng = lng;
            ELEMENTS.cabos.forEach(c => {
                if (!c.pontos) return;
                const pts = typeof c.pontos === 'string' ? JSON.parse(c.pontos) : c.pontos;
                let changed = false;
                pts.forEach(pt => { if (pt.et === 'poste' && pt.eid === p.id) { pt.lat = lat; pt.lng = lng; changed = true; } });
                if (changed) { c.pontos = pts; }
            });
            renderCabos();
            await fetch(`${BASE_URL}/api/elements.php?type=move_poste`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: p.id, lat, lng })
            });
            showToast(`Poste ${p.codigo} movido`, 'success');
        });
    });
}

function renderCeos() {
    layers.ceos.clearLayers();
    ELEMENTS.ceos.forEach(c => {
        const mk = L.marker([c.lat, c.lng], { icon: iconCeo(c.status), draggable: moveMode });
        mk.addTo(layers.ceos);
        mk.on('click', function(e) {
            if (drawingCabo && currentTool === 'cabo') {
                L.DomEvent.stopPropagation(e);
                addCaboPoint(c.lat, c.lng, 'ceo', c.id, c.codigo);
            } else if (!moveMode) { showInfo('ceo', c); }
        });
        mk.on('dragend', async function(e) {
            if (!moveMode) return;
            const { lat, lng } = e.target.getLatLng();
            c.lat = lat; c.lng = lng;
            // Move linked cable points
            ELEMENTS.cabos.forEach(cb => {
                if (!cb.pontos) return;
                const pts = typeof cb.pontos === 'string' ? JSON.parse(cb.pontos) : cb.pontos;
                let changed = false;
                pts.forEach(pt => { if (pt.et === 'ceo' && pt.eid === c.id) { pt.lat = lat; pt.lng = lng; changed = true; } });
                if (changed) { cb.pontos = pts; }
            });
            renderCabos();
            await fetch(`${BASE_URL}/api/elements.php?type=move_elem`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tipo: 'ceo', id: c.id, lat, lng })
            });
            showToast(`CEO ${c.codigo} movido`, 'success');
        });
    });
}

function renderCtos() {
    layers.ctos.clearLayers();
    ELEMENTS.ctos.forEach(c => {
        const pct = c.capacidade_portas > 0 ? Math.round((c.clientes_ativos / c.capacidade_portas) * 100) : 0;
        const mk = L.marker([c.lat, c.lng], { icon: iconCto(pct, c.status), draggable: moveMode });
        mk.addTo(layers.ctos);
        mk.on('click', function(e) {
            if (drawingCabo && currentTool === 'cabo') {
                L.DomEvent.stopPropagation(e);
                addCaboPoint(c.lat, c.lng, 'cto', c.id, c.codigo);
            } else if (dropMode) {
                L.DomEvent.stopPropagation(e);
                showDropPortModal(c);
            } else if (!moveMode) { showInfo('cto', c); }
        });
        mk.on('dragend', async function(e) {
            if (!moveMode) return;
            const { lat, lng } = e.target.getLatLng();
            c.lat = lat; c.lng = lng;
            ELEMENTS.cabos.forEach(cb => {
                if (!cb.pontos) return;
                const pts = typeof cb.pontos === 'string' ? JSON.parse(cb.pontos) : cb.pontos;
                let changed = false;
                pts.forEach(pt => { if (pt.et === 'cto' && pt.eid === c.id) { pt.lat = lat; pt.lng = lng; changed = true; } });
                if (changed) { cb.pontos = pts; }
            });
            renderCabos();
            await fetch(`${BASE_URL}/api/elements.php?type=move_elem`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tipo: 'cto', id: c.id, lat, lng })
            });
            showToast(`CTO ${c.codigo} movido`, 'success');
        });
    });
}

function renderRacks() {
    layers.racks.clearLayers();
    ELEMENTS.racks.forEach(o => {
        const mk = L.marker([o.lat, o.lng], { icon: iconRack(o.status), draggable: moveMode });
        mk.addTo(layers.racks);
        mk.on('click', function(e) {
            if (drawingCabo && currentTool === 'cabo') {
                L.DomEvent.stopPropagation(e);
                addCaboPoint(o.lat, o.lng, 'rack', o.id, o.codigo);
            } else if (!moveMode) { showInfo('rack', o); }
        });
        mk.on('dragend', async function(e) {
            if (!moveMode) return;
            const { lat, lng } = e.target.getLatLng();
            o.lat = lat; o.lng = lng;
            ELEMENTS.cabos.forEach(cb => {
                if (!cb.pontos) return;
                const pts = typeof cb.pontos === 'string' ? JSON.parse(cb.pontos) : cb.pontos;
                let changed = false;
                pts.forEach(pt => { if (pt.et === 'rack' && pt.eid === o.id) { pt.lat = lat; pt.lng = lng; changed = true; } });
                if (changed) { cb.pontos = pts; }
            });
            renderCabos();
            await fetch(`${BASE_URL}/api/elements.php?type=move_elem`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tipo: 'rack', id: o.id, lat, lng })
            });
            showToast(`Rack ${o.codigo} movido`, 'success');
        });
    });
}

function renderClientes() {
    layers.clientes.clearLayers();
    ELEMENTS.clientes.forEach(c => {
        const mk = L.marker([c.lat, c.lng], { icon: iconCliente(c.status) })
            .addTo(layers.clientes)
            .on('click', () => showInfo('cliente', c));
        mk.bindTooltip(c.login || c.nome, { permanent: false, direction: 'top', offset: [0, -32] });
    });
}

// Vertex markers for move mode (cabo point handles)
let vertexMarkers = [];

function clearVertexMarkers() {
    vertexMarkers.forEach(m => map.removeLayer(m));
    vertexMarkers = [];
}

// ---- Parallel cable offset ----
// Detects cables that share a segment (by lat/lng proximity) and assigns
// centered pixel offsets so they render as parallel lines.

function _caboPts(c) {
    return typeof c.pontos === 'string' ? JSON.parse(c.pontos) : (c.pontos || []);
}

// Returns true if cable A and B share at least one consecutive-point segment
function _cabosOverlap(ca, cb) {
    const THRESH = 0.000060; // ~6.5 m – covers cables snapped to the same pole
    const pa = _caboPts(ca), pb = _caboPts(cb);
    const near = (p, q) => Math.abs(p.lat - q.lat) < THRESH && Math.abs(p.lng - q.lng) < THRESH;
    for (let i = 0; i < pa.length - 1; i++) {
        for (let j = 0; j < pb.length - 1; j++) {
            if ((near(pa[i], pb[j])   && near(pa[i+1], pb[j+1])) ||
                (near(pa[i], pb[j+1]) && near(pa[i+1], pb[j]))) return true;
        }
    }
    return false;
}

function computeCaboOffsets(cabos) {
    // Union-Find
    const parent = new Map(cabos.map(c => [c.id, c.id]));
    function find(x) {
        if (parent.get(x) !== x) parent.set(x, find(parent.get(x)));
        return parent.get(x);
    }
    function union(a, b) { parent.set(find(a), find(b)); }

    for (let i = 0; i < cabos.length; i++)
        for (let j = i + 1; j < cabos.length; j++)
            if (_cabosOverlap(cabos[i], cabos[j])) union(cabos[i].id, cabos[j].id);

    const groups = new Map();
    cabos.forEach(c => {
        const root = find(c.id);
        if (!groups.has(root)) groups.set(root, []);
        groups.get(root).push(c.id);
    });
    groups.forEach(g => g.sort((a, b) => a - b));

    // Fixed meter offset between parallel cables — constant across zoom levels
    const STEP_M = 7.0; // meters between parallel cables (visible at zoom 17-19)
    const offsets   = new Map(); // meter offset per cable id
    const groupSize = new Map(); // group size per cable id
    groups.forEach(g => {
        const n = g.length;
        g.forEach((id, i) => {
            offsets.set(id, (i - (n - 1) / 2) * STEP_M);
            groupSize.set(id, n);
        });
    });
    return { offsets, groupSize };
}

// Shift polyline points perpendicularly by offsetM meters (fixed geo offset, zoom-independent).
// Positive offset = left of travel direction.
function applyLatLngOffset(latLngs, offsetM) {
    if (!offsetM || latLngs.length < 2) return latLngs;
    return latLngs.map((p, i) => {
        let nx = 0, ny = 0;
        const addSeg = (from, to) => {
            const dlat = to[0] - from[0], dlng = to[1] - from[1];
            const len = Math.sqrt(dlat * dlat + dlng * dlng);
            if (len < 1e-12) return;
            nx += -dlng / len;   // left-perpendicular
            ny +=  dlat / len;
        };
        if (i > 0)                addSeg(latLngs[i - 1], latLngs[i]);
        if (i < latLngs.length - 1) addSeg(latLngs[i], latLngs[i + 1]);
        const nlen = Math.sqrt(nx * nx + ny * ny);
        if (nlen < 1e-12) return p;
        nx /= nlen; ny /= nlen;
        const dLat = (offsetM * ny) / 111320;
        const dLng = (offsetM * nx) / (111320 * Math.cos(p[0] * Math.PI / 180));
        return [p[0] + dLat, p[1] + dLng];
    });
}

function renderCabos() {
    layers.cabos.clearLayers();
    clearVertexMarkers();
    _cablePolyMap.clear();
    const { offsets: caboOffsets, groupSize: caboGroupSize } = computeCaboOffsets(ELEMENTS.cabos);
    ELEMENTS.cabos.forEach(c => {
        if (!c.pontos) return;
        const pts = typeof c.pontos === 'string' ? JSON.parse(c.pontos) : c.pontos;
        if (!pts || pts.length < 2) return;
        // Normalize to array so vertex-drag mutations persist across re-renders
        if (typeof c.pontos === 'string') c.pontos = pts;
        const rawLatLngs = pts.map(p => [p.lat, p.lng]);
        const foColors = { 2:'#888888', 4:'#cc44ff', 6:'#ff8800', 8:'#ffee00', 12:'#3399ff', 24:'#00cc66', 36:'#00ffcc', 48:'#ff4455', 72:'#ff66bb', 96:'#ff99dd', 144:'#dddddd' };
        const statusColors = { reserva: '#ffaa00', defeito: '#ff2244', cortado: '#555555' };
        const foColor = foColors[c.num_fibras] || '#3399ff';
        const color = c.cor_mapa || statusColors[c.status] || foColor;
        const offsetM   = caboOffsets.get(c.id) || 0;
        const grpSize   = caboGroupSize.get(c.id) || 1;
        // Cabos em grupo ficam levemente mais finos; área clicável mantida generosa com weight
        const lineWeight = grpSize > 1 ? 4 : 5;
        const latLngs   = moveMode ? rawLatLngs : applyLatLngOffset(rawLatLngs, offsetM);
        const poly = L.polyline(latLngs, {
            color, weight: lineWeight, opacity: 0.92,
            dashArray: c.status === 'reserva' ? '10,5' : null
        }).addTo(layers.cabos);
        _cablePolyMap.set(c.id, poly);
        // Tooltip showing cable info for easy identification
        const tipLabel = `${c.codigo || 'Cabo #'+c.id} · ${c.num_fibras || '?'}FO`;
        poly.bindTooltip(tipLabel, { sticky: true, opacity: 0.85, className: 'cabo-tooltip' });
        poly.on('click', function(e) {
            if (moveMode) return;
            showInfo('cabo', c);
        });
        poly.on('contextmenu', function(e) {
            if (moveMode) return;
            showCtxCabo(e, c);
        });

        // In move mode, draw draggable vertex handles on all points
        // Free points: yellow filled circle; Anchored points: orange hollow circle (drag to detach)
        if (moveMode) {
            pts.forEach((pt, idx) => {
                const isAnchored = !!pt.et;
                const vm = L.circleMarker([pt.lat, pt.lng], {
                    radius: isAnchored ? 8 : 7,
                    color: isAnchored ? '#ff8800' : '#ffcc00',
                    fillColor: isAnchored ? 'transparent' : '#ffcc00',
                    fillOpacity: isAnchored ? 0 : 0.9,
                    weight: isAnchored ? 3 : 2,
                    draggable: false
                }).addTo(map);
                vm.options.draggable = true;
                vm._dragging = false;
                // Custom drag via mousedown on the circle
                vm.on('mousedown', function(e) {
                    L.DomEvent.stopPropagation(e);
                    map.dragging.disable();
                    vm._dragging = true;
                    map.on('mousemove', onVmMove);
                    map.once('mouseup', onVmUp);

                    function onVmMove(me) {
                        if (!vm._dragging) return;
                        vm.setLatLng(me.latlng);
                        pt.lat = me.latlng.lat; pt.lng = me.latlng.lng;
                        const newLatLngs = pts.map(p => [p.lat, p.lng]);
                        poly.setLatLngs(newLatLngs);
                        // Show snap ring if near an element
                        const snap = getNearestSnap(me.latlng);
                        if (snap) {
                            const ll = L.latLng(snap.lat, snap.lng);
                            if (!snapRing) snapRing = L.circleMarker(ll, { radius:18, color:'#ffcc00', weight:2.5, fill:false, opacity:0.9, interactive:false }).addTo(map);
                            else snapRing.setLatLng(ll);
                        } else {
                            if (snapRing) { map.removeLayer(snapRing); snapRing = null; }
                        }
                    }
                    async function onVmUp(me) {
                        map.off('mousemove', onVmMove);
                        map.dragging.enable();
                        vm._dragging = false;
                        if (snapRing) { map.removeLayer(snapRing); snapRing = null; }
                        pt.lat = me.latlng.lat; pt.lng = me.latlng.lng;
                        const newLatLngs = pts.map(p => [p.lat, p.lng]);
                        poly.setLatLngs(newLatLngs);
                        // Check if released near an element — snap to anchor
                        const snap = getNearestSnap(me.latlng);
                        if (snap) {
                            pt.lat = snap.lat; pt.lng = snap.lng;
                            pt.et = snap.tipo; pt.eid = snap.id;
                            poly.setLatLngs(pts.map(p => [p.lat, p.lng]));
                            await fetch(`${BASE_URL}/api/elements.php?type=anchor_cabo_pt`, {
                                method: 'POST', headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ cabo_id: c.id, sequencia: idx, lat: pt.lat, lng: pt.lng, elemento_tipo: snap.tipo, elemento_id: snap.id })
                            });
                            showToast(`Ancorado em ${snap.tipo.toUpperCase()}: ${snap.label}`, 'success');
                            renderCabos();
                        } else if (isAnchored) {
                            // Was anchored, dropped in free space — detach from element
                            pt.et = null; pt.eid = null;
                            await fetch(`${BASE_URL}/api/elements.php?type=detach_move_cabo_pt`, {
                                method: 'POST', headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ cabo_id: c.id, sequencia: idx, lat: pt.lat, lng: pt.lng })
                            });
                            showToast('Ponto desvinculado e movido', 'success');
                            renderCabos();
                        } else {
                            await fetch(`${BASE_URL}/api/elements.php?type=move_cabo_pt`, {
                                method: 'POST', headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ cabo_id: c.id, sequencia: idx, lat: pt.lat, lng: pt.lng })
                            });
                            showToast('Ponto movido', 'success');
                        }
                    }
                });
                vertexMarkers.push(vm);
            });
        }
    });
    // Re-apply selection glow after rebuilding polylines
    updateSelectionRing();
}

let moveMode     = false;
renderAll();

// On zoom: offsets are in meters (fixed in geo space), so no need to re-render cables.
// Only update selection ring and direction arrows which depend on current projection.
map.on('zoomend', () => {
    if (_selectedCaboForArrows) drawCaboArrows(_selectedCaboForArrows, _selectedSinalDirecao);
    updateSelectionRing();
});

// ---- Cable signal & direction arrows ----
let _caboArrowMarkers = [];
let _selectedCaboForArrows = null;
let _selectedSinalDirecao = 'inicio_para_fim';

function clearCaboArrows() {
    _caboArrowMarkers.forEach(m => map.removeLayer(m));
    _caboArrowMarkers = [];
    _selectedCaboForArrows = null;
}

function signalColor(dbm) {
    if (dbm === null) return '#888';
    if (dbm >= 0)   return '#00cc66';
    if (dbm >= -10) return '#66dd44';
    if (dbm >= -20) return '#ffcc00';
    if (dbm >= -27) return '#ff8800';
    return '#ff4455';
}

function signalBadge(dbm, label) {
    const color = signalColor(dbm);
    const val = dbm !== null ? dbm.toFixed(2) + ' dBm' : '—';
    return `<span style="background:rgba(${hexToRgb(color)},.15);color:${color};border:1px solid rgba(${hexToRgb(color)},.3);border-radius:6px;padding:2px 7px;font-size:12px;font-weight:600">${label}: ${val}</span>`;
}

function hexToRgb(hex) {
    const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
    return `${r},${g},${b}`;
}

function bearingBetween(from, to) {
    const lat1 = from[0]*Math.PI/180, lat2 = to[0]*Math.PI/180;
    const dLng = (to[1]-from[1])*Math.PI/180;
    const y = Math.sin(dLng)*Math.cos(lat2);
    const x = Math.cos(lat1)*Math.sin(lat2) - Math.sin(lat1)*Math.cos(lat2)*Math.cos(dLng);
    return (Math.atan2(y, x)*180/Math.PI + 360) % 360;
}

function pointAtFraction(latLngs, frac) {
    let segs = [], total = 0;
    for (let i = 0; i < latLngs.length-1; i++) {
        const d = map.distance(latLngs[i], latLngs[i+1]);
        segs.push(d); total += d;
    }
    if (total === 0) return { lat: latLngs[0][0], lng: latLngs[0][1], bearing: 0 };
    let target = frac * total, acc = 0;
    for (let i = 0; i < segs.length; i++) {
        if (acc + segs[i] >= target || i === segs.length-1) {
            const t = segs[i] > 0 ? (target - acc) / segs[i] : 0;
            const a = latLngs[i], b = latLngs[i+1];
            return {
                lat: a[0] + t*(b[0]-a[0]),
                lng: a[1] + t*(b[1]-a[1]),
                bearing: bearingBetween(a, b)
            };
        }
        acc += segs[i];
    }
    const last = latLngs[latLngs.length-1];
    return { lat: last[0], lng: last[1], bearing: 0 };
}

function drawCaboArrows(c, direcao) {
    clearCaboArrows();
    _selectedCaboForArrows = c;
    const pts = _caboPts(c);
    if (pts.length < 2) return;
    let latLngs = pts.map(p => [p.lat, p.lng]);
    if (direcao === 'fim_para_inicio') latLngs = [...latLngs].reverse();
    const color = c.cor_mapa || '#3399ff';
    [0.25, 0.50, 0.75].forEach(frac => {
        const pos = pointAtFraction(latLngs, frac);
        const bear = pos.bearing;
        const icon = L.divIcon({
            html: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16">
                <polygon points="8,1 15,15 8,11 1,15" fill="${color}" stroke="rgba(0,0,0,0.5)" stroke-width="0.8"
                    transform="rotate(${bear.toFixed(1)},8,8)"/>
            </svg>`,
            iconSize: [16,16], iconAnchor: [8,8], className: ''
        });
        _caboArrowMarkers.push(
            L.marker([pos.lat, pos.lng], { icon, interactive: false, zIndexOffset: 500 }).addTo(map)
        );
    });
}

async function fetchCaboSinal(c) {
    const box = document.getElementById('cabo-sinal-box');
    if (!box) return;
    try {
        const r = await fetch(`${BASE_URL}/api/sinal.php?tipo=cabo&id=${c.id}`);
        const d = await r.json();
        if (!d.success) { box.innerHTML = `<span style="color:#ff4455">${d.error||'Erro'}</span>`; return; }
        if (d.sinal_entrada === null) {
            box.innerHTML = `<span style="color:var(--text-muted);font-style:italic">${d.aviso||'Sinal não rastreável'}</span>`;
            return;
        }
        const direcao = d.direcao || 'inicio_para_fim';
        _selectedSinalDirecao = direcao;
        drawCaboArrows(c, direcao);
        const avisoHtml = d.aviso ? `<div style="color:#ff8800;font-size:11px;margin-top:4px"><i class="fas fa-exclamation-triangle"></i> ${d.aviso}</div>` : '';
        const dirLabel = direcao === 'inicio_para_fim' ? '→' : '←';
        box.innerHTML = `
            <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
                ${signalBadge(d.sinal_entrada, 'Entrada')}
                <span style="color:var(--text-muted);font-size:13px">${dirLabel}</span>
                ${signalBadge(d.sinal_saida, 'Saída')}
            </div>
            <div style="color:var(--text-muted);font-size:11px;margin-top:4px">Perda no cabo: ${d.perda_cabo?.toFixed(3)} dBm · Fibra ${d.fibra}</div>
            ${avisoHtml}`;
    } catch(err) {
        if (box) box.innerHTML = `<span style="color:#ff4455">Erro ao calcular sinal</span>`;
    }
}

async function invertCaboDir(caboId) {
    const btn = document.getElementById('btn-invert-dir');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }
    try {
        const r = await fetch(`${BASE_URL}/api/elements.php?type=invert_cabo_dir`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: caboId })
        });
        const d = await r.json();
        if (!d.success) { showToast(d.error || 'Erro ao inverter', 'error'); return; }
        // Update local ELEMENTS data
        const cabo = ELEMENTS.cabos.find(c => c.id == caboId);
        if (cabo) cabo.direcao_invertida = d.direcao_invertida;
        // Update button appearance
        const invertado = d.direcao_invertida == 1;
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-exchange-alt"></i> ${invertado ? 'Direção Invertida' : 'Inverter Direção'}`;
            btn.style.background = invertado ? 'rgba(255,153,0,.25)' : 'rgba(255,255,255,.06)';
            btn.style.color = invertado ? '#ff9900' : 'var(--text-muted)';
            btn.style.border = `1px solid ${invertado ? 'rgba(255,153,0,.4)' : 'var(--border)'}`;
        }
        // Reload signal with new direction
        const box = document.getElementById('cabo-sinal-box');
        if (box) box.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right:4px"></i> Recalculando...';
        fetchCaboSinal(cabo || { id: caboId });
    } catch(e) {
        showToast('Erro de comunicação', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-exchange-alt"></i> Inverter Direção'; }
    }
}

async function fetchCtoSinal(c) {
    const box = document.getElementById('cto-sinal-box');
    if (!box) return;
    try {
        const r = await fetch(`${BASE_URL}/api/sinal.php?tipo=cto&id=${c.id}`);
        const d = await r.json();
        if (!d.success) { box.innerHTML = `<span style="color:#ff4455">${d.error||'Erro'}</span>`; return; }
        if (d.sinal === null) {
            box.innerHTML = `<span style="color:var(--text-muted);font-style:italic">${d.aviso||'Sinal não rastreável'}</span>`;
            return;
        }
        const splInfo = d.spl_codigo ? `<span style="color:var(--text-muted);font-size:11px"> · ${d.spl_codigo} (${d.spl_relacao})</span>` : '';
        const compStr = d.comprimento_m != null ? (d.comprimento_m >= 1000 ? (d.comprimento_m/1000).toFixed(2)+' km' : Math.round(d.comprimento_m)+' m') : null;
        const avisoHtml = d.aviso ? `<div style="color:#ff8800;font-size:11px;margin-top:4px"><i class="fas fa-exclamation-triangle"></i> ${d.aviso}</div>` : '';
        box.innerHTML = `
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                ${signalBadge(d.sinal, 'Saída splitter')}${splInfo}
            </div>
            ${compStr ? `<div style="font-size:11px;color:var(--text-muted);margin-top:4px"><i class="fas fa-ruler-horizontal" style="margin-right:3px"></i> Fibra total: ${compStr}</div>` : ''}
            ${avisoHtml}`;
    } catch(err) {
        if (box) box.innerHTML = `<span style="color:#ff4455">Erro ao calcular sinal</span>`;
    }
}

// ---- Info Panel ----
function showInfo(type, data) {
    const panel = document.getElementById('infoPanel');
    const configs = {
        poste:   { icon: 'fa-border-all', color: '#888',    bg: '#333', title: 'Poste', link: 'postes' },
        ceo:     { icon: 'fa-box',        color: '#9933ff', bg: '#2a1545', title: 'CEO', link: 'ceos' },
        cto:     { icon: 'fa-box-open',   color: '#00cc66', bg: '#0a2015', title: 'CTO', link: 'ctos' },
        rack:    { icon: 'fa-server',     color: '#cc8800', bg: '#2a1800', title: 'Rack', link: 'racks' },
        cliente: { icon: 'fa-user',       color: '#00ccff', bg: '#001522', title: 'Cliente', link: 'clientes' },
        cabo:    { icon: 'fa-minus',      color: '#3399ff', bg: '#001022', title: 'Cabo', link: 'cabos' },
    };
    const cfg = configs[type];
    let rows = '';
    if (type === 'poste') {
        rows = infoRow('Código', data.codigo) + infoRow('Tipo', data.tipo) + infoRow('Status', statusBadge(data.status));
        rows += cabosAncoradosHtml('poste', data.id);
    } else if (type === 'ceo') {
        rows = infoRow('Código', data.codigo) + infoRow('Capacidade', data.capacidade_fo+' FO') + infoRow('Tipo', data.tipo) + infoRow('Status', statusBadge(data.status));
        rows += cabosAncoradosHtml('ceo', data.id);
    } else if (type === 'cto') {
        const pct = Math.round((data.clientes_ativos / data.capacidade_portas) * 100);
        const ponLabel = data.pon_olt_nome
            ? `<span style="color:#00ccff;font-weight:600">Slot ${data.pon_slot} / PON ${data.pon_numero}</span> <span style="color:var(--text-muted);font-size:11px">— ${data.pon_olt_nome}</span>`
            : '<span style="color:var(--text-muted)">—</span>';
        rows = infoRow('Código', data.codigo)
             + infoRow('Capacidade', data.clientes_ativos+'/'+data.capacidade_portas+' portas ('+pct+'%)')
             + infoRow('Slot / PON', ponLabel)
             + infoRow('Status', statusBadge(data.status));
        rows += cabosAncoradosHtml('cto', data.id);
        rows += `<div class="info-item" style="grid-column:1/-1;margin-top:4px;padding-top:6px;border-top:1px solid var(--border)">
            <div class="label" style="display:flex;align-items:center;gap:5px;margin-bottom:4px"><i class="fas fa-signal" style="font-size:10px"></i> Sinal na CTO</div>
            <div id="cto-sinal-box" style="font-size:12px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin" style="margin-right:4px"></i> Calculando...</div>
        </div>`;
        // Port usage map
        const ctoClients = ELEMENTS.clientes.filter(cl => cl.cto_id == data.id);
        if (data.capacidade_portas > 0) {
            let portHtml = '';
            for (let i = 1; i <= data.capacidade_portas; i++) {
                const cl = ctoClients.find(c => c.porta_cto == i);
                if (cl) {
                    portHtml += `<div title="Porta ${i}: ${cl.login||cl.nome}" style="background:rgba(0,204,102,.2);border:1px solid rgba(0,204,102,.4);border-radius:5px;padding:4px 7px;font-size:10px;color:#00cc66;cursor:pointer;min-width:32px;text-align:center" onclick="showInfo('cliente',ELEMENTS.clientes.find(c=>c.id==${cl.id}))">
                        <div style="font-weight:700;font-size:11px">${i}</div>
                        <div style="font-size:9px;white-space:nowrap">${cl.login||cl.nome}</div>
                    </div>`;
                } else {
                    portHtml += `<div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:5px;padding:4px 7px;font-size:10px;color:#444;text-align:center;min-width:32px">
                        <div style="font-weight:700;font-size:11px">${i}</div>
                        <div style="font-size:9px">livre</div>
                    </div>`;
                }
            }
            rows += `<div class="info-item" style="grid-column:1/-1">
                <div class="label">Portas</div>
                <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:4px">${portHtml}</div>
            </div>`;
        }
    } else if (type === 'rack') {
        rows = infoRow('Código', data.codigo) + infoRow('Nome', data.nome||'—') + infoRow('Status', statusBadge(data.status)) + infoRow('Localização', data.localizacao||'—') + infoRow('OLTs', data.total_olts||0);
        rows += cabosAncoradosHtml('rack', data.id);
        rows += `<div class="info-item" style="grid-column:1/-1;margin-top:4px">
            <a href="${BASE_URL}/modules/racks/fusao.php?id=${data.id}" class="btn btn-sm" style="background:rgba(204,136,0,.2);color:#cc8800;border:1px solid rgba(204,136,0,.3);width:100%;display:block;text-align:center;text-decoration:none">
                <i class="fas fa-project-diagram"></i> Mapa de Conexões
            </a></div>`;
    } else if (type === 'cliente') {
        const cto = data.cto_id ? ELEMENTS.ctos.find(c=>c.id==data.cto_id) : null;
        const ctoLabel = cto ? `${cto.codigo} — Porta ${data.porta_cto||'?'}` : (data.cto_id ? 'CTO #'+data.cto_id : '—');
        const ponLabel = cto && cto.pon_olt_nome
            ? `<span style="color:#00ccff;font-weight:600">Slot ${cto.pon_slot} / PON ${cto.pon_numero}</span> <span style="font-size:11px;color:var(--text-muted)">— ${cto.pon_olt_nome}</span>`
            : '<span style="color:var(--text-muted)">—</span>';
        rows = infoRow('Nome', data.nome) + infoRow('Login', data.login||'—') + infoRow('Serial ONU', data.serial_onu||'—') + infoRow('Status', statusBadge(data.status)) + infoRow('Conexão Drop', ctoLabel) + infoRow('Slot / PON', ponLabel);
        // Sinal do cliente via drop
        if (cto && cto.lat && data.lat) {
            const dropM = Math.round(haversineM(cto.lat, cto.lng, data.lat, data.lng));
            rows += `<div class="info-item" style="grid-column:1/-1;margin-top:4px;padding-top:6px;border-top:1px solid var(--border)">
                <div class="label" style="display:flex;align-items:center;gap:5px;margin-bottom:4px">
                    <i class="fas fa-signal" style="font-size:10px"></i> Sinal no Cliente
                    <span style="font-size:10px;color:var(--text-muted)">(Drop: ~${dropM} m)</span>
                </div>
                <div id="cli-sinal-box" style="font-size:12px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin" style="margin-right:4px"></i> Calculando...</div>
            </div>`;
            // Fetch CTO signal then compute client signal
            setTimeout(async () => {
                try {
                    const r = await fetch(`${BASE_URL}/api/sinal.php?tipo=cto&id=${cto.id}`);
                    const d = await r.json();
                    const box = document.getElementById('cli-sinal-box');
                    if (!box) return;
                    if (!d.success || d.sinal == null) {
                        box.innerHTML = '<span style="color:#555">Sinal da CTO não disponível</span>';
                        return;
                    }
                    const FIBER_ATTN = 0.00025;
                    const CONN_LOSS  = 0.8;
                    const ctoSinal  = d.sinal;
                    const dropLoss  = dropM * FIBER_ATTN;
                    const cliSinal  = Math.round((ctoSinal - dropLoss - CONN_LOSS) * 100) / 100;
                    const c = sinalCorNet(cliSinal);
                    box.innerHTML = `<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                        <span style="font-size:18px;font-weight:800;color:${c}">${(cliSinal>=0?'+':'')}${cliSinal.toFixed(2)} dBm</span>
                        <div style="font-size:10px;color:#555;line-height:1.5">
                            CTO: ${(ctoSinal>=0?'+':'')}${ctoSinal.toFixed(2)} dBm<br>
                            Drop: −${dropLoss.toFixed(4)} dBm<br>
                            Conector: −${CONN_LOSS} dBm
                        </div>
                    </div>`;
                } catch(e) {
                    const box = document.getElementById('cli-sinal-box');
                    if (box) box.innerHTML = '<span style="color:#555">Erro ao calcular sinal</span>';
                }
            }, 0);
        }
        rows += `<div class="info-item full" style="grid-column:1/-1;margin-top:4px">
            <button class="btn btn-sm" style="background:rgba(0,204,102,.2);color:#00cc66;border:1px solid rgba(0,204,102,.3);width:100%"
                onclick="startDropModeById(${data.id})">
                <i class="fas fa-plug"></i> ${data.cto_id ? 'Alterar Ramal (Drop)' : 'Adicionar Ramal (Drop)'}
            </button></div>`;
    } else if (type === 'cabo') {
        const fmtM = v => v != null ? (v >= 1000 ? (v/1000).toFixed(2)+' km' : Math.round(v)+' m') : '—';
        const compMapa = fmtM(data.comprimento_m);
        const compReal = data.comprimento_real != null ? fmtM(data.comprimento_real) : null;
        const compLabel = compReal
            ? `<span style="color:var(--success);font-weight:700">${compReal}</span> <span style="font-size:11px;color:var(--text-muted)">(real) · ${compMapa} mapa</span>`
            : `${compMapa} <span style="font-size:11px;color:var(--text-muted)">(mapa)</span>`;
        rows = infoRow('Código', data.codigo) + infoRow('Tipo', data.tipo) + infoRow('Fibras', data.num_fibras+' FO') + infoRow('Comprimento', compLabel) + infoRow('Status', statusBadge(data.status));
        const invertado = data.direcao_invertida == 1;
        rows += `<div class="info-item" style="grid-column:1/-1;margin-top:4px;padding-top:6px;border-top:1px solid var(--border)">
            <div class="label" style="display:flex;align-items:center;gap:6px;margin-bottom:6px">
                <i class="fas fa-signal" style="font-size:10px"></i> Sinal no Cabo
                <button id="btn-invert-dir" onclick="invertCaboDir(${data.id})" title="Inverter direção do sinal"
                    style="margin-left:auto;padding:2px 8px;font-size:10px;border-radius:6px;cursor:pointer;
                           background:${invertado?'rgba(255,153,0,.25)':'rgba(255,255,255,.06)'};
                           color:${invertado?'#ff9900':'var(--text-muted)'};
                           border:1px solid ${invertado?'rgba(255,153,0,.4)':'var(--border)'};
                           display:flex;align-items:center;gap:5px">
                    <i class="fas fa-exchange-alt"></i> ${invertado?'Direção Invertida':'Inverter Direção'}
                </button>
            </div>
            <div id="cabo-sinal-box" style="font-size:12px;color:var(--text-muted)">
                <i class="fas fa-spinner fa-spin" style="margin-right:4px"></i> Calculando...
            </div>
        </div>`;
    }

    const closeBtn = `<button class="btn btn-sm btn-secondary ip-close-btn" onclick="document.getElementById('infoPanel').classList.remove('show');clearCaboArrows();clearSelection()" title="Fechar"><i class="fas fa-times"></i></button>`;
    const viewBtn   = cfg.link ? `<a href="${BASE_URL}/modules/${cfg.link}/view.php?id=${data.id}" class="btn btn-sm btn-secondary" title="Ver detalhes"><i class="fas fa-eye"></i> <span class="ip-btn-label">Ver</span></a>` : '';
    const extraBtn  = type==='ceo'  ? `<a href="${BASE_URL}/modules/fusoes/view.php?ceo_id=${data.id}" class="btn btn-sm btn-secondary" title="Mapa de Fusão"><i class="fas fa-project-diagram"></i> <span class="ip-btn-label">Fusões</span></a>` :
                      type==='cto'  ? `<a href="${BASE_URL}/modules/fusoes/view.php?cto_id=${data.id}" class="btn btn-sm btn-secondary" title="Mapa de Fusão"><i class="fas fa-project-diagram"></i> <span class="ip-btn-label">Fusões</span></a>` :
                      type==='rack' ? `<a href="${BASE_URL}/modules/racks/fusao.php?id=${data.id}" class="btn btn-sm btn-secondary" title="Mapa de Conexões"><i class="fas fa-project-diagram"></i> <span class="ip-btn-label">Conexões</span></a>` : '';
    panel.innerHTML = `
        <div class="info-panel-header">
            <div class="info-panel-icon" style="background:${cfg.bg};color:${cfg.color}"><i class="fas ${cfg.icon}"></i></div>
            <div class="info-panel-title">
                <h4>${cfg.title}: ${data.codigo||data.nome||data.id}</h4>
                <span>${data.nome||''}</span>
            </div>
            ${closeBtn}
        </div>
        <div class="ip-actions">
            ${viewBtn}
            ${extraBtn}
            <a href="${BASE_URL}/modules/${cfg.link}/edit.php?id=${data.id}" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> <span class="ip-btn-label">Editar</span></a>
            <button class="btn btn-sm btn-danger" onclick="deleteElement('${type}',${data.id},'${(data.codigo||data.nome||'#'+data.id).replace(/'/g,'')}')"><i class="fas fa-trash"></i> <span class="ip-btn-label">Excluir</span></button>
        </div>
        <div class="info-grid">${rows}</div>`;
    panel.classList.add('show');
    setSelection(type, data);

    // For cable: fetch signal data and draw direction arrows
    clearCaboArrows();
    if (type === 'cabo') {
        _selectedCaboForArrows = data;
        fetchCaboSinal(data);
    } else if (type === 'cto') {
        fetchCtoSinal(data);
    }
}

function infoRow(label, value) {
    return `<div class="info-item"><div class="label">${label}</div><div class="value">${value}</div></div>`;
}

function statusBadge(s) {
    const labels = { ativo:'Ativo', inativo:'Inativo', cheio:'Cheio', defeito:'Defeito', manutencao:'Manutenção', reserva:'Reserva', cortado:'Cortado', cancelado:'Cancelado' };
    return `<span class="status-badge status-${s}">${labels[s]||s}</span>`;
}

// ============================================================
// ---- TOOL SYSTEM ----
// ============================================================
let currentTool  = 'select';
let caboPoints   = [];
let _continuandoCabo = null; // { caboId, existingPontos } — set when extending an existing cable
let caboPolyline = null;
let tempMarkers  = [];
let drawingCabo  = false;

// ---- Snap System ----
const SNAP_PX = 28; // raio em pixels para encaixar
let snapTargets = [];
let snapRing    = null;

function buildSnapTargets() {
    snapTargets = [];
    ELEMENTS.postes.forEach(p => snapTargets.push({ tipo:'poste', id:p.id, lat:p.lat, lng:p.lng, label:p.codigo }));
    ELEMENTS.ceos.forEach(c   => snapTargets.push({ tipo:'ceo',   id:c.id, lat:c.lat, lng:c.lng, label:c.codigo }));
    ELEMENTS.ctos.forEach(c   => snapTargets.push({ tipo:'cto',   id:c.id, lat:c.lat, lng:c.lng, label:c.codigo }));
    ELEMENTS.racks.forEach(r  => snapTargets.push({ tipo:'rack',  id:r.id, lat:r.lat, lng:r.lng, label:r.codigo }));
}

function getNearestSnap(latlng) {
    let best = null, bestD = SNAP_PX;
    const pt = map.latLngToContainerPoint(latlng);
    for (const t of snapTargets) {
        const d = pt.distanceTo(map.latLngToContainerPoint(L.latLng(t.lat, t.lng)));
        if (d < bestD) { bestD = d; best = t; }
    }
    return best;
}

map.on('mousemove', function(e) {
    if (!drawingCabo) { if (snapRing) { map.removeLayer(snapRing); snapRing = null; } return; }
    const snap = getNearestSnap(e.latlng);
    if (snap) {
        const ll = L.latLng(snap.lat, snap.lng);
        if (!snapRing) {
            snapRing = L.circleMarker(ll, { radius:18, color:'#ffcc00', weight:2.5, fill:false, opacity:0.9, interactive:false }).addTo(map);
        } else { snapRing.setLatLng(ll); }
        map.getContainer().style.cursor = 'crosshair';
    } else {
        if (snapRing) { map.removeLayer(snapRing); snapRing = null; }
        map.getContainer().style.cursor = '';
    }
});

// Chama buildSnapTargets após renderizar elementos
const _renderAllOrig = renderAll;
renderAll = function() { _renderAllOrig(); buildSnapTargets(); };

// --- Batch Postes ---
let batchPosteAtivo = false;
let batchPosteCount = 0;

// --- Floating status bar (para cabo e postes em batch) ---
const floatBar = document.getElementById('float-draw-bar');

function showFloatBar(html) {
    floatBar.innerHTML = html;
    floatBar.style.display = 'flex';
}
function hideFloatBar() {
    floatBar.style.display = 'none';
}

// ---- Move Mode ----
function toggleMoveMode() {
    // Cannot activate move mode while drawing a cable
    if (drawingCabo) { showToast('Finalize o cabo antes de entrar no modo mover.', 'warning'); return; }

    moveMode = !moveMode;
    const btn = document.getElementById('tool-move');

    if (moveMode) {
        btn.classList.add('active');
        btn.style.color = '#ffcc00';
        map.getContainer().style.cursor = 'move';
        // Keep map.dragging enabled — markers handle their own drag independently
        showFloatBar(`
            <i class="fas fa-arrows-alt" style="color:#ffcc00"></i>
            <span style="color:#ffcc00;font-weight:700">MODO MOVER ATIVO</span>
            <span style="font-size:11px;color:#888">Arraste postes, caixas ou pontos de cabo (pontos amarelos)</span>
            <button class="btn btn-sm" style="background:#ff4455;color:#fff;margin-left:8px" onclick="toggleMoveMode()">
                <i class="fas fa-times"></i> Sair
            </button>
        `);
        renderAll();
        showToast('Modo Mover ativado — arraste os elementos', 'info');
    } else {
        btn.classList.remove('active');
        btn.style.color = '';
        map.getContainer().style.cursor = '';
        map.dragging.enable();
        hideFloatBar();
        renderAll(); // re-render without draggable/vertices
        showToast('Modo Mover desativado', 'info');
    }
}

// ---- setTool ----
function setTool(tool) {
    // Sair do modo mover ao usar outra ferramenta
    if (moveMode) { moveMode = false; document.getElementById('tool-move').classList.remove('active'); document.getElementById('tool-move').style.color=''; map.dragging.enable(); hideFloatBar(); renderAll(); }
    // Desativar batch poste se trocar de ferramenta
    if (batchPosteAtivo && tool !== 'poste') {
        finalizarBatchPoste();
    }
    // Parar cabo se trocar
    if (drawingCabo && tool !== 'cabo') {
        stopDrawCabo();
    }

    currentTool = tool;
    document.querySelectorAll('.tool-btn[id^="tool-"]').forEach(b => b.classList.remove('active'));
    document.getElementById('tool-' + tool)?.classList.add('active');
    map.getContainer().style.cursor = tool === 'select' ? '' : 'crosshair';
}

// ============================================================
// BATCH POSTES — clica N vezes, adiciona N postes sem modal
// ============================================================
function toggleBatchPoste() {
    if (batchPosteAtivo) {
        finalizarBatchPoste();
    } else {
        batchPosteAtivo = true;
        batchPosteCount = 0;
        setTool('poste');
        showFloatBar(`
            <i class="fas fa-border-all" style="color:#ffee44"></i>
            <span>Modo Postes ATIVO — clique no mapa para adicionar</span>
            <span id="batch-count" style="background:rgba(255,238,68,0.2);padding:3px 10px;border-radius:10px;font-weight:700;color:#ffee44">0 adicionados</span>
            <button class="btn btn-sm" style="background:#ff4455;color:#fff;margin-left:8px" onclick="finalizarBatchPoste()">
                <i class="fas fa-stop"></i> Finalizar
            </button>
        `);
        showToast('Clique no mapa para adicionar postes. Clique em Finalizar quando terminar.', 'info');
    }
}

async function addPosteInstant(lat, lng) {
    // Gerar código automático
    const rand  = Math.random().toString(36).substr(2, 6).toUpperCase();
    const seq   = String(ELEMENTS.postes.length + batchPosteCount + 1).padStart(3, '0');
    const code  = `PST-${seq}-${rand}`;

    // Marker temporário imediato (amarelo) enquanto salva
    const tmpMarker = L.marker([lat, lng], { icon: iconPosteBatch() })
        .addTo(layers.postes)
        .bindTooltip(code, { permanent: false, direction: 'top' });

    try {
        const res = await fetch(`${BASE_URL}/api/elements.php?type=poste`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ codigo: code, lat, lng, tipo: 'concreto', proprietario: 'Próprio', observacoes: '' })
        });
        const result = await res.json();

        if (result.success) {
            // Substituir ícone amarelo pelo ícone real
            layers.postes.removeLayer(tmpMarker);
            const p = result.data;
            ELEMENTS.postes.push(p);
            L.marker([p.lat, p.lng], { icon: iconPoste(p.status) })
                .addTo(layers.postes)
                .on('click', () => showInfo('poste', p))
                .bindTooltip(p.codigo, { permanent: false, direction: 'top' });

            batchPosteCount++;
            const el = document.getElementById('batch-count');
            if (el) el.textContent = batchPosteCount + ' adicionados';
        } else {
            layers.postes.removeLayer(tmpMarker);
            showToast('Erro ao salvar poste: ' + (result.error || ''), 'error');
        }
    } catch (err) {
        layers.postes.removeLayer(tmpMarker);
        showToast('Erro de conexão ao salvar poste.', 'error');
    }
}

function finalizarBatchPoste() {
    batchPosteAtivo = false;
    hideFloatBar();
    setTool('select');
    if (batchPosteCount > 0) {
        showToast(`✓ ${batchPosteCount} poste${batchPosteCount>1?'s':''} adicionado${batchPosteCount>1?'s':''}!`, 'success');
    }
    batchPosteCount = 0;
}

// ============================================================
// CABO — desenha PRIMEIRO, modal DEPOIS
// ============================================================
function iniciarDrawCabo() {
    drawingCabo = true;
    caboPoints  = [];
    tempMarkers = [];
    setTool('cabo');
    showFloatBar(`
        <i class="fas fa-minus" style="color:#00b4ff"></i>
        <span>Traçando cabo — clique para adicionar pontos</span>
        <span id="cabo-pts-bar" style="color:#00b4ff;font-weight:700">0 pts | 0 m</span>
        <button class="btn btn-sm btn-secondary" onclick="undoLastPoint()" style="margin-left:4px"><i class="fas fa-undo"></i></button>
        <button class="btn btn-sm" style="background:#00cc66;color:#fff;margin-left:4px" onclick="finalizarTracadoCabo()" id="btn-finalizar-cabo" disabled>
            <i class="fas fa-check"></i> Finalizar traçado
        </button>
        <button class="btn btn-sm btn-danger" onclick="cancelarCabo()" style="margin-left:4px">
            <i class="fas fa-times"></i>
        </button>
    `);
    showToast('Clique no mapa para traçar o cabo. Duplo-clique ou botão Finalizar para concluir.', 'info');
}

function finalizarTracadoCabo() {
    if (_continuandoCabo) { finalizarContinuacaoCabo(); return; }
    if (caboPoints.length < 2) { showToast('Adicione ao menos 2 pontos no traçado.', 'warning'); return; }
    hideFloatBar();
    // Preenche o comprimento no modal e abre
    const comp = calcCaboLength();
    document.getElementById('cabo-pontos-count').textContent = caboPoints.length + ' pontos';
    document.getElementById('cabo-length').textContent = comp >= 1000 ? (comp/1000).toFixed(2)+' km' : Math.round(comp)+' m';
    document.getElementById('btn-salvar-cabo').disabled = false;
    openModal('modal-cabo');
}

function cancelarCabo() {
    _continuandoCabo = null;
    stopDrawCabo();
    setTool('select');
    hideFloatBar();
}

function stopDrawCabo() {
    drawingCabo = false;
    if (caboPolyline) { map.removeLayer(caboPolyline); caboPolyline = null; }
    tempMarkers.forEach(m => map.removeLayer(m));
    tempMarkers = [];
    caboPoints  = [];
}

function calcCaboLength() {
    let total = 0;
    for (let i = 1; i < caboPoints.length; i++) {
        const R = 6371000;
        const dLat = (caboPoints[i].lat - caboPoints[i-1].lat) * Math.PI / 180;
        const dLon = (caboPoints[i].lng - caboPoints[i-1].lng) * Math.PI / 180;
        const a = Math.sin(dLat/2)**2 + Math.cos(caboPoints[i-1].lat*Math.PI/180) * Math.cos(caboPoints[i].lat*Math.PI/180) * Math.sin(dLon/2)**2;
        total += R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    }
    return total;
}

function updateCaboLine() {
    if (caboPolyline) map.removeLayer(caboPolyline);
    if (caboPoints.length < 2) return;
    caboPolyline = L.polyline(caboPoints.map(p => [p.lat, p.lng]), {
        color: '#00b4ff', weight: 3, opacity: 0.9, dashArray: '8,4'
    });
    caboPolyline.addTo(map);
}

function updateCaboBarInfo() {
    const comp = calcCaboLength();
    const el = document.getElementById('cabo-pts-bar');
    if (el) el.textContent = caboPoints.length + ' pts | ' + (comp >= 1000 ? (comp/1000).toFixed(2)+' km' : Math.round(comp)+' m');
    const btn = document.getElementById('btn-finalizar-cabo');
    if (btn) btn.disabled = caboPoints.length < 2;
}

function updateCaboInfo() {
    // Chamado do modal legado (manter compatibilidade)
    const comp = calcCaboLength();
    const el1 = document.getElementById('cabo-pontos-count');
    const el2 = document.getElementById('cabo-length');
    if (el1) el1.textContent = caboPoints.length + ' pontos';
    if (el2) el2.textContent = comp >= 1000 ? (comp/1000).toFixed(2)+' km' : Math.round(comp)+' m';
}

function undoLastPoint() {
    if (caboPoints.length === 0) return;
    caboPoints.pop();
    const m = tempMarkers.pop();
    if (m) map.removeLayer(m);
    updateCaboLine();
    updateCaboBarInfo();
}

// ============================================================
// ---- MAP CLICK / DBLCLICK HANDLERS ----
// ============================================================
map.on('click', function(e) {
    const { lat, lng } = e.latlng;

    if (currentTool === 'poste' && batchPosteAtivo) {
        addPosteInstant(lat, lng);

    } else if (currentTool === 'cto') {
        document.getElementById('cto-lat').value = lat;
        document.getElementById('cto-lng').value = lng;
        openModal('modal-cto');
        setTool('select');

    } else if (currentTool === 'ceo') {
        document.getElementById('ceo-lat').value = lat;
        document.getElementById('ceo-lng').value = lng;
        openModal('modal-ceo');
        setTool('select');

    } else if (currentTool === 'cliente') {
        document.getElementById('cliente-lat').value = lat;
        document.getElementById('cliente-lng').value = lng;
        openModal('modal-cliente');
        setTool('select');

    } else if (currentTool === 'cabo' && drawingCabo) {
        // Clique em área vazia — verifica snap por proximidade
        const snap = getNearestSnap(e.latlng);
        if (snap) {
            addCaboPoint(snap.lat, snap.lng, snap.tipo, snap.id, snap.label);
        } else {
            addCaboPoint(lat, lng, null, null, null);
        }
    }
});

map.on('dblclick', function(e) {
    if (currentTool === 'cabo' && drawingCabo && caboPoints.length >= 2) {
        L.DomEvent.stop(e);
        finalizarTracadoCabo();
    }
});

// ---- Save Functions ----
async function saveElement(event, type) {
    event.preventDefault();
    const form = event.target;
    const data = Object.fromEntries(new FormData(form));
    try {
        const res = await fetch(`${BASE_URL}/api/elements.php?type=${type}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            closeModal('modal-'+type);
            form.reset();
            ELEMENTS[type+'s']?.push(result.data);
            try { renderAll(); } catch(re) { console.warn('renderAll:', re); }
            showToast(result.message || 'Salvo com sucesso!', 'success');
        } else {
            showToast(result.error || 'Erro ao salvar.', 'error');
        }
    } catch(e) {
        showToast('Erro de comunicação. Verifique a conexão.', 'error');
        console.error('saveElement error:', e);
    }
}

async function saveCabo(event) {
    event.preventDefault();
    if (caboPoints.length < 2) { showToast('Trace o percurso no mapa primeiro.', 'warning'); return; }
    const form = event.target;
    const data = { ...Object.fromEntries(new FormData(form)), pontos: caboPoints };
    try {
        const res = await fetch(`${BASE_URL}/api/elements.php?type=cabo`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            closeModal('modal-cabo');
            form.reset();
            stopDrawCabo();
            setTool('select');
            hideFloatBar();
            ELEMENTS.cabos.push(result.data);
            try { renderAll(); } catch(re) { console.warn('renderAll after saveCabo:', re); }
            showToast('Cabo salvo! ' + (result.message || ''), 'success');
        } else {
            showToast(result.error || 'Erro ao salvar cabo.', 'error');
        }
    } catch(e) {
        showToast('Erro de comunicação. Verifique a conexão.', 'error');
        console.error('saveCabo error:', e);
    }
}

// ---- Cables anchored to an element ----
function getCablesForElem(tipo, eid) {
    return ELEMENTS.cabos.filter(c => {
        const pts = typeof c.pontos === 'string' ? JSON.parse(c.pontos) : (c.pontos || []);
        return pts.some(pt => pt.et === tipo && pt.eid == eid);
    });
}

function cabosAncoradosHtml(tipo, eid) {
    const cabos = getCablesForElem(tipo, eid);
    if (!cabos.length) return '';
    return `<div class="info-item" style="grid-column:1/-1">
        <div class="label">Cabos Ancorados</div>
        <div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:4px">` +
        cabos.map(c => `
            <span style="background:rgba(51,153,255,.12);padding:2px 8px;border-radius:6px;font-size:11px;display:flex;align-items:center;gap:4px;color:#3399ff">
                ${c.codigo}
                <button onclick="unlinkCaboMapElem(${c.id},'${tipo}',${eid})" title="Desvincular"
                    style="background:none;border:none;color:#ff4455;cursor:pointer;padding:0;font-size:11px;line-height:1">✕</button>
            </span>`).join('') +
        `</div></div>`;
}

// ---- Drop Mode (connect client to CTO port) ----
let dropMode = false;
let dropClientData = null;
let dropLine = null;

function startDropModeById(clientId) {
    const data = ELEMENTS.clientes.find(c => c.id == clientId);
    if (!data) { showToast('Cliente não encontrado.', 'error'); return; }
    startDropMode(data);
}

function startDropMode(clientData) {
    dropMode = true;
    dropClientData = clientData;
    document.getElementById('infoPanel').classList.remove('show');
    map.getContainer().style.cursor = 'crosshair';
    map.on('mousemove', onDropMove);
    showFloatBar(`
        <i class="fas fa-plug" style="color:#00cc66"></i>
        <span style="color:#00cc66;font-weight:700">MODO RAMAL ATIVO</span>
        <span style="font-size:11px;color:#888">Clique em uma CTO para conectar <strong style="color:#fff">${clientData.login || clientData.nome}</strong></span>
        <button class="btn btn-sm btn-danger" onclick="cancelDropMode()" style="margin-left:8px"><i class="fas fa-times"></i> Cancelar</button>
    `);
}

function onDropMove(e) {
    if (!dropMode || !dropClientData) return;
    if (dropLine) map.removeLayer(dropLine);
    dropLine = L.polyline([[dropClientData.lat, dropClientData.lng], [e.latlng.lat, e.latlng.lng]], {
        color: '#00cc66', weight: 2, dashArray: '6,4', opacity: 0.8, interactive: false
    }).addTo(map);
}

function cancelDropMode() {
    dropMode = false;
    dropClientData = null;
    if (dropLine) { map.removeLayer(dropLine); dropLine = null; }
    map.off('mousemove', onDropMove);
    map.getContainer().style.cursor = '';
    hideFloatBar();
    closeModal('modal-drop-port');
}

function showDropPortModal(ctoData) {
    map.off('mousemove', onDropMove);
    if (dropLine) { map.removeLayer(dropLine); dropLine = null; }
    hideFloatBar();
    map.getContainer().style.cursor = '';

    const usedPorts = ELEMENTS.clientes
        .filter(cl => cl.cto_id == ctoData.id && cl.id != dropClientData.id)
        .map(cl => parseInt(cl.porta_cto));

    document.getElementById('drop-modal-title').textContent = `Ramal → ${ctoData.codigo}`;
    const grid = document.getElementById('drop-ports-grid');
    grid.innerHTML = '';
    for (let i = 1; i <= ctoData.capacidade_portas; i++) {
        const used = usedPorts.includes(i);
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = i;
        btn.style.cssText = `width:44px;height:44px;border-radius:8px;font-size:14px;font-weight:700;cursor:${used?'not-allowed':'pointer'};
            border:2px solid ${used?'rgba(255,68,85,.3)':'rgba(0,204,102,.5)'};
            background:${used?'rgba(255,68,85,.1)':'rgba(0,204,102,.1)'};
            color:${used?'#ff6677':'#00cc66'};`;
        if (used) { btn.disabled = true; btn.title = 'Porta ocupada'; }
        else { btn.onclick = () => confirmDrop(ctoData, i); }
        grid.appendChild(btn);
    }
    openModal('modal-drop-port');
}

async function confirmDrop(ctoData, porta) {
    const res = await fetch(`${BASE_URL}/api/elements.php?type=link_drop`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cliente_id: dropClientData.id, cto_id: ctoData.id, porta_cto: porta })
    });
    const result = await res.json();
    if (result.success) {
        // Update local data
        const cl = ELEMENTS.clientes.find(c => c.id == dropClientData.id);
        if (cl) { cl.cto_id = ctoData.id; cl.porta_cto = porta; }
        cancelDropMode();
        renderDropLines();
        showToast(`Drop conectado: ${ctoData.codigo} — Porta ${porta}`, 'success');
    } else {
        showToast(result.error || 'Erro ao conectar drop.', 'error');
    }
}

// ---- Unlink cabo from element (map info panel) ----
async function unlinkCaboMapElem(caboId, tipo, eid) {
    if (!confirm(`Desvincular cabo de ${tipo.toUpperCase()}?`)) return;
    const res = await fetch(`${BASE_URL}/api/elements.php?type=unlink_cabo_pt`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cabo_id: caboId, elemento_tipo: tipo, elemento_id: eid })
    });
    const result = await res.json();
    if (result.success) {
        // Update local pontos: remove anchor from matching points
        const cabo = ELEMENTS.cabos.find(c => c.id === caboId);
        if (cabo) {
            const pts = typeof cabo.pontos === 'string' ? JSON.parse(cabo.pontos) : (cabo.pontos || []);
            pts.forEach(pt => { if (pt.et === tipo && pt.eid == eid) { pt.et = null; pt.eid = null; } });
            cabo.pontos = pts;
        }
        renderCabos();
        document.getElementById('infoPanel').classList.remove('show');
        showToast(`Cabo desvinculado de ${tipo.toUpperCase()}`, 'success');
    } else {
        showToast(result.error || 'Erro ao desvincular.', 'error');
    }
}

// ---- Delete Element ----
async function deleteElement(type, id, label) {
    if (!confirm(`Remover ${type} "${label}"?\nEsta ação não pode ser desfeita.`)) return;
    const res = await fetch(`${BASE_URL}/api/elements.php?type=${type}&id=${id}`, { method: 'DELETE' });
    const result = await res.json();
    if (result.success) {
        document.getElementById('infoPanel').classList.remove('show');
        // Remove do array local e re-renderiza
        const map2arr = { poste:'postes', ceo:'ceos', cto:'ctos', rack:'racks', cliente:'clientes', cabo:'cabos' };
        const arr = map2arr[type];
        if (arr) ELEMENTS[arr] = ELEMENTS[arr].filter(e => e.id !== id);
        renderAll();
        buildSnapTargets();
        showToast(`${type.toUpperCase()} removido.`, 'success');
    } else {
        // Errors with \n get an alert (fusões message), otherwise toast
        if (result.error && result.error.includes('\n')) alert(result.error);
        else showToast(result.error || 'Erro ao remover.', 'error');
    }
}

// ---- Modal Helpers ----
function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (el.classList.contains('modal-overlay')) {
        // Garante display:flex antes de adicionar a classe para a transição funcionar
        el.style.display = 'flex';
        requestAnimationFrame(() => el.classList.add('show'));
    } else {
        el.style.display = 'flex';
        el.classList.add('show');
    }
    if (id === 'modal-mapa-config') setTimeout(initConfigMap, 100);
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (el.classList.contains('modal-overlay')) {
        el.classList.remove('show');
        // Aguarda a transição e esconde completamente via display:none
        setTimeout(() => { if (!el.classList.contains('show')) el.style.display = 'none'; }, 280);
    } else {
        el.style.display = 'none';
    }
}

// Fechar modal ao clicar no overlay (fundo escuro)
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => {
        if (e.target === o) { closeModal(o.id); stopDrawCabo(); setTool('select'); }
    });
});

// ---- Search ----
const _ssiCfg = {
    poste:   { icon: 'fa-border-all',    color: '#aaaaaa', bg: '#1a1a1a', label: 'Poste'   },
    ceo:     { icon: 'fa-box',           color: '#9933ff', bg: '#1a0033', label: 'CEO'     },
    cto:     { icon: 'fa-box-open',      color: '#00cc66', bg: '#001a0d', label: 'CTO'     },
    rack:    { icon: 'fa-server',        color: '#ff6600', bg: '#1a0d00', label: 'Rack'    },
    cliente: { icon: 'fa-user',          color: '#00ccff', bg: '#001522', label: 'Cliente' },
};

function _searchLocal(q) {
    const ql = q.toLowerCase();
    const results = [];
    const add = (tipo, el, sub) => results.push({ tipo, el, sub });

    ELEMENTS.postes.forEach(e => {
        if ((e.codigo||'').toLowerCase().includes(ql) || (e.nome||'').toLowerCase().includes(ql)) add('poste', e, e.codigo);
    });
    ELEMENTS.ceos.forEach(e => {
        if ((e.codigo||'').toLowerCase().includes(ql) || (e.nome||'').toLowerCase().includes(ql)) add('ceo', e, e.codigo);
    });
    ELEMENTS.ctos.forEach(e => {
        if ((e.codigo||'').toLowerCase().includes(ql) || (e.nome||'').toLowerCase().includes(ql)) add('cto', e, e.codigo);
    });
    ELEMENTS.racks.forEach(e => {
        if ((e.codigo||'').toLowerCase().includes(ql) || (e.nome||'').toLowerCase().includes(ql)) add('rack', e, e.codigo);
    });
    ELEMENTS.clientes.forEach(e => {
        if ((e.nome||'').toLowerCase().includes(ql) || (e.login||'').toLowerCase().includes(ql) || (e.codigo||'').toLowerCase().includes(ql)) {
            add('cliente', e, e.login || '');
        }
    });
    return results;
}

async function searchMap(q) {
    const input = document.getElementById('mapSearch');
    q = q !== undefined ? q : input.value.trim();
    if (!q) return;
    hideSuggestions();

    const results = _searchLocal(q);
    if (results.length > 0) {
        const r = results[0];
        map.setView([r.el.lat, r.el.lng], 18);
        return;
    }

    // Geocode via Nominatim
    const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=1`;
    try {
        const res = await fetch(url, { headers: { 'Accept-Language': 'pt-BR' } });
        const data = await res.json();
        if (data.length > 0) {
            map.setView([data[0].lat, data[0].lon], 17);
        } else {
            showToast('Endereço não encontrado.', 'warning');
        }
    } catch(e) {
        showToast('Erro ao buscar endereço.', 'error');
    }
}

// ---- Autocomplete ----
let _ssiActive = -1;

function showSuggestions(items) {
    const box = document.getElementById('searchSuggestions');
    if (!items.length) { hideSuggestions(); return; }
    box.innerHTML = items.map((r, i) => {
        const cfg = _ssiCfg[r.tipo] || _ssiCfg.poste;
        const name = r.el.nome || r.el.codigo || r.el.login || '';
        const sub  = r.sub && r.sub !== name ? r.sub : r.tipo.charAt(0).toUpperCase() + r.tipo.slice(1);
        return `<div class="search-suggestion-item" data-idx="${i}"
            style="cursor:pointer"
            onmousedown="pickSuggestion(event, '${(name).replace(/'/g,"\\'")}')">
            <div class="ssi-icon" style="background:${cfg.bg};color:${cfg.color}"><i class="fas ${cfg.icon}"></i></div>
            <div class="ssi-text">
                <div class="ssi-name">${name}</div>
                <div class="ssi-sub">${sub}</div>
            </div>
        </div>`;
    }).join('');
    _ssiActive = -1;
    box.style.display = 'block';
}

function hideSuggestions() {
    const box = document.getElementById('searchSuggestions');
    box.style.display = 'none';
    box.innerHTML = '';
    _ssiActive = -1;
}

function pickSuggestion(e, name) {
    e.preventDefault();
    const input = document.getElementById('mapSearch');
    input.value = name;
    hideSuggestions();
    searchMap(name);
}

(function () {
    const input = document.getElementById('mapSearch');

    input.addEventListener('input', function () {
        const q = this.value.trim();
        if (q.length < 4) { hideSuggestions(); return; }
        const results = _searchLocal(q).slice(0, 10);
        showSuggestions(results);
    });

    input.addEventListener('keydown', function (e) {
        const box = document.getElementById('searchSuggestions');
        const items = box.querySelectorAll('.search-suggestion-item');
        if (e.key === 'Enter') {
            if (_ssiActive >= 0 && items[_ssiActive]) {
                const name = items[_ssiActive].querySelector('.ssi-name').textContent;
                input.value = name;
                hideSuggestions();
                searchMap(name);
            } else {
                searchMap();
            }
            e.preventDefault();
        } else if (e.key === 'ArrowDown') {
            _ssiActive = Math.min(_ssiActive + 1, items.length - 1);
            items.forEach((el, i) => el.classList.toggle('ssi-hover', i === _ssiActive));
            e.preventDefault();
        } else if (e.key === 'ArrowUp') {
            _ssiActive = Math.max(_ssiActive - 1, 0);
            items.forEach((el, i) => el.classList.toggle('ssi-hover', i === _ssiActive));
            e.preventDefault();
        } else if (e.key === 'Escape') {
            hideSuggestions();
        }
    });

    input.addEventListener('blur', function () {
        setTimeout(hideSuggestions, 150);
    });
})();

function showToast(message, type = 'success') {
    const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle' };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<i class="fas ${icons[type]||icons.success}"></i><span class="toast-msg">${message}</span>`;
    document.getElementById('toastContainer').appendChild(toast);
    setTimeout(() => { toast.style.opacity='0'; toast.style.transition='opacity 0.3s'; setTimeout(()=>toast.remove(),300); }, 4000);
}

// ================================================================
// ---- Contexto de Cabo (botão direito) + Rompimento ----
// ================================================================
let _ctxCabo       = null; // {id, codigo, pts, clickLatLng, segIdx}
let _rompimentoData = null; // persiste enquanto o modal está aberto
const ctxEl        = document.getElementById('ctx-cabo');

function closeCtxCabo() { ctxEl.style.display = 'none'; _ctxCabo = null; }
document.addEventListener('click', () => { closeCtxCabo(); closeCaboPicker(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeCtxCabo(); closeCaboPicker(); } });

// Distância de ponto P ao segmento AB (em pixels de container)
function ptToSegDist(p, a, b) {
    const abx = b.x - a.x, aby = b.y - a.y;
    const len2 = abx*abx + aby*aby;
    if (len2 === 0) return Math.hypot(p.x - a.x, p.y - a.y);
    const t = Math.max(0, Math.min(1, ((p.x-a.x)*abx + (p.y-a.y)*aby) / len2));
    return Math.hypot(p.x - a.x - t*abx, p.y - a.y - t*aby);
}

function findNearestSeg(pts, clickLL) {
    const cp  = map.latLngToContainerPoint(clickLL);
    let best  = 0, bestD = Infinity;
    for (let i = 0; i < pts.length - 1; i++) {
        const a = map.latLngToContainerPoint(L.latLng(pts[i].lat, pts[i].lng));
        const b = map.latLngToContainerPoint(L.latLng(pts[i+1].lat, pts[i+1].lng));
        const d = ptToSegDist(cp, a, b);
        if (d < bestD) { bestD = d; best = i; }
    }
    return best; // break AFTER this index
}

// ---- Cable picker (when multiple cables overlap) ----
let _pickerEvent = null;

function findCabosAtPoint(latlng, thresholdPx = 8) {
    const cp = map.latLngToContainerPoint(latlng);
    return ELEMENTS.cabos.filter(cabo => {
        const pts = typeof cabo.pontos === 'string' ? JSON.parse(cabo.pontos) : (cabo.pontos || []);
        if (pts.length < 2) return false;
        for (let i = 0; i < pts.length - 1; i++) {
            const a = map.latLngToContainerPoint(L.latLng(pts[i].lat, pts[i].lng));
            const b = map.latLngToContainerPoint(L.latLng(pts[i+1].lat, pts[i+1].lng));
            if (ptToSegDist(cp, a, b) < thresholdPx) return true;
        }
        return false;
    });
}

function closeCaboPicker() {
    document.getElementById('cabo-picker').style.display = 'none';
    _pickerEvent = null;
}

function showCaboPicker(e, cabos, action) {
    L.DomEvent.stopPropagation(e);
    _pickerEvent = e;
    const list = document.getElementById('cabo-picker-list');
    list.innerHTML = cabos.map(c => {
        const comp = c.comprimento_m != null
            ? (c.comprimento_m >= 1000 ? (c.comprimento_m/1000).toFixed(1)+' km' : Math.round(c.comprimento_m)+' m')
            : '';
        const dot = `<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${c.cor_mapa||'#3399ff'};flex-shrink:0"></span>`;
        return `<div onclick="pickerSelect(${c.id},'${action}',event)"
            style="padding:8px 14px;cursor:pointer;display:flex;align-items:center;gap:8px;font-size:13px"
            onmouseover="this.style.background='rgba(255,255,255,.06)'"
            onmouseout="this.style.background=''">
            ${dot}
            <span style="flex:1;font-weight:500">${c.codigo}</span>
            <span style="font-size:11px;color:var(--text-muted)">${c.num_fibras}FO${comp?' · '+comp:''}</span>
        </div>`;
    }).join('');
    const picker = document.getElementById('cabo-picker');
    picker.style.display = 'block';
    const x = e.originalEvent.clientX, y = e.originalEvent.clientY;
    // Adjust if would go off-screen
    picker.style.left = (x + 6) + 'px';
    picker.style.top  = (y + 6) + 'px';
    // Correct overflow after render
    requestAnimationFrame(() => {
        const r = picker.getBoundingClientRect();
        if (r.right  > window.innerWidth)  picker.style.left = (x - r.width  - 6) + 'px';
        if (r.bottom > window.innerHeight) picker.style.top  = (y - r.height - 6) + 'px';
    });
}

function pickerSelect(caboId, action, event) {
    event.stopPropagation();
    const e = _pickerEvent;
    closeCaboPicker();
    const cabo = ELEMENTS.cabos.find(c => c.id == caboId);
    if (!cabo) return;
    if (action === 'info') showInfo('cabo', cabo);
    else if (action === 'ctx') showCtxCabo(e, cabo);
}

function showCtxCabo(e, cabo) {
    L.DomEvent.stopPropagation(e);
    if (drawingCabo || moveMode) return;
    const pts = typeof cabo.pontos === 'string' ? JSON.parse(cabo.pontos) : (cabo.pontos || []);
    const segIdx = findNearestSeg(pts, e.latlng);
    _ctxCabo = { id: cabo.id, codigo: cabo.codigo, pts, clickLatLng: e.latlng, segIdx };
    document.getElementById('ctx-cabo-info').textContent = `Cabo: ${cabo.codigo} · ${cabo.num_fibras}FO`;
    ctxEl.style.display = 'block';
    ctxEl.style.left = (e.originalEvent.clientX + 4) + 'px';
    ctxEl.style.top  = (e.originalEvent.clientY + 4) + 'px';
}

function ctxRemoverCabo() {
    const cabo = _ctxCabo;
    closeCtxCabo();
    if (!cabo) return;
    deleteElement('cabo', cabo.id, cabo.codigo);
}

function iniciarRompimento() {
    const cabo = _ctxCabo;
    closeCtxCabo();
    if (!cabo) return;
    _rompimentoData = cabo;  // persiste independente do menu
    document.getElementById('romper-cabo-info').innerHTML =
        `<strong>Cabo:</strong> ${cabo.codigo} &nbsp;·&nbsp; <strong>Ponto de corte:</strong> entre segmentos ${cabo.segIdx + 1} e ${cabo.segIdx + 2} de ${cabo.pts.length}`;
    document.getElementById('romper-novo-codigo').value = cabo.codigo + 'B';
    openModal('modal-romper');
}

// ── Adicionar ponto intermediário ao cabo ──────────────────────────────────────
async function ctxAdicionarPonto() {
    const cabo = _ctxCabo;
    closeCtxCabo();
    if (!cabo) return;
    const { id, pts, segIdx, clickLatLng } = cabo;
    // Insert point after segIdx
    const res = await fetch(`${BASE_URL}/api/elements.php?type=add_cabo_pt`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cabo_id: id, after_seq: segIdx, lat: clickLatLng.lat, lng: clickLatLng.lng })
    });
    const result = await res.json();
    if (!result.success) { showToast(result.error || 'Erro ao adicionar ponto.', 'error'); return; }
    // Update local cable data
    const idx = ELEMENTS.cabos.findIndex(c => c.id === id);
    if (idx >= 0) {
        ELEMENTS.cabos[idx].pontos = result.pontos.map(p => ({ lat: parseFloat(p.lat), lng: parseFloat(p.lng), et: p.elemento_tipo || null, eid: p.elemento_id || null }));
    }
    renderAll();
    if (!moveMode) toggleMoveMode();
    showToast('Ponto adicionado — arraste-o para a posição correta.', 'success');
}

// ── Continuar desenhando cabo a partir do último ponto ────────────────────────
function continuarDesenhoCabo() {
    const cabo = _ctxCabo;
    closeCtxCabo();
    if (!cabo) return;
    const pts = typeof cabo.pts === 'string' ? JSON.parse(cabo.pts) : (cabo.pts || []);
    if (pts.length < 1) { showToast('Cabo sem pontos.', 'warning'); return; }

    _continuandoCabo = { caboId: cabo.id, codigo: cabo.codigo, existingPontos: pts };
    drawingCabo = true;
    caboPoints = []; // new points only (will be appended)
    tempMarkers = [];
    setTool('cabo');

    // Start visually from last existing point
    const lastPt = pts[pts.length - 1];
    const startLatLng = [lastPt.lat, lastPt.lng];
    // Draw a temporary dotted line showing existing cable
    if (caboPolyline) map.removeLayer(caboPolyline);
    caboPolyline = L.polyline([startLatLng], { color: '#00b4ff', weight: 3, opacity: 0.9, dashArray: '8,4' }).addTo(map);

    // Add anchor point as first new point so the line connects
    caboPoints.push({ lat: lastPt.lat, lng: lastPt.lng, et: lastPt.et || null, eid: lastPt.eid || null });

    showFloatBar(`
        <i class="fas fa-pencil-alt" style="color:#00cc66"></i>
        <span>Continuando cabo <strong>${cabo.codigo}</strong> — clique para adicionar pontos</span>
        <span id="cabo-pts-bar" style="color:#00b4ff;font-weight:700">0 pts | 0 m</span>
        <button class="btn btn-sm btn-secondary" onclick="undoLastPoint()" style="margin-left:4px"><i class="fas fa-undo"></i></button>
        <button class="btn btn-sm" style="background:#00cc66;color:#fff;margin-left:4px" onclick="finalizarContinuacaoCabo()" id="btn-finalizar-cabo" disabled>
            <i class="fas fa-check"></i> Finalizar
        </button>
        <button class="btn btn-sm btn-danger" onclick="cancelarContinuacaoCabo()" style="margin-left:4px">
            <i class="fas fa-times"></i>
        </button>
    `);
    map.panTo(startLatLng);
    showToast(`Continuando ${cabo.codigo} — clique para adicionar pontos. Duplo-clique para finalizar.`, 'info');
}

async function finalizarContinuacaoCabo() {
    if (!_continuandoCabo || caboPoints.length < 2) { showToast('Adicione ao menos 1 ponto novo.', 'warning'); return; }
    // Skip the first point (anchor) — it already exists in the cable
    const novos = caboPoints.slice(1);
    const res = await fetch(`${BASE_URL}/api/elements.php?type=extend_cabo`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cabo_id: _continuandoCabo.caboId, novos_pontos: novos })
    });
    const result = await res.json();
    const cont = _continuandoCabo;
    stopDrawCabo();
    hideFloatBar();
    _continuandoCabo = null;
    if (result.success) {
        const idx = ELEMENTS.cabos.findIndex(c => c.id === cont.caboId);
        if (idx >= 0) ELEMENTS.cabos[idx] = { ...ELEMENTS.cabos[idx], ...result.data };
        renderCabos();
        showToast(`Cabo ${cont.codigo} estendido com ${novos.length} ponto${novos.length > 1 ? 's' : ''}.`, 'success');
    } else {
        showToast(result.error || 'Erro ao estender cabo.', 'error');
    }
}

function cancelarContinuacaoCabo() {
    _continuandoCabo = null;
    stopDrawCabo();
    hideFloatBar();
    setTool('select');
}

// (continuation mode finalization is handled by finalizarTracadoCabo checking _continuandoCabo)

// ── Reserva Técnica ───────────────────────────────────────────────────────────
let _reservaCtx = null; // {cabo_id, cabo_codigo, lat, lng} — para nova reserva
let _reservaEdit = null; // reserva atual ao editar

function renderReservas() {
    layers.reservas.clearLayers();
    (ELEMENTS.reservas || []).forEach(r => {
        if (!r.lat || !r.lng) return;
        const cabo = ELEMENTS.cabos.find(c => c.id == r.cabo_id);
        const caboLabel = cabo ? cabo.codigo : `Cabo #${r.cabo_id}`;
        const metros = parseFloat(r.metros);
        const label = metros >= 1000 ? (metros/1000).toFixed(2)+' km' : Math.round(metros)+' m';

        // Ícone: círculo laranja com "R"
        const icon = L.divIcon({
            className: '',
            html: `<div style="width:28px;height:28px;border-radius:50%;background:rgba(255,170,0,.9);border:2px solid #cc8800;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#1a1a1a;box-shadow:0 2px 6px rgba(0,0,0,.4);cursor:pointer">R</div>`,
            iconSize: [28, 28],
            iconAnchor: [14, 14],
        });
        const m = L.marker([r.lat, r.lng], { icon })
            .bindTooltip(`Reserva: ${label}<br>${caboLabel}`, { permanent: false, direction: 'top' })
            .addTo(layers.reservas);
        m.on('click', () => abrirEditarReserva(r));
    });
}

function ctxAdicionarReserva() {
    const cabo = _ctxCabo; // salva antes de closeCtxCabo() zerar _ctxCabo
    closeCtxCabo();
    if (!cabo) return;
    _reservaCtx = { cabo_id: cabo.id, cabo_codigo: cabo.codigo, lat: cabo.clickLatLng.lat, lng: cabo.clickLatLng.lng };
    document.getElementById('reserva-cabo-info').textContent = `Cabo: ${cabo.codigo}`;
    document.getElementById('reserva-metros').value = '';
    document.getElementById('reserva-descricao').value = '';
    document.getElementById('modal-reserva').style.display = 'flex';
    setTimeout(() => document.getElementById('reserva-metros').focus(), 100);
}

async function salvarReserva() {
    if (!_reservaCtx) return;
    const metros = parseFloat(document.getElementById('reserva-metros').value);
    if (!metros || metros <= 0) { showToast('Informe a metragem.', 'warning'); return; }
    const desc = document.getElementById('reserva-descricao').value.trim();
    const btn = document.getElementById('reserva-salvar-btn');
    btn.disabled = true;
    try {
        const res = await fetch(`${BASE_URL}/api/elements.php?type=add_reserva`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cabo_id: _reservaCtx.cabo_id, lat: _reservaCtx.lat, lng: _reservaCtx.lng, metros, descricao: desc })
        });
        const d = await res.json();
        if (!d.success) { showToast(d.error || 'Erro ao salvar reserva.', 'error'); return; }
        // Update local data
        ELEMENTS.reservas.push(d.reserva);
        const ci = ELEMENTS.cabos.findIndex(c => c.id == _reservaCtx.cabo_id);
        if (ci >= 0) ELEMENTS.cabos[ci].comprimento_m = d.comprimento_m;
        closeModal('modal-reserva');
        renderReservas();
        renderCabos(); // update info panel if open
        showToast(`Reserva de ${metros} m adicionada ao cabo ${_reservaCtx.cabo_codigo}.`, 'success');
        _reservaCtx = null;
    } catch(e) {
        showToast('Erro de comunicação.', 'error');
    } finally {
        btn.disabled = false;
    }
}

function abrirEditarReserva(r) {
    _reservaEdit = r;
    const cabo = ELEMENTS.cabos.find(c => c.id == r.cabo_id);
    const caboLabel = cabo ? cabo.codigo : `Cabo #${r.cabo_id}`;
    document.getElementById('reserva-edit-info').textContent = `Cabo: ${caboLabel}`;
    document.getElementById('reserva-edit-metros').value = r.metros;
    document.getElementById('reserva-edit-descricao').value = r.descricao || '';
    document.getElementById('modal-reserva-edit').style.display = 'flex';
    setTimeout(() => document.getElementById('reserva-edit-metros').focus(), 100);
}

async function atualizarReserva() {
    if (!_reservaEdit) return;
    const metros = parseFloat(document.getElementById('reserva-edit-metros').value);
    if (!metros || metros <= 0) { showToast('Informe a metragem.', 'warning'); return; }
    const desc = document.getElementById('reserva-edit-descricao').value.trim();
    try {
        const res = await fetch(`${BASE_URL}/api/elements.php?type=update_reserva`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ reserva_id: _reservaEdit.id, metros, descricao: desc })
        });
        const d = await res.json();
        if (!d.success) { showToast(d.error || 'Erro ao atualizar reserva.', 'error'); return; }
        const ri = ELEMENTS.reservas.findIndex(r => r.id == _reservaEdit.id);
        if (ri >= 0) ELEMENTS.reservas[ri] = d.reserva;
        const ci = ELEMENTS.cabos.findIndex(c => c.id == _reservaEdit.cabo_id);
        if (ci >= 0) ELEMENTS.cabos[ci].comprimento_m = d.comprimento_m;
        closeModal('modal-reserva-edit');
        renderReservas();
        renderCabos();
        showToast(`Reserva atualizada para ${metros} m.`, 'success');
        _reservaEdit = null;
    } catch(e) {
        showToast('Erro de comunicação.', 'error');
    }
}

async function removerReserva() {
    if (!_reservaEdit) return;
    if (!confirm(`Remover reserva de ${_reservaEdit.metros} m? O comprimento do cabo será recalculado.`)) return;
    try {
        const res = await fetch(`${BASE_URL}/api/elements.php?type=delete_reserva`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ reserva_id: _reservaEdit.id })
        });
        const d = await res.json();
        if (!d.success) { showToast(d.error || 'Erro ao remover reserva.', 'error'); return; }
        ELEMENTS.reservas = ELEMENTS.reservas.filter(r => r.id != _reservaEdit.id);
        const ci = ELEMENTS.cabos.findIndex(c => c.id == _reservaEdit.cabo_id);
        if (ci >= 0) ELEMENTS.cabos[ci].comprimento_m = d.comprimento_m;
        closeModal('modal-reserva-edit');
        renderReservas();
        renderCabos();
        showToast('Reserva removida.', 'success');
        _reservaEdit = null;
    } catch(e) {
        showToast('Erro de comunicação.', 'error');
    }
}

// ── Helpers de sinal e distância ──────────────────────────────────────────────
function haversineM(lat1, lng1, lat2, lng2) {
    const R = 6371000;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLng/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

// ── Rota da Fibra (mapa de rede) ──────────────────────────────────────────────
function sinalCorNet(dbm) {
    if (dbm === null) return '#888';
    if (dbm >= -20) return '#00cc66';
    if (dbm >= -24) return '#66dd44';
    if (dbm >= -27) return '#ffcc00';
    if (dbm >= -30) return '#ff8800';
    return '#ff4455';
}

function renderRotaDiagramNet(rota, sinalFinal, aviso) {
    function esc(s) { const d = document.createElement('div'); d.textContent = String(s||''); return d.innerHTML; }
    function r3(v) { return Math.round(v * 1000) / 1000; }
    function fmtM(m) { return m >= 1000 ? (m/1000).toFixed(2)+' km' : Math.round(m)+' m'; }
    function sigPct(dbm) { return Math.max(0,Math.min(100,((dbm+30)/30)*100)); }
    function sigQ(dbm) {
        if (dbm >= -15) return {lbl:'Excelente',c:'#00ff88'};
        if (dbm >= -20) return {lbl:'Bom',c:'#88ff00'};
        if (dbm >= -25) return {lbl:'Aceitável',c:'#ffcc00'};
        if (dbm >= -30) return {lbl:'Crítico',c:'#ff8800'};
        return {lbl:'Ruim',c:'#ff4444'};
    }
    function sigBar(dbm, w) {
        w = w||'100%'; const q = sigQ(dbm); const p = sigPct(dbm);
        return '<div style="width:'+w+';height:3px;background:rgba(255,255,255,.08);border-radius:2px;overflow:hidden;margin-top:4px"><div style="width:'+p+'%;height:100%;background:'+q.c+'"></div></div>';
    }
    function sigChip(dbm) {
        const q = sigQ(dbm);
        return '<div style="text-align:right"><div style="font-size:15px;font-weight:800;color:'+q.c+';line-height:1">'+(dbm>=0?'+':'')+dbm.toFixed(2)+'</div><div style="font-size:8px;color:'+q.c+'99;margin-top:1px">dBm</div></div>';
    }
    function vline(color, h) {
        color = color||'rgba(51,153,255,.35)'; h = h||16;
        return '<div style="display:flex;justify-content:center;height:'+h+'px"><div style="width:2px;height:100%;background:'+color+'"></div></div>';
    }

    if (!rota || !rota.length) return '<div style="padding:32px;text-align:center;color:#555"><i class="fas fa-unlink" style="font-size:32px;margin-bottom:12px;display:block"></i><div style="font-size:13px">Fibra não conectada à OLT — verifique as fusões</div></div>';

    let oltNode = null;
    const segments = [];
    let i = 0;
    if (rota[0] && rota[0].t === 'olt') { oltNode = rota[0]; i = 1; }
    while (i < rota.length) {
        if (rota[i].t === 'cabo') {
            const cable = rota[i++];
            const events = [];
            while (i < rota.length && (rota[i].t === 'splice' || rota[i].t === 'splitter')) events.push(rota[i++]);
            segments.push({ cable, events });
        } else { i++; }
    }

    let totalDist = 0;
    segments.forEach(s => totalDist += s.cable.comprimento_m || 0);
    const sinalInicio = oltNode ? oltNode.potencia_dbm : null;
    const totalLoss = sinalInicio != null && sinalFinal != null ? r3(sinalInicio - sinalFinal) : null;

    let sinalAcum = sinalInicio;
    let html = '<div style="display:flex;flex-direction:column;width:100%;max-width:500px;margin:0 auto;font-size:13px">';

    // Resumo
    html += '<div style="border-radius:10px;padding:8px 14px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);margin-bottom:14px;display:flex;gap:16px;flex-wrap:wrap;align-items:center">';
    html += '<span style="font-size:10px;color:#777"><i class="fas fa-route" style="margin-right:4px;color:#555"></i>Distância <strong style="color:#bbb">'+fmtM(totalDist)+'</strong></span>';
    if (totalLoss != null) html += '<span style="font-size:10px;color:#777"><i class="fas fa-arrow-down" style="margin-right:4px;color:#ff8800"></i>Perda total <strong style="color:#ff8800">−'+totalLoss.toFixed(2)+' dBm</strong></span>';
    html += '<span style="font-size:10px;color:#777"><i class="fas fa-box" style="margin-right:4px;color:#555"></i>Caixas <strong style="color:#bbb">'+segments.filter(s=>s.events.length).length+'</strong></span>';
    html += '</div>';

    // OLT
    if (oltNode) {
        html += '<div style="border-radius:12px;padding:14px 16px;background:rgba(0,173,255,.06);border:1.5px solid rgba(0,173,255,.25);overflow:hidden;position:relative">';
        html += '<div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(0,173,255,.07) 0%,transparent 60%);pointer-events:none"></div>';
        html += '<div style="display:flex;align-items:center;gap:12px;position:relative">';
        html += '<div style="width:40px;height:40px;border-radius:10px;background:rgba(0,173,255,.15);display:flex;align-items:center;justify-content:center;color:#00adff;font-size:17px;flex-shrink:0"><i class="fas fa-server"></i></div>';
        html += '<div style="flex:1;min-width:0"><div style="font-size:8px;color:#00adff;text-transform:uppercase;letter-spacing:1.2px;font-weight:700">OLT — Origem</div><div style="font-size:14px;font-weight:700;margin-top:1px">'+esc(oltNode.nome)+'</div><div style="font-size:11px;color:#555;margin-top:2px">'+esc(String(oltNode.pon))+'</div></div>';
        html += '<div style="flex-shrink:0">'+sigChip(oltNode.potencia_dbm)+'<div style="font-size:8px;color:#444;text-align:right;margin-top:2px">TX</div>'+sigBar(oltNode.potencia_dbm,'70px')+'</div>';
        html += '</div></div>';
    }

    // Segmentos
    for (const seg of segments) {
        const cab = seg.cable;
        const sinalDepois = sinalAcum != null ? r3(sinalAcum - cab.perda_cabo) : null;
        const corSai = sinalDepois != null ? sigQ(sinalDepois).c : '#555';

        // Linha + Cabo (pílula centrada)
        html += vline('rgba(51,153,255,.4)',12);
        html += '<div style="border:1px solid rgba(51,153,255,.22);border-radius:10px;padding:8px 14px;background:rgba(51,153,255,.04)">';
        html += '<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">';
        html += '<div style="display:flex;align-items:center;gap:6px"><i class="fas fa-grip-lines-vertical" style="color:#3399ff;font-size:11px"></i><span style="font-size:12px;font-weight:700;color:#3399ff">'+esc(cab.codigo)+'</span><span style="font-size:10px;color:#444">·</span><span style="font-size:10px;color:#555">Fibra '+cab.fibra_num+'</span><span style="font-size:10px;color:#444">·</span><span style="font-size:11px;font-weight:600;color:#888">'+fmtM(cab.comprimento_m)+'</span></div>';
        html += '<div style="font-size:10px;color:#444;display:flex;align-items:center;gap:5px"><span>−'+cab.perda_cabo.toFixed(4)+' dBm</span>';
        if (sinalDepois != null) html += '<span style="color:#333">→</span><span style="font-weight:700;color:'+corSai+'">'+(sinalDepois>=0?'+':'')+sinalDepois.toFixed(2)+' dBm</span>';
        html += '</div></div></div>';
        sinalAcum = sinalDepois;

        if (seg.events.length === 0) continue;

        // Linha + Caixa CEO/CTO
        const ev0 = seg.events[0];
        const eTipo = (ev0.elem_tipo || '').toUpperCase();
        const eCod = ev0.elem_cod || '';
        const isCTO = eTipo === 'CTO';
        const bBdr = isCTO ? 'rgba(0,204,102,.28)' : 'rgba(153,51,255,.28)';
        const bBg  = isCTO ? 'rgba(0,204,102,.04)' : 'rgba(153,51,255,.04)';
        const bClr = isCTO ? '#00cc66' : '#9933ff';
        const bIco = isCTO ? '<i class="fas fa-network-wired"></i>' : '<i class="fas fa-box-open"></i>';

        html += vline(bClr+'55', 12);
        html += '<div style="border:1.5px solid '+bBdr+';border-radius:12px;background:'+bBg+';overflow:hidden">';
        html += '<div style="padding:9px 14px;border-bottom:1px solid '+bBdr+';display:flex;align-items:center;gap:9px">';
        html += '<div style="width:30px;height:30px;border-radius:8px;background:rgba(128,128,128,.08);display:flex;align-items:center;justify-content:center;color:'+bClr+';font-size:13px;flex-shrink:0">'+bIco+'</div>';
        html += '<div><div style="font-size:8px;color:'+bClr+';text-transform:uppercase;letter-spacing:1px;font-weight:700">'+eTipo+'</div><div style="font-size:13px;font-weight:700">'+esc(eCod)+'</div></div>';
        html += '</div>';
        html += '<div style="padding:8px 10px;display:flex;flex-direction:column;gap:5px">';

        let evHtml = '';
        for (const ev of seg.events) {
            if (ev.t === 'splice') {
                const perda = ev.perda_db != null ? ev.perda_db : 0;
                sinalAcum = sinalAcum != null ? r3(sinalAcum - perda) : null;
                const tipoLbl = ev.tipo === 'passante' ? 'Passante' : 'Emenda';
                evHtml += '<div style="display:flex;align-items:center;gap:10px;padding:7px 10px;border-radius:8px;background:rgba(255,136,0,.06);border:1px solid rgba(255,136,0,.14)">';
                evHtml += '<div style="width:26px;height:26px;border-radius:7px;background:rgba(255,136,0,.1);display:flex;align-items:center;justify-content:center;color:#ff8800;flex-shrink:0;font-size:11px"><i class="fas fa-compress-alt"></i></div>';
                evHtml += '<div style="flex:1;min-width:0"><div style="font-size:11px;font-weight:700;color:#ff8800">'+tipoLbl+'</div><div style="font-size:10px;color:#555;margin-top:1px">Perda: −'+perda+' dBm</div></div>';
                if (sinalAcum != null) evHtml += '<div>'+sigChip(sinalAcum)+sigBar(sinalAcum,'56px')+'</div>';
                evHtml += '</div>';
            } else if (ev.t === 'splitter') {
                const connLoss = ev.perda_emenda != null ? ev.perda_emenda : 0;
                sinalAcum = sinalAcum != null ? r3(sinalAcum - connLoss) : null;
                const splLoss = ev.perda_db;
                sinalAcum = sinalAcum != null && splLoss != null ? r3(sinalAcum - splLoss) : null;
                const porta = ev.porta ? parseInt(ev.porta.slice(1)) + 1 : null;
                evHtml += '<div style="display:flex;align-items:center;gap:10px;padding:7px 10px;border-radius:8px;background:rgba(255,204,0,.05);border:1px solid rgba(255,204,0,.16)">';
                evHtml += '<div style="font-size:20px;color:#ffcc00;flex-shrink:0;width:26px;text-align:center;line-height:1">▽</div>';
                evHtml += '<div style="flex:1;min-width:0"><div style="font-size:11px;font-weight:700;color:#ffcc00">'+esc(ev.codigo||'')+' '+esc(ev.relacao||'')+(porta?' · Saída '+porta:'')+'</div>';
                evHtml += '<div style="font-size:10px;color:#555;margin-top:1px">'+(connLoss?'Conector −'+connLoss+' · ':'')+'Divisão '+(splLoss!=null?'−'+splLoss:'?')+' dBm</div></div>';
                if (sinalAcum != null) evHtml += '<div>'+sigChip(sinalAcum)+sigBar(sinalAcum,'56px')+'</div>';
                evHtml += '</div>';
            }
        }
        html += evHtml + '</div></div>';
    }

    // Linha + Ponto Final
    html += vline('rgba(0,204,102,.4)', 16);
    const qFinal = sinalFinal != null ? sigQ(sinalFinal) : null;
    html += '<div style="border:1.5px solid rgba(0,204,102,.25);border-radius:12px;padding:14px 16px;background:rgba(0,204,102,.04);overflow:hidden;position:relative">';
    html += '<div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(0,204,102,.05) 0%,transparent 60%);pointer-events:none"></div>';
    html += '<div style="display:flex;align-items:center;gap:12px;position:relative">';
    html += '<div style="width:40px;height:40px;border-radius:10px;background:rgba(0,204,102,.12);display:flex;align-items:center;justify-content:center;color:#00cc66;font-size:17px;flex-shrink:0"><i class="fas fa-map-marker-alt"></i></div>';
    html += '<div style="flex:1;min-width:0"><div style="font-size:8px;color:#00cc66;text-transform:uppercase;letter-spacing:1.2px;font-weight:700">Ponto Final</div>';
    if (qFinal) html += '<div style="font-size:12px;color:'+qFinal.c+';font-weight:600;margin-top:3px">'+qFinal.lbl+'</div>';
    html += '</div><div style="flex-shrink:0;text-align:right">';
    if (sinalFinal != null) {
        html += '<div style="font-size:26px;font-weight:800;color:'+qFinal.c+';line-height:1">'+(sinalFinal>=0?'+':'')+sinalFinal.toFixed(2)+'</div>';
        html += '<div style="font-size:9px;color:#555;margin-top:1px">dBm</div>';
        html += sigBar(sinalFinal,'80px');
    } else { html += '<span style="font-size:20px;color:#555">—</span>'; }
    html += '</div></div>';
    if (aviso) html += '<div style="margin-top:10px;padding:7px 10px;border-radius:7px;background:rgba(255,136,0,.1);border:1px solid rgba(255,136,0,.18);font-size:11px;color:#ff8800;display:flex;align-items:center;gap:6px"><i class="fas fa-exclamation-triangle"></i>'+esc(aviso)+'</div>';
    html += '</div></div>';
    return html;
}

async function confirmarRompimento() {
    const novoCodigo = document.getElementById('romper-novo-codigo').value.trim();
    if (!novoCodigo) { showToast('Informe o código do novo cabo.', 'warning'); return; }
    if (!_rompimentoData) { showToast('Dados do rompimento perdidos, tente novamente.', 'error'); return; }
    const cabo = _rompimentoData;

    const res = await fetch(`${BASE_URL}/api/elements.php?type=romper_cabo`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            cabo_id:     cabo.id,
            seg_idx:     cabo.segIdx,
            break_lat:   cabo.clickLatLng.lat,
            break_lng:   cabo.clickLatLng.lng,
            novo_codigo: novoCodigo,
        })
    });
    const result = await res.json();
    closeModal('modal-romper');
    _rompimentoData = null;

    if (result.success) {
        // Update original cable data
        const idx = ELEMENTS.cabos.findIndex(c => c.id === result.cabo.id);
        if (idx >= 0) ELEMENTS.cabos[idx] = { ...ELEMENTS.cabos[idx], ...result.cabo };
        // Add new cable
        ELEMENTS.cabos.push(result.novo_cabo);
        renderCabos();
        buildSnapTargets();
        showToast(`Cabo rompido — ${result.cabo.codigo} e ${result.novo_cabo.codigo} criados.`, 'success');
    } else {
        showToast(result.error || 'Erro ao romper cabo.', 'error');
    }
}
