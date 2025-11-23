(function(){
  const DATA = (window.SCHMITKE_DOORS_DATA || {});
  if(!DATA.models){ console.error("SCHMITKE_DOORS_DATA missing"); return; }

  const root = document.getElementById("schmitke-door-configurator");
  if(!root) return;

  const STORAGE_KEY = 'schmitke_doors_offer_state';

  // Apply design variables
  const d = DATA.design || {};
  const style = document.documentElement.style;
  if(d.primaryColor) style.setProperty('--scc-primary', d.primaryColor);
  if(d.accentColor)  style.setProperty('--scc-accent', d.accentColor);
  if(d.textColor)    style.setProperty('--scc-text', d.textColor);
  if(d.borderColor)  style.setProperty('--scc-border', d.borderColor);
  if(d.fontFamily)   style.setProperty('--scc-font', d.fontFamily);
  if(typeof d.buttonRadius !== 'undefined') style.setProperty('--scc-btn-radius', d.buttonRadius+'px');
  if(typeof d.cardRadius !== 'undefined') style.setProperty('--scc-card-radius', d.cardRadius+'px');

  const extrasMap = new Map((DATA.extras||[]).map(ex=>[ex.code, ex.label]));
  const constructionLabels = DATA.rules?.constructionLabels || {stumpf:"Stumpf", gefaelzt:"Gefälzt"};

  function createDefaultConfig(){
    const modelIndex = 0;
    const model = DATA.models[modelIndex] || {};
    return {
      modelIndex,
      width: (DATA.sizes?.dinWidthsMm || [860])[0] || 0,
      height: (DATA.sizes?.dinHeightsMm || [1985])[0] || 0,
      specialSize: false,
      construction: model.constructionDefault || "stumpf",
      la: (model.laOptions && model.laOptions[0]) || "ohne LA",
      frame: (DATA.frames?.[0]?.code || "RR"),
      direction: "DIN Links",
      leafs: "1-flg.",
      extras: new Set()
    };
  }

  function normalizeConfig(cfg){
    const modelIndex = typeof cfg.modelIndex === 'number' ? Math.max(0, Math.min(DATA.models.length-1, cfg.modelIndex)) : 0;
    const model = DATA.models[modelIndex] || {};
    return {
      modelIndex,
      width: Number(cfg.width) || (DATA.sizes?.dinWidthsMm||[0])[0] || 0,
      height: Number(cfg.height)|| (DATA.sizes?.dinHeightsMm||[0])[0] || 0,
      specialSize: !!cfg.specialSize,
      construction: cfg.construction || model.constructionDefault || "stumpf",
      la: cfg.la || (model.laOptions && model.laOptions[0]) || "ohne LA",
      frame: cfg.frame || (DATA.frames?.[0]?.code || "RR"),
      direction: cfg.direction || "DIN Links",
      leafs: cfg.leafs || "1-flg.",
      extras: new Set(cfg.extras || [])
    };
  }

  function toSerializable(cfg){
    return {...cfg, extras:Array.from(cfg.extras||[])};
  }

  function loadFromStorage(){
    try{
      const raw = localStorage.getItem(STORAGE_KEY);
      if(!raw) return null;
      const parsed = JSON.parse(raw);
      if(!parsed || typeof parsed !== 'object') return null;
      return {
        current: parsed.current ? normalizeConfig(parsed.current) : createDefaultConfig(),
        positions: Array.isArray(parsed.positions) ? parsed.positions.map(normalizeConfig) : [],
        meta: parsed.meta && typeof parsed.meta==='object' ? {...parsed.meta} : {trade:'door', createdAt:new Date().toISOString()}
      };
    }catch(e){
      return null;
    }
  }

  function applyPreconfig(targetState){
    const params = new URLSearchParams(window.location.search);
    const pre = params.get('preconfig');
    if(!pre) return;
    try{
      const decoded = JSON.parse(decodeURIComponent(atob(pre)));
      targetState.current = normalizeConfig(decoded);
    }catch(e){
      console.warn('Preconfig konnte nicht geladen werden', e);
    }
  }

  let state = loadFromStorage() || {
    current: createDefaultConfig(),
    positions: [],
    meta: {trade:'door', createdAt:new Date().toISOString()}
  };
  applyPreconfig(state);

  let editingIndex = null;

  function saveState(){
    try{
      localStorage.setItem(STORAGE_KEY, JSON.stringify({
        current: toSerializable(state.current),
        positions: state.positions.map(toSerializable),
        meta: state.meta
      }));
    }catch(e){ /* ignore */ }
  }

  function syncUrlWithCurrent(){
    try{
      const encoded = btoa(encodeURIComponent(JSON.stringify(toSerializable(state.current))));
      const url = new URL(window.location.href);
      url.searchParams.set('preconfig', encoded);
      window.history.replaceState({}, '', url.toString());
    }catch(e){ /* ignore */ }
  }

  function el(tag, attrs = {}, children = []) {
    const e = document.createElement(tag);

    Object.entries(attrs).forEach(([k, v]) => {
      if (k === "class") {
        e.className = v;
      } else if (k === "html") {
        e.innerHTML = v;
      } else if (k.startsWith("on") && typeof v === "function") {
        // e.g. onchange -> "change"
        e.addEventListener(k.slice(2), v);
      } else if (k === "selected" || k === "checked" || k === "disabled") {
        // Boolean properties must be set directly
        e[k] = !!v;
      } else if (v !== false && v !== null && v !== undefined) {
        e.setAttribute(k, v);
      }
    });

    children.forEach(c =>
      e.appendChild(typeof c === "string" ? document.createTextNode(c) : c)
    );

    return e;
  }

  function validateCurrent(){
    const m = DATA.models[state.current.modelIndex];
    if(!m){
      alert("Bitte ein Modell auswählen.");
      return false;
    }
    if(!state.current.width || !state.current.height){
      alert("Bitte gültige Maße wählen.");
      return false;
    }
    return true;
  }

  function formatExtras(cfg){
    if(!cfg.extras || !cfg.extras.size) return 'keine';
    return Array.from(cfg.extras).map(code=>extrasMap.get(code)||code).join(', ');
  }

  function formatConfigLines(cfg, idxLabel){
    const mod = DATA.models[cfg.modelIndex] || {};
    return [
      `${idxLabel}: ${mod.name || 'Modell'} (${mod.family || ''})`,
      `Bauart: ${constructionLabels[cfg.construction] || cfg.construction}`,
      `Maß: ${cfg.width} x ${cfg.height} mm ${cfg.specialSize? '(Sondermaß)':'(DIN)'}`,
      `Lichtausschnitt: ${cfg.la}`,
      `Oberfläche/Finish: ${mod.finish || ''}`,
      `Zarge: ${cfg.frame}`,
      `Richtung: ${cfg.direction}`,
      `Flügel: ${cfg.leafs}`,
      `Extras: ${formatExtras(cfg)}`
    ].join('\n');
  }

  function addPosition(){
    if(!validateCurrent()) return;
    const cfg = normalizeConfig(state.current);
    state.positions = state.positions.slice();
    if(editingIndex !== null && typeof state.positions[editingIndex] !== 'undefined'){
      state.positions[editingIndex] = cfg;
    } else {
      state.positions.push(cfg);
    }
    state.current = createDefaultConfig();
    editingIndex = null;
    render();
  }

  function removePosition(idx){
    state.positions = state.positions.filter((_,i)=>i!==idx);
    if(editingIndex !== null){
      if(editingIndex === idx) editingIndex = null;
      else if(editingIndex > idx) editingIndex -= 1;
    }
    render();
  }

  function editPosition(idx){
    const cfg = state.positions[idx];
    if(!cfg) return;
    state.current = normalizeConfig(cfg);
    editingIndex = idx;
    render();
  }

  function resetOffer(){
    try{ localStorage.removeItem(STORAGE_KEY); }catch(e){}
    state = {current:createDefaultConfig(), positions:[], meta:{trade:'door', createdAt:new Date().toISOString()}};
    editingIndex = null;
    render();
  }

  function render(){
    saveState();
    syncUrlWithCurrent();

    root.innerHTML="";
    const m = DATA.models[state.current.modelIndex];
    if(!m) return;

    if(!state.current.construction) state.current.construction = m.constructionDefault || "stumpf";
    if(!state.current.la) state.current.la = (m.laOptions && m.laOptions[0]) || "ohne LA";

    const modelsGrid = el("div",{class:"scc-models"},
      DATA.models.map((mod,idx)=>{
        const card=el("div",{class:"scc-model"+(idx===state.current.modelIndex?" sel":"")});
        const img=el("img",{src:mod.image||"",alt:mod.name||"",onerror:"this.style.opacity=.4; this.title='Bild fehlt'"});

        const p=el("div",{class:"p"},[
          el("div",{class:"t"},[mod.name||""]),
          el("div",{class:"m"},[(mod.family||"")+" · "+(mod.finish||"")]),
          el("div",{class:"scc-badges"},[
            el("span",{class:"scc-badge"},[constructionLabels[mod.constructionDefault]||mod.constructionDefault||""])
          ])
        ]);
        card.append(img,p);
        card.onclick=()=>{state.current.modelIndex=idx; state.current.construction=mod.constructionDefault; state.current.la=(mod.laOptions && mod.laOptions[0])||"ohne LA"; render();};
        return card;
      })
    );

    const widthSelect = el("select",{id:"widthSel"},
      (DATA.sizes?.dinWidthsMm || []).map(v=>el("option",{value:v,selected:(!state.current.specialSize && state.current.width==v)},[v+" mm"]))
    );
    const heightSelect = el("select",{id:"heightSel"},
      (DATA.sizes?.dinHeightsMm || []).map(v=>el("option",{value:v,selected:(!state.current.specialSize && state.current.height==v)},[v+" mm"]))
    );

    const specialToggle = el("input",{type:"checkbox",id:"specialSize",checked:state.current.specialSize,onchange:(e)=>{state.current.specialSize=e.target.checked; render();}});

    const specialInputs = el("div",{class:"scc-row",style: state.current.specialSize?"":"display:none"},[
      el("div",{},[
        el("label",{for:"widthNum"},["Sonder-Breite (mm)"]),
        el("input",{type:"number",min:"500",max:"2000",step:"1",id:"widthNum",value:state.current.width,oninput:(e)=>{state.current.width=Number(e.target.value||0); render();}}),
        el("div",{class:"scc-note"},["DIN-Maße empfohlen, Sondermaß wird geprüft."])
      ]),
      el("div",{},[
        el("label",{for:"heightNum"},["Sonder-Höhe (mm)"]),
        el("input",{type:"number",min:"1800",max:"2600",step:"1",id:"heightNum",value:state.current.height,oninput:(e)=>{state.current.height=Number(e.target.value||0); render();}})
      ])
    ]);

    const constructionSel = el("select",{id:"constructionSel",onchange:(e)=>{state.current.construction=e.target.value; render();}},[
      el("option",{value:"stumpf",selected:state.current.construction==="stumpf"},["Stumpf einschlagend"]),
      el("option",{value:"gefaelzt",selected:state.current.construction==="gefaelzt"},["Gefälzt (Normfalz)"])
    ]);

    const laSel = el("select",{id:"laSel",onchange:(e)=>{state.current.la=e.target.value; render();}},
      (m.laOptions||["ohne LA"]).map(v=>el("option",{value:v,selected:state.current.la===v},[v]))
    );

    const frameSel=el("select",{id:"frameSel",onchange:(e)=>{state.current.frame=e.target.value; render();}},
      (DATA.frames||[]).map(f=>el("option",{value:f.code,selected:state.current.frame===f.code},[f.label]))
    );

    const dirSel=el("select",{id:"dirSel",onchange:(e)=>{state.current.direction=e.target.value; render();}},[
      el("option",{value:"DIN Links",selected:state.current.direction==="DIN Links"},["DIN Links"]),
      el("option",{value:"DIN Rechts",selected:state.current.direction==="DIN Rechts"},["DIN Rechts"])
    ]);

    const leafSel=el("select",{id:"leafSel",onchange:(e)=>{state.current.leafs=e.target.value; render();}},[
      el("option",{value:"1-flg.",selected:state.current.leafs==="1-flg."},["1-flügelig"]),
      el("option",{value:"2-flg.",selected:state.current.leafs==="2-flg."},["2-flügelig"])
    ]);

    const checks=el("div",{class:"scc-checks"},
      (DATA.extras||[]).map(ex=>{
        const c=el("input",{type:"checkbox",value:ex.code,checked:state.current.extras.has(ex.code),onchange:(e)=>{
          e.target.checked?state.current.extras.add(ex.code):state.current.extras.delete(ex.code); render();
        }});
        return el("label",{class:"scc-check"},[c, ex.label]);
      })
    );

    const summary = el("div",{class:"scc-summary"},[
      el("h4",{},[editingIndex!==null ? `Position ${editingIndex+1} bearbeiten` : "Ihre Konfiguration"]),
      el("div",{},["Modell: ", el("strong",{},[m.name]), " (", m.family, ")"]),
      el("div",{},["Oberfläche: ", m.finish]),
      el("div",{},["Bauart: ", constructionLabels[state.current.construction]]),
      el("div",{},["Türmaß: ", state.current.width+" × "+state.current.height+" mm", state.current.specialSize?" (Sondermaß)":" (DIN)"]),
      el("div",{},["Öffnungsrichtung: ", state.current.direction]),
      el("div",{},["Flügel: ", state.current.leafs]),
      el("div",{},["Zarge: ", state.current.frame]),
      el("div",{},["Lichtausschnitt: ", state.current.la]),
      el("div",{},["Extras: ", formatExtras(state.current)]),
      el("div",{class:"scc-note"},["Hinweis: Preise/Technik werden nach Prüfung durch Schmitke final bestätigt."])
    ]);

    const addBtn = el("button",{class:"scc-btn primary",type:"button",onclick:addPosition},["Neue Position anlegen"]);

    const positionsList = el("div",{class:"scc-positions"},[
      el("div",{class:"scc-positions-header"},[
        el("h5",{},["Positionen"]),
        el("a",{href:"#",class:"scc-reset",onclick:(e)=>{e.preventDefault(); resetOffer();}},["Angebot zurücksetzen"])
      ]),
      state.positions.length ? null : el("div",{class:"scc-note"},["Noch keine Positionen gespeichert. Legen Sie eine neue Position an."])
    ].filter(Boolean));

    state.positions.forEach((cfg,idx)=>{
      const mod = DATA.models[cfg.modelIndex] || {};
      const item = el("div",{class:"scc-position"},[
        el("div",{class:"scc-position-head"},[
          el("div",{class:"scc-position-title"},[`Pos. ${idx+1}`]),
          el("div",{class:"scc-position-actions"},[
            el("button",{class:"scc-btn ghost",type:"button",onclick:()=>editPosition(idx)},["Bearbeiten"]),
            el("button",{class:"scc-btn ghost danger",type:"button",onclick:()=>removePosition(idx)},["Entfernen"])
          ])
        ]),
        el("div",{class:"scc-position-info"},[
          el("div",{},["Familie/Serie: ", mod.family || "–"]),
          el("div",{},["Modell: ", mod.name || "–"]),
          el("div",{},["Bauart: ", constructionLabels[cfg.construction] || cfg.construction]),
          el("div",{},["Maße: ", `${cfg.width} × ${cfg.height} mm`, cfg.specialSize?" (Sondermaß)":" (DIN)"]),
          el("div",{},["Lichtausschnitt: ", cfg.la || "–"]),
          el("div",{},["Finish: ", mod.finish || "–"]),
          el("div",{},["Extras: ", formatExtras(cfg)])
        ])
      ]);
      positionsList.appendChild(item);
    });

    const mailBody = () => {
      const parts = [];
      if(state.positions.length){
        parts.push("Gespeicherte Positionen:");
        state.positions.forEach((cfg,idx)=>{
          parts.push(formatConfigLines(cfg, `Pos. ${idx+1}`));
        });
      }
      parts.push(editingIndex!==null ? "Aktuelle Bearbeitung:" : (state.positions.length?"Weitere gewünschte Position (aktuell geöffnet):":"Aktuelle Position:"));
      parts.push(formatConfigLines(state.current, editingIndex!==null ? `Pos. ${editingIndex+1}` : "Neu"));
      return encodeURIComponent(`Türen-Konfiguration (Schmitke Bauelemente)\n\n${parts.join('\n\n')}`);
    };

    const btnMail = el("button",{class:"scc-btn primary",type:"button",onclick:()=>{
      if(!validateCurrent()) return;
      const to = DATA.email_to || "info@schmitke-bauelemente.de";
      window.location.href=`mailto:${to}?subject=${encodeURIComponent("Türen-Konfiguration")}&body=${mailBody()}`;
    }},["Angebot anfordern"]);

    const btnCopy = el("button",{class:"scc-btn ghost",type:"button",onclick:async()=>{
      try{
        await navigator.clipboard.writeText(decodeURIComponent(mailBody()));
        btnCopy.textContent="Kopiert ✓";
        setTimeout(()=>btnCopy.textContent="Konfiguration kopieren",1200);
      }catch(e){alert("Kopieren nicht möglich. Bitte manuell markieren.");}
    }},["Konfiguration kopieren"]);

    const left = el("div",{class:"scc-card"},[
      el("div",{class:"scc-h"},["1) Modell wählen"]),
      el("div",{class:"scc-sub"},["Bestseller-Modelle aus den Schmitke-Prospekten."]),
      modelsGrid,
      el("hr",{style:"border:none;border-top:1px solid #eee;margin:14px 0"}),
      el("div",{class:"scc-h"},["2) Maße & Ausführung"]),
      el("div",{class:"scc-row"},[
        el("div",{},[el("label",{},["DIN-Breite"]), widthSelect]),
        el("div",{},[el("label",{},["DIN-Höhe"]), heightSelect])
      ]),
      el("label",{style:"margin-top:8px;display:flex;gap:8px;align-items:center"},[specialToggle, "Sondermaß eingeben"]),
      specialInputs,
      el("div",{class:"scc-row",style:"margin-top:10px"},[
        el("div",{},[el("label",{},["Bauart / Falz"]), constructionSel]),
        el("div",{},[el("label",{},["Lichtausschnitt"]), laSel])
      ])
    ]);

    const right = el("div",{class:"scc-card"},[
      el("div",{class:"scc-h"},["3) Zarge, Richtung & Extras"]),
      el("div",{class:"scc-row"},[
        el("div",{},[el("label",{},["Zargen-Ausführung"]), frameSel]),
        el("div",{},[el("label",{},["Öffnungsrichtung"]), dirSel])
      ]),
      el("div",{class:"scc-row",style:"margin-top:10px"},[
        el("div",{},[el("label",{},["Flügel"]), leafSel]),
        el("div",{},[el("label",{},["Extras"]), checks])
      ]),
      el("hr",{style:"border:none;border-top:1px solid #eee;margin:14px 0"}),
      summary,
      el("div",{class:"scc-actions"},[addBtn]),
      positionsList,
      el("div",{class:"scc-cta"},[btnMail, btnCopy])
    ]);

    root.appendChild(el("div",{class:"scc-wrap"},[
      el("div",{class:"scc-grid"},[left,right])
    ]));

    widthSelect.onchange=e=>{state.current.specialSize=false; state.current.width=Number(e.target.value); render();};
    heightSelect.onchange=e=>{state.current.specialSize=false; state.current.height=Number(e.target.value); render();};
  }

  render();
})();
