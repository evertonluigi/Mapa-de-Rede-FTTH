// FTTH Network Manager — Rack DIO Connection Map
// Extracted from modules/racks/fusao.php
// Globals: RACK_ID, PONS_DATA, DIOS_DATA, CABOS_DATA, CONEXOES_PON, CONEXOES_FIBRA,
//   BASE_URL, canvasWrap, canvas, svg, storageKey, selectedItem, cardPositions, cards,
//   OLT_COLORS, oltColorMap

// ── Fiber colors (ABNT) ───────────────────────────────────────────────────────
const FIBER_COLORS = ['#2E7D32','#F9A825','#EEEEEE','#1565C0','#C62828','#6A1B9A',
                      '#4E342E','#E91E63','#212121','#757575','#E65100','#00838F'];
function fiberHex(n) { return FIBER_COLORS[((n-1) % 12)]; }

// ── Fiber label: T1-F01 (igual mapa de fusão das caixas) ─────────────────────
function getFpt(cabo) {
    let cfg = null;
    try { cfg = cabo.config_cores ? JSON.parse(cabo.config_cores) : null; } catch(e) {}
    return (cfg?.fibras_por_tubo) || cabo.fibras_por_tubo || 12;
}
function fiberLabel(cabo, fn) {
    const fpt     = getFpt(cabo);
    const tubeIdx = Math.floor((fn - 1) / fpt) + 1;
    const fInTube = ((fn - 1) % fpt) + 1;
    return `T${tubeIdx}-F${String(fInTube).padStart(2, '0')}`;
}

// ── Connection indexes ────────────────────────────────────────────────────────
// connByPon[ponId]                → PON conexao
// connByFibra[caboId-fibraNum]    → fiber conexao
// connByDioPort[dioId-porta-lado] → any conexao (PON or fiber)
const connByPon     = {};
const connByFibra   = {};
const connByDioPort = {};

