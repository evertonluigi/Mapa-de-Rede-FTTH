// FTTH Network Manager — Fusion Map Engine
// Extracted from modules/fusoes/view.php
// Globals available: CABLES, INST_SPLITTERS, ALL_SPLITTERS, ELEM_TIPO, ELEM_ID,
//   BASE_URL, FC_ABNT, DB_POSITIONS, DB_ORIENTATIONS, POS_KEY, ORI_KEY, CLIENTES_PORTA,
//   fusoes, positions, orientations, pendingFusion, dragSrc, tempLine, cardDrag

// ── Fiber color ───────────────────────────────────────────────────────────────
function cableFiberColor(cable, n) {
    let cfg=null;
    try{cfg=cable.config_cores?JSON.parse(cable.config_cores):null;}catch(e){}
    if(cfg&&cfg.cores&&cfg.cores.length>0) return {hex:cfg.cores[(n-1)%cfg.cores.length],nome:'FO'+n};
    return FC_ABNT[((n-1)%12)+1];
}

// ── Fiber label: T1-F01 nomenclature ─────────────────────────────────────────
function getFpt(cable){
    let cfg=null;
    try{cfg=cable.config_cores?JSON.parse(cable.config_cores):null;}catch(e){}
    return cfg?.fibras_por_tubo||cable.fibras_por_tubo||12;
}
function fiberLabel(cable, fiberNum){
    const fpt=getFpt(cable);
    const tubeIdx=Math.floor((fiberNum-1)/fpt)+1;
    const fInTube=((fiberNum-1)%fpt)+1;
    return `T${tubeIdx}-F${String(fInTube).padStart(2,'0')}`;
}
function fiberLabelById(cableId, fiberNum){
    const cable=CABLES.find(c=>c.id==cableId);
    return cable?fiberLabel(cable,fiberNum):`F${String(fiberNum).padStart(2,'0')}`;
}
// Short number inside dot = fiber index within its tube (1-based)
function fiberInTube(cable, fiberNum){
    return ((fiberNum-1)%getFpt(cable))+1;
}
function isDark(hex){if(!hex||hex.length<7)return false;const r=parseInt(hex.slice(1,3),16),g=parseInt(hex.slice(3,5),16),b=parseInt(hex.slice(5,7),16);return(r*299+g*587+b*114)/1000<128;}

// ── Positions / orientations ──────────────────────────────────────────────────
// Prioridade: banco de dados → localStorage → padrão automático
function loadPositions(){
    // 1. Tenta dados do banco (carregados no PHP e injetados na página)
    if(DB_POSITIONS && Object.keys(DB_POSITIONS).length>0){
        positions=Object.assign({},DB_POSITIONS);
        return;
    }
    // 2. Fallback para localStorage (sessões anteriores sem banco)
    try{const s=localStorage.getItem(POS_KEY);if(s)positions=JSON.parse(s);}catch(e){}
}
function loadOrientations(){
    if(DB_ORIENTATIONS && Object.keys(DB_ORIENTATIONS).length>0){
        orientations=Object.assign({},DB_ORIENTATIONS);
        return;
    }
    try{const s=localStorage.getItem(ORI_KEY);if(s)orientations=JSON.parse(s);}catch(e){}
}

// Salva no banco via AJAX (e no localStorage como backup)
let _saveTimer=null;
function savePositions(){
    localStorage.setItem(POS_KEY,JSON.stringify(positions));
    // Debounce: aguarda 800ms sem novos saves antes de enviar ao banco
    clearTimeout(_saveTimer);
    _saveTimer=setTimeout(()=>_persistLayout(),800);
}
function saveOrientations(){
    localStorage.setItem(ORI_KEY,JSON.stringify(orientations));
    clearTimeout(_saveTimer);
    _saveTimer=setTimeout(()=>_persistLayout(),800);
}
function _persistLayout(){
    fetch(location.href,{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({save_layout:true,positions,orientations})
    }).catch(()=>{}); // silencia erros de rede — localStorage já é fallback
}

function getPos(key,defaultX,defaultY){return positions[key]||{x:defaultX,y:defaultY};}
function getOri(key){return orientations[key]||'H';}
function setOri(key,ori){orientations[key]=ori;saveOrientations();}
// Determine if a cable card is on the left half of the canvas (threshold 420px)
function getCardSide(key){
    const cardEl=document.getElementById('card-'+key);
    const x=cardEl?parseInt(cardEl.style.left)||0:(positions[key]?.x||0);
    return x<420?'L':'R';
}

function resetLayout(){
    positions={};orientations={};
    localStorage.removeItem(POS_KEY);
    localStorage.removeItem(ORI_KEY);
    _persistLayout(); // apaga do banco também
    buildCanvas();
}

// ── Canvas & SVG size ─────────────────────────────────────────────────────────
function resizeCanvas(){
    const canvas=document.getElementById('fcanvas');
    const svg=document.getElementById('fsvg');
    let maxX=900,maxY=620;
    // Extra width: orthogonal rail (44px) + client drop cables (200px) + margin
    const dropBuf=Object.keys(CLIENTES_PORTA).length>0?240:100;
    document.querySelectorAll('.fcard').forEach(c=>{
        maxX=Math.max(maxX,(parseInt(c.style.left)||0)+c.offsetWidth+dropBuf);
        maxY=Math.max(maxY,(parseInt(c.style.top)||0)+c.offsetHeight+60);
    });
    svg.setAttribute('width',maxX);svg.setAttribute('height',maxY);
    svg.style.width=maxX+'px';svg.style.height=maxY+'px';
}

// ── Mouse → canvas coords ────────────────────────────────────────────────────
function mouseToCanvas(e){
    const c=document.getElementById('fcanvas');
    const r=c.getBoundingClientRect();
    return{x:e.clientX-r.left+c.scrollLeft,y:e.clientY-r.top+c.scrollTop};
}

// ── Dot center (canvas coords) ────────────────────────────────────────────────
function dotCenter(id){
    const el=document.getElementById(id);
    if(!el)return null;
    const canvas=document.getElementById('fcanvas');
    const cr=canvas.getBoundingClientRect();
    const er=el.getBoundingClientRect();
    return{x:er.left-cr.left+canvas.scrollLeft+er.width/2,y:er.top-cr.top+canvas.scrollTop+er.height/2};
}

// ── Build canvas ──────────────────────────────────────────────────────────────
function buildCanvas(){
    // Remove old cards
    document.querySelectorAll('.fcard').forEach(c=>c.remove());

    // Default layout: cables spread across left side, splitters in center
    const gap=20;
    let cx=30,cy=30;
    const colW=200;

    CABLES.forEach((cable,i)=>{
        const key='c-'+cable.id;
        const def={x:30+i*(colW+gap),y:30};
        const pos=getPos(key,def.x,def.y);
        const card=createCableCard(cable,pos.x,pos.y);
        document.getElementById('fcanvas').appendChild(card);
        makeDraggable(card,key);
    });

    const sColStart=Math.max(30+CABLES.length*(colW+gap),30);
    INST_SPLITTERS.forEach((inst,i)=>{
        const key='s-'+inst.id;
        const pos=getPos(key,sColStart+i*160,30);
        const card=createSplitterCard(inst,pos.x,pos.y);
        document.getElementById('fcanvas').appendChild(card);
        makeDraggable(card,key);
    });

    resizeCanvas();
    buildBandejaTabs();
    // Wait for browser layout before drawing SVG connections (getBoundingClientRect needs final positions)
    requestAnimationFrame(()=>{ resizeCanvas(); renderConnections(); });
}

// ── Cable card ────────────────────────────────────────────────────────────────
function createCableCard(cable,x,y){
    const key='c-'+cable.id;
    const ori=getOri(key);
    const card=document.createElement('div');
    card.className='fcard';
    card.id='card-'+key;
    card.style.left=x+'px';card.style.top=y+'px';

    let cfg=null;try{cfg=cable.config_cores?JSON.parse(cable.config_cores):null;}catch(e){}
    const fpt=cfg?.fibras_por_tubo||cable.fibras_por_tubo||12;
    const numTubes=Math.ceil(cable.num_fibras/fpt);

    card.innerHTML=`
    <div class="fcard-hdr">
        <div style="flex:1;min-width:0">
            <div class="fcard-title">${escHtml(cable.codigo)}</div>
            <div class="fcard-sub">${cable.num_fibras}F · ${cable.tipo}</div>
        </div>
        <button class="fcard-btn" title="${ori==='H'?'Mudar para orientação vertical':'Mudar para orientação horizontal'}"
                onclick="toggleOri('${key}',this)" id="ori-btn-${key}">${ori==='H'?'↕':'↔'}</button>
    </div>
    <div class="fcard-body" id="body-${key}"></div>`;

    renderCableBody(card.querySelector('#body-'+key), cable, key, ori);
    return card;
}

