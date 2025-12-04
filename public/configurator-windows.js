(function(){
  const DATA = (window.SCHMITKE_WINDOWS_DATA || {});
  if(!DATA.models){ console.error('SCHMITKE_WINDOWS_DATA missing'); return; }

  const root = document.getElementById('schmitke-window-configurator');
  if(!root) return;

  const STORAGE_KEY = 'schmitke_windows_offer_state';

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
  const OPTIONS = DATA.options || {};

  function first(list, fallback){
    if(Array.isArray(list) && list.length) return list[0];
    return fallback;
  }

  function getAvailableInteriorColors(exteriorKey){
    const exteriorList = OPTIONS.colorExterior || [];
    const exteriorColor = exteriorList.find(o=>o.key===exteriorKey);
    const whiteColor = exteriorList.find(o=>o.key==="weiss_glatt");

    if(exteriorColor?.key === "weiss_glatt"){
      return whiteColor ? [whiteColor] : [];
    }

    const result = [];
    if(exteriorColor) result.push(exteriorColor);
    if(whiteColor) result.push(whiteColor);
    return result;
  }

  const optionListFirstKey = (name) => {
    const list = OPTIONS?.[name] || [];
    return list[0]?.key || '';
  };

  function normalizeOptionKey(list, key){
    if(!Array.isArray(list) || list.length===0) return '';
    return list.some(o=>o.key===key) ? key : list[0].key;
  }

  function createDefaultConfig(){
    const modelIndex = 0;
    const model = DATA.models[modelIndex] || {};
    const defaultExterior = optionListFirstKey('colorExterior');
    const defaultInterior = getAvailableInteriorColors(defaultExterior)[0]?.key || optionListFirstKey('colorInterior');
    return {
      modelIndex,
      width: (DATA.sizes?.dinWidthsMm || [1000])[0] || 0,
      height: (DATA.sizes?.dinHeightsMm || [1000])[0] || 0,
      specialSize: false,
      material: model.material || (DATA.materials?.[0]?.code || ''),
      openingType: first(model.openingTypes, first(DATA.openingTypes, 'Dreh-Kipp')),
      handleSide: 'links',
      glazing: first(model.glazingOptions, (DATA.glass?.[0]?.code || '')),
      frame: (DATA.frames?.[0]?.code || ''),
      extras: new Set(),
      typeKey: optionListFirstKey('type'),
      manufacturerKey: optionListFirstKey('manufacturer'),
      profileKey: optionListFirstKey('profile'),
      formKey: optionListFirstKey('form'),
      sashesKey: optionListFirstKey('sashes'),
      openingKey: optionListFirstKey('opening'),
      colorExteriorKey: defaultExterior,
      colorInteriorKey: defaultInterior
    };
  }

  function normalizeConfig(cfg){
    const modelIndex = typeof cfg.modelIndex === 'number' ? Math.max(0, Math.min(DATA.models.length-1, cfg.modelIndex)) : 0;
    const model = DATA.models[modelIndex] || {};
    const openingFallback = first(model.openingTypes, first(DATA.openingTypes, 'Dreh-Kipp'));
    const glazingFallback = first(model.glazingOptions, (DATA.glass?.[0]?.code || ''));
    const result = {
      modelIndex,
      width: Number(cfg.width) || (DATA.sizes?.dinWidthsMm||[0])[0] || 0,
      height: Number(cfg.height)|| (DATA.sizes?.dinHeightsMm||[0])[0] || 0,
      specialSize: !!cfg.specialSize,
      material: cfg.material || model.material || (DATA.materials?.[0]?.code || ''),
      openingType: cfg.openingType || openingFallback,
      handleSide: cfg.handleSide === 'rechts' ? 'rechts' : 'links',
      glazing: cfg.glazing || glazingFallback,
      frame: cfg.frame || (DATA.frames?.[0]?.code || ''),
      extras: new Set(cfg.extras || []),
      typeKey: normalizeOptionKey(OPTIONS.type, cfg.typeKey),
      manufacturerKey: normalizeOptionKey(OPTIONS.manufacturer, cfg.manufacturerKey),
      profileKey: normalizeOptionKey(OPTIONS.profile, cfg.profileKey),
      formKey: normalizeOptionKey(OPTIONS.form, cfg.formKey),
      sashesKey: normalizeOptionKey(OPTIONS.sashes, cfg.sashesKey),
      openingKey: normalizeOptionKey(OPTIONS.opening, cfg.openingKey),
      colorExteriorKey: normalizeOptionKey(OPTIONS.colorExterior, cfg.colorExteriorKey)
    };
    const interiorOptions = getAvailableInteriorColors(result.colorExteriorKey);
    const preferredInterior = result.colorExteriorKey === 'weiss_glatt' ? 'weiss_glatt' : result.colorExteriorKey;
    result.colorInteriorKey = normalizeOptionKey(interiorOptions, cfg.colorInteriorKey || preferredInterior);
    return result;
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
        meta: parsed.meta && typeof parsed.meta==='object' ? {...parsed.meta} : {trade:'window', createdAt:new Date().toISOString()}
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
      const parsed = JSON.parse(pre);
      if(parsed?.abmessung){
        targetState.current.width = Number(parsed.abmessung.breite) || targetState.current.width;
        targetState.current.height = Number(parsed.abmessung.hoehe) || targetState.current.height;
      }
      targetState.meta = {...(targetState.meta||{}), trade:'window', windowMaterial: parsed.windowMaterial, windowTyp: parsed.windowTyp};
      const modelByTyp = new Map();
      DATA.models.forEach((m,idx)=>{
        const key = m.windowTypId ?? m.windowTyp ?? m.typId ?? m.typeId;
        if(key!==undefined && key!==null) modelByTyp.set(String(key), idx);
      });
      const typKey = parsed.windowTyp!==undefined && parsed.windowTyp!==null ? String(parsed.windowTyp) : null;
      if(typKey && modelByTyp.has(typKey)){
        targetState.current.modelIndex = modelByTyp.get(typKey);
      }
      const selectedModel = DATA.models[targetState.current.modelIndex] || {};
      if(parsed.windowMaterial){
        targetState.current.material = parsed.windowMaterial;
      } else if(selectedModel.material){
        targetState.current.material = selectedModel.material;
      }
      targetState.current = normalizeConfig(targetState.current);
    }catch(e){
      console.warn('Preconfig konnte nicht geladen werden', e);
    }
  }

  let state = loadFromStorage() || {
    current: createDefaultConfig(),
    positions: [],
    meta: {trade:'window', createdAt:new Date().toISOString()}
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
      const cfg = state.current;
      const model = DATA.models[cfg.modelIndex] || {};
      const preconfig = JSON.stringify({
        trade: 'window',
        abmessung: {breite: cfg.width, hoehe: cfg.height},
        windowMaterial: state.meta?.windowMaterial || model.windowMaterialId || model.materialId || cfg.material,
        windowTyp: state.meta?.windowTyp || model.windowTypId || model.typId || model.typeId
      });
      const url = new URL(window.location.href);
      url.searchParams.set('preconfig', preconfig);
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
        e.addEventListener(k.slice(2), v);
      } else if (k === "selected" || k === "checked" || k === "disabled") {
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

  function getOptionTitle(list, key){
    const found = (list||[]).find(o=>o.key===key);
    return found?.title || key || '–';
  }

  function formatConfigLines(cfg, idxLabel){
    const mod = DATA.models[cfg.modelIndex] || {};
    return [
      `${idxLabel}: ${mod.name || 'Fenster'} (${mod.system || mod.family || ''})`,
      `Typ: ${getOptionTitle(OPTIONS.type, cfg.typeKey)}`,
      `Hersteller: ${getOptionTitle(OPTIONS.manufacturer, cfg.manufacturerKey)}`,
      `Profil: ${getOptionTitle(OPTIONS.profile, cfg.profileKey)}`,
      `Form: ${getOptionTitle(OPTIONS.form, cfg.formKey)}`,
      `Flügel: ${getOptionTitle(OPTIONS.sashes, cfg.sashesKey)}`,
      `Öffnungsart: ${getOptionTitle(OPTIONS.opening, cfg.openingKey)}`,
      `Material: ${cfg.material || mod.material || ''}`,
      `Maß: ${cfg.width} x ${cfg.height} mm ${cfg.specialSize? '(Sondermaß)':'(DIN)'}`,
      `Bedienung: ${cfg.openingType} (${cfg.handleSide}-anschlag)` ,
      `Verglasung: ${cfg.glazing || 'k.A.'}`,
      `Rahmen: ${cfg.frame || ''}`,
      `Außenfarbe: ${getOptionTitle(OPTIONS.colorExterior, cfg.colorExteriorKey)}`,
      `Innenfarbe: ${getOptionTitle(getAvailableInteriorColors(cfg.colorExteriorKey), cfg.colorInteriorKey)}`,
      `Uw-Wert lt. Modell: ${mod.uw || 'k.A.'}`,
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
    state = {current:createDefaultConfig(), positions:[], meta:{trade:'window', createdAt:new Date().toISOString()}};
    editingIndex = null;
    render();
  }

  function renderOptionGrid(optionsList, selectedKey, onSelect){
    if(!Array.isArray(optionsList) || !optionsList.length){
      return el("div",{class:"scc-note"},["Keine Optionen verfügbar."]);
    }
    return el("div",{class:"swc-option-grid"},
      optionsList.map(option=>{
        const card = el("div",{class:"swc-option-card" + (option.key===selectedKey?" sel":""),onclick:()=>{onSelect(option.key);}});
        const img = option.image ? el("img",{src:option.image,alt:option.title||option.key,onerror:"this.style.display='none'"}) : el("div",{class:"swc-option-placeholder"});
        const title = el("div",{class:"swc-option-title"},[option.title || option.key || "Option"]);
        card.append(img,title);
        return card;
      })
    );
  }

  function render(){
    saveState();
    syncUrlWithCurrent();

    root.innerHTML="";
    const m = DATA.models[state.current.modelIndex];
    if(!m) return;

    const typeGrid = renderOptionGrid(OPTIONS.type, state.current.typeKey, (key)=>{state.current.typeKey=key; render();});
    const manufacturerGrid = renderOptionGrid(OPTIONS.manufacturer, state.current.manufacturerKey, (key)=>{state.current.manufacturerKey=key; render();});
    const profileGrid = renderOptionGrid(OPTIONS.profile, state.current.profileKey, (key)=>{state.current.profileKey=key; render();});
    const formGrid = renderOptionGrid(OPTIONS.form, state.current.formKey, (key)=>{state.current.formKey=key; render();});
    const sashesGrid = renderOptionGrid(OPTIONS.sashes, state.current.sashesKey, (key)=>{state.current.sashesKey=key; render();});
    const openingGrid = renderOptionGrid(OPTIONS.opening, state.current.openingKey, (key)=>{state.current.openingKey=key; render();});
    const availableInteriorColors = getAvailableInteriorColors(state.current.colorExteriorKey);
    if(!availableInteriorColors.some(o=>o.key===state.current.colorInteriorKey)){
      const fallback = state.current.colorExteriorKey === 'weiss_glatt' ? 'weiss_glatt' : state.current.colorExteriorKey;
      state.current.colorInteriorKey = normalizeOptionKey(availableInteriorColors, fallback);
    }
    const colorExteriorGrid = renderOptionGrid(OPTIONS.colorExterior, state.current.colorExteriorKey, (key)=>{
      state.current.colorExteriorKey = key;
      const interiorList = getAvailableInteriorColors(key);
      const preferred = key === 'weiss_glatt' ? 'weiss_glatt' : key;
      let nextInterior = state.current.colorInteriorKey;
      if(!interiorList.some(o=>o.key===nextInterior)){
        nextInterior = normalizeOptionKey(interiorList, preferred);
      }
      state.current.colorInteriorKey = nextInterior;
      render();
    });
    const colorInteriorGrid = renderOptionGrid(availableInteriorColors, state.current.colorInteriorKey, (key)=>{state.current.colorInteriorKey=key; render();});

    const modelsGrid = el("div",{class:"scc-models scc-window-models"},
      DATA.models.map((mod,idx)=>{
        const card=el("div",{class:"scc-model scc-window-model"+(idx===state.current.modelIndex?" sel":"")});
        const img=el("img",{src:mod.image||"",alt:mod.name||"",onerror:"this.style.opacity=.4; this.title='Bild fehlt'"});

        const p=el("div",{class:"p"},[
          el("div",{class:"t"},[mod.name||""]),
          el("div",{class:"m"},[(mod.system||mod.family||"")+" · "+(mod.material||"")]),
          el("div",{class:"scc-badges"},[
            mod.uw ? el("span",{class:"scc-badge"},[`Uw ${mod.uw}`]) : null,
            mod.sound ? el("span",{class:"scc-badge"},[`Rw ${mod.sound}`]) : null
          ].filter(Boolean))
        ]);
        card.append(img,p);
        card.onclick=()=>{state.current.modelIndex=idx; state.current.glazing=first(mod.glazingOptions, state.current.glazing); state.current.material=mod.material||state.current.material; state.current.openingType=first(mod.openingTypes, state.current.openingType); render();};
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
        el("input",{type:"number",min:"400",max:"2600",step:"1",id:"widthNum",value:state.current.width,oninput:(e)=>{state.current.width=Number(e.target.value||0); render();}}),
        el("div",{class:"scc-note"},["DIN-Maße empfohlen, Sondermaß wird geprüft."])
      ]),
      el("div",{},[
        el("label",{for:"heightNum"},["Sonder-Höhe (mm)"]),
        el("input",{type:"number",min:"400",max:"2600",step:"1",id:"heightNum",value:state.current.height,oninput:(e)=>{state.current.height=Number(e.target.value||0); render();}})
      ])
    ]);

    const openingSel = el("select",{id:"openingSel",onchange:(e)=>{state.current.openingType=e.target.value; render();}},
      (m.openingTypes || DATA.openingTypes || ["Dreh-Kipp","Dreh","Kipp","Fest"]).map(v=>el("option",{value:v,selected:state.current.openingType===v},[v]))
    );

    const handleSel = el("select",{id:"handleSel",onchange:(e)=>{state.current.handleSide=e.target.value; render();}},[
      el("option",{value:"links",selected:state.current.handleSide==="links"},["Griff links"]),
      el("option",{value:"rechts",selected:state.current.handleSide==="rechts"},["Griff rechts"])
    ]);

    const materialSel = el("select",{id:"materialSel",onchange:(e)=>{state.current.material=e.target.value; render();}},
      (DATA.materials||[]).map(mat=>el("option",{value:mat.code,selected:state.current.material===mat.code},[mat.label || mat.code]))
    );

    const glazingSel=el("select",{id:"glazingSel",onchange:(e)=>{state.current.glazing=e.target.value; render();}},
      (m.glazingOptions || DATA.glass || []).map(g=>{
        const value = typeof g === 'string' ? g : g.code;
        const label = typeof g === 'string' ? g : (g.label || g.code);
        return el("option",{value,selected:state.current.glazing===value},[label]);
      })
    );

    const frameSel=el("select",{id:"frameSel",onchange:(e)=>{state.current.frame=e.target.value; render();}},
      (DATA.frames||[]).map(f=>el("option",{value:f.code,selected:state.current.frame===f.code},[f.label]))
    );

    const checks=el("div",{class:"scc-checks scc-window-checks"},
      (DATA.extras||[]).map(ex=>{
        const c=el("input",{type:"checkbox",value:ex.code,checked:state.current.extras.has(ex.code),onchange:(e)=>{
          e.target.checked?state.current.extras.add(ex.code):state.current.extras.delete(ex.code); render();
        }});
        return el("label",{class:"scc-check"},[c, ex.label]);
      })
    );

    const summary = el("div",{class:"scc-summary scc-window-summary"},[
      el("h4",{},[editingIndex!==null ? `Position ${editingIndex+1} bearbeiten` : "Ihre Fenster-Konfiguration"]),
      el("div",{},["Typ: ", getOptionTitle(OPTIONS.type, state.current.typeKey)]),
      el("div",{},["Hersteller: ", getOptionTitle(OPTIONS.manufacturer, state.current.manufacturerKey)]),
      el("div",{},["Profil: ", getOptionTitle(OPTIONS.profile, state.current.profileKey)]),
      el("div",{},["Form: ", getOptionTitle(OPTIONS.form, state.current.formKey)]),
      el("div",{},["Flügel: ", getOptionTitle(OPTIONS.sashes, state.current.sashesKey)]),
      el("div",{},["Öffnungsart: ", getOptionTitle(OPTIONS.opening, state.current.openingKey)]),
      el("div",{},["Außenfarbe: ", getOptionTitle(OPTIONS.colorExterior, state.current.colorExteriorKey)]),
      el("div",{},["Innenfarbe: ", getOptionTitle(getAvailableInteriorColors(state.current.colorExteriorKey), state.current.colorInteriorKey)]),
      el("div",{},["Modell: ", el("strong",{},[m.name]), " (", m.system || m.family || "", ")"]),
      el("div",{},["Material: ", state.current.material || m.material || "–"]),
      el("div",{},["Uw-Wert lt. Modell: ", m.uw || "k.A."] ),
      el("div",{},["Fenstermaß: ", state.current.width+" × "+state.current.height+" mm", state.current.specialSize?" (Sondermaß)":" (DIN)"]),
      el("div",{},["Bedienung: ", state.current.openingType, " · Griff ", state.current.handleSide]),
      el("div",{},["Verglasung: ", state.current.glazing || "–"]),
      el("div",{},["Rahmen: ", state.current.frame || "–"]),
      el("div",{},["Extras: ", formatExtras(state.current)]),
      el("div",{class:"scc-note"},["Hinweis: Preise/Technik werden nach Prüfung durch Schmitke final bestätigt."])
    ]);

    const addBtn = el("button",{class:"scc-btn primary",type:"button",onclick:addPosition},[editingIndex!==null?"Position aktualisieren":"Neue Position anlegen"]);

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
          el("div",{},["Typ: ", getOptionTitle(OPTIONS.type, cfg.typeKey)]),
          el("div",{},["Hersteller: ", getOptionTitle(OPTIONS.manufacturer, cfg.manufacturerKey)]),
          el("div",{},["Profil: ", getOptionTitle(OPTIONS.profile, cfg.profileKey)]),
          el("div",{},["Form: ", getOptionTitle(OPTIONS.form, cfg.formKey)]),
          el("div",{},["Flügel: ", getOptionTitle(OPTIONS.sashes, cfg.sashesKey)]),
          el("div",{},["Öffnungsart: ", getOptionTitle(OPTIONS.opening, cfg.openingKey)]),
          el("div",{},["Farbkombination: außen ", getOptionTitle(OPTIONS.colorExterior, cfg.colorExteriorKey), ", innen ", getOptionTitle(getAvailableInteriorColors(cfg.colorExteriorKey), cfg.colorInteriorKey)]),
          el("div",{},["System: ", mod.system || mod.family || "–"]),
          el("div",{},["Modell: ", mod.name || "–"]),
          el("div",{},["Material: ", cfg.material || mod.material || "–"]),
          el("div",{},["Maße: ", `${cfg.width} × ${cfg.height} mm`, cfg.specialSize?" (Sondermaß)":" (DIN)"]),
          el("div",{},["Bedienung: ", cfg.openingType || "–", ", Griff ", cfg.handleSide]),
          el("div",{},["Verglasung: ", cfg.glazing || "–"]),
          el("div",{},["Rahmen: ", cfg.frame || "–"]),
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
      return encodeURIComponent(`Fenster-Konfiguration (Schmitke Bauelemente)\n\n${parts.join('\n\n')}`);
    };

    const btnMail = el("button",{class:"scc-btn primary",type:"button",onclick:()=>{
      if(!validateCurrent()) return;
      const to = DATA.email_to || "info@schmitke-bauelemente.de";
      window.location.href=`mailto:${to}?subject=${encodeURIComponent("Fenster-Konfiguration")}&body=${mailBody()}`;
    }},["Angebot anfordern"]);

    const btnCopy = el("button",{class:"scc-btn ghost",type:"button",onclick:async()=>{
      try{
        await navigator.clipboard.writeText(decodeURIComponent(mailBody()));
        btnCopy.textContent="Kopiert ✓";
        setTimeout(()=>btnCopy.textContent="Konfiguration kopieren",1200);
      }catch(e){alert("Kopieren nicht möglich. Bitte manuell markieren.");}
    }},["Konfiguration kopieren"]);

    const optionsCard = el("div",{class:"scc-card scc-window-card"},[
      el("div",{class:"scc-h"},["Typ"]),
      typeGrid,
      el("div",{class:"scc-h",style:"margin-top:14px"},["Hersteller"]),
      manufacturerGrid,
      el("div",{class:"scc-h",style:"margin-top:14px"},["Profil"]),
      profileGrid,
      el("div",{class:"scc-h",style:"margin-top:14px"},["Bauform"]),
      el("div",{class:"scc-sub"},["Form"]),
      formGrid,
      el("div",{class:"scc-sub",style:"margin-top:10px"},["Flügel"]),
      sashesGrid,
      el("div",{class:"scc-sub",style:"margin-top:10px"},["Öffnungsart"]),
      openingGrid,
      el("div",{class:"scc-h",style:"margin-top:14px"},["Farben"]),
      el("div",{class:"scc-sub"},["Außenfarbe"]),
      colorExteriorGrid,
      el("div",{class:"scc-sub",style:"margin-top:10px"},["Innenfarbe"]),
      colorInteriorGrid
    ]);

    const left = el("div",{class:"scc-card scc-window-card"},[
      el("div",{class:"scc-h"},["Fenster-Modell wählen"]),
      el("div",{class:"scc-sub"},["Bestseller-Fenster aus den Schmitke-Prospekten."]),
      modelsGrid,
      el("hr",{style:"border:none;border-top:1px solid #eee;margin:14px 0"}),
      el("div",{class:"scc-h"},["Maße & Öffnungsart"]),
      el("div",{class:"scc-row"},[
        el("div",{},[el("label",{},["DIN-Breite"]), widthSelect]),
        el("div",{},[el("label",{},["DIN-Höhe"]), heightSelect])
      ]),
      el("label",{style:"margin-top:8px;display:flex;gap:8px;align-items:center"},[specialToggle, "Sondermaß eingeben"]),
      specialInputs,
      el("div",{class:"scc-row",style:"margin-top:10px"},[
        el("div",{},[el("label",{},["Öffnungsart"]), openingSel]),
        el("div",{},[el("label",{},["Griff-Seite"]), handleSel])
      ]),
      el("div",{class:"scc-row",style:"margin-top:10px"},[
        el("div",{},[el("label",{},["Material / Profil"]), materialSel]),
        el("div",{},[el("label",{},["Verglasung"]), glazingSel])
      ])
    ]);

    const right = el("div",{class:"scc-card scc-window-card"},[
      el("div",{class:"scc-h"},["3) Rahmen, Extras & Zusammenfassung"]),
      el("div",{class:"scc-row"},[
        el("div",{},[el("label",{},["Rahmen-Ausführung"]), frameSel]),
        el("div",{},[el("label",{},["Extras"]), checks])
      ]),
      el("hr",{style:"border:none;border-top:1px solid #eee;margin:14px 0"}),
      summary,
      el("div",{class:"scc-actions"},[addBtn]),
      positionsList,
      el("div",{class:"scc-cta"},[btnMail, btnCopy])
    ]);

    const leftColumn = el("div",{},[optionsCard, left]);

    root.appendChild(el("div",{class:"scc-wrap scc-window-wrap"},[
      el("div",{class:"scc-grid scc-window-grid"},[leftColumn,right])
    ]));

    widthSelect.onchange=e=>{state.current.specialSize=false; state.current.width=Number(e.target.value); render();};
    heightSelect.onchange=e=>{state.current.specialSize=false; state.current.height=Number(e.target.value); render();};
  }

  render();
})();