CONEXOES_PON.forEach(c => {
    connByPon[c.olt_pon_id] = c;
    connByDioPort[c.dio_id + '-' + c.dio_porta + '-' + (c.lado||'A')] = {type:'pon', ...c};
});
CONEXOES_FIBRA.forEach(c => {
    connByFibra[c.cabo_id + '-' + c.fibra_num] = c;
    connByDioPort[c.dio_id + '-' + c.dio_porta + '-' + (c.lado||'A')] = {type:'fibra', ...c};
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function savePos(cid, x, y) {
    cardPositions[cid] = {x, y};
    localStorage.setItem(storageKey, JSON.stringify(cardPositions));
}

// Dot center in canvas-coordinate space
function getDotCenter(dotEl) {
    const cr = canvas.getBoundingClientRect();
    const dr = dotEl.getBoundingClientRect();
    return {
        x: dr.left + dr.width  / 2 - cr.left,
        y: dr.top  + dr.height / 2 - cr.top,
    };
}

function buildPath(p1, p2) {
    const dx  = p2.x - p1.x;
    const cp  = Math.max(Math.abs(dx) * 0.48, 80);
    const sgn = dx >= 0 ? 1 : -1;
    if (Math.abs(p1.y - p2.y) < 3) return `M${p1.x},${p1.y} L${p2.x},${p2.y}`;
    return `M${p1.x},${p1.y} C${p1.x+sgn*cp},${p1.y} ${p2.x-sgn*cp},${p2.y} ${p2.x},${p2.y}`;
}

// ── Draw SVG lines ────────────────────────────────────────────────────────────
function makeLine(p1, p2, color, onCtxMenu) {
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', buildPath(p1, p2));
    path.setAttribute('class', 'conn-line');
    path.setAttribute('stroke', color);
    path.addEventListener('contextmenu', e => { e.preventDefault(); onCtxMenu(); });
    svg.appendChild(path);
}

function drawLines() {
    while (svg.firstChild) svg.removeChild(svg.firstChild);

    // PON connections
    CONEXOES_PON.forEach(c => {
        const lado   = c.lado || 'A';
        const ponDot = document.querySelector(`.fport-dot[data-pon="${c.olt_pon_id}"]`);
        const dioDot = document.querySelector(`.fport-dot[data-dio="${c.dio_id}"][data-porta="${c.dio_porta}"][data-lado="${lado}"]`);
        if (!ponDot || !dioDot) return;
        makeLine(getDotCenter(ponDot), getDotCenter(dioDot),
            oltColorMap[c.olt_id] || '#cc8800',
            () => { if (confirm(`Remover PON S${c.slot}/${c.numero_pon} → ${c.dio_codigo} P${c.dio_porta}?`)) disconnectByPon(c.olt_pon_id); }
        );
    });

    // Cable fiber connections
    CONEXOES_FIBRA.forEach(c => {
        const lado     = c.lado || 'A';
        const fibraDot = document.querySelector(`.fport-dot[data-cabo="${c.cabo_id}"][data-fibra="${c.fibra_num}"]`);
        const dioDot   = document.querySelector(`.fport-dot[data-dio="${c.dio_id}"][data-porta="${c.dio_porta}"][data-lado="${lado}"]`);
        if (!fibraDot || !dioDot) return;
        const caboData2 = CABOS_DATA.find(cb => cb.id == c.cabo_id);
        const flbl2 = caboData2 ? fiberLabel(caboData2, c.fibra_num) : `F${c.fibra_num}`;
        makeLine(getDotCenter(fibraDot), getDotCenter(dioDot),
            fiberHex(c.fibra_num),
            () => { if (confirm(`Remover ${flbl2} (${c.cabo_codigo}) → ${c.dio_codigo} P${c.dio_porta}?`)) disconnectByFibra(c.cabo_id, c.fibra_num); }
        );
    });
}

// ── Draggable cards ───────────────────────────────────────────────────────────
function makeDraggable(el, header) {
    let ox, oy;
    header.addEventListener('mousedown', e => {
        if (e.button !== 0) return;
        e.preventDefault();
        const rect = el.getBoundingClientRect();
        ox = e.clientX - rect.left;
        oy = e.clientY - rect.top;
        function onMove(ev) {
            const cr = canvas.getBoundingClientRect();
            let x = ev.clientX - cr.left - ox;
            let y = ev.clientY - cr.top  - oy;
            x = Math.max(0, x);
            y = Math.max(0, y);
            el.style.left = x + 'px';
            el.style.top  = y + 'px';
            savePos(el.dataset.cardid, x, y);
            drawLines();
        }
        function onUp() {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
            const cid = el.dataset.cardid || '';
            if (cid.startsWith('olt-') || cid.startsWith('cabo-'))
                updateCardOrientations();
            fitCanvas();
        }
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    });
}

// ── Select / Deselect item ────────────────────────────────────────────────────
function selectItem(item, dotEl) {
    document.querySelectorAll('.fport-dot.selected').forEach(d => d.classList.remove('selected'));
    if (selectedItem && selectedItem.type === item.type &&
        JSON.stringify(selectedItem) === JSON.stringify({...item, dotEl: selectedItem.dotEl})) {
        selectedItem = null;
        return;
    }
    selectedItem = {...item, dotEl};
    dotEl.classList.add('selected');
}

// ── Connect to DIO side ───────────────────────────────────────────────────────
function connectToDio(dioId, porta, lado) {
    if (!selectedItem) return;
    const si = selectedItem;

    // Build POST body
    let body = `action=connect&dio_id=${dioId}&dio_porta=${porta}&lado=${lado}`;
    if (si.type === 'pon') body += `&tipo=pon&pon_id=${si.id}`;
    else                   body += `&tipo=fibra&cabo_id=${si.caboId}&fibra_num=${si.fibraNum}`;

    fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
    .then(r => r.json())
    .then(res => {
        if (!res.ok) return;
        const dioKey = dioId + '-' + porta + '-' + lado;

        // Remove old connection occupying this DIO slot
        const oldDio = connByDioPort[dioKey];
        if (oldDio) {
            if (oldDio.type === 'pon') {
                const idx = CONEXOES_PON.findIndex(c => c.olt_pon_id == oldDio.olt_pon_id);
                if (idx >= 0) CONEXOES_PON.splice(idx, 1);
                delete connByPon[oldDio.olt_pon_id];
                dotClass(`.fport-dot[data-pon="${oldDio.olt_pon_id}"]`, 'connected', false);
            } else {
                const idx = CONEXOES_FIBRA.findIndex(c => c.cabo_id == oldDio.cabo_id && c.fibra_num == oldDio.fibra_num);
                if (idx >= 0) CONEXOES_FIBRA.splice(idx, 1);
                delete connByFibra[oldDio.cabo_id + '-' + oldDio.fibra_num];
                dotClass(`.fport-dot[data-cabo="${oldDio.cabo_id}"][data-fibra="${oldDio.fibra_num}"]`, 'connected', false);
            }
            delete connByDioPort[dioKey];
        }

        const dioData = DIOS_DATA.find(d => d.id == dioId);
        const dioCode = dioData ? dioData.codigo : '';

        if (si.type === 'pon') {
            // Remove old connection for this PON
            const oldPon = connByPon[si.id];
            if (oldPon) {
                const k2 = oldPon.dio_id + '-' + oldPon.dio_porta + '-' + (oldPon.lado||'A');
                delete connByDioPort[k2];
                dotClass(`.fport-dot[data-dio="${oldPon.dio_id}"][data-porta="${oldPon.dio_porta}"][data-lado="${oldPon.lado||'A'}"]`, 'connected', false);
                const idx = CONEXOES_PON.findIndex(c => c.olt_pon_id == si.id);
                if (idx >= 0) CONEXOES_PON.splice(idx, 1);
            }
            const ponData = PONS_DATA.find(p => p.id == si.id);
            const entry = {olt_pon_id: si.id, dio_id: dioId, dio_porta: porta, lado,
                olt_id: ponData?.olt_id||0, slot: ponData?.slot||0, numero_pon: ponData?.numero_pon||0,
                olt_codigo: ponData?.olt_codigo||'', dio_codigo: dioCode};
            CONEXOES_PON.push(entry);
            connByPon[si.id] = entry;
            connByDioPort[dioKey] = {type:'pon', ...entry};
            dotClass(`.fport-dot[data-pon="${si.id}"]`, 'connected', true);
        } else {
            // Remove old connection for this fiber
            const fKey = si.caboId + '-' + si.fibraNum;
            const oldFibra = connByFibra[fKey];
            if (oldFibra) {
                const k2 = oldFibra.dio_id + '-' + oldFibra.dio_porta + '-' + (oldFibra.lado||'A');
                delete connByDioPort[k2];
                dotClass(`.fport-dot[data-dio="${oldFibra.dio_id}"][data-porta="${oldFibra.dio_porta}"][data-lado="${oldFibra.lado||'A'}"]`, 'connected', false);
                const idx = CONEXOES_FIBRA.findIndex(c => c.cabo_id == si.caboId && c.fibra_num == si.fibraNum);
                if (idx >= 0) CONEXOES_FIBRA.splice(idx, 1);
            }
            const caboData = CABOS_DATA.find(c => c.id == si.caboId);
            const entry = {cabo_id: si.caboId, fibra_num: si.fibraNum, dio_id: dioId, dio_porta: porta, lado,
                cabo_codigo: caboData?.codigo||'', dio_codigo: dioCode};
            CONEXOES_FIBRA.push(entry);
            connByFibra[fKey] = entry;
            connByDioPort[dioKey] = {type:'fibra', ...entry};
            dotClass(`.fport-dot[data-cabo="${si.caboId}"][data-fibra="${si.fibraNum}"]`, 'connected', true);
        }

        document.querySelectorAll('.fport-dot.selected').forEach(d => d.classList.remove('selected'));
        dotClass(`.fport-dot[data-dio="${dioId}"][data-porta="${porta}"][data-lado="${lado}"]`, 'connected', true);
        selectedItem = null;
        drawLines();
    });
}

function dotClass(sel, cls, add) {
    const el = document.querySelector(sel);
    if (el) el.classList[add ? 'add' : 'remove'](cls);
}

// ── Disconnect helpers ────────────────────────────────────────────────────────
function disconnectByPon(ponId) {
    fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=disconnect&pon_id=${ponId}` })
    .then(r => r.json()).then(res => {
        if (!res.ok) return;
        const idx = CONEXOES_PON.findIndex(c => c.olt_pon_id == ponId);
        if (idx >= 0) {
            const c = CONEXOES_PON[idx];
            delete connByDioPort[c.dio_id + '-' + c.dio_porta + '-' + (c.lado||'A')];
            dotClass(`.fport-dot[data-dio="${c.dio_id}"][data-porta="${c.dio_porta}"][data-lado="${c.lado||'A'}"]`, 'connected', false);
            CONEXOES_PON.splice(idx, 1);
        }
        delete connByPon[ponId];
        dotClass(`.fport-dot[data-pon="${ponId}"]`, 'connected', false);
        drawLines();
    });
}

function disconnectByFibra(caboId, fibraNum) {
    fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=disconnect&cabo_id=${caboId}&fibra_num=${fibraNum}` })
    .then(r => r.json()).then(res => {
        if (!res.ok) return;
        const key = caboId + '-' + fibraNum;
        const idx = CONEXOES_FIBRA.findIndex(c => c.cabo_id == caboId && c.fibra_num == fibraNum);
        if (idx >= 0) {
            const c = CONEXOES_FIBRA[idx];
            delete connByDioPort[c.dio_id + '-' + c.dio_porta + '-' + (c.lado||'A')];
            dotClass(`.fport-dot[data-dio="${c.dio_id}"][data-porta="${c.dio_porta}"][data-lado="${c.lado||'A'}"]`, 'connected', false);
            CONEXOES_FIBRA.splice(idx, 1);
        }
        delete connByFibra[key];
        dotClass(`.fport-dot[data-cabo="${caboId}"][data-fibra="${fibraNum}"]`, 'connected', false);
        drawLines();
    });
}