function renderCableBody(bodyEl, cable, key, ori){
    bodyEl.innerHTML='';
    const isLeft = getCardSide(key) === 'L';
    let cfg=null;try{cfg=cable.config_cores?JSON.parse(cable.config_cores):null;}catch(e){}
    const fpt=cfg?.fibras_por_tubo||cable.fibras_por_tubo||12;
    const numTubes=Math.ceil(cable.num_fibras/fpt);

    if(ori==='V'){
        // Vertical: fibers stacked, one per row
        // For left-side cards: [label][dot] so the dot (connection point) faces center
        const list=document.createElement('div');
        list.className='fiber-v';
        for(let f=1;f<=cable.num_fibras;f++){
            const fc=cableFiberColor(cable,f);
            const bd=fc.hex==='#EEEEEE'?'#999':fc.hex;
            const lbl=fiberLabel(cable,f);
            const innerN=fiberInTube(cable,f);
            const item=document.createElement('div');
            item.className='fo-item';
            const dotHtml=`<div class="fo-dot"
                 id="fd-${cable.id}-${f}"
                 data-dottype="fiber"
                 data-cable="${cable.id}"
                 data-fiber="${f}"
                 data-color="${fc.hex}"
                 style="background:${fc.hex};border-color:${bd};color:${fc.hex}"
                 title="${escHtml(cable.codigo)} ${lbl} — ${fc.nome}">
                <span style="font-size:8px;font-weight:700;color:${isDark(fc.hex)?'#fff':'#000'};pointer-events:none">${innerN}</span>
            </div>`;
            const lblHtml=`<span class="fo-num" style="min-width:52px${isLeft?';text-align:right':''}">${lbl} <span style="color:#444">${fc.nome}</span></span>`;
            item.innerHTML = isLeft ? lblHtml + dotHtml : dotHtml + lblHtml;
            list.appendChild(item);
        }
        bodyEl.appendChild(list);
    } else {
        // Horizontal: tube rows
        for(let t=0;t<numTubes;t++){
            const row=document.createElement('div');
            row.className='tube-row';
            if(numTubes>1) row.innerHTML=`<span class="tube-lbl">T${t+1}</span>`;
            const start=t*fpt+1,end=Math.min((t+1)*fpt,cable.num_fibras);
            for(let f=start;f<=end;f++){
                const fc=cableFiberColor(cable,f);
                const bd=fc.hex==='#EEEEEE'?'#999':fc.hex;
                const lbl=fiberLabel(cable,f);
                const innerN=fiberInTube(cable,f);
                const item=document.createElement('div');
                item.className='fo-item';
                item.innerHTML=`
                    <div class="fo-dot"
                         id="fd-${cable.id}-${f}"
                         data-dottype="fiber"
                         data-cable="${cable.id}"
                         data-fiber="${f}"
                         data-color="${fc.hex}"
                         style="background:${fc.hex};border-color:${bd};color:${fc.hex}"
                         title="${escHtml(cable.codigo)} ${lbl} — ${fc.nome}">
                        <span style="font-size:8px;font-weight:700;color:${isDark(fc.hex)?'#fff':'#000'};pointer-events:none">${innerN}</span>
                    </div>
                    <span class="fo-num">${lbl}</span>`;
                row.appendChild(item);
            }
            bodyEl.appendChild(row);
        }
    }
}

function toggleOri(key, btn){
    const newOri=getOri(key)==='H'?'V':'H';
    setOri(key,newOri);
    btn.textContent=newOri==='H'?'↕':'↔';
    btn.title=newOri==='H'?'Mudar para orientação vertical':'Mudar para orientação horizontal';
    // Find cable
    const cid=parseInt(key.slice(2));
    const cable=CABLES.find(c=>c.id==cid);
    if(!cable) return;
    const bodyEl=document.getElementById('body-'+key);
    renderCableBody(bodyEl,cable,key,newOri);
    markFusedDots();
    resizeCanvas();
    renderConnections();
}

// ── Splitter card ─────────────────────────────────────────────────────────────
function createSplitterCard(inst,x,y){
    const key='s-'+inst.id;
    const rel=inst.relacao||'1:8';
    const parts=rel.split(':');
    const numIn=parseInt(parts[0])||1;
    const numOut=parseInt(parts[1])||8;

    const card=document.createElement('div');
    card.className='fcard';
    card.id='card-'+key;
    card.style.left=x+'px';card.style.top=y+'px';
    card.style.borderColor='rgba(255,204,0,.3)';

    let inPorts='',outPorts='';
    for(let i=0;i<numIn;i++){
        inPorts+=`<div class="spl-port" id="sp-${inst.id}-i${i}" data-dottype="spl" data-spl-id="${inst.id}" data-spl-porta="i${i}" title="Entrada ${i+1}">${i+1}</div>`;
    }
    for(let i=0;i<numOut;i++){
        const portaNum1 = i + 1;
        const cli = CLIENTES_PORTA[portaNum1];
        outPorts+=`<div class="spl-port" id="sp-${inst.id}-o${i}" data-dottype="spl" data-spl-id="${inst.id}" data-spl-porta="o${i}" title="Saída ${portaNum1}${cli?' — '+(cli.login||cli.nome||''):''}">${portaNum1}</div>`;
    }

    card.innerHTML=`
    <div class="fcard-hdr" style="border-bottom-color:rgba(255,204,0,.15)">
        <div style="flex:1;min-width:0">
            <div class="fcard-title" style="color:#ffcc00"><i class="fas fa-project-diagram"></i> ${escHtml(inst.spl_codigo)}
                ${inst.subtipo==='atendimento'?'<span style="font-size:9px;background:rgba(0,204,102,.2);color:#00cc66;border:1px solid rgba(0,204,102,.3);border-radius:4px;padding:1px 5px;margin-left:4px">Atendimento</span>':'<span style="font-size:9px;color:#555;margin-left:4px">Derivação</span>'}
            </div>
            <div class="fcard-sub">${rel}${inst.spl_nome?' — '+escHtml(inst.spl_nome):''}</div>
        </div>
        <button class="fcard-btn danger" title="Remover splitter deste diagrama" onclick="removeSplitter(${inst.id})">✕</button>
    </div>
    <div class="fcard-body" style="padding:0">
        <div style="display:flex;justify-content:space-between;padding:3px 8px 0;font-size:8px;color:#555;pointer-events:none">
            <span>↓ IN</span><span>OUT ↓</span>
        </div>
        <div class="spl-body">
            <div class="spl-ports">${inPorts}</div>
            <div class="spl-mid">${rel}</div>
            <div class="spl-ports" style="gap:20px">${outPorts}</div>
        </div>
    </div>`;

    // Fetch output port signals asynchronously and show badges
    // Use requestAnimationFrame to ensure the card is in the DOM first
    requestAnimationFrame(()=>requestAnimationFrame(()=>loadSplitterPortSinals(inst.id, card)));

    return card;
}

// ── Splitter output port signal badges ────────────────────────────────────────
// Debounce: redraw connections once after ALL splitter badges finish loading
let _rcTimer=null;
function _scheduleRC(){ clearTimeout(_rcTimer); _rcTimer=setTimeout(()=>{resizeCanvas();renderConnections();},60); }

async function loadSplitterPortSinals(instId, cardEl){
    let data;
    try{
        const r=await fetch(`${BASE_URL}/api/sinal.php?tipo=spl_ports&id=${instId}`);
        data=await r.json();
    }catch(e){return;}
    if(!data.success||!data.ports)return;
    const root=cardEl||document;
    Object.entries(data.ports).forEach(([porta,info])=>{
        const dot=root.querySelector?root.querySelector(`#sp-${instId}-${porta}`):document.getElementById(`sp-${instId}-${porta}`);
        if(!dot)return;
        const dbm=info.sinal;
        if(dbm===null||dbm===undefined)return;
        const c=sinalCor(dbm);
        // Badge positioned absolutely so it does NOT grow the port circle height
        dot.style.position='relative';
        let badge=dot.querySelector('.spl-sinal-badge');
        if(!badge){
            badge=document.createElement('span');
            badge.className='spl-sinal-badge';
            badge.style.cssText='position:absolute;bottom:-12px;left:50%;transform:translateX(-50%);font-size:8px;line-height:1;white-space:nowrap;pointer-events:none;background:rgba(8,11,15,.85);padding:1px 2px;border-radius:2px';
            dot.appendChild(badge);
        }
        badge.style.color=c;
        badge.textContent=(dbm>=0?'+':'')+dbm.toFixed(1);
        const portNum=parseInt(porta.slice(1))+1;
        dot.title=`Saída ${portNum} · ${(dbm>=0?'+':'')+dbm.toFixed(2)} dBm`;
    });
    // Badges may shift layout — schedule a connections redraw
    _scheduleRC();
}

// ── Card drag ─────────────────────────────────────────────────────────────────
function makeDraggable(cardEl, key){
    const hdr=cardEl.querySelector('.fcard-hdr');
    hdr.addEventListener('mousedown',e=>{
        if(e.target.closest('.fcard-btn'))return;
        if(dragSrc)return; // Don't start card drag while fiber dragging
        e.preventDefault();
        cardDrag={el:cardEl,key,startX:e.clientX,startY:e.clientY,startLeft:parseInt(cardEl.style.left)||0,startTop:parseInt(cardEl.style.top)||0};
        cardEl.style.zIndex=10;
    });
    // Touch support: arrastar card com o dedo
    hdr.addEventListener('touchstart',e=>{
        if(e.target.closest('.fcard-btn'))return;
        if(dragSrc)return;
        e.preventDefault();
        const t=e.touches[0];
        cardDrag={el:cardEl,key,startX:t.clientX,startY:t.clientY,startLeft:parseInt(cardEl.style.left)||0,startTop:parseInt(cardEl.style.top)||0};
        cardEl.style.zIndex=10;
    },{passive:false});
}

// ── Connection render ─────────────────────────────────────────────────────────
function dotIdFromFusao(f, side){
    if(side==='ent'){
        if(f.cabo_entrada_id!=null) return `fd-${f.cabo_entrada_id}-${f.fibra_entrada}`;
        if(f.spl_ent_id!=null) return `sp-${f.spl_ent_id}-${f.spl_ent_porta}`;
    } else {
        if(f.cabo_saida_id!=null) return `fd-${f.cabo_saida_id}-${f.fibra_saida}`;
        if(f.spl_sai_id!=null) return `sp-${f.spl_sai_id}-${f.spl_sai_porta}`;
    }
    return null;
}

function dotColor(dotId){
    const el=document.getElementById(dotId);
    if(!el) return '#888';
    return el.dataset.color||'#ffcc00';
}

// Get the bounding box of the card containing a dot, in canvas-relative coords
function getCardBox(dotId){
    const el=document.getElementById(dotId);
    if(!el) return null;
    const card=el.closest('.fcard');
    if(!card) return null;
    const canvas=document.getElementById('fcanvas');
    const cr=canvas.getBoundingClientRect();
    const r=card.getBoundingClientRect();
    const sl=canvas.scrollLeft, st=canvas.scrollTop;
    return {
        left:   r.left   - cr.left + sl,
        top:    r.top    - cr.top  + st,
        right:  r.right  - cr.left + sl,
        bottom: r.bottom - cr.top  + st
    };
}

