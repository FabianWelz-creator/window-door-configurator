
(function(){
  const DATA = (window.SCHMITKE_DOORS_DATA || {});
  if(!DATA.models){ console.error("SCHMITKE_DOORS_DATA missing"); return; }

  const root = document.getElementById("schmitke-door-configurator");
  if(!root) return;

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

  const state = {
    modelIndex: 0,
    width: (DATA.sizes?.dinWidthsMm || [860])[0],
    height: (DATA.sizes?.dinHeightsMm || [1985])[0],
    specialSize: false,
    construction: null,
    la: null,
    frame: (DATA.frames?.[0]?.code || "RR"),
    direction: "DIN Links",
    leafs: "1-flg.",
    extras: new Set()
  };

  function el(tag, attrs={}, children=[]){
    const e=document.createElement(tag);
    Object.entries(attrs).forEach(([k,v])=>{
      if(k==="class") e.className=v;
      else if(k==="html") e.innerHTML=v;
      else if(k.startsWith("on") && typeof v==="function") e.addEventListener(k.slice(2), v);
      else e.setAttribute(k,v);
    });
    children.forEach(c=>e.appendChild(typeof c==="string"?document.createTextNode(c):c));
    return e;
  }

  function render(){
    root.innerHTML="";
    const m = DATA.models[state.modelIndex];
    if(!m) return;

    if(!state.construction) state.construction = m.constructionDefault || "stumpf";
    if(!state.la) state.la = (m.laOptions && m.laOptions[0]) || "ohne LA";

    const constructionLabels = DATA.rules?.constructionLabels || {stumpf:"Stumpf", gefaelzt:"Gefälzt"};

    const modelsGrid = el("div",{class:"scc-models"},
      DATA.models.map((mod,idx)=>{
        const card=el("div",{class:"scc-model"+(idx===state.modelIndex?" sel":"")});
        const img=el("img",{src:mod.image||"",alt:mod.name||"",onerror:"this.style.opacity=.4; this.title='Bild fehlt'"});        
        const p=el("div",{class:"p"},[
          el("div",{class:"t"},[mod.name||""]),
          el("div",{class:"m"},[(mod.family||"")+" · "+(mod.finish||"")]),
          el("div",{class:"scc-badges"},[
            el("span",{class:"scc-badge"},[constructionLabels[mod.constructionDefault]||mod.constructionDefault||""])
          ])
        ]);
        card.append(img,p);
        card.onclick=()=>{state.modelIndex=idx; state.construction=mod.constructionDefault; state.la=(mod.laOptions && mod.laOptions[0])||"ohne LA"; render();}
        return card;
      })
    );

    const widthSelect = el("select",{id:"widthSel"},
      (DATA.sizes?.dinWidthsMm || []).map(v=>el("option",{value:v,selected:(!state.specialSize && state.width==v)},[v+" mm"]))
    );
    const heightSelect = el("select",{id:"heightSel"},
      (DATA.sizes?.dinHeightsMm || []).map(v=>el("option",{value:v,selected:(!state.specialSize && state.height==v)},[v+" mm"]))
    );

    const specialToggle = el("input",{type:"checkbox",id:"specialSize",checked:state.specialSize,onchange:(e)=>{state.specialSize=e.target.checked; render();}});

    const specialInputs = el("div",{class:"scc-row",style: state.specialSize?"":"display:none"},[
      el("div",{},[
        el("label",{for:"widthNum"},["Sonder-Breite (mm)"]),
        el("input",{type:"number",min:"500",max:"2000",step:"1",id:"widthNum",value:state.width,oninput:(e)=>{state.width=Number(e.target.value||0); render();}}),
        el("div",{class:"scc-note"},["DIN-Maße empfohlen, Sondermaß wird geprüft."])
      ]),
      el("div",{},[
        el("label",{for:"heightNum"},["Sonder-Höhe (mm)"]),
        el("input",{type:"number",min:"1800",max:"2600",step:"1",id:"heightNum",value:state.height,oninput:(e)=>{state.height=Number(e.target.value||0); render();}})
      ])
    ]);

    const constructionSel = el("select",{id:"constructionSel",onchange:(e)=>{state.construction=e.target.value; render();}},[
      el("option",{value:"stumpf",selected:state.construction==="stumpf"},["Stumpf einschlagend"]),
      el("option",{value:"gefaelzt",selected:state.construction==="gefaelzt"},["Gefälzt (Normfalz)"])
    ]);

    const laSel = el("select",{id:"laSel",onchange:(e)=>{state.la=e.target.value; render();}},
      (m.laOptions||["ohne LA"]).map(v=>el("option",{value:v,selected:state.la===v},[v]))
    );

    const frameSel=el("select",{id:"frameSel",onchange:(e)=>{state.frame=e.target.value; render();}},
      (DATA.frames||[]).map(f=>el("option",{value:f.code,selected:state.frame===f.code},[f.label]))
    );

    const dirSel=el("select",{id:"dirSel",onchange:(e)=>{state.direction=e.target.value; render();}},[
      el("option",{value:"DIN Links",selected:state.direction==="DIN Links"},["DIN Links"]),
      el("option",{value:"DIN Rechts",selected:state.direction==="DIN Rechts"},["DIN Rechts"])
    ]);

    const leafSel=el("select",{id:"leafSel",onchange:(e)=>{state.leafs=e.target.value; render();}},[
      el("option",{value:"1-flg.",selected:state.leafs==="1-flg."},["1-flügelig"]),
      el("option",{value:"2-flg.",selected:state.leafs==="2-flg."},["2-flügelig"])
    ]);

    const checks=el("div",{class:"scc-checks"},
      (DATA.extras||[]).map(ex=>{
        const c=el("input",{type:"checkbox",value:ex.code,checked:state.extras.has(ex.code),onchange:(e)=>{
          e.target.checked?state.extras.add(ex.code):state.extras.delete(ex.code); render();
        }});
        return el("label",{class:"scc-check"},[c, ex.label]);
      })
    );

    const summary = el("div",{class:"scc-summary"},[
      el("h4",{},["Ihre Konfiguration"]),
      el("div",{},["Modell: ", el("strong",{},[m.name]), " (", m.family, ")"]),
      el("div",{},["Oberfläche: ", m.finish]),
      el("div",{},["Bauart: ", constructionLabels[state.construction]]),
      el("div",{},["Türmaß: ", state.width+" × "+state.height+" mm", state.specialSize?" (Sondermaß)":" (DIN)"]),
      el("div",{},["Öffnungsrichtung: ", state.direction]),
      el("div",{},["Flügel: ", state.leafs]),
      el("div",{},["Zarge: ", state.frame]),
      el("div",{},["Lichtausschnitt: ", state.la]),
      el("div",{},["Extras: ", (state.extras.size?Array.from(state.extras).join(", "):"keine")]),
      el("div",{class:"scc-note"},["Hinweis: Preise/Technik werden nach Prüfung durch Schmitke final bestätigt."])
    ]);

    const mailBody = () => {
      const extras = state.extras.size ? Array.from(state.extras).join(", ") : "keine";
      return encodeURIComponent(
`Türen-Konfiguration (Schmitke Bauelemente)

Modell: ${m.name} (${m.family})
Oberfläche: ${m.finish}
Bauart: ${constructionLabels[state.construction]}
Türmaß: ${state.width} x ${state.height} mm ${state.specialSize?"(Sondermaß)":"(DIN)"}
Öffnungsrichtung: ${state.direction}
Flügel: ${state.leafs}
Zarge: ${state.frame}
Lichtausschnitt: ${state.la}
Extras: ${extras}

Bitte erstellen Sie mir ein unverbindliches Angebot.`
      );
    };

    const btnMail = el("button",{class:"scc-btn primary",type:"button",onclick:()=>{
      if(state.width<=0 || state.height<=0){alert("Bitte gültige Maße wählen.");return;}
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
      el("div",{class:"scc-cta"},[btnMail, btnCopy])
    ]);

    root.appendChild(el("div",{class:"scc-wrap"},[
      el("div",{class:"scc-grid"},[left,right])
    ]));

    widthSelect.onchange=e=>{state.width=Number(e.target.value); render();};
    heightSelect.onchange=e=>{state.height=Number(e.target.value); render();};
  }

  render();
})();