function disconnectByDioSide(dioId, porta, lado) {
    const key  = dioId + '-' + porta + '-' + lado;
    const conn = connByDioPort[key];
    if (!conn) return;
    if (conn.type === 'pon') { disconnectByPon(conn.olt_pon_id); return; }
    disconnectByFibra(conn.cabo_id, conn.fibra_num);
}

// ── Dot title helpers ─────────────────────────────────────────────────────────
function updatePonDotTitle(dotEl, ponId) {
    const c = connByPon[ponId];
    if (!c) { dotEl.title = 'Clique para selecionar'; return; }
    dotEl.title = `Conectado: ${c.dio_codigo} P${c.dio_porta} — clique p/ desconectar`;
}

function updateDioDotTitle(dotEl, dioId, porta, lado) {
    const c = connByDioPort[dioId + '-' + porta + '-' + lado];
    if (!c) { dotEl.title = `Livre (lado ${lado})`; return; }
    if (c.type === 'pon') dotEl.title = `PON S${c.slot}/${c.numero_pon} (${c.olt_codigo}) — clique p/ desconectar`;
    else                  dotEl.title = `F${c.fibra_num} (${c.cabo_codigo}) — clique p/ desconectar`;
}

// ── Build OLT cards ───────────────────────────────────────────────────────────
function buildOltCards() {
    const groups = {};
    PONS_DATA.forEach(p => {
        if (!groups[p.olt_id]) groups[p.olt_id] = {nome: p.olt_nome, codigo: p.olt_codigo, pons: []};
        groups[p.olt_id].pons.push(p);
    });

    Object.entries(groups).forEach(([oltId, g], idx) => {
        const cid   = 'olt-' + oltId;
        const color = OLT_COLORS[idx % OLT_COLORS.length];
        const card  = document.createElement('div');
        card.className     = 'fcard';
        card.dataset.cardid = cid;
        card.innerHTML = `
            <div class="fcard-hdr">
                <i class="fas fa-server" style="color:${color};font-size:11px"></i>
                <div style="flex:1;min-width:0">
                    <div class="fcard-title" style="color:${color}">${esc(g.codigo)}</div>
                    <div class="fcard-sub">${esc(g.nome)}</div>
                </div>
                <span style="font-size:10px;color:#555">${g.pons.length} PON</span>
            </div>
            <div class="fcard-ports" id="ports-olt-${oltId}"></div>`;

        const portsEl = card.querySelector('#ports-olt-' + oltId);
        g.pons.forEach(pon => {
            const isConn = !!connByPon[pon.id];
            const row    = document.createElement('div');
            row.className = 'fport';
            row.style.gap = '4px';

            const dot = document.createElement('div');
            dot.className      = 'fport-dot' + (isConn ? ' connected' : '');
            dot.dataset.pon    = pon.id;
            updatePonDotTitle(dot, pon.id);

            // Port identifier — stays on the outer side
            const portLbl = document.createElement('span');
            portLbl.className = 'fport-info';
            portLbl.style.flex = '1';
            portLbl.textContent = `S${pon.slot} / PON ${pon.numero_pon}`;
            if (pon.descricao) portLbl.title = pon.descricao;

            // Connection description — always stays adjacent to the dot
            const connDesc = document.createElement('span');
            connDesc.style.cssText = 'font-size:9px;color:#00cc66;white-space:nowrap;';
            const ponConn = connByPon[pon.id];
            if (ponConn) connDesc.textContent = `→${ponConn.dio_codigo}`;

            // DOM order [portLbl][connDesc][dot]: row-reverse flips to [dot][connDesc][portLbl]
            // keeping connDesc always next to dot regardless of orientation
            row.appendChild(portLbl);
            row.appendChild(connDesc);
            row.appendChild(dot);

            dot.addEventListener('click', () => {
                if (dot.classList.contains('connected')) {
                    if (confirm('Desconectar esta PON?')) disconnectByPon(pon.id);
                    return;
                }
                selectItem({type:'pon', id: pon.id}, dot);
            });

            portsEl.appendChild(row);
        });

        const pos = cardPositions[cid];
        card.style.left = (pos ? pos.x : 20) + 'px';
        card.style.top  = (pos ? pos.y : 20 + idx * 260) + 'px';
        makeDraggable(card, card.querySelector('.fcard-hdr'));
        canvas.appendChild(card);
        cards[cid] = card;
    });
}