// Build SVG connection path between two dots.
// Stacked (vertical): orthogonal H→V→H going outside both card bounding boxes.
// Side-by-side: smooth S-bezier.
// box1/box2: {left,top,right,bottom} of the source/destination cards.
function buildConnPath(p1, p2, box1, box2) {
    const dx = p2.x - p1.x;
    const dy = p2.y - p1.y;
    const r  = 10; // rounded corner radius

    // Same height — straight line
    if (Math.abs(dy) < 8) return `M${p1.x},${p1.y} L${p2.x},${p2.y}`;

    // Determine if the cards overlap horizontally (stacked layout)
    const b1 = box1 ?? {left: p1.x - 14, right: p1.x + 14};
    const b2 = box2 ?? {left: p2.x - 14, right: p2.x + 14};
    const hOverlap = b1.left < b2.right && b2.left < b1.right;

    if (hOverlap) {
        // ── Orthogonal U-route: right of both cards ──────────────────────────
        // Source exits RIGHT → vertical rail → destination enters from RIGHT
        const rail = Math.max(b1.right, b2.right) + 44;
        const dir  = dy > 0 ? 1 : -1;
        const y1   = p1.y, y2 = p2.y;
        return [
            `M${p1.x},${y1}`,
            `H${rail - r}`,
            `Q${rail},${y1} ${rail},${y1 + dir * r}`,
            `V${y2 - dir * r}`,
            `Q${rail},${y2} ${rail - r},${y2}`,
            `H${p2.x}`
        ].join(' ');
    }

    // ── Normal side-by-side: smooth S-bezier ─────────────────────────────────
    const sign = dx > 0 ? 1 : -1;
    const cp   = Math.max(Math.abs(dx) * 0.48, 85);
    return `M${p1.x},${p1.y} C${p1.x + sign*cp},${p1.y} ${p2.x - sign*cp},${p2.y} ${p2.x},${p2.y}`;
}

function renderConnections(){
    const svg=document.getElementById('fsvg');
    svg.querySelectorAll('.conn,.conn-lbl,.client-drop').forEach(e=>e.remove());
    document.querySelectorAll('.fo-dot.fused,.fo-dot.passante').forEach(d=>d.classList.remove('fused','passante'));
    document.querySelectorAll('.spl-port.fused').forEach(d=>d.classList.remove('fused'));

    const visible=activeBandeja==='all'?fusoes:fusoes.filter(f=>f.bandeja==activeBandeja);

    // When a specific bandeja is selected, hide cards that have no connection in this bandeja
    if(activeBandeja!=='all'){
        // Collect card keys referenced by visible fusoes
        const activeKeys=new Set();
        visible.forEach(f=>{
            if(f.cabo_entrada_id!=null) activeKeys.add('c-'+f.cabo_entrada_id);
            if(f.cabo_saida_id!=null)   activeKeys.add('c-'+f.cabo_saida_id);
            if(f.spl_ent_id!=null)      activeKeys.add('s-'+f.spl_ent_id);
            if(f.spl_sai_id!=null)      activeKeys.add('s-'+f.spl_sai_id);
        });
        document.querySelectorAll('.fcard').forEach(card=>{
            const key=card.id.replace('card-','');
            card.style.display=activeKeys.has(key)?'':'none';
        });
    } else {
        document.querySelectorAll('.fcard').forEach(c=>c.style.display='');
    }

    visible.forEach(f=>{
        const passante=f.tipo==='passante';
        const dEntId=dotIdFromFusao(f,'ent');
        const dSaiId=dotIdFromFusao(f,'sai');

        // Self-referential passante (same cable passes through): mark entry dot only, no line
        if(passante && f.cabo_entrada_id && f.cabo_entrada_id===f.cabo_saida_id){
            if(dEntId){const el=document.getElementById(dEntId);if(el){el.classList.add('passante');el.title=(el.title||'')+' [PASSAGEM]';}}
            return;
        }

        if(!dEntId||!dSaiId)return;
        const p1=dotCenter(dEntId), p2=dotCenter(dSaiId);
        if(!p1||!p2)return;

        const box1=getCardBox(dEntId), box2=getCardBox(dSaiId);

        const cE=dotColor(dEntId), cS=dotColor(dSaiId);
        const gradId='g'+f.id;
        let grad=document.getElementById(gradId);
        if(!grad){grad=document.createElementNS('http://www.w3.org/2000/svg','linearGradient');grad.id=gradId;document.getElementById('svg-defs').appendChild(grad);}
        grad.setAttribute('gradientUnits','userSpaceOnUse');
        grad.setAttribute('x1',p1.x);grad.setAttribute('y1',p1.y);
        grad.setAttribute('x2',p2.x);grad.setAttribute('y2',p2.y);
        grad.innerHTML=`<stop offset="0%" stop-color="${cE}"/><stop offset="49%" stop-color="${cE}"/><stop offset="51%" stop-color="${cS}"/><stop offset="100%" stop-color="${cS}"/>`;

        const pathD=buildConnPath(p1,p2,box1,box2);
        const path=document.createElementNS('http://www.w3.org/2000/svg','path');
        path.setAttribute('d',pathD);
        path.setAttribute('stroke',passante?'#ffaa00':`url(#${gradId})`);
        path.setAttribute('stroke-width',passante?'2':'2.5');
        path.setAttribute('fill','none');
        path.setAttribute('opacity',passante?'0.75':'0.9');
        if(passante) path.setAttribute('stroke-dasharray','6,3');
        path.classList.add('conn');
        path.dataset.fusaoId=f.id;
        path.style.pointerEvents='stroke';
        path.addEventListener('mouseenter',function(){this.setAttribute('stroke-width','4');});
        path.addEventListener('mouseleave',function(){this.setAttribute('stroke-width','2.5');});
        path.addEventListener('click',()=>{
            const label=buildConnLabel(f);
            const tipo=f.tipo==='passante'?'passagem':'fusão';
            if(confirm(`Remover ${tipo}: ${label}?`)) deleteFusao(f.id);
        });
        svg.appendChild(path);

        // Bandeja label at midpoint
        const mx=(p1.x+p2.x)/2, my=(p1.y+p2.y)/2;
        const lbl=document.createElementNS('http://www.w3.org/2000/svg','text');
        lbl.setAttribute('x',mx);lbl.setAttribute('y',my-5);
        lbl.setAttribute('text-anchor','middle');lbl.setAttribute('fill',passante?'#ffaa00':'#444');lbl.setAttribute('font-size','9');
        lbl.style.pointerEvents='none';lbl.textContent=passante?'↔':'B'+f.bandeja;lbl.classList.add('conn-lbl');
        svg.appendChild(lbl);

        [dEntId,dSaiId].forEach(id=>{
            const el=document.getElementById(id);
            // Cross-cable passante: dots show as fused; the dashed line conveys it's a passante
            if(el) el.classList.add('fused');
        });
    });

    // ── Draw client drop cables in SVG (outside splitter card) ───────────────
    if(Object.keys(CLIENTES_PORTA).length>0){
        INST_SPLITTERS.forEach(inst=>{
            const relParts=(inst.relacao||'1:8').split(':');
            const numOut=parseInt(relParts[1])||8;
            for(let i=0;i<numOut;i++){
                const portaNum1=i+1;
                const cli=CLIENTES_PORTA[portaNum1];
                if(!cli)continue;
                const p=dotCenter(`sp-${inst.id}-o${i}`);
                if(!p)continue;

                const x1=p.x, y=p.y;
                const lineEndX=x1+70;
                const circX=x1+86;
                const textX=circX+17;

                // Dashed drop cable line
                const ln=document.createElementNS('http://www.w3.org/2000/svg','line');
                ln.setAttribute('x1',x1);ln.setAttribute('y1',y);
                ln.setAttribute('x2',lineEndX);ln.setAttribute('y2',y);
                ln.setAttribute('stroke','rgba(0,204,102,.65)');
                ln.setAttribute('stroke-width','2');
                ln.setAttribute('stroke-dasharray','6,3');
                ln.style.pointerEvents='none';
                ln.classList.add('client-drop');
                svg.appendChild(ln);

                // Circle around person icon
                const circ=document.createElementNS('http://www.w3.org/2000/svg','circle');
                circ.setAttribute('cx',circX);circ.setAttribute('cy',y);
                circ.setAttribute('r','13');
                circ.setAttribute('fill','rgba(0,204,102,.12)');
                circ.setAttribute('stroke','rgba(0,204,102,.6)');
                circ.setAttribute('stroke-width','1.5');
                circ.style.pointerEvents='none';
                circ.classList.add('client-drop');
                svg.appendChild(circ);

                // Person emoji
                const ico=document.createElementNS('http://www.w3.org/2000/svg','text');
                ico.setAttribute('x',circX);ico.setAttribute('y',y);
                ico.setAttribute('text-anchor','middle');
                ico.setAttribute('dominant-baseline','middle');
                ico.setAttribute('font-size','13');
                ico.style.pointerEvents='none';
                ico.classList.add('client-drop');
                ico.textContent='👤';
                svg.appendChild(ico);

                // Login text
                const txt=document.createElementNS('http://www.w3.org/2000/svg','text');
                txt.setAttribute('x',textX);txt.setAttribute('y',y);
                txt.setAttribute('dominant-baseline','middle');
                txt.setAttribute('font-size','13');
                txt.setAttribute('fill','#00cc66');
                txt.setAttribute('font-weight','600');
                txt.style.pointerEvents='none';
                txt.textContent=cli.login||cli.nome||'';
                txt.classList.add('client-drop');
                svg.appendChild(txt);
            }
        });
    }
}

