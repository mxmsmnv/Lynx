(function(){
  var el = document.getElementById('lynx-links');
  if(!el) return;
  if(window.Sortable){ Sortable.create(el, {handle:'.lynx-drag', animation:0}); }
  var noEmptyMsg = el.getAttribute('data-noempty') || 'No empty link row available — save first to get more rows.';
  // preset buttons fill the first empty row
  document.querySelectorAll('.lynx-preset').forEach(function(btn){
    btn.addEventListener('click', function(){
      var rows = el.querySelectorAll('.lynx-row');
      for(var i=0;i<rows.length;i++){
        var label = rows[i].querySelector('[name^="link_label["]');
        var url = rows[i].querySelector('[name^="link_url["]');
        var icon = rows[i].querySelector('[name^="link_icon["]');
        var social = rows[i].querySelector('input[type="checkbox"][name^="link_social["]');
        if(!label.value && !url.value){
          label.value = btn.dataset.label;
          url.value = btn.dataset.prefix;
          icon.value = btn.dataset.icon;
          if(social) social.checked = true;
          url.focus();
          return;
        }
      }
      alert(noEmptyMsg);
    });
  });
})();
