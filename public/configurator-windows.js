(function(){
  const root = document.getElementById('schmitke-window-configurator');
  if(!root) return;
  const DATA = (window.SCHMITKE_WINDOWS_DATA || {});
  const LANG = (DATA.locale === 'de') ? 'de' : 'en';

  // Apply design variables early so v2 also benefits from them
  const d = DATA.design || {};
  const style = document.documentElement.style;
  if(d.primaryColor) style.setProperty('--scc-primary', d.primaryColor);
  if(d.accentColor)  style.setProperty('--scc-accent', d.accentColor);
  if(d.textColor)    style.setProperty('--scc-text', d.textColor);
  if(d.borderColor)  style.setProperty('--scc-border', d.borderColor);
  if(d.fontFamily)   style.setProperty('--scc-font', d.fontFamily);
  if(typeof d.buttonRadius !== 'undefined') style.setProperty('--scc-btn-radius', d.buttonRadius+'px');
  if(typeof d.cardRadius !== 'undefined') style.setProperty('--scc-card-radius', d.cardRadius+'px');

  function getLabel(label){
    if(label && typeof label === 'object'){
      return label[LANG] || label.de || label.en || Object.values(label)[0] || '';
    }
    return label || '';
  }

  if(DATA.v2 && Array.isArray(DATA.v2.elements) && DATA.v2.elements.length){
    renderV2(DATA.v2);
    return;
  }
  if(!DATA.models){ console.error('SCHMITKE_WINDOWS_DATA missing'); return; }

  function renderV2(settings){
    const elements = Array.isArray(settings.elements) ? settings.elements.slice() : [];
    elements.sort((a,b)=> (a.order||0)-(b.order||0));
    const optionsMap = settings.options_by_element || {};
    const elementLabels = {};
    const elementTypes = {};
    const optionLabelMap = {};
    elements.forEach(el=>{
      elementLabels[el.element_key] = getLabel(el.labels);
      elementTypes[el.element_key] = el.type || 'single';
    });
    Object.entries(optionsMap).forEach(([key, list])=>{
      const map = new Map();
      (list || []).forEach(opt=>{
        map.set(opt.option_code, getLabel(opt.labels) || opt.option_code || '');
      });
      optionLabelMap[key] = map;
    });
    const STORAGE_KEY_V2 = 'schmitke_windows_offer_state_v2';
    const state = {
      selections:{},
      positions:[],
      contact:{name:'', email:'', phone:'', address:'', message:''},
      positionDraft:''
    };
    const accordionEnabled = settings?.global_ui?.accordion_enabled !== false;
    const elementOpenState = new Map();
    const measurementLabels = (settings.measurements && settings.measurements.labels) ? settings.measurements.labels : {};
    const getMeasurementLabel = (key, fallback) => {
      const lbl = measurementLabels[key];
      if(lbl) return getLabel(lbl);
      return fallback || key;
    };
    const missingPositionsMessage = getLabel(settings?.messages?.missing_positions) || 'Bitte mindestens eine Position hinzufügen.';
    const getSectionOpen = (key, idx) => {
      if(!accordionEnabled) return true;
      if(elementOpenState.has(key)) return elementOpenState.get(key);
      const open = !!(elements[idx] && elements[idx].accordion_default_open) || idx === 0;
      elementOpenState.set(key, open);
      return open;
    };
    const setSectionOpen = (key, val) => {
      if(!accordionEnabled) return;
      elementOpenState.set(key, !!val);
    };

    const wrap = document.createElement('div');
    wrap.className = 'scc-wrap';
    const grid = document.createElement('div');
    grid.className = 'scc-grid';
    const main = document.createElement('div');
    main.className = 'scc-card';
    const summary = document.createElement('div');
    summary.className = 'scc-card';
    grid.appendChild(main);
    grid.appendChild(summary);
    wrap.appendChild(grid);
    wrap.classList.toggle('accordion-enabled', accordionEnabled);
    root.innerHTML = '';
    root.appendChild(wrap);

    function evaluateRuleConditions(rule){
      if(!rule || !rule.when || !Array.isArray(rule.when.conditions)) return false;
      const type = (rule.when.type || 'and').toLowerCase() === 'or' ? 'or' : 'and';
      const evalCond = (cond)=>{
        if(!cond || !cond.element_key) return false;
        const val = state.selections[cond.element_key];
        const operator = cond.operator || 'equals';
        const cmpVal = cond.value;
        switch(operator){
          case 'exists':
            if(Array.isArray(val)) return val.length>0;
            return val !== undefined && val !== null && val !== '';
          case 'equals':
            if(Array.isArray(val)) return val.includes(cmpVal);
            return val === cmpVal;
          case 'not_equals':
            if(Array.isArray(val)) return !val.includes(cmpVal);
            return val !== cmpVal;
          case 'in':
            if(!Array.isArray(cmpVal)) return false;
            if(Array.isArray(val)) return val.some(v=>cmpVal.includes(v));
            return cmpVal.includes(val);
          case 'not_in':
            if(!Array.isArray(cmpVal)) return true;
            if(Array.isArray(val)) return val.every(v=>!cmpVal.includes(v));
            return !cmpVal.includes(val);
          case 'contains':
            if(Array.isArray(val)) return val.includes(cmpVal);
            if(typeof val === 'string') return val.indexOf(cmpVal)>=0;
            return false;
          default:
            return false;
        }
      };
      const matches = rule.when.conditions.map(evalCond);
      return type === 'or' ? matches.some(Boolean) : matches.every(Boolean);
    }

    function evaluateRules(){
      const rules = Array.isArray(settings.rules) ? settings.rules.slice().sort((a,b)=> (a.priority||0)-(b.priority||0)) : [];
      const visibility = new Map();
      const hidden = new Set();
      const filters = new Map();
      const disabled = new Map();
      const required = new Map();
      rules.forEach(rule=>{
        if(!evaluateRuleConditions(rule)) return;
        const actions = rule.then || {};
        (actions.show_elements || []).forEach(k=> visibility.set(k, true));
        (actions.hide_elements || []).forEach(k=> { hidden.add(k); visibility.set(k, false); });
        (actions.filter_options || []).forEach(entry=>{
          if(!entry || !entry.element_key || !Array.isArray(entry.allowed)) return;
          const allowedSet = new Set(entry.allowed);
          if(filters.has(entry.element_key)){
            const cur = filters.get(entry.element_key);
            const intersection = new Set();
            cur.forEach(code=>{ if(allowedSet.has(code)) intersection.add(code); });
            filters.set(entry.element_key, intersection);
          } else {
            filters.set(entry.element_key, allowedSet);
          }
        });
        (actions.disable_options || []).forEach(entry=>{
          if(!entry || !entry.element_key || !Array.isArray(entry.codes)) return;
          const map = disabled.get(entry.element_key) || new Map();
          entry.codes.forEach(code=>{
            map.set(code, entry.reason || null);
          });
          disabled.set(entry.element_key, map);
        });
        (actions.set_required || []).forEach(k=> required.set(k, true));
        (actions.unset_required || []).forEach(k=> required.set(k, false));
      });
      return {visibility, hidden, filters, disabled, required};
    }

    function sanitizeSelections(ruleEffects){
      elements.forEach(el=>{
        const visibleOverride = ruleEffects.visibility.has(el.element_key) ? ruleEffects.visibility.get(el.element_key) : undefined;
        const isVisible = ruleEffects.hidden.has(el.element_key)
          ? false
          : (visibleOverride !== undefined ? visibleOverride : (el.visible_default !== false));
        if(!isVisible){
          delete state.selections[el.element_key];
          return;
        }
        const allowed = ruleEffects.filters.get(el.element_key);
        if(!allowed) return;
        const sel = state.selections[el.element_key];
        if(Array.isArray(sel)){
          const filtered = sel.filter(code=>allowed.has(code));
          if(filtered.length){ state.selections[el.element_key] = filtered; }
          else { delete state.selections[el.element_key]; }
        } else if(sel && !allowed.has(sel)){
          delete state.selections[el.element_key];
        }
      });
    }

    function renderElements(){
      main.innerHTML = '';
      const ruleEffects = evaluateRules();
      sanitizeSelections(ruleEffects);
      const requiredFlags = {};
      elements.forEach(function(el, idx){
        if(ruleEffects.hidden.has(el.element_key)) return;
        const visibleOverride = ruleEffects.visibility.has(el.element_key) ? ruleEffects.visibility.get(el.element_key) : undefined;
        const isVisible = (visibleOverride !== undefined) ? visibleOverride : (el.visible_default !== false);
        if(!isVisible) return;
        const required = ruleEffects.required.has(el.element_key) ? ruleEffects.required.get(el.element_key) : !!el.required_default;
        requiredFlags[el.element_key] = required;
        const sectionKey = el.element_key || ('el_'+idx);
        const section = document.createElement('div');
        section.className = 'scc-section';
        const header = document.createElement('div');
        header.className = 'scc-section-head';
        const headerText = document.createElement('div');
        headerText.className = 'scc-section-text';
        const title = document.createElement('h3');
        title.className = 'scc-h';
        title.textContent = getLabel(el.labels) + (required ? ' *' : '');
        headerText.appendChild(title);
        header.appendChild(headerText);
        const body = document.createElement('div');
        body.className = 'scc-section-body';
        const isOpen = getSectionOpen(sectionKey, idx);
        if(accordionEnabled){
          section.classList.toggle('open', isOpen);
          body.hidden = !isOpen;
          const toggle = document.createElement('button');
          toggle.type = 'button';
          toggle.className = 'scc-section-toggle';
          toggle.setAttribute('aria-expanded', isOpen);
          toggle.innerHTML = '<span class="scc-toggle-icon" aria-hidden="true"></span>';
          toggle.addEventListener('click', function(){
            const next = !section.classList.contains('open');
            setSectionOpen(sectionKey, next);
            section.classList.toggle('open', next);
            body.hidden = !next;
            toggle.setAttribute('aria-expanded', next);
          });
          header.appendChild(toggle);
        } else {
          section.classList.add('open');
        }
        section.appendChild(header);
        if(el.info && (el.info.de || el.info.en)){
          const info = document.createElement('p');
          info.className = 'scc-sub';
          info.textContent = getLabel(el.info);
          body.appendChild(info);
        }

        if(el.type === 'measurements'){
          const gridWrap = document.createElement('div');
          gridWrap.className = 'scc-row';
          ['width','height','quantity'].forEach(function(field){
            const label = document.createElement('label');
            label.textContent = getMeasurementLabel(field, field);
            const input = document.createElement('input');
            input.type = 'number';
            const measurement = state.selections[el.element_key] || {};
            input.value = (measurement[field] !== undefined && measurement[field] !== null) ? measurement[field] : '';
            input.addEventListener('input', function(){
              state.selections[el.element_key] = Object.assign({}, state.selections[el.element_key] || {}, {[field]: Number(input.value)||0});
              renderSummary();
            });
            label.appendChild(input);
            gridWrap.appendChild(label);
          });
          body.appendChild(gridWrap);
        } else if(el.type === 'upload'){
          const label = document.createElement('label');
          label.textContent = getMeasurementLabel('upload', 'Upload');
          const input = document.createElement('input');
          input.type = 'file';
          input.accept = 'image/*';
          input.addEventListener('change', function(){
            const file = input.files && input.files[0];
            const current = state.selections[el.element_key] || {};
            if(!file){
              state.selections[el.element_key] = Object.assign({}, current, { filename: '', dataUrl: '' });
              renderSummary();
              return;
            }
            const reader = new FileReader();
            reader.onload = function(){
              state.selections[el.element_key] = Object.assign({}, current, { filename: file.name, dataUrl: reader.result || '' });
              renderSummary();
            };
            reader.readAsDataURL(file);
          });
          label.appendChild(input);
          const note = document.createElement('textarea');
          note.rows = 2;
          note.placeholder = getMeasurementLabel('note', 'Notiz');
          const uploadState = state.selections[el.element_key] || {};
          note.value = uploadState.note || '';
          note.addEventListener('input', function(){
            state.selections[el.element_key] = Object.assign({}, state.selections[el.element_key] || {}, {note: note.value});
            renderSummary();
          });
          body.appendChild(label);
          body.appendChild(note);
        } else {
          const allowedSet = ruleEffects.filters.get(el.element_key);
          const opts = (optionsMap[el.element_key] || []).filter(opt=>{
            if(!allowedSet) return true;
            return allowedSet.has(opt.option_code);
          });
          const gridWrap = document.createElement('div');
          gridWrap.className = 'swc-option-grid';
          opts.forEach(function(opt){
            const tile = document.createElement('div');
            tile.className = 'swc-option-card';
            const selectedVal = state.selections[el.element_key];
            const isSelected = Array.isArray(selectedVal) ? selectedVal.includes(opt.option_code) : selectedVal === opt.option_code;
            if(isSelected) tile.classList.add('sel');
            const disabledMap = ruleEffects.disabled.get(el.element_key);
            const disableReason = disabledMap ? disabledMap.get(opt.option_code) : null;
            const isDisabled = !!disableReason;
            if(isDisabled){
              tile.classList.add('swc-option-disabled');
              const reasonLabel = getLabel(disableReason);
              if(reasonLabel) tile.title = reasonLabel;
            }
            if(opt.image){
              const img = document.createElement('img');
              img.src = opt.image;
              img.alt = getLabel(opt.labels) || opt.option_code || 'Option';
              img.onerror = function(){ this.style.display='none'; };
              tile.appendChild(img);
            } else {
              const placeholder = document.createElement('div');
              placeholder.className = 'swc-option-placeholder';
              tile.appendChild(placeholder);
            }
            const body = document.createElement('div');
            body.className = 'swc-option-text';
            const titleRow = document.createElement('div');
            titleRow.className = 'swc-option-title-row';
            const title = document.createElement('div');
            title.className = 'swc-option-title';
            title.textContent = getLabel(opt.labels);
            titleRow.appendChild(title);
            const infoText = getLabel(opt.info);
            if(infoText){
              const infoWrap = document.createElement('span');
              infoWrap.className = 'swc-option-info';
              infoWrap.setAttribute('aria-label', infoText);
              infoWrap.textContent = 'i';
              const tooltip = document.createElement('span');
              tooltip.className = 'swc-option-tooltip';
              tooltip.textContent = infoText;
              infoWrap.appendChild(tooltip);
              infoWrap.addEventListener('click', function(e){ e.stopPropagation(); });
              infoWrap.addEventListener('mousedown', function(e){ e.stopPropagation(); });
              titleRow.appendChild(infoWrap);
            }
            body.appendChild(titleRow);
            tile.appendChild(body);
            tile.addEventListener('click', function(){
              if(isDisabled) return;
              if(el.type === 'multi'){
                const cur = Array.isArray(state.selections[el.element_key]) ? state.selections[el.element_key].slice() : [];
                const idx = cur.indexOf(opt.option_code);
                if(idx >= 0){ cur.splice(idx,1); } else { cur.push(opt.option_code); }
                state.selections[el.element_key] = cur;
              } else {
                state.selections[el.element_key] = opt.option_code;
              }
              renderElements();
              renderSummary();
            });
            gridWrap.appendChild(tile);
          });
          body.appendChild(gridWrap);
        }
        section.appendChild(body);
        main.appendChild(section);
      });
      state.requiredFlags = requiredFlags;
    }

    function getOptionLabel(elementKey, code){
      if(!code) return '';
      const map = optionLabelMap[elementKey];
      if(map && map.has(code)) return map.get(code);
      return code;
    }

    function formatSelectionValue(elementKey, val){
      if(Array.isArray(val)){
        return val.map(code=>getOptionLabel(elementKey, code)).filter(Boolean).join(', ');
      }
      if(val && typeof val === 'object'){
        const parts = [];
        if(val.width) parts.push(getMeasurementLabel('width', 'B') + ' ' + val.width);
        if(val.height) parts.push(getMeasurementLabel('height', 'H') + ' ' + val.height);
        if(val.quantity) parts.push(getMeasurementLabel('quantity', 'Anz') + ' ' + val.quantity);
        if(val.filename) parts.push(val.filename);
        if(val.note) parts.push(val.note);
        return parts.join(' ');
      }
      return getOptionLabel(elementKey, val) || '';
    }

    function getOrderedSummaryItems(selections){
      const items = [];
      elements.forEach(function(el){
        const key = el.element_key;
        if(!key) return;
        const val = selections[key];
        if(Array.isArray(val) && !val.length) return;
        if(val === undefined || val === null || val === '') return;
        const label = elementLabels[key] || key;
        const formatted = formatSelectionValue(key, val);
        if(formatted === '') return;
        items.push({label, value: formatted});
      });
      return items;
    }

    function saveOfferState(){
      try{
        window.localStorage.setItem(STORAGE_KEY_V2, JSON.stringify({
          positions: state.positions,
          contact: state.contact
        }));
      }catch(e){}
    }

    function loadOfferState(){
      try{
        const raw = window.localStorage.getItem(STORAGE_KEY_V2);
        if(!raw) return;
        const parsed = JSON.parse(raw);
        if(parsed && Array.isArray(parsed.positions)) state.positions = parsed.positions;
        if(parsed && parsed.contact) state.contact = Object.assign(state.contact, parsed.contact);
      }catch(e){}
    }

    function renderSummary(){
      summary.innerHTML = '';
      const title = document.createElement('h3');
      title.className = 'scc-h';
      title.textContent = 'Zusammenfassung';
      summary.appendChild(title);
      const list = document.createElement('ul');
      list.className = 'scc-summary-list';
      const summaryItems = getOrderedSummaryItems(state.selections);
      summaryItems.forEach(function(itemData){
        const item = document.createElement('li');
        item.textContent = itemData.label + ': ' + itemData.value;
        list.appendChild(item);
      });
      if(!list.children.length){
        const empty = document.createElement('p');
        empty.textContent = 'Keine Auswahl getroffen.';
        summary.appendChild(empty);
      } else {
        summary.appendChild(list);
      }

      const positionWrap = document.createElement('div');
      positionWrap.className = 'scc-positions scc-positions-compact';
      const positionHead = document.createElement('div');
      positionHead.className = 'scc-positions-header';
      const posTitle = document.createElement('h5');
      posTitle.textContent = 'Positionen';
      positionHead.appendChild(posTitle);
      positionWrap.appendChild(positionHead);

      const nameLabel = document.createElement('label');
      nameLabel.textContent = 'Positionsname';
      const nameInput = document.createElement('input');
      nameInput.type = 'text';
      nameInput.value = state.positionDraft || '';
      nameInput.placeholder = 'z. B. Küche, EG links';
      nameInput.addEventListener('input', function(){
        state.positionDraft = nameInput.value;
      });
      nameLabel.appendChild(nameInput);
      positionWrap.appendChild(nameLabel);

      const addBtn = document.createElement('button');
      addBtn.type = 'button';
      addBtn.className = 'scc-btn primary';
      addBtn.textContent = 'Position hinzufügen';
      addBtn.addEventListener('click', function(){
        const items = getOrderedSummaryItems(state.selections);
        if(!items.length){
          alert('Bitte zuerst eine Auswahl treffen.');
          return;
        }
        const name = (state.positionDraft || '').trim() || `Position ${state.positions.length + 1}`;
        const uploads = elements.filter(el=>el.type === 'upload').map(function(el){
          const val = state.selections[el.element_key] || {};
          if(!val.dataUrl) return null;
          return {
            label: elementLabels[el.element_key] || el.element_key,
            filename: val.filename || '',
            note: val.note || '',
            data: val.dataUrl || ''
          };
        }).filter(Boolean);
        state.positions.push({
          name,
          summary: items.map(entry=>`${entry.label}: ${entry.value}`),
          uploads
        });
        state.positionDraft = '';
        saveOfferState();
        renderSummary();
      });
      positionWrap.appendChild(addBtn);

      if(state.positions.length){
        state.positions.forEach(function(pos, idx){
          const details = document.createElement('details');
          details.className = 'scc-position-summary';
          const summaryLine = document.createElement('summary');
          summaryLine.textContent = `Position ${idx + 1}: ${pos.name}`;
          details.appendChild(summaryLine);
          const posList = document.createElement('ul');
          posList.className = 'scc-summary-list scc-summary-list-compact';
          (pos.summary || []).forEach(function(line){
            const li = document.createElement('li');
            li.textContent = line;
            posList.appendChild(li);
          });
          details.appendChild(posList);
          const removeBtn = document.createElement('button');
          removeBtn.type = 'button';
          removeBtn.className = 'scc-btn ghost danger scc-position-remove';
          removeBtn.textContent = 'Entfernen';
          removeBtn.addEventListener('click', function(event){
            event.preventDefault();
            state.positions.splice(idx, 1);
            saveOfferState();
            renderSummary();
          });
          details.appendChild(removeBtn);
          positionWrap.appendChild(details);
        });
      } else {
        const emptyPositions = document.createElement('p');
        emptyPositions.className = 'scc-note';
        emptyPositions.textContent = 'Noch keine Position gespeichert.';
        positionWrap.appendChild(emptyPositions);
      }

      summary.appendChild(positionWrap);

      const contactWrap = document.createElement('div');
      contactWrap.className = 'scc-contact';
      const contactTitle = document.createElement('h4');
      contactTitle.textContent = 'Angebot anfragen';
      contactWrap.appendChild(contactTitle);

      const contactFields = [
        {key:'name', label:'Name *', type:'text'},
        {key:'email', label:'E-Mail *', type:'email'},
        {key:'phone', label:'Telefon', type:'text'},
        {key:'address', label:'Adresse', type:'text'}
      ];
      contactFields.forEach(function(field){
        const label = document.createElement('label');
        label.textContent = field.label;
        const input = document.createElement('input');
        input.type = field.type;
        input.value = state.contact[field.key] || '';
        input.addEventListener('input', function(){
          state.contact[field.key] = input.value;
          saveOfferState();
        });
        label.appendChild(input);
        contactWrap.appendChild(label);
      });

      const messageLabel = document.createElement('label');
      messageLabel.textContent = 'Nachricht';
      const messageInput = document.createElement('textarea');
      messageInput.rows = 3;
      messageInput.value = state.contact.message || '';
      messageInput.addEventListener('input', function(){
        state.contact.message = messageInput.value;
        saveOfferState();
      });
      messageLabel.appendChild(messageInput);
      contactWrap.appendChild(messageLabel);

      const requestBtn = document.createElement('button');
      requestBtn.type = 'button';
      requestBtn.className = 'scc-btn primary';
      requestBtn.textContent = 'Angebot anfragen';
      requestBtn.addEventListener('click', async function(){
        const name = (state.contact.name || '').trim();
        const email = (state.contact.email || '').trim();
        if(!name || !email){
          alert('Bitte Name und E-Mail angeben.');
          return;
        }
        if(!state.positions.length){
          alert(missingPositionsMessage);
          return;
        }
        requestBtn.disabled = true;
        requestBtn.textContent = 'Sende...';
        try{
          const payload = {
            contact: state.contact,
            positions: state.positions
          };
          const formData = new FormData();
          formData.append('action', 'schmitke_windows_request_quote');
          formData.append('nonce', DATA.ajax_nonce || '');
          formData.append('payload', JSON.stringify(payload));
          const response = await fetch(DATA.ajax_url, {method:'POST', body: formData});
          const result = await response.json();
          if(result && result.success){
            alert('Vielen Dank! Ihre Anfrage wurde versendet.');
            if(result.data && result.data.reset){
              state.positions = [];
              state.positionDraft = '';
              saveOfferState();
              renderSummary();
            }
          } else {
            const message = (result && result.data && result.data.message) ? result.data.message : 'Anfrage konnte nicht versendet werden.';
            alert(message);
          }
        }catch(e){
          alert('Anfrage konnte nicht versendet werden.');
        } finally {
          requestBtn.disabled = false;
          requestBtn.textContent = 'Angebot anfragen';
        }
      });

      contactWrap.appendChild(requestBtn);
      summary.appendChild(contactWrap);
    }

    loadOfferState();
    renderElements();
    renderSummary();
  }

  const STORAGE_KEY = 'schmitke_windows_offer_state';

  const extrasMap = new Map((DATA.extras||[]).map(ex=>[ex.code, getLabel(ex.label)]));
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

  function renderOptionGrid(optionsArray, currentKey, onSelect){
    if(!Array.isArray(optionsArray) || !optionsArray.length){
      return el("div",{class:"scc-note"},["Keine Optionen verfügbar."]);
    }

    return el("div",{class:"swc-option-grid"},
      optionsArray.map(option=>{
        const isSelected = option.key===currentKey;
        const card = el("div",{class:`swc-option-card${isSelected?" sel":""}`,onclick:()=>{ if(onSelect) onSelect(option.key); }});
        if(option.image){
          card.appendChild(el("img",{src:option.image,alt:option.title||option.key||"Option",onerror:"this.style.display='none'"}));
        } else {
          card.appendChild(el("div",{class:"swc-option-placeholder"}));
        }

        const text=el("div",{class:"swc-option-text"},[
          el("div",{class:"swc-option-title"},[option.title || option.key || "Option"])
        ]);
        if(option.subtitle){
          text.appendChild(el("div",{class:"swc-option-subtitle"},[option.subtitle]));
        }
        card.appendChild(text);
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