function buildConnLabel(f){
    const labels=[];
    if(f.cabo_entrada_id) labels.push((f.cabo_e_cod||'?')+' '+fiberLabelById(f.cabo_entrada_id,f.fibra_entrada));
    if(f.spl_ent_id) labels.push('SPL p'+f.spl_ent_porta);
    if(f.cabo_saida_id) labels.push((f.cabo_s_cod||'?')+' '+fiberLabelById(f.cabo_saida_id,f.fibra_saida));
    if(f.spl_sai_id) labels.push('SPL p'+f.spl_sai_porta);
    return labels.join(' → ');
}

function markFusedDots(){
    document.querySelectorAll('.fo-dot.fused,.fo-dot.passante').forEach(d=>d.classList.remove('fused','passante'));
    document.querySelectorAll('.spl-port.fused').forEach(d=>d.classList.remove('fused'));
    const visible=activeBandeja==='all'?fusoes:fusoes.filter(f=>f.bandeja==activeBandeja);
    visible.forEach(f=>{
        // Self-referential passante (same cable): pulsing dot; cross-cable passante: fused dot + dashed line
        const isSelfPassante = f.tipo==='passante' && f.cabo_entrada_id!=null && f.cabo_entrada_id===f.cabo_saida_id;
        const dotClass = isSelfPassante ? 'passante' : 'fused';
        [dotIdFromFusao(f,'ent'),dotIdFromFusao(f,'sai')].forEach(id=>{
            if(!id) return;
            const el=document.getElementById(id);
            if(el) el.classList.add(dotClass);
        });
    });
}

// ── Drag for fiber connections ─────────────────────────────────────────────────
document.addEventListener('mousedown',e=>{
    const dot=e.target.closest('.fo-dot,.spl-port');
    if(!dot||cardDrag)return;
    if(e.button!==0)return;
    e.preventDefault();e.stopPropagation();
    dragSrc={
        dotId:dot.id,
        dottype:dot.dataset.dottype,
        cableId:dot.dataset.cable?parseInt(dot.dataset.cable):null,
        fiberNum:dot.dataset.fiber?parseInt(dot.dataset.fiber):null,
        splId:dot.dataset.splId?parseInt(dot.dataset['splId']):null,
        splPorta:dot.dataset.splPorta||null,
        color:dot.dataset.color||'#ffcc00',
        el:dot,
    };
    dot.classList.add('selected');
    const svg=document.getElementById('fsvg');
    tempLine=document.createElementNS('http://www.w3.org/2000/svg','line');
    tempLine.setAttribute('stroke','#ffcc00');
    tempLine.setAttribute('stroke-width','2');
    tempLine.setAttribute('stroke-dasharray','6,3');
    tempLine.setAttribute('opacity','0.8');
    svg.appendChild(tempLine);
    const p=dotCenter(dot.id);
    if(p){tempLine.setAttribute('x1',p.x);tempLine.setAttribute('y1',p.y);tempLine.setAttribute('x2',p.x);tempLine.setAttribute('y2',p.y);}
});

document.addEventListener('mousemove',e=>{
    // Card drag
    if(cardDrag){
        e.preventDefault();
        const dx=e.clientX-cardDrag.startX,dy=e.clientY-cardDrag.startY;
        const nx=Math.max(0,cardDrag.startLeft+dx),ny=Math.max(0,cardDrag.startTop+dy);
        cardDrag.el.style.left=nx+'px';cardDrag.el.style.top=ny+'px';
        positions[cardDrag.key]={x:nx,y:ny};
        resizeCanvas();renderConnections();
        return;
    }
    // Fiber drag
    if(!dragSrc||!tempLine)return;
    const p=dotCenter(dragSrc.dotId);
    const mc=mouseToCanvas(e);
    if(p){tempLine.setAttribute('x1',p.x);tempLine.setAttribute('y1',p.y);tempLine.setAttribute('x2',mc.x);tempLine.setAttribute('y2',mc.y);}
});

document.addEventListener('mouseup',e=>{
    if(cardDrag){
        savePositions();
        cardDrag.el.style.zIndex=5;
        // Re-render cable body if side changed after drag
        if(cardDrag.key.startsWith('c-')){
            const cid=parseInt(cardDrag.key.slice(2));
            const cable=CABLES.find(c=>c.id==cid);
            if(cable){
                renderCableBody(document.getElementById('body-'+cardDrag.key),cable,cardDrag.key,getOri(cardDrag.key));
                markFusedDots();
                renderConnections();
            }
        }
        cardDrag=null;
        return;
    }
    if(!dragSrc)return;
    const target=e.target.closest('.fo-dot,.spl-port');
    if(target && target.id!==dragSrc.dotId){
        tryConnect(dragSrc,target,e.clientX,e.clientY);
    }
    if(tempLine){tempLine.remove();tempLine=null;}
    if(dragSrc?.el) dragSrc.el.classList.remove('selected');
    dragSrc=null;
});

// ── Touch support (mobile) ─────────────────────────────────────────────────────
function touchToCanvas(touch){
    const c=document.getElementById('fcanvas');
    const r=c.getBoundingClientRect();
    return{x:touch.clientX-r.left+c.scrollLeft,y:touch.clientY-r.top+c.scrollTop};
}

document.addEventListener('touchstart',e=>{
    const dot=e.target.closest('.fo-dot,.spl-port');
    if(dot&&!cardDrag){
        e.preventDefault();
        const t=e.touches[0];
        dragSrc={
            dotId:dot.id,dottype:dot.dataset.dottype,
            cableId:dot.dataset.cable?parseInt(dot.dataset.cable):null,
            fiberNum:dot.dataset.fiber?parseInt(dot.dataset.fiber):null,
            splId:dot.dataset.splId?parseInt(dot.dataset['splId']):null,
            splPorta:dot.dataset.splPorta||null,
            color:dot.dataset.color||'#ffcc00',el:dot,
        };
        dot.classList.add('selected');
        const svg=document.getElementById('fsvg');
        tempLine=document.createElementNS('http://www.w3.org/2000/svg','line');
        tempLine.setAttribute('stroke','#ffcc00');tempLine.setAttribute('stroke-width','2');
        tempLine.setAttribute('stroke-dasharray','6,3');tempLine.setAttribute('opacity','0.8');
        svg.appendChild(tempLine);
        const p=dotCenter(dot.id);
        if(p){tempLine.setAttribute('x1',p.x);tempLine.setAttribute('y1',p.y);tempLine.setAttribute('x2',p.x);tempLine.setAttribute('y2',p.y);}
    }
},{passive:false});

document.addEventListener('touchmove',e=>{
    if(cardDrag){
        e.preventDefault();
        const t=e.touches[0];
        const dx=t.clientX-cardDrag.startX,dy=t.clientY-cardDrag.startY;
        const nx=Math.max(0,cardDrag.startLeft+dx),ny=Math.max(0,cardDrag.startTop+dy);
        cardDrag.el.style.left=nx+'px';cardDrag.el.style.top=ny+'px';
        positions[cardDrag.key]={x:nx,y:ny};
        resizeCanvas();renderConnections();
        return;
    }
    if(!dragSrc||!tempLine)return;
    e.preventDefault();
    const t=e.touches[0];
    const p=dotCenter(dragSrc.dotId);
    const mc=touchToCanvas(t);
    if(p){tempLine.setAttribute('x1',p.x);tempLine.setAttribute('y1',p.y);tempLine.setAttribute('x2',mc.x);tempLine.setAttribute('y2',mc.y);}
},{passive:false});

document.addEventListener('touchend',e=>{
    if(cardDrag){
        savePositions();
        cardDrag.el.style.zIndex=5;
        if(cardDrag.key.startsWith('c-')){
            const cid=parseInt(cardDrag.key.slice(2));
            const cable=CABLES.find(c=>c.id==cid);
            if(cable){renderCableBody(document.getElementById('body-'+cardDrag.key),cable,cardDrag.key,getOri(cardDrag.key));markFusedDots();renderConnections();}
        }
        cardDrag=null;return;
    }
    if(!dragSrc)return;
    const t=e.changedTouches[0];
    const el=document.elementFromPoint(t.clientX,t.clientY);
    const target=el?el.closest('.fo-dot,.spl-port'):null;
    if(target&&target.id!==dragSrc.dotId){tryConnect(dragSrc,target,t.clientX,t.clientY);}
    if(tempLine){tempLine.remove();tempLine=null;}
    if(dragSrc?.el) dragSrc.el.classList.remove('selected');
    dragSrc=null;
});