// ── Build DIO cards ───────────────────────────────────────────────────────────
function buildDioCards() {
    DIOS_DATA.forEach((dio, idx) => {
        const cid  = 'dio-' + dio.id;
        const card = document.createElement('div');
        card.className      = 'fcard';
        card.dataset.cardid = cid;

        const uLabel = dio.posicao_u ? `U${dio.posicao_u} · ` : '';
        card.innerHTML = `
            <div class="fcard-hdr">
                <i class="fas fa-th-large" style="color:#aa6600;font-size:11px"></i>
                <div style="flex:1;min-width:0">
                    <div class="fcard-title" style="color:#cc8800">${esc(dio.codigo)}</div>
                    <div class="fcard-sub">${uLabel}${esc(dio.tipo_conector||'')} · ${dio.capacidade_portas}p</div>
                </div>
            </div>
            <div style="display:flex;align-items:center;padding:3px 10px 2px;font-size:9px;color:#555;text-transform:uppercase;letter-spacing:.5px;gap:4px">
                <div style="width:14px;flex-shrink:0"></div>
                <div style="flex:1;text-align:right">A (int)</div>
                <div style="width:24px;flex-shrink:0;text-align:center">P</div>
                <div style="flex:1;text-align:left">B (ext)</div>
                <div style="width:1px;flex-shrink:0;margin:0 2px"></div>
                <div style="width:14px;flex-shrink:0"></div>
            </div>
            <div class="fcard-ports" id="ports-dio-${dio.id}"></div>`;

        const portsEl = card.querySelector('#ports-dio-' + dio.id);

        for (let porta = 1; porta <= dio.capacidade_portas; porta++) {
            const keyA = dio.id + '-' + porta + '-A';
            const keyB = dio.id + '-' + porta + '-B';
            const connA = connByDioPort[keyA];
            const connB = connByDioPort[keyB];

            const row = document.createElement('div');
            row.className = 'fport';
            row.style.gap = '4px';

            // ── Dot A (left / internal) ───────────────────────────────────────
            const dotA = document.createElement('div');
            dotA.className      = 'fport-dot' + (connA ? ' connected' : '');
            dotA.dataset.dio    = dio.id;
            dotA.dataset.porta  = porta;
            dotA.dataset.lado   = 'A';
            updateDioDotTitle(dotA, dio.id, porta, 'A');

            dotA.addEventListener('click', () => {
                if (selectedItem) { connectToDio(dio.id, porta, 'A'); return; }
                if (dotA.classList.contains('connected'))
                    if (confirm(`Desconectar lado A da porta ${porta}?`))
                        disconnectByDioSide(dio.id, porta, 'A');
            });

            // ── Center: [connA | portNum | connB] ─────────────────────────────
            function connLabel(c) {
                if (!c) return '';
                if (c.type === 'pon') return `<span style="font-size:9px;color:#00cc66;white-space:nowrap">S${c.slot}/P${c.numero_pon}</span>`;
                const caboData = CABOS_DATA.find(cb => cb.id == c.cabo_id);
                const flbl = caboData ? fiberLabel(caboData, c.fibra_num) : `F${c.fibra_num}`;
                return `<span style="font-size:9px;color:${fiberHex(c.fibra_num)};white-space:nowrap">${flbl}</span>`;
            }
            const lbl = document.createElement('div');
            lbl.style.cssText = 'flex:1;display:flex;align-items:center;gap:2px;overflow:hidden;min-width:0';

            const connADesc = document.createElement('div');
            connADesc.style.cssText = 'flex:1;text-align:right;overflow:hidden;min-width:0';
            connADesc.innerHTML = connLabel(connA);

            const portNum = document.createElement('strong');
            portNum.style.cssText = 'width:24px;flex-shrink:0;text-align:center;font-size:11px;color:var(--text)';
            portNum.textContent = porta;

            const connBDesc = document.createElement('div');
            connBDesc.style.cssText = 'flex:1;text-align:left;overflow:hidden;min-width:0';
            connBDesc.innerHTML = connLabel(connB);

            lbl.appendChild(connADesc);
            lbl.appendChild(portNum);
            lbl.appendChild(connBDesc);

            // ── Separator ─────────────────────────────────────────────────────
            const sep = document.createElement('div');
            sep.className = 'fport-sep';

            // ── Dot B (right / external) ──────────────────────────────────────
            const dotB = document.createElement('div');
            dotB.className      = 'fport-dot' + (connB ? ' connected' : '');
            dotB.dataset.dio    = dio.id;
            dotB.dataset.porta  = porta;
            dotB.dataset.lado   = 'B';
            updateDioDotTitle(dotB, dio.id, porta, 'B');

            dotB.addEventListener('click', () => {
                if (selectedItem) { connectToDio(dio.id, porta, 'B'); return; }
                if (dotB.classList.contains('connected'))
                    if (confirm(`Desconectar lado B da porta ${porta}?`))
                        disconnectByDioSide(dio.id, porta, 'B');
            });

            row.appendChild(dotA);
            row.appendChild(lbl);
            row.appendChild(sep);
            row.appendChild(dotB);
            portsEl.appendChild(row);
        }

        const pos = cardPositions[cid];
        card.style.left = (pos ? pos.x : 1500) + 'px';
        card.style.top  = (pos ? pos.y : 20 + idx * 320) + 'px';
        makeDraggable(card, card.querySelector('.fcard-hdr'));
        canvas.appendChild(card);
        cards[cid] = card;
    });
}

