
jQuery(function($){
  // Color pickers
  $('.schmitke-color').wpColorPicker();

  // Toggle cards
  $(document).on('click','.schmitke-toggle',function(){
    const card=$(this).closest('.schmitke-model-card');
    card.toggleClass('open');
    $(this).text(card.hasClass('open') ? 'Schließen' : 'Öffnen');
  });

  // Remove card
  $(document).on('click','.schmitke-remove',function(){
    $(this).closest('.schmitke-model-card').remove();
  });

  // Add card
  $('#schmitke-add-model').on('click',function(){
    const list=$('#schmitke-model-list');
    const idx=list.children('.schmitke-model-card').length;
    let tpl=$('#schmitke-model-template').html();
    tpl=tpl.replace(/__i__/g, idx);
    list.append(tpl);
  });

  // Media uploader
  function openMedia(button){
    const card=button.closest('.schmitke-model-card');
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

  $(document).on('click','.schmitke-pick-image',function(e){
    e.preventDefault(); openMedia($(this));
  });

  $(document).on('click','.schmitke-clear-image',function(e){
    e.preventDefault();
    const card=$(this).closest('.schmitke-model-card');
    card.find('.schmitke-image-preview').attr('src','');
    card.find('.schmitke-image-id').val(0);
    card.find('.schmitke-image-url').val('');
  });

  // Live update title/sub from inputs
  $(document).on('input','input[name*="[family]"],input[name*="[name]"],input[name*="[finish]"]',function(){
    const card=$(this).closest('.schmitke-model-card');
    const fam=card.find('input[name*="[family]"]').val();
    const name=card.find('input[name*="[name]"]').val();
    const fin=card.find('input[name*="[finish]"]').val();
    card.find('.schmitke-model-title').text((fam?fam:'') + (name?(' – '+name):'') || 'Modell');
    card.find('.schmitke-model-sub').text(fin || '');
  });
});