function tryConnect(src, tgtEl, mx, my){
    const tgt={
        dotId:tgtEl.id,
        dottype:tgtEl.dataset.dottype,
        cableId:tgtEl.dataset.cable?parseInt(tgtEl.dataset.cable):null,
        fiberNum:tgtEl.dataset.fiber?parseInt(tgtEl.dataset.fiber):null,
        splId:tgtEl.dataset.splId?parseInt(tgtEl.dataset['splId']):null,
        splPorta:tgtEl.dataset.splPorta||null,
        color:tgtEl.dataset.color||'#ffcc00',
        el:tgtEl,
    };

    // Check if either dot is already fully fused (not passante, not splitter multi-port)
    const srcFused=isFused(src), tgtFused=isFused(tgt);
    if(srcFused||tgtFused){
        const which=srcFused?'Fibra de origem':'Fibra de destino';
        alert(`${which} já está fusionada. Remova a fusão existente antes de refazer.`);
        return;
    }

    // Determine connection type
    let tipo='emenda';
    let payload={};

    // Both fiber dots?
    if(src.dottype==='fiber' && tgt.dottype==='fiber'){
        // Same cable = passagem
        if(src.cableId===tgt.cableId){
            tipo='passante';
            // Only allow same fiber number for passagem (exact pass-through)
            payload={cabo_entrada_id:src.cableId,fibra_entrada:src.fiberNum,cabo_saida_id:src.cableId,fibra_saida:src.fiberNum,tipo:'passante'};
            showBandejaDialog(payload,src,tgt,mx,my,'Registrar Passagem','passante');
            return;
        }
        payload={cabo_entrada_id:src.cableId,fibra_entrada:src.fiberNum,cabo_saida_id:tgt.cableId,fibra_saida:tgt.fiberNum,tipo:'emenda'};
    }
    // Fiber → splitter port
    else if(src.dottype==='fiber' && tgt.dottype==='spl'){
        payload={cabo_entrada_id:src.cableId,fibra_entrada:src.fiberNum,spl_sai_id:parseInt(tgt.el.dataset.splId),spl_sai_porta:tgt.el.dataset.splPorta,tipo:'splitter'};
    }
    // Splitter port → fiber
    else if(src.dottype==='spl' && tgt.dottype==='fiber'){
        payload={spl_ent_id:parseInt(src.el.dataset.splId),spl_ent_porta:src.el.dataset.splPorta,cabo_saida_id:tgt.cableId,fibra_saida:tgt.fiberNum,tipo:'splitter'};
    }
    // Splitter → splitter
    else if(src.dottype==='spl' && tgt.dottype==='spl'){
        payload={spl_ent_id:parseInt(src.el.dataset.splId),spl_ent_porta:src.el.dataset.splPorta,spl_sai_id:parseInt(tgt.el.dataset.splId),spl_sai_porta:tgt.el.dataset.splPorta,tipo:'splitter'};
    }

    showBandejaDialog(payload,src,tgt,mx,my,'Nova Fusão','emenda');
}

function isFused(dot){
    if(!dot)return false;
    // Passante fibers are not "fused" in the blocking sense - they can still be unregistered
    const vis=activeBandeja==='all'?fusoes:fusoes.filter(f=>f.bandeja==activeBandeja);
    return vis.some(f=>{
        // Self-referential passante (same cable) doesn't block — fiber is just marked as passing through
        if(f.tipo==='passante' && f.cabo_entrada_id && f.cabo_entrada_id===f.cabo_saida_id) return false;
        const eid=dotIdFromFusao(f,'ent'),sid=dotIdFromFusao(f,'sai');
        return eid===dot.dotId||sid===dot.dotId;
    });
}

// ── Bandeja dialog ─────────────────────────────────────────────────────────────
function showBandejaDialog(payload,src,tgt,mx,my,title,tipo){
    pendingFusion={...payload,tipo};
    document.getElementById('bd-title').textContent=title;

    // Show tipo selector only when connecting two different cables (not same cable, not splitters)
    const isDiffCables = tipo==='emenda' && src.dottype==='fiber' && tgt.dottype==='fiber' && src.cableId!==tgt.cableId;
    document.getElementById('bd-tipo-row').style.display = isDiffCables ? '' : 'none';
    document.getElementById('bd-tipo-hint').style.display = 'none';
    if(isDiffCables){
        document.getElementById('bd-tipo-emenda').checked=true;
        document.querySelectorAll('[name="bd-tipo"]').forEach(r=>{
            r.onchange=()=>{
                const isPass=document.getElementById('bd-tipo-passante').checked;
                document.getElementById('bd-tipo-hint').style.display=isPass?'':'none';
                document.getElementById('bd-bandeja-row').style.display=isPass?'none':'';
            };
        });
    }

    const srcFc=src.dottype==='fiber'?cableFiberColor(CABLES.find(c=>c.id==src.cableId)||{},src.fiberNum):{nome:''};
    const tgtFc=tgt.dottype==='fiber'?cableFiberColor(CABLES.find(c=>c.id==tgt.cableId)||{},tgt.fiberNum):{nome:''};
    const srcLbl=src.dottype==='fiber'?fiberLabelById(src.cableId,src.fiberNum):'';
    const tgtLbl=tgt.dottype==='fiber'?fiberLabelById(tgt.cableId,tgt.fiberNum):'';
    const srcLabel=src.dottype==='fiber'
        ?`<span style="color:${src.color}">■</span> <b>${(CABLES.find(c=>c.id==src.cableId)||{}).codigo||'?'}</b> ${srcLbl} <span style="color:${src.color}">(${srcFc.nome})</span>`
        :`<span style="color:#ffcc00">◆</span> Splitter porta ${src.splPorta}`;
    const tgtLabel=tgt.dottype==='fiber'
        ?`<span style="color:${tgt.color}">■</span> <b>${(CABLES.find(c=>c.id==tgt.cableId)||{}).codigo||'?'}</b> ${tgtLbl} <span style="color:${tgt.color}">(${tgtFc.nome})</span>`
        :`<span style="color:#ffcc00">◆</span> Splitter porta ${tgt.splPorta}`;
    document.getElementById('bd-info').innerHTML=`${srcLabel}<br>→ ${tgtLabel}`;

    // Passagem doesn't need bandeja prompt - just use bandeja 1
    if(tipo==='passante'){
        document.getElementById('bd-bandeja-row').style.display='none';
    } else {
        document.getElementById('bd-bandeja-row').style.display='';
    }

    const dlg=document.getElementById('bd-dialog');
    dlg.style.display='block';
    dlg.style.left=Math.min(mx,window.innerWidth-260)+'px';
    dlg.style.top=Math.min(my,window.innerHeight-220)+'px';
    setTimeout(()=>document.getElementById('bd-bandeja').focus(),50);
}

document.getElementById('bd-cancel').addEventListener('click',()=>{document.getElementById('bd-dialog').style.display='none';pendingFusion=null;});
document.getElementById('bd-ok').addEventListener('click',confirmFusion);
document.getElementById('bd-bandeja').addEventListener('keydown',e=>{if(e.key==='Enter')confirmFusion();});

async function confirmFusion(){
    if(!pendingFusion)return;
    // If tipo selector is visible, override tipo with user's choice
    const tipoRow=document.getElementById('bd-tipo-row');
    if(tipoRow.style.display!=='none'){
        const chosen=document.querySelector('[name="bd-tipo"]:checked')?.value||'emenda';
        pendingFusion={...pendingFusion,tipo:chosen};
    }
    const bandeja=parseInt(document.getElementById('bd-bandeja').value)||1;
    document.getElementById('bd-dialog').style.display='none';
    const payload={...pendingFusion,bandeja};
    pendingFusion=null;
    const res=await fetch(location.href,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const data=await res.json();
    if(data.success){
        fusoes.push(data.fusao);
        buildBandejaTabs();
        resizeCanvas();
        renderConnections();
    } else {
        alert('Erro: '+(data.error||'Falha ao salvar'));
    }
}

async function deleteFusao(id){
    const res=await fetch(location.href,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({delete_id:id})});
    const data=await res.json();
    if(data.success){fusoes=fusoes.filter(f=>f.id!=id);buildBandejaTabs();renderConnections();}
}

// ── Right-click context menu ──────────────────────────────────────────────────
document.addEventListener('contextmenu',e=>{
    const dot=e.target.closest('.fo-dot');
    const spl=e.target.closest('.spl-port');
    if(!dot&&!spl)return;
    e.preventDefault();
    if(dot) showDotMenu(dot,e.clientX,e.clientY);
    else showSplitterPortMenu(spl,e.clientX,e.clientY);
});

function showDotMenu(dot,mx,my){
    const cid=parseInt(dot.dataset.cable),fn=parseInt(dot.dataset.fiber);
    const existing=fusoes.find(f=>dotIdFromFusao(f,'ent')===dot.id||dotIdFromFusao(f,'sai')===dot.id);
    const menu=document.getElementById('dot-menu');
    let html='';

    if(!existing){
        html+=`<div class="dot-menu-item" onclick="markPassagem(${cid},${fn});closeDotMenu()">
            <i class="fas fa-arrow-right" style="color:#ffaa00"></i> Marcar Passagem
        </div>`;
    } else if(existing.tipo==='passante'){
        html+=`<div class="dot-menu-item danger" onclick="deleteFusao(${existing.id});closeDotMenu()">
            <i class="fas fa-times"></i> Remover Passagem
        </div>`;
    } else {
        html+=`<div class="dot-menu-item danger" onclick="deleteFusao(${existing.id});closeDotMenu()">
            <i class="fas fa-times"></i> Remover Fusão
        </div>`;
    }
    html+=`<div class="dot-menu-item" onclick="verSinalFibra(${cid},${fn});closeDotMenu()">
        <i class="fas fa-signal" style="color:#00ccff"></i> Ver Sinal
    </div>`;
    html+=`<div class="dot-menu-item" onclick="verRotaFibra(${cid},${fn});closeDotMenu()">
        <i class="fas fa-route" style="color:#ffaa00"></i> Ver Rota da Fibra
    </div>`;
    html+=`<div class="dot-menu-item" style="color:#555;font-size:11px;padding-top:2px;padding-bottom:2px" onclick="closeDotMenu()">
        <i class="fas fa-times-circle"></i> Fechar
    </div>`;

    menu.innerHTML=html;
    menu.style.display='block';
    menu.style.left=Math.min(mx,window.innerWidth-180)+'px';
    menu.style.top=Math.min(my,window.innerHeight-120)+'px';
}

function closeDotMenu(){document.getElementById('dot-menu').style.display='none';}
document.addEventListener('click',e=>{if(!e.target.closest('#dot-menu'))closeDotMenu();});

