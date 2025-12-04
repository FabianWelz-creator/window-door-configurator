
jQuery(function($){
  const cardSelector='.schmitke-card';

  function bindImageFields(container, cardSel){
    const target = container || $(document);

    target.on('click','.schmitke-pick-image',function(e){
      const card=$(this).closest(cardSel||cardSelector);
      if(!card.length) return;
      e.preventDefault();
      openMedia(card);
    });

    target.on('click','.schmitke-clear-image',function(e){
      const card=$(this).closest(cardSel||cardSelector);
      if(!card.length) return;
      e.preventDefault();
      card.find('.schmitke-image-preview').attr('src','');
      card.find('.schmitke-image-id').val(0);
      card.find('.schmitke-image-url').val('');
    });
  }

  // Color pickers
  $('.schmitke-color').wpColorPicker();

  // Toggle cards
  $(document).on('click','.schmitke-toggle',function(){
    const card=$(this).closest(cardSelector);
    if(card.hasClass('schmitke-option-card')) return;
    card.toggleClass('open');
    $(this).text(card.hasClass('open') ? 'Schließen' : 'Öffnen');
  });

  // Remove card
  $(document).on('click','.schmitke-remove',function(){
    if($(this).closest('.schmitke-option-card').length) return;
    $(this).closest(cardSelector).remove();
  });

  // Add model card
  $('#schmitke-add-model').on('click',function(){
    const list=$('#schmitke-model-list');
    const idx=list.children('.schmitke-model-card').length;
    let tpl=$('#schmitke-model-template').html();
    tpl=tpl.replace(/__i__/g, idx);
    list.append(tpl);
  });


  // Media uploader
  function openMedia(card){
    const preview=card.find('.schmitke-image-preview');
    const idField=card.find('.schmitke-image-id');
    const urlField=card.find('.schmitke-image-url');

    const frame=wp.media({
      title: 'Bild auswählen',
      button: { text: 'Übernehmen' },
      multiple: false
    });

    frame.on('select', function(){
      const attachment=frame.state().get('selection').first().toJSON();
      preview.attr('src', attachment.sizes?.thumbnail?.url || attachment.url);
      idField.val(attachment.id);
      urlField.val(attachment.url);
    });

    frame.open();
  }

  function initSchmitkeOptionRepeater(containerSelector){
    const container=$(containerSelector);
    if(!container.length) return;

    const optionCardSel='.schmitke-option-card';

    container.on('click','.schmitke-option-toggle',function(){
      const panel=$(this).closest('.schmitke-option-panel');
      panel.toggleClass('open');
      $(this).attr('aria-expanded', panel.hasClass('open'));
    });

    container.on('click','.schmitke-add-option',function(){
      const group=$(this).data('group');
      const templateId=$(this).data('template');
      const list=container.find('.schmitke-option-list[data-group="'+group+'"]').first();
      if (!list.length || !templateId) return;
      const idx=list.children(optionCardSel).length;
      let tpl=$(templateId).html();
      tpl=tpl.replace(/__i__/g, idx);
      list.append(tpl);
    });

    container.on('click','.schmitke-toggle',function(){
      const card=$(this).closest(optionCardSel);
      if(!card.length) return;
      card.toggleClass('open');
      $(this).text(card.hasClass('open') ? 'Schließen' : 'Öffnen');
    });

    container.on('click','.schmitke-remove',function(){
      const card=$(this).closest(optionCardSel);
      if(card.length) card.remove();
    });

    bindImageFields(container, optionCardSel);
  }

  bindImageFields($(document), '.schmitke-model-card');
  initSchmitkeOptionRepeater('.schmitke-option-accordion');

  // Live update title/sub from inputs for models
  $(document).on('input','input[name*="[family]"],input[name*="[name]"],input[name*="[finish]"]',function(){
    const card=$(this).closest('.schmitke-model-card');
    const fam=card.find('input[name*="[family]"]').val();
    const name=card.find('input[name*="[name]"]').val();
    const fin=card.find('input[name*="[finish]"]').val();
    card.find('.schmitke-model-title').text((fam?fam:'') + (name?(' – '+name):'') || 'Modell');
    card.find('.schmitke-model-sub').text(fin || '');
  });

  // Live update title/sub for option cards
  $(document).on('input','.schmitke-option-card input[name*="[title]"], .schmitke-option-card input[name*="[subtitle]"], .schmitke-option-card input[name*="[key]"]',function(){
    const card=$(this).closest('.schmitke-option-card');
    const title=card.find('input[name*="[title]"]').first().val();
    const subtitle=card.find('input[name*="[subtitle]"]').first().val();
    const key=card.find('input[name*="[key]"]').first().val();
    card.find('.schmitke-card-title').text(title || 'Option');
    card.find('.schmitke-card-sub').text([key, subtitle].filter(Boolean).join(' – '));
  });
});
