(function(){
  const DATA = (window.SCHMITKE_WINDOWS_DATA || {});
  if(!DATA.models){ console.error('SCHMITKE_WINDOWS_DATA missing'); return; }

  const root = document.getElementById('schmitke-window-configurator');
  if(!root) return;

  // Placeholder to confirm script loads; implement window-specific UI later
  root.innerHTML = '<div class="schmitke-notice">Fenster Konfigurator wird geladen…</div>';
})();