function showSplitterPortMenu(spl,mx,my){
    const instId=parseInt(spl.dataset.splId);
    const porta=spl.dataset.splPorta;
    const isOutput=porta.startsWith('o');
    // Find connected fusão
    let existing=null;
    if(isOutput){
        existing=fusoes.find(f=>f.spl_ent_id==instId&&f.spl_ent_porta==porta);
    } else {
        existing=fusoes.find(f=>f.spl_sai_id==instId&&f.spl_sai_porta==porta);
    }
    const menu=document.getElementById('dot-menu');
    let html='';
    if(existing){
        html+=`<div class="dot-menu-item danger" onclick="deleteFusao(${existing.id});closeDotMenu()">
            <i class="fas fa-times"></i> Remover Fusão
        </div>`;
    }
    html+=`<div class="dot-menu-item" onclick="verSinalSplitter(${instId},'${porta}');closeDotMenu()">
        <i class="fas fa-signal" style="color:#00ccff"></i> Ver Sinal
    </div>`;
    html+=`<div class="dot-menu-item" onclick="verRotaSplitter(${instId},'${porta}');closeDotMenu()">
        <i class="fas fa-route" style="color:#ffaa00"></i> Ver Rota da Fibra
    </div>`;
    html+=`<div class="dot-menu-item" style="color:#555;font-size:11px;padding-top:2px;padding-bottom:2px" onclick="closeDotMenu()">
        <i class="fas fa-times-circle"></i> Fechar
    </div>`;
    menu.innerHTML=html;
    menu.style.display='block';
    menu.style.left=Math.min(mx,window.innerWidth-200)+'px';
    menu.style.top=Math.min(my,window.innerHeight-160)+'px';
}

async function verSinalSplitter(instId,porta){
    const box=document.getElementById('sinal-popup');
    const content=document.getElementById('sinal-popup-content');
    content.innerHTML='<i class="fas fa-spinner fa-spin"></i> Calculando...';
    box.style.display='flex';
    try{
        const r2=await fetch(`${BASE_URL}/api/rota.php?tipo=spl&id=${instId}&porta=${encodeURIComponent(porta)}`);
        const d=await r2.json();
        if(!d.success){content.innerHTML=`<span style="color:#ff4455">${d.error||'Erro'}</span>`;return;}
        const inst=INST_SPLITTERS.find(s=>s.id==instId);
        const isOut=porta.startsWith('o');
        const portaNum=parseInt(porta.slice(1))+1;
        const portaLabel=isOut?`Saída ${portaNum}`:`Entrada ${portaNum}`;
        const splLabel=inst?`${inst.spl_codigo} · ${portaLabel}`:portaLabel;
        const comprimento_m=d.rota?d.rota.filter(n=>n.t==='cabo').reduce((s,n)=>s+(n.comprimento_m||0),0):null;
        renderSinalPopup(content,d.sinal,d.aviso,splLabel,comprimento_m>0?comprimento_m:null);
    }catch(e){content.innerHTML='<span style="color:#ff4455">Erro de comunicação</span>';}
}

// ── Sinal helpers ─────────────────────────────────────────────────────────────
function sinalCor(dbm){
    if(dbm===null)return'#888';
    if(dbm>=-20)return'#00cc66';
    if(dbm>=-24)return'#66dd44';
    if(dbm>=-27)return'#ffcc00';
    if(dbm>=-30)return'#ff8800';
    return'#ff4455';
}

function fmtComp(m){
    if(m===null||m===undefined)return null;
    return m>=1000?(m/1000).toFixed(2)+' km':Math.round(m)+' m';
}

function renderSinalPopup(content,sinal,aviso,label,comprimento_m){
    const cor=sinalCor(sinal);
    const avisoHtml=aviso?`<div style="color:#ff8800;font-size:11px;margin-top:6px"><i class="fas fa-exclamation-triangle"></i> ${aviso}</div>`:'';
    const compHtml=comprimento_m!=null?`<div style="font-size:11px;color:#666;margin-top:4px"><i class="fas fa-ruler-horizontal" style="margin-right:3px"></i> Fibra total: ${fmtComp(comprimento_m)}</div>`:'';
    if(sinal===null){
        content.innerHTML=`<div style="font-size:13px;color:#888">${label}</div><div style="color:#888;font-style:italic;margin-top:4px">Sinal não rastreável até a OLT</div>${avisoHtml}`;
    } else {
        content.innerHTML=`<div style="font-size:12px;color:#888;margin-bottom:8px">${label}</div>
        <div style="font-size:28px;font-weight:700;color:${cor}">${sinal.toFixed(2)} <span style="font-size:14px">dBm</span></div>
        ${compHtml}${avisoHtml}`;
    }
}

async function verSinalFibra(caboId,fibraNum){
    const box=document.getElementById('sinal-popup');
    const content=document.getElementById('sinal-popup-content');
    content.innerHTML='<i class="fas fa-spinner fa-spin"></i> Calculando...';
    box.style.display='flex';
    try{
        const r=await fetch(`${BASE_URL}/api/sinal.php?tipo=fibra&id=${caboId}&fibra=${fibraNum}&elem_tipo=${ELEM_TIPO}&elem_id=${ELEM_ID}`);
        const d=await r.json();
        if(!d.success){content.innerHTML=`<span style="color:#ff4455">${d.error||'Erro'}</span>`;return;}
        const cabo=CABLES.find(c=>c.id==caboId);
        const label=cabo?cabo.codigo:`Cabo #${caboId}`;
        const fpt=cabo?(cabo.fibras_por_tubo||12):12;
        const t=Math.floor((fibraNum-1)/fpt)+1;
        const ff=((fibraNum-1)%fpt)+1;
        const flbl=`T${t}-F${String(ff).padStart(2,'0')}`;
        renderSinalPopup(content,d.sinal,d.aviso,`${label} · ${flbl}`,d.comprimento_m??null);
    }catch(e){content.innerHTML='<span style="color:#ff4455">Erro de comunicação</span>';}
}

// ── Ver Rota da Fibra ─────────────────────────────────────────────────────────
async function verRotaFibra(caboId,fibraNum){
    document.getElementById('rota-modal').style.display='flex';
    const content=document.getElementById('rota-content');
    content.innerHTML='<div style="text-align:center;color:#888"><i class="fas fa-spinner fa-spin"></i> Carregando rota...</div>';
    try{
        const r=await fetch(`${BASE_URL}/api/rota.php?tipo=fibra&id=${caboId}&fibra=${fibraNum}&elem_tipo=${ELEM_TIPO}&elem_id=${ELEM_ID}`);
        const d=await r.json();
        if(!d.success){content.innerHTML=`<div style="color:#ff4455">${d.error||'Erro'}</div>`;return;}
        content.innerHTML=renderRotaDiagram(d.rota,d.sinal,d.aviso);
    }catch(e){content.innerHTML='<div style="color:#ff4455">Erro de comunicação</div>';}
}

async function verRotaSplitter(instId,porta){
    document.getElementById('rota-modal').style.display='flex';
    const content=document.getElementById('rota-content');
    content.innerHTML='<div style="text-align:center;color:#888"><i class="fas fa-spinner fa-spin"></i> Carregando rota...</div>';
    try{
        const r=await fetch(`${BASE_URL}/api/rota.php?tipo=spl&id=${instId}&porta=${encodeURIComponent(porta)}`);
        const d=await r.json();
        if(!d.success){content.innerHTML=`<div style="color:#ff4455">${d.error||'Erro'}</div>`;return;}
        content.innerHTML=renderRotaDiagram(d.rota,d.sinal,d.aviso);
    }catch(e){content.innerHTML='<div style="color:#ff4455">Erro de comunicação</div>';}
}

// ── Rota diagram renderer ─────────────────────────────────────────────────────
function round3(v){return Math.round(v*1000)/1000;}

function rotaSinalBadge(dbm){
    if(dbm===null||dbm===undefined) return '<span style="color:#555;font-size:11px">—</span>';
    const c=sinalCor(dbm);
    return `<span style="font-size:13px;font-weight:700;color:${c}">${dbm>=0?'+':''}${dbm.toFixed(2)} dBm</span>`;
}


