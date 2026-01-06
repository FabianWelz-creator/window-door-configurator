(function($){
  const data = window.SCHMITKE_CONFIG_V2_ADMIN || {};
  const app = $('#schmitke-configurator-v2-app');
  if(!app.length) return;
  const hidden = $('#schmitke-configurator-v2-input');
  const jsonFallback = $('#schmitke-configurator-v2-json');
  const form = $('#schmitke-configurator-v2-form');
  app.on('change input', 'input, textarea, select', function(){
    syncInputs();
  });

  function defaultDesign(){
    return {
      primaryColor: '#111111',
      accentColor: '#f2f2f2',
      textColor: '#111111',
      borderColor: '#e7e7e7',
      fontFamily: 'system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif',
      buttonRadius: 10,
      cardRadius: 14
    };
  }

  function defaultMeasurements(){
    return {
      labels: {
        width: {de:'Breite (mm)', en:'Width (mm)'},
        height: {de:'Höhe (mm)', en:'Height (mm)'},
        quantity: {de:'Anzahl', en:'Quantity'},
        note: {de:'Notiz', en:'Note'},
        upload: {de:'Upload', en:'Upload'}
      }
    };
  }

  const defaults = {
    email_to: '',
    design: defaultDesign(),
    measurements: defaultMeasurements(),
    elements: [],
    options_by_element: {},
    rules: [],
    global_ui: {sticky_summary_enabled:true, accordion_enabled:true, locale_mode:'wp_locale'}
  };
  let settings = $.extend(true, {}, defaults, data.settings || {});
  const elementOpenState = new Map();

  function captureElementOpenState(){
    app.find('.v2-element').each(function(){
      const uid = $(this).data('uid');
      if(!uid) return;
      elementOpenState.set(uid, $(this).hasClass('open'));
    });
  }

  function getElementOpenState(el, idx){
    if(elementOpenState.has(el.__uid)) return elementOpenState.get(el.__uid);
    return !!el.accordion_default_open || idx === 0;
  }

  function generateUid(){
    return 'el_' + Math.random().toString(36).slice(2) + '_' + Date.now();
  }

  function normalizeSettings(){
    settings.design = $.extend(true, {}, defaultDesign(), settings.design || {});
    settings.measurements = $.extend(true, {}, defaultMeasurements(), settings.measurements || {});
    const incomingOptions = settings.options_by_element || {};
    const mappedOptions = {};
    settings.elements = (settings.elements || []).map(function(el, idx){
      if(!el.__uid) el.__uid = generateUid();
      el.labels = el.labels || {de:'', en:''};
      el.info = el.info || {de:'', en:''};
      if(typeof el.order === 'undefined') el.order = idx + 1;
      const optKey = incomingOptions.hasOwnProperty(el.__uid) ? el.__uid : el.element_key;
      if(optKey && incomingOptions[optKey]){
        mappedOptions[el.__uid] = incomingOptions[optKey];
      }
      return el;
    });
    settings.options_by_element = mappedOptions;
    settings.rules = (settings.rules || []).map(function(rule){
      rule.when = rule.when || {conditions: []};
      rule.then = rule.then || {show_elements: [], hide_elements: [], filter_options: [], disable_options: [], set_required: [], unset_required: []};
      return rule;
    });
  }

  normalizeSettings();

  const attachmentCache = {};
  function resolveAttachmentUrl(id){
    return new Promise(function(resolve){
      if(!id || !wp || !wp.media || !wp.media.attachment){
        resolve('');
        return;
      }
      if(attachmentCache[id]){
        resolve(attachmentCache[id]);
        return;
      }
      try{
        const attachment = wp.media.attachment(id);
        if(!attachment){
          resolve('');
          return;
        }
        attachment.fetch().then(function(){
          const data = attachment.toJSON ? attachment.toJSON() : attachment.attributes || {};
          const url = (data.sizes && data.sizes.thumbnail && data.sizes.thumbnail.url) ? data.sizes.thumbnail.url : (data.url || '');
          attachmentCache[id] = url || '';
          resolve(url || '');
        }).catch(function(){ resolve(''); });
      } catch(e){
        resolve('');
      }
    });
  }

  function pickOptionImage(option, previewEl){
    if(!wp || !wp.media) return;
    const frame = wp.media({
      title: 'Bild auswählen',
      button: { text: 'Übernehmen' },
      multiple: false
    });
    frame.on('select', function(){
      const attachment = frame.state().get('selection').first().toJSON();
      option.image_id = attachment.id || 0;
      const url = (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) ? attachment.sizes.thumbnail.url : (attachment.url || '');
      if(previewEl && previewEl.length){
        previewEl.attr('src', url);
      }
      syncInputs();
    });
    frame.open();
  }

  function applyOptionPreview(previewEl, imageId){
    if(!previewEl || !previewEl.length) return;
    if(!imageId){
      previewEl.attr('src','');
      return;
    }
    resolveAttachmentUrl(imageId).then(function(url){
      if(previewEl.closest('body').length === 0) return;
      previewEl.attr('src', url || '');
    });
  }

  function syncInputs(){
    hidden.val(JSON.stringify(settings));
    if(jsonFallback.length){
      jsonFallback.val(JSON.stringify(settings, null, 2));
    }
  }

  if(form.length){
    form.on('submit', function(){
      syncInputs();
    });
  }

  if(jsonFallback.length){
    jsonFallback.on('change blur', function(){
      try{
        const parsed = JSON.parse($(this).val());
        if(parsed && typeof parsed === 'object'){
          settings = $.extend(true, {}, defaults, parsed);
          normalizeSettings();
          render();
        }
      }catch(e){/* ignore parse errors */}
    });
  }

  function elementTemplate(el, idx){
    const card = $('<div class="schmitke-card v2-element"></div>').attr('data-uid', el.__uid);
    const startOpen = getElementOpenState(el, idx);
    if(startOpen) card.addClass('open');
    const head = $('<div class="schmitke-model-head"></div>').appendTo(card);
    const elLabelDe = (el.labels && el.labels.de) ? el.labels.de : '';
    const titleEl = $('<strong class="schmitke-model-title"></strong>').text(elLabelDe || el.element_key || 'Element').appendTo(head);
    const actions = $('<div class="schmitke-model-actions"></div>').appendTo(head);
    $('<button type="button" class="button schmitke-toggle"></button>')
      .text(startOpen ? 'Schließen' : 'Öffnen')
      .appendTo(actions);
    $('<button type="button" class="button">'+('⬆')+'</button>').on('click', function(){ moveElement(idx, -1); }).appendTo(actions);
    $('<button type="button" class="button">'+('⬇')+'</button>').on('click', function(){ moveElement(idx, 1); }).appendTo(actions);
    $('<button type="button" class="button button-link-delete">'+('Löschen')+'</button>').on('click', function(){
      settings.elements.splice(idx,1);
      render();
    }).appendTo(actions);

    const body = $('<div class="schmitke-model-body"></div>').appendTo(card);
    const grid = $('<div class="schmitke-model-grid"></div>').appendTo(body);

    $('<label>Element Key <input type="text"></label>').find('input').val(el.element_key).on('input', function(){
      el.element_key = $(this).val();
      const titleDe = (el.labels && el.labels.de) ? el.labels.de : '';
      titleEl.text(titleDe || el.element_key || 'Element');
      syncInputs();
    }).end().appendTo(grid);

    const typeSel = $('<select></select>')
      .append('<option value="single">single</option>')
      .append('<option value="multi">multi</option>')
      .append('<option value="measurements">measurements</option>')
      .append('<option value="upload">upload</option>')
      .val(el.type || 'single')
      .on('change', function(){ el.type = $(this).val(); });
    $('<label>Type</label>').append(typeSel).appendTo(grid);

    $('<label>Label DE <input type="text"></label>').find('input').val((el.labels && el.labels.de) || '').on('input', function(){ el.labels.de = $(this).val(); titleEl.text(el.labels.de || el.element_key || 'Element'); }).end().appendTo(grid);
    $('<label>Label EN <input type="text"></label>').find('input').val((el.labels && el.labels.en) || '').on('input', function(){ el.labels.en = $(this).val(); }).end().appendTo(grid);
    $('<label>Info DE <textarea rows="2"></textarea></label>').find('textarea').val((el.info && el.info.de) || '').on('input', function(){ el.info.de = $(this).val(); }).end().appendTo(grid);
    $('<label>Info EN <textarea rows="2"></textarea></label>').find('textarea').val((el.info && el.info.en) || '').on('input', function(){ el.info.en = $(this).val(); }).end().appendTo(grid);

    addToggle(grid, 'visible_default', el, 'Standard sichtbar');
    addToggle(grid, 'required_default', el, 'Pflichtfeld');
    addToggle(grid, 'accordion_default_open', el, 'Accordion geöffnet');
    addToggle(grid, 'allow_search', el, 'Suche erlauben');

    addNumber(grid, 'order', el, 'Reihenfolge');
    addNumber(grid, 'ui_columns_desktop', el, 'Spalten Desktop', 1, 4);
    addNumber(grid, 'ui_columns_tablet', el, 'Spalten Tablet', 1, 3);
    addNumber(grid, 'ui_columns_mobile', el, 'Spalten Mobile', 1, 2);

    const optsWrap = $('<div class="schmitke-option-list" aria-label="Options"></div>').appendTo(body);
    const optHeader = $('<div class="schmitke-model-head"></div>').appendTo(optsWrap);
    $('<strong>Optionen</strong>').appendTo(optHeader);
    $('<button type="button" class="button">+ Option</button>').on('click', function(){
      const key = el.__uid;
      settings.options_by_element[key] = settings.options_by_element[key] || [];
      settings.options_by_element[key].push(defaultOption());
      render();
    }).appendTo(optHeader);

    const optKey = el.__uid;
    const options = settings.options_by_element[optKey] || [];
    options.forEach((opt,optIdx)=>{
      const optCard = $('<div class="schmitke-card schmitke-option-card"></div>');
      const optHead = $('<div class="schmitke-model-head"></div>').appendTo(optCard);
      $('<strong class="schmitke-card-title"></strong>').text(opt.option_code || 'Option').appendTo(optHead);
      $('<button type="button" class="button button-link-delete">'+('Löschen')+'</button>').on('click', function(){
        options.splice(optIdx,1);
        settings.options_by_element[optKey] = options;
        render();
      }).appendTo(optHead);

      const optBody = $('<div class="schmitke-model-grid"></div>').appendTo(optCard);
      $('<label>Code <input type="text"></label>').find('input').val(opt.option_code||'').on('input', function(){ opt.option_code = $(this).val(); }).end().appendTo(optBody);
      $('<label>Label DE <input type="text"></label>').find('input').val((opt.labels && opt.labels.de) || '').on('input', function(){ opt.labels.de = $(this).val(); }).end().appendTo(optBody);
      $('<label>Label EN <input type="text"></label>').find('input').val((opt.labels && opt.labels.en) || '').on('input', function(){ opt.labels.en = $(this).val(); }).end().appendTo(optBody);
      $('<label>Info DE <textarea rows="2"></textarea></label>').find('textarea').val((opt.info && opt.info.de) || '').on('input', function(){ opt.info = opt.info || {de:'', en:''}; opt.info.de = $(this).val(); }).end().appendTo(optBody);
      $('<label>Info EN <textarea rows="2"></textarea></label>').find('textarea').val((opt.info && opt.info.en) || '').on('input', function(){ opt.info = opt.info || {de:'', en:''}; opt.info.en = $(this).val(); }).end().appendTo(optBody);
      addToggle(optBody, 'is_default', opt, 'Default');
      addNumber(optBody, 'price', opt, 'Preis', 0, null, true);
      $('<label>Unit <input type="text"></label>').find('input').val(opt.unit||'').on('input', function(){ opt.unit = $(this).val(); }).end().appendTo(optBody);

      const imageField = $('<div class="schmitke-image-field"></div>').appendTo(optBody);
      $('<label>Bild (WP-Mediathek)</label>').appendTo(imageField);
      const imgRow = $('<div class="schmitke-image-row"></div>').appendTo(imageField);
      const preview = $('<img class="schmitke-image-preview" alt="">').appendTo(imgRow);
      const imgBtns = $('<div class="schmitke-image-buttons"></div>').appendTo(imgRow);
      $('<button type="button" class="button">Bild wählen</button>').on('click', function(){ pickOptionImage(opt, preview); }).appendTo(imgBtns);
      $('<button type="button" class="button">Entfernen</button>').on('click', function(){ opt.image_id = 0; preview.attr('src',''); syncInputs(); }).appendTo(imgBtns);
      applyOptionPreview(preview, opt.image_id);

      optCard.appendTo(optsWrap);
    });

    // Rules placeholder appended only once after loop
    return card;
  }

  function renderDesignSection(){
    const card = $('<div class="schmitke-card open"></div>');
    const head = $('<div class="schmitke-model-head"></div>').appendTo(card);
    $('<strong class="schmitke-model-title">Design</strong>').appendTo(head);
    $('<div class="schmitke-model-actions"></div>').appendTo(head).append('<span class="description">Farben & Typografie</span>');

    const body = $('<div class="schmitke-model-body"></div>').appendTo(card);
    const grid = $('<div class="schmitke-design-grid"></div>').appendTo(body);

    const addColor = function(label, key){
      $('<label>'+label+'<input type="text" class="schmitke-color"></label>')
        .find('input')
        .val(settings.design[key] || '')
        .on('input change', function(){ settings.design[key] = $(this).val(); })
        .end()
        .appendTo(grid);
    };

    addColor('Primary Color', 'primaryColor');
    addColor('Accent Color', 'accentColor');
    addColor('Text Color', 'textColor');
    addColor('Border Color', 'borderColor');

    $('<label>Font Family <input type="text"></label>')
      .find('input')
      .val(settings.design.fontFamily || '')
      .on('input', function(){ settings.design.fontFamily = $(this).val(); })
      .end()
      .appendTo(grid);

    addNumber(grid, 'buttonRadius', settings.design, 'Button Radius (px)', 0, 40);
    addNumber(grid, 'cardRadius', settings.design, 'Card Radius (px)', 0, 40);

    return card;
  }

  function renderEmailSection(){
    const card = $('<div class="schmitke-card open"></div>');
    const head = $('<div class="schmitke-model-head"></div>').appendTo(card);
    $('<strong class="schmitke-model-title">Empfänger E-Mail</strong>').appendTo(head);
    $('<div class="schmitke-model-actions"></div>').appendTo(head).append('<span class="description">Zieladresse für Angebotsanfragen</span>');

    const body = $('<div class="schmitke-model-body"></div>').appendTo(card);
    const grid = $('<div class="schmitke-model-grid"></div>').appendTo(body);
    $('<label>E-Mail <input type="email"></label>')
      .find('input')
      .val(settings.email_to || '')
      .on('input', function(){ settings.email_to = $(this).val(); })
      .end()
      .appendTo(grid);

    return card;
  }

  function renderMeasurementsSection(){
    const card = $('<div class="schmitke-card open"></div>');
    const head = $('<div class="schmitke-model-head"></div>').appendTo(card);
    $('<strong class="schmitke-model-title">Maße & Upload Labels</strong>').appendTo(head);
    $('<div class="schmitke-model-actions"></div>').appendTo(head).append('<span class="description">Angezeigte Feldnamen im Frontend</span>');

    const body = $('<div class="schmitke-model-body"></div>').appendTo(card);
    const labels = settings.measurements.labels || {};
    const rows = [
      {key:'width', title:'Breite'},
      {key:'height', title:'Höhe'},
      {key:'quantity', title:'Anzahl'},
      {key:'note', title:'Notiz'},
      {key:'upload', title:'Upload'},
    ];

    rows.forEach(function(row){
      const grid = $('<div class="schmitke-model-grid"></div>').appendTo(body);
      $('<label>'+row.title+' (DE) <input type="text"></label>')
        .find('input')
        .val((labels[row.key] && labels[row.key].de) || '')
        .on('input', function(){
          settings.measurements.labels[row.key] = settings.measurements.labels[row.key] || {de:'', en:''};
          settings.measurements.labels[row.key].de = $(this).val();
        })
        .end()
        .appendTo(grid);

      $('<label>'+row.title+' (EN) <input type="text"></label>')
        .find('input')
        .val((labels[row.key] && labels[row.key].en) || '')
        .on('input', function(){
          settings.measurements.labels[row.key] = settings.measurements.labels[row.key] || {de:'', en:''};
          settings.measurements.labels[row.key].en = $(this).val();
        })
        .end()
        .appendTo(grid);
    });

    return card;
  }

  function addToggle(container, key, obj, label){
    const wrapper=$('<label class="schmitke-toggle-row"></label>');
    const checkbox=$('<input type="checkbox">').prop('checked', !!obj[key]).on('change', function(){ obj[key]=!!$(this).is(':checked'); });
    wrapper.append(checkbox).append('<span>'+label+'</span>');
    container.append(wrapper);
  }

  function addNumber(container, key, obj, label, min, max, allowNull){
    const input=$('<input type="number">');
    if(typeof min !== 'undefined') input.attr('min', min);
    if(typeof max !== 'undefined' && max !== null) input.attr('max', max);
    const val = obj[key];
    input.val(val === null && allowNull ? '' : (typeof val === 'undefined' ? '' : val));
    input.on('input', function(){
      const v=$(this).val();
      obj[key] = (v === '' && allowNull) ? null : Number(v);
    });
    container.append($('<label>'+label+'</label>').append(input));
  }

  function defaultElement(){
    return {
      __uid: generateUid(),
      element_key: '',
      type: 'single',
      labels: {de:'', en:''},
      info: {de:'', en:''},
      visible_default: true,
      required_default: false,
      accordion_default_open: false,
      order: settings.elements.length + 1,
      allow_search: false,
      ui_columns_desktop: 3,
      ui_columns_tablet: 2,
      ui_columns_mobile: 1
    };
  }

  function defaultOption(){
    return {
      option_code: '',
      labels: {de:'', en:''},
      info: {de:'', en:''},
      image_id: 0,
      is_default: false,
      price: null,
      unit: '',
      disabled: false
    };
  }

  function moveElement(idx, delta){
    const target = idx + delta;
    if(target < 0 || target >= settings.elements.length) return;
    const clone = settings.elements[idx];
    settings.elements.splice(idx,1);
    settings.elements.splice(target,0,clone);
    settings.elements.forEach((el,i)=>{ el.order = i+1; });
    render();
  }

  function renderRules(container){
    container.empty();
    const header = $('<div class="schmitke-model-head"></div>').appendTo(container);
    $('<strong>Regeln</strong>').appendTo(header);
    $('<button type="button" class="button">+ Regel</button>').on('click', function(){
      settings.rules.push({id:'', name:'', priority:0, when:{conditions:[]}, then:{show_elements:[], hide_elements:[], filter_options:[], disable_options:[], set_required:[], unset_required:[]}});
      render();
    }).appendTo(header);

    settings.rules.forEach((rule, idx)=>{
      const card=$('<div class="schmitke-card schmitke-rule-card"></div>').appendTo(container);
      const head=$('<div class="schmitke-model-head"></div>').appendTo(card);
      $('<strong></strong>').text(rule.name || 'Regel '+(idx+1)).appendTo(head);
      $('<button type="button" class="button button-link-delete">Löschen</button>').on('click', function(){ settings.rules.splice(idx,1); render(); }).appendTo(head);

      const grid=$('<div class="schmitke-model-grid"></div>').appendTo(card);
      $('<label>ID <input type="text"></label>').find('input').val(rule.id||'').on('input', function(){ rule.id=$(this).val(); }).end().appendTo(grid);
      $('<label>Name <input type="text"></label>').find('input').val(rule.name||'').on('input', function(){ rule.name=$(this).val(); }).end().appendTo(grid);
      addNumber(grid, 'priority', rule, 'Priorität');

      const condWrap=$('<div class="schmitke-conditions"></div>').appendTo(card);
      $('<strong>Bedingungen</strong>').appendTo(condWrap);
      $('<button type="button" class="button">+ Bedingung</button>').on('click', function(){
        rule.when.conditions.push({element_key:'', operator:'equals', value:''});
        render();
      }).appendTo(condWrap);
      (rule.when.conditions||[]).forEach((cond, cIdx)=>{
        const row=$('<div class="schmitke-condition-row"></div>').appendTo(condWrap);
        const sel=$('<select></select>');
        settings.elements.forEach(function(el){
          var label = (el.labels && el.labels.de) ? el.labels.de : el.element_key;
          sel.append('<option value="'+el.element_key+'">'+label+'</option>');
        });
        sel.val(cond.element_key).on('change', function(){ cond.element_key=$(this).val(); });
        row.append(sel);
        const opSel=$('<select></select>').append(['equals','not_equals','in','not_in','contains','exists'].map(op=>'<option value="'+op+'">'+op+'</option>'));
        opSel.val(cond.operator).on('change', function(){ cond.operator=$(this).val(); });
        row.append(opSel);
        $('<input type="text" placeholder="Wert">').val(cond.value||'').on('input', function(){ cond.value=$(this).val(); }).appendTo(row);
        $('<button type="button" class="button-link-delete">×</button>').on('click', function(){ rule.when.conditions.splice(cIdx,1); render(); }).appendTo(row);
      });

      const actions=$('<div class="schmitke-actions"></div>').appendTo(card);
      $('<label>Elemente zeigen (Keys, Komma)</label>').append($('<input type="text">').val((rule.then.show_elements||[]).join(',')).on('input', function(){ rule.then.show_elements = splitList($(this).val()); })).appendTo(actions);
      $('<label>Elemente verstecken (Keys, Komma)</label>').append($('<input type="text">').val((rule.then.hide_elements||[]).join(',')).on('input', function(){ rule.then.hide_elements = splitList($(this).val()); })).appendTo(actions);
      $('<label>Pflicht setzen (Keys, Komma)</label>').append($('<input type="text">').val((rule.then.set_required||[]).join(',')).on('input', function(){ rule.then.set_required = splitList($(this).val()); })).appendTo(actions);
      $('<label>Pflicht entfernen (Keys, Komma)</label>').append($('<input type="text">').val((rule.then.unset_required||[]).join(',')).on('input', function(){ rule.then.unset_required = splitList($(this).val()); })).appendTo(actions);
      $('<label>filter_options (JSON)</label>').append($('<textarea rows="2"></textarea>').val(JSON.stringify(rule.then.filter_options||[])).on('change', function(){
        try { rule.then.filter_options = JSON.parse($(this).val()) || []; } catch(e){ /* ignore */ }
      })).appendTo(actions);
      $('<label>disable_options (JSON)</label>').append($('<textarea rows="2"></textarea>').val(JSON.stringify(rule.then.disable_options||[])).on('change', function(){
        try { rule.then.disable_options = JSON.parse($(this).val()) || []; } catch(e){ /* ignore */ }
      })).appendTo(actions);
    });
  }

  function splitList(val){
    return (val||'').split(',').map(v=>v.trim()).filter(Boolean);
  }

  function render(){
    captureElementOpenState();
    normalizeSettings();
    app.empty();
    app.append(renderEmailSection());
    app.append(renderDesignSection());
    app.append(renderMeasurementsSection());
    if($.fn.wpColorPicker){
      app.find('.schmitke-color').wpColorPicker();
    }

    const list = $('<div class="schmitke-v2-element-list"></div>');
    settings.elements.sort((a,b)=> (a.order||0)-(b.order||0));
    settings.elements.forEach((el, idx)=>{
      const card = elementTemplate(el, idx);
      list.append(card);
    });
    $('<p><button type="button" class="button button-secondary">+ Element hinzufügen</button></p>')
      .find('button')
      .on('click', function(){
        const newEl = defaultElement();
        settings.elements.push(newEl);
        elementOpenState.set(newEl.__uid, true);
        render();
      })
      .end()
      .appendTo(list);

    const rulesContainer = $('<div class="schmitke-rules"></div>');
    renderRules(rulesContainer);
    app.append(list);
    app.append(rulesContainer);

    syncInputs();
  }

  render();
})(jQuery);