// ── Build cable cards ─────────────────────────────────────────────────────────
function buildCableCards() {
    CABOS_DATA.forEach((cabo, idx) => {
        const cid  = 'cabo-' + cabo.id;
        const card = document.createElement('div');
        card.className      = 'fcard';
        card.dataset.cardid = cid;

        card.innerHTML = `
            <div class="fcard-hdr">
                <i class="fas fa-minus" style="color:#3399ff;font-size:11px"></i>
                <div style="flex:1;min-width:0">
                    <div class="fcard-title" style="color:#3399ff">${esc(cabo.codigo)}</div>
                    <div class="fcard-sub">${cabo.num_fibras}F · ${esc(cabo.tipo||'')}${cabo.nome ? ' · '+esc(cabo.nome) : ''}</div>
                </div>
            </div>
            <div class="fcard-ports" id="ports-cabo-${cabo.id}"></div>`;

        const portsEl = card.querySelector('#ports-cabo-' + cabo.id);

        for (let fn = 1; fn <= cabo.num_fibras; fn++) {
            const fKey   = cabo.id + '-' + fn;
            const isConn = !!connByFibra[fKey];
            const color  = fiberHex(fn);

            const row = document.createElement('div');
            row.className = 'fport';
            row.style.gap = '4px';

            const dot = document.createElement('div');
            dot.className         = 'fport-dot' + (isConn ? ' connected' : '');
            dot.dataset.cabo      = cabo.id;
            dot.dataset.fibra     = fn;
            dot.style.borderColor = isConn ? '#00cc66' : color;
            if (!isConn) dot.style.background = color + '33';

            const c        = connByFibra[fKey];
            const lbl_text = fiberLabel(cabo, fn);
            dot.title = c ? `${lbl_text} → ${c.dio_codigo} P${c.dio_porta} — clique p/ desconectar`
                          : `${lbl_text} — clique para selecionar`;

            // Fiber identifier — outer side
            const portLbl = document.createElement('span');
            portLbl.className = 'fport-info';
            portLbl.style.flex = '1';
            portLbl.innerHTML = `<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${color};margin-right:4px;vertical-align:middle"></span>${lbl_text}`;

            // Connection description — always adjacent to dot
            const connDesc = document.createElement('span');
            connDesc.style.cssText = 'font-size:9px;color:#00cc66;white-space:nowrap;';
            if (c) connDesc.textContent = `→${c.dio_codigo}P${c.dio_porta}`;

            dot.addEventListener('click', () => {
                if (dot.classList.contains('connected')) {
                    const conn = connByFibra[fKey];
                    if (conn && confirm(`Desconectar ${lbl_text}?`)) disconnectByFibra(cabo.id, fn);
                    return;
                }
                selectItem({type:'fibra', caboId: cabo.id, fibraNum: fn}, dot);
            });

            row.appendChild(portLbl);
            row.appendChild(connDesc);
            row.appendChild(dot);
            portsEl.appendChild(row);
        }

        const pos = cardPositions[cid];
        card.style.left = (pos ? pos.x : 1200) + 'px';
        card.style.top  = (pos ? pos.y : 30 + idx * 280) + 'px';
        makeDraggable(card, card.querySelector('.fcard-hdr'));
        canvas.appendChild(card);
        cards[cid] = card;
    });
}