function renderRotaDiagram(rota,sinalFinal,aviso){
    if(!rota||!rota.length) return `<div style="padding:32px;text-align:center;color:#555">
        <i class="fas fa-unlink" style="font-size:32px;margin-bottom:12px;display:block"></i>
        <div style="font-size:13px">Fibra não conectada à OLT — verifique as fusões</div>
    </div>`;

    // ── Parse rota ────────────────────────────────────────────────────────────
    let oltNode=null;
    const segments=[];
    let i=0;
    if(rota[0]&&rota[0].t==='olt'){oltNode=rota[0];i=1;}
    while(i<rota.length){
        if(rota[i].t==='cabo'){
            const cable=rota[i++];
            const events=[];
            while(i<rota.length&&(rota[i].t==='splice'||rota[i].t==='splitter'))events.push(rota[i++]);
            segments.push({cable,events});
        } else {i++;}
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    const fmtM=m=>m>=1000?(m/1000).toFixed(2)+' km':Math.round(m)+' m';
    const sigQ=dbm=>{
        if(dbm>=-15)return{lbl:'Excelente',c:'#00ff88'};
        if(dbm>=-20)return{lbl:'Bom',c:'#88ff00'};
        if(dbm>=-25)return{lbl:'Aceitável',c:'#ffcc00'};
        if(dbm>=-30)return{lbl:'Crítico',c:'#ff8800'};
        return{lbl:'Ruim',c:'#ff4444'};
    };
    const sigBar=(dbm,w='64px')=>{
        const q=sigQ(dbm),p=Math.max(0,Math.min(100,((dbm+30)/30)*100));
        return `<div style="width:${w};height:3px;background:rgba(255,255,255,.08);border-radius:2px;overflow:hidden;margin-top:3px"><div style="width:${p}%;height:100%;background:${q.c}"></div></div>`;
    };
    const sigChip=dbm=>{
        if(dbm===null||dbm===undefined)return'';
        const q=sigQ(dbm);
        return `<div style="font-size:13px;font-weight:800;color:${q.c};line-height:1;white-space:nowrap">${dbm>=0?'+':''}${dbm.toFixed(2)}<span style="font-size:9px;margin-left:2px;font-weight:400">dBm</span></div>`;
    };
    const SC='rgba(51,153,255,.5)'; // snake connector color
    const hline=(color=SC,w=10)=>
        `<div style="width:${w}px;height:2px;background:${color};align-self:center;flex-shrink:0"></div>`;

    let totalDist=0;
    segments.forEach(s=>totalDist+=s.cable.comprimento_m||0);
    const sinalInicio=oltNode?oltNode.potencia_dbm:null;
    const totalLoss=sinalInicio!=null&&sinalFinal!=null?round3(sinalInicio-sinalFinal):null;
    let sinalAcum=sinalInicio;

    // ── Construir lista plana de itens {html, estW, isLine} ──────────────────
    const items=[];
    const addItem=(html,estW,isLine=false)=>items.push({html,estW,isLine});
    const addLine=(color=SC,w=10)=>addItem(hline(color,w),w,true);

    // OLT
    if(oltNode){
        addItem(`<div style="flex-shrink:0;width:110px;border-radius:9px;padding:10px 8px;background:rgba(0,173,255,.06);border:1.5px solid rgba(0,173,255,.25);display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:4px">
            <div style="width:28px;height:28px;border-radius:8px;background:rgba(0,173,255,.13);display:flex;align-items:center;justify-content:center;color:#00adff;font-size:13px"><i class="fas fa-server"></i></div>
            <div style="font-size:7px;color:#00adff;text-transform:uppercase;letter-spacing:1px;font-weight:700">OLT</div>
            <div style="font-size:10px;font-weight:700;line-height:1.3;word-break:break-word">${escHtml(oltNode.nome)}</div>
            <div style="font-size:9px;color:#555;line-height:1.3">${escHtml(String(oltNode.pon))}</div>
            ${sigChip(oltNode.potencia_dbm)}
            ${sigBar(oltNode.potencia_dbm)}
        </div>`,110);
        addLine();
    }

    // Segmentos
    for(const seg of segments){
        const cab=seg.cable;
        const sinalDepois=sinalAcum!=null?round3(sinalAcum-cab.perda_cabo):null;
        const corCab=sinalDepois!=null?sigQ(sinalDepois).c:'#555';

        addItem(`<div style="flex-shrink:0;align-self:center;border:1px solid rgba(51,153,255,.22);border-radius:8px;padding:6px 9px;background:rgba(8,11,15,.92);min-width:90px;text-align:center">
            <div style="font-size:10px;font-weight:700;color:#3399ff;white-space:nowrap"><i class="fas fa-grip-lines-vertical" style="font-size:9px;margin-right:2px"></i>${escHtml(cab.codigo)}</div>
            <div style="font-size:9px;color:#666;margin-top:2px">Fibra ${cab.fibra_num}</div>
            <div style="font-size:9px;color:#888;font-weight:600;margin-top:1px">${fmtM(cab.comprimento_m)}</div>
            <div style="font-size:8px;color:#444;margin-top:1px">−${cab.perda_cabo.toFixed(3)} dBm</div>
            ${sinalDepois!=null?`<div style="font-size:10px;font-weight:700;color:${corCab};margin-top:2px">${sinalDepois>=0?'+':''}${sinalDepois.toFixed(2)} dBm</div>`:''}
        </div>`,110);
        addLine();
        sinalAcum=sinalDepois;

        if(seg.events.length===0) continue;

        // Agrupa eventos por caixa (CEO/CTO) para exibir cada caixa separadamente
        // (ex: passante em CEO-A + emenda em CEO-B ficam em blocos distintos)
        const boxGroups=[];
        let curGrp=null;
        for(const ev of seg.events){
            const key=(ev.elem_tipo||'')+'|'+(ev.elem_cod||'');
            if(!curGrp||curGrp.key!==key){
                curGrp={key,elem_tipo:ev.elem_tipo||'',elem_cod:ev.elem_cod||'',events:[]};
                boxGroups.push(curGrp);
            }
            curGrp.events.push(ev);
        }

        for(const grp of boxGroups){
            const eTipo=grp.elem_tipo.toUpperCase();
            const eCod=grp.elem_cod;
            const isCTO=eTipo==='CTO';
            const bBdr=isCTO?'rgba(0,204,102,.28)':'rgba(153,51,255,.28)';
            const bBg =isCTO?'rgba(0,204,102,.04)':'rgba(153,51,255,.04)';
            const bClr=isCTO?'#00cc66':'#9933ff';
            const bIco=isCTO?'<i class="fas fa-network-wired"></i>':'<i class="fas fa-box-open"></i>';

            let bx=`<div style="flex-shrink:0;min-width:130px;max-width:170px;border:1.5px solid ${bBdr};border-radius:9px;background:${bBg};overflow:hidden;align-self:center">`;
            if(eCod){
                bx+=`<div style="padding:6px 8px;border-bottom:1px solid ${bBdr};display:flex;align-items:center;gap:5px">
                    <div style="width:22px;height:22px;border-radius:6px;background:rgba(128,128,128,.08);display:flex;align-items:center;justify-content:center;color:${bClr};font-size:11px;flex-shrink:0">${bIco}</div>
                    <div>
                        <div style="font-size:7px;color:${bClr};text-transform:uppercase;letter-spacing:.7px;font-weight:700">${eTipo}</div>
                        <div style="font-size:11px;font-weight:700;line-height:1.2">${escHtml(eCod)}</div>
                    </div>
                </div>`;
            }
            bx+=`<div style="padding:5px 7px;display:flex;flex-direction:column;gap:4px">`;
            for(const ev of grp.events){
                if(ev.t==='splice'){
                    const perda=ev.perda_db??0;
                    sinalAcum=sinalAcum!=null?round3(sinalAcum-perda):null;
                    const isPass=ev.tipo==='passante';
                    const tipoLbl=isPass?'Passante':'Emenda';
                    const passColor=isPass?'#ffaa00':'#ff8800';
                    const passBg=isPass?'rgba(255,170,0,.06)':'rgba(255,136,0,.06)';
                    const passBdr=isPass?'rgba(255,170,0,.2)':'rgba(255,136,0,.14)';
                    bx+=`<div style="padding:4px 6px;border-radius:6px;background:${passBg};border:1px solid ${passBdr}">
                        <div style="font-size:10px;font-weight:700;color:${passColor}">${tipoLbl}${isPass?' <span style="font-size:8px;opacity:.7">(passagem)</span>':''}</div>
                        <div style="font-size:9px;color:#555;margin-top:1px">Perda: −${perda} dBm</div>
                        ${sinalAcum!=null?`<div style="margin-top:3px">${sigChip(sinalAcum)}${sigBar(sinalAcum)}</div>`:''}
                    </div>`;
                } else if(ev.t==='splitter'){
                    const connLoss=ev.perda_emenda??0;
                    sinalAcum=sinalAcum!=null?round3(sinalAcum-connLoss):null;
                    const splLoss=ev.perda_db;
                    sinalAcum=sinalAcum!=null&&splLoss!=null?round3(sinalAcum-splLoss):null;
                    const porta=ev.porta?parseInt(ev.porta.slice(1))+1:null;
                    bx+=`<div style="padding:4px 6px;border-radius:6px;background:rgba(255,204,0,.05);border:1px solid rgba(255,204,0,.16)">
                        <div style="display:flex;align-items:center;gap:4px">
                            <span style="font-size:13px;color:#ffcc00;line-height:1;flex-shrink:0">▽</span>
                            <div>
                                <div style="font-size:10px;font-weight:700;color:#ffcc00">${escHtml(ev.codigo||'')} ${escHtml(ev.relacao||'')}${porta?` · S${porta}`:''}</div>
                                <div style="font-size:8px;color:#555;margin-top:1px">${connLoss?`Conn −${connLoss} · `:''}Div ${splLoss!=null?'−'+splLoss:'?'} dBm</div>
                            </div>
                        </div>
                        ${sinalAcum!=null?`<div style="margin-top:3px">${sigChip(sinalAcum)}${sigBar(sinalAcum)}</div>`:''}
                    </div>`;
                }
            }
            bx+=`</div></div>`;
            addItem(bx,150);
            addLine();
        }
    }

    // Ponto Final
    const qFinal=sinalFinal!=null?sigQ(sinalFinal):null;
    // Remove última hline antes do endpoint para substituir por cor verde
    if(items.length&&items[items.length-1].isLine) items.pop();
    addLine('rgba(0,204,102,.5)');
    addItem(`<div style="flex-shrink:0;width:100px;border:1.5px solid rgba(0,204,102,.25);border-radius:9px;padding:10px 8px;background:rgba(0,204,102,.04);display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:3px">
        <div style="width:24px;height:24px;border-radius:7px;background:rgba(0,204,102,.1);display:flex;align-items:center;justify-content:center;color:#00cc66;font-size:12px"><i class="fas fa-map-marker-alt"></i></div>
        <div style="font-size:7px;color:#00cc66;text-transform:uppercase;letter-spacing:.8px;font-weight:700">Destino</div>
        ${qFinal?`<div style="font-size:9px;color:${qFinal.c};font-weight:600">${qFinal.lbl}</div>`:''}
        ${sinalFinal!=null?`${sigChip(sinalFinal)}${sigBar(sinalFinal)}`:'<span style="color:#555;font-size:14px">—</span>'}
    </div>`,100);

    // ── Dividir itens em linhas (quebra de cobra) ──────────────────────────────
    const MAX_ROW_W=1160;
    const rows=[];
    let curRow=[],curW=0;
    for(const item of items){
        // Não iniciar nova linha com hline
        if(item.isLine&&curRow.length===0) continue;
        if(curW+item.estW>MAX_ROW_W&&curRow.length>0){
            // Remover hline final da linha antes de quebrar
            if(curRow.length&&curRow[curRow.length-1].isLine) curRow.pop();
            rows.push(curRow);
            curRow=item.isLine?[]:[item];
            curW=item.isLine?0:item.estW;
        } else {
            curRow.push(item);
            curW+=item.estW;
        }
    }
    if(curRow.length) rows.push(curRow);

    // ── Montar HTML ───────────────────────────────────────────────────────────
    let html=`<div style="display:flex;flex-direction:column;gap:0;font-family:inherit">`;

    // Barra de resumo
    html+=`<div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center;padding:7px 12px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:7px;font-size:10px;color:#777;margin-bottom:10px">
        <span><i class="fas fa-route" style="color:#555;margin-right:4px"></i>Distância <b style="color:#bbb">${fmtM(totalDist)}</b></span>
        ${totalLoss!=null?`<span><i class="fas fa-arrow-right" style="color:#ff8800;margin-right:4px"></i>Perda total <b style="color:#ff8800">−${totalLoss.toFixed(2)} dBm</b></span>`:''}
        <span><i class="fas fa-box" style="color:#555;margin-right:4px"></i>Caixas <b style="color:#bbb">${segments.filter(s=>s.events.length).length}</b></span>
    </div>`;

    // Linhas com conector cobra entre elas
    for(let r=0;r<rows.length;r++){
        const isLast=r===rows.length-1;
        html+=`<div style="display:flex;align-items:center;flex-wrap:nowrap">`;
        html+=rows[r].map(it=>it.html).join('');
        // Estender linha até a borda direita (para criar o caminho de cobra)
        if(!isLast) html+=`<div style="flex:1;height:2px;background:${SC};min-width:8px"></div>`;
        html+=`</div>`;
        if(!isLast){
            // Conector cobra: vertical direita → horizontal completa → vertical esquerda
            html+=`<div style="display:flex;flex-direction:column">
                <div style="align-self:flex-end;width:2px;height:20px;background:${SC}"></div>
                <div style="height:2px;background:${SC}"></div>
                <div style="align-self:flex-start;width:2px;height:20px;background:${SC}"></div>
            </div>`;
        }
    }

    // Aviso
    if(aviso){
        html+=`<div style="margin-top:10px;padding:5px 8px;border-radius:6px;background:rgba(255,136,0,.1);border:1px solid rgba(255,136,0,.18);font-size:10px;color:#ff8800;display:flex;align-items:center;gap:5px"><i class="fas fa-exclamation-triangle"></i>${escHtml(aviso)}</div>`;
    }

    html+=`</div>`;
    return html;
}

async function markPassagem(cableId,fiberNum){
    const payload={cabo_entrada_id:cableId,fibra_entrada:fiberNum,cabo_saida_id:cableId,fibra_saida:fiberNum,tipo:'passante',bandeja:1};
    const res=await fetch(location.href,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const data=await res.json();
    if(data.success){fusoes.push(data.fusao);buildBandejaTabs();renderConnections();}
}

// ── Splitter management ───────────────────────────────────────────────────────
function showAddSplitter(){
    const list=document.getElementById('spl-list');
    list.innerHTML='';
    ALL_SPLITTERS.forEach(s=>{
        list.innerHTML+=`<div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:8px;padding:10px 12px;margin-bottom:4px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                <div>
                    <div style="font-weight:700;font-size:13px">${escHtml(s.codigo)}</div>
                    <div style="font-size:11px;color:#555">${s.relacao}${s.nome?' — '+escHtml(s.nome):''}</div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <div style="font-size:11px;color:#888">Tipo:</div>
                <label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer">
                    <input type="radio" name="subtipo-${s.id}" value="derivacao" checked> Derivação
                </label>
                <label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer">
                    <input type="radio" name="subtipo-${s.id}" value="atendimento"> Atendimento
                </label>
                <button class="btn btn-sm btn-primary" onclick="addSplitter(${s.id},document.querySelector('input[name=subtipo-${s.id}]:checked').value)">
                    + Adicionar
                </button>
            </div>
        </div>`;
    });
    document.getElementById('spl-modal').style.display='flex';
}

async function addSplitter(sid, subtipo='derivacao'){
    const res=await fetch(location.href,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({add_splitter:sid,subtipo})});
    const data=await res.json();
    if(!data.success){alert('Erro: '+(data.error||'Falha ao adicionar splitter'));return;}
    INST_SPLITTERS.push(data.inst);
    document.getElementById('spl-modal').style.display='none';
    const canvas=document.getElementById('fcanvas');
    const cx=canvas.scrollLeft+Math.round(canvas.clientWidth/2);
    const cy=canvas.scrollTop+50;
    const key='s-'+data.inst.id;
    positions[key]={x:cx,y:cy};
    const card=createSplitterCard(data.inst,cx,cy);
    canvas.appendChild(card);
    makeDraggable(card,key);
    resizeCanvas();renderConnections();
}

async function removeSplitter(instId){
    if(!confirm('Remover este splitter do diagrama? As fusões vinculadas também serão removidas.'))return;
    const res=await fetch(location.href,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({delete_splitter:instId})});
    const data=await res.json();
    if(data.success){
        fusoes=fusoes.filter(f=>f.spl_ent_id!=instId&&f.spl_sai_id!=instId);
        const idx=INST_SPLITTERS.findIndex(s=>s.id==instId);
        if(idx>=0)INST_SPLITTERS.splice(idx,1);
        const card=document.getElementById('card-s-'+instId);
        if(card)card.remove();
        delete positions['s-'+instId];
        savePositions();
        buildBandejaTabs();renderConnections();
    }
}