// ── Card orientation (dot side based on position relative to DIOs) ────────────
function updateCardOrientations() {
    const dioXs = Object.entries(cards)
        .filter(([k]) => k.startsWith('dio-'))
        .map(([, card]) => card.offsetLeft + card.offsetWidth / 2);

    if (!dioXs.length) return;
    const avgDioX = dioXs.reduce((a, b) => a + b, 0) / dioXs.length;

    // OLT cards and cable cards flip dot side based on position
    Object.entries(cards)
        .filter(([k]) => k.startsWith('olt-') || k.startsWith('cabo-'))
        .forEach(([, card]) => {
            const cx  = card.offsetLeft + card.offsetWidth / 2;
            const dir = cx > avgDioX ? 'row-reverse' : 'row';
            card.querySelectorAll('.fport').forEach(row => { row.style.flexDirection = dir; });
        });

    drawLines();
}

// ── Auto-layout ───────────────────────────────────────────────────────────────
function autoLayout() {
    const margin = 30;
    const gap    = 16;

    // OLT cards — left column
    const oltCards = Object.entries(cards).filter(([k]) => k.startsWith('olt-'));
    let yOlt = margin;
    oltCards.forEach(([cid, card]) => {
        card.style.left = margin + 'px';
        card.style.top  = yOlt + 'px';
        savePos(cid, margin, yOlt);
        yOlt += card.offsetHeight + gap;
    });

    // DIO cards — center column
    const dioCards = Object.entries(cards).filter(([k]) => k.startsWith('dio-'));
    const dioX = 560;
    let yDio = margin;
    dioCards.forEach(([cid, card]) => {
        card.style.left = dioX + 'px';
        card.style.top  = yDio + 'px';
        savePos(cid, dioX, yDio);
        yDio += card.offsetHeight + gap;
    });

    // Cable cards — right column
    const caboCards = Object.entries(cards).filter(([k]) => k.startsWith('cabo-'));
    const caboX = 1100;
    let yCabo = margin;
    caboCards.forEach(([cid, card]) => {
        card.style.left = caboX + 'px';
        card.style.top  = yCabo + 'px';
        savePos(cid, caboX, yCabo);
        yCabo += card.offsetHeight + gap;
    });

    setTimeout(() => { updateCardOrientations(); fitCanvas(); }, 50);
}