// ── Bandeja tabs ──────────────────────────────────────────────────────────────
function buildBandejaTabs(){
    const bs=[...new Set(fusoes.map(f=>f.bandeja))].sort((a,b)=>a-b);
    const wrap=document.getElementById('bandeja-tabs');
    let html=`<button class="b-tab ${activeBandeja==='all'?'active':''}" onclick="setBandeja('all')">Todas</button>`;
    bs.forEach(b=>{html+=`<button class="b-tab ${activeBandeja==b?'active':''}" onclick="setBandeja(${b})">Bandeja ${b}</button>`;});
    wrap.innerHTML=html;
}
function setBandeja(b){activeBandeja=b;buildBandejaTabs();renderConnections();}

// ── Legend ────────────────────────────────────────────────────────────────────
function buildLegend(){
    const el=document.getElementById('legend-wrap');
    if(!el)return;
    for(let i=1;i<=12;i++){
        const c=FC_ABNT[i],bd=c.hex==='#EEEEEE'?'#aaa':c.hex;
        el.innerHTML+=`<div style="display:flex;align-items:center;gap:4px;font-size:11px;color:var(--text-muted)">
            <div style="width:12px;height:12px;border-radius:50%;background:${c.hex};border:1px solid ${bd}"></div>
            FO${i} ${c.nome}</div>`;
    }
    // Passagem legend
    el.innerHTML+=`<div style="display:flex;align-items:center;gap:4px;font-size:11px;color:var(--text-muted)">
        <div style="width:12px;height:12px;border-radius:50%;border:2px dashed #ffaa00;background:transparent"></div> Passagem</div>`;
}

// ── Export — captures the FULL diagram, not just visible area ────────────────
async function captureFullDiagram(){
    // Temporarily remove overflow and scroll constraints so html2canvas sees everything
    const wrap=document.getElementById('fcanvas');
    const origOverflow=wrap.style.overflow;
    const origMaxH=wrap.style.maxHeight;
    wrap.style.overflow='visible';
    wrap.style.maxHeight='none';
    resizeCanvas();
    // Give the browser a moment to reflow
    await new Promise(r=>setTimeout(r,80));
    const canvas=await html2canvas(wrap,{backgroundColor:'#080b0f',scale:2,useCORS:true,
        width:wrap.scrollWidth,height:wrap.scrollHeight,
        windowWidth:wrap.scrollWidth,windowHeight:wrap.scrollHeight});
    wrap.style.overflow=origOverflow;
    wrap.style.maxHeight=origMaxH;
    return canvas;
}

async function exportarPNG(){
    const canvas=await captureFullDiagram();
    const a=document.createElement('a');a.href=canvas.toDataURL('image/png');
    a.download=`fusoes_${ELEM_CODIGO}_${EXPORT_DATE_YMD}.png`;a.click();
}
async function exportarPDF(){
    const canvas=await captureFullDiagram();
    const imgData=canvas.toDataURL('image/png');
    const{jsPDF}=window.jspdf;
    // Choose orientation based on diagram proportions
    const isLandscape=canvas.width>canvas.height;
    const pdf=new jsPDF({orientation:isLandscape?'landscape':'portrait',unit:'mm',format:'a4'});
    const pw=pdf.internal.pageSize.getWidth(), ph=pdf.internal.pageSize.getHeight();
    const margin=10, titleH=10;
    const imgW=pw-margin*2;
    const imgH=Math.min((canvas.height/canvas.width)*imgW, ph-margin*2-titleH);
    pdf.setFillColor(8,11,15);pdf.rect(0,0,pw,ph,'F');
    pdf.setTextColor(180,180,180);pdf.setFontSize(10);
    pdf.text(`Mapa de Fusões — ${ELEM_CODIGO}  ·  ${ELEM_LABEL}  ·  ${EXPORT_DATE}`,margin,margin);
    pdf.addImage(imgData,'PNG',margin,margin+titleH,imgW,imgH);
    // If diagram is taller than one page, add extra pages
    const totalImgH=(canvas.height/canvas.width)*imgW;
    if(totalImgH>imgH){
        let yOffset=imgH;
        while(yOffset<totalImgH){
            pdf.addPage();
            pdf.setFillColor(8,11,15);pdf.rect(0,0,pw,ph,'F');
            pdf.addImage(imgData,'PNG',margin,-(yOffset)+margin+titleH,imgW,totalImgH);
            yOffset+=ph-margin*2;
        }
    }
    pdf.save(`fusoes_${ELEM_CODIGO}_${EXPORT_DATE_YMD}.pdf`);
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function escHtml(s){const d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}

// ── Init ──────────────────────────────────────────────────────────────────────
loadPositions();
loadOrientations();
buildLegend();
buildBandejaTabs();
buildCanvas();

window.addEventListener('resize',()=>{resizeCanvas();renderConnections();});