function resetPositions() {
    cardPositions = {};
    localStorage.removeItem(storageKey);
    autoLayout();
}

// ── Fit canvas to card content ────────────────────────────────────────────────
function fitCanvas() {
    const pad = 80;
    let maxR = 0, maxB = 0;
    Object.values(cards).forEach(card => {
        const r = card.offsetLeft + card.offsetWidth;
        const b = card.offsetTop  + card.offsetHeight;
        if (r > maxR) maxR = r;
        if (b > maxB) maxB = b;
    });
    const wrapW = canvasWrap.clientWidth;
    const wrapH = canvasWrap.clientHeight;
    const w = Math.max(wrapW, maxR + pad);
    const h = Math.max(wrapH, maxB + pad);
    canvas.style.width  = w + 'px';
    canvas.style.height = h + 'px';
    svg.setAttribute('width',  w);
    svg.setAttribute('height', h);
    drawLines();
}

// ── Init ──────────────────────────────────────────────────────────────────────
buildOltCards();
buildDioCards();
buildCableCards();

setTimeout(() => {
    if (!Object.keys(cardPositions).length) autoLayout();
    else { updateCardOrientations(); fitCanvas(); }
}, 120);

window.addEventListener('resize', () => { fitCanvas(); drawLines(); });
canvasWrap.addEventListener('scroll', drawLines);
