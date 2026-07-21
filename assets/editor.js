(function(){
  var S = window.LYNX.state, ROOT = window.LYNX.root, csrf = window.__lynxCsrf;
  var I = window.LYNX.i18n || {};
  function t(k,d){return I[k]||d;}
  var form = document.getElementById('form');
  var saveState = document.getElementById('savestate');
  var preview = document.getElementById('preview');
  var appearance = document.getElementById('appearance');
  var app = document.getElementById('app');
  var splitter = document.getElementById('splitter');
  var dirty = false, saveTimer = null;
  var activeTransLang = '';
  var lastSavedSlug = S.slug;

  function esc(s){return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
  function el(html){var d=document.createElement('div');d.innerHTML=html.trim();return d.firstChild;}
  function langs(){return window.LYNX.languages||['en'];}
  function transLangs(){return langs().filter(function(k){return k !== (S.lang||'en');});}
  function ensure(obj,k,base){obj[k]=obj[k]||base||{};return obj[k];}
  function meaningfulTranslation(value){
    if(Array.isArray(value))return value.some(meaningfulTranslation);
    if(value && typeof value === 'object')return Object.keys(value).some(function(k){
      return k !== 'enabled' && meaningfulTranslation(value[k]);
    });
    return value !== '' && value !== null && value !== undefined;
  }
  function normalizeEnabledTranslations(){
    S.translations = S.translations || {};
    Object.keys(S.translations).forEach(function(lang){
      if(lang !== S.lang && S.translations[lang] && typeof S.translations[lang] === 'object') {
        S.translations[lang].enabled = '1';
      }
    });
  }
  function enabledTransLangs(){
    return transLangs().filter(function(lang){
      var tr = S.translations && S.translations[lang];
      return !!(tr && typeof tr === 'object' && (tr.enabled || Object.keys(tr).length));
    });
  }
  function hasTranslationContent(lang){
    if(meaningfulTranslation((S.translations || {})[lang]))return true;
    if((S.links || []).some(function(item){return meaningfulTranslation((item.translations || {})[lang]);}))return true;
    return (S.blocks || []).some(function(item){return meaningfulTranslation((item.translations || {})[lang]);});
  }
  function route(path){
    path = String(path || '').replace(/^\/+/, '');
    return '/' + (ROOT ? ROOT.replace(/^\/+|\/+$/g, '') + '/' : '') + path;
  }
  function profilePath(lang){return route((lang ? lang+'/' : '')+S.slug);}
  function previewPath(lang){return profilePath(lang)+'?lynxpreview=1';}
  function fonts(){return window.LYNX.fonts || {'':{label:'System UI', family:"-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif", bunny:''}};}

  function themeOptions(sel){
    return Object.keys(window.LYNX.themes).map(function(k){
      return '<option value="'+k+'"'+(k===sel?' selected':'')+'>'+esc(window.LYNX.themes[k].label)+'</option>';
    }).join('');
  }

  function fontOptions(sel){
    var all = fonts();
    return Object.keys(all).map(function(k){
      return '<option value="'+k+'"'+(k===sel?' selected':'')+'>'+esc(all[k].label)+'</option>';
    }).join('');
  }

  function fontCssFamily(font){
    return (font && font.family) || fonts()[''].family;
  }

  function backgroundOptions(sel){
    var items = [
      ['', t('backgroundNone','Theme default')],
      ['color', t('backgroundColor','Color')],
      ['image', t('backgroundImage','Image URL')],
      ['gradient', t('backgroundGradient','Gradient CSS')]
    ];
    return items.map(function(item){
      return '<option value="'+esc(item[0])+'"'+(item[0]===sel?' selected':'')+'>'+esc(item[1])+'</option>';
    }).join('');
  }

  function defaultBackgroundValue(type){
    if(type === 'color')return '#f4f5f7';
    if(type === 'gradient')return 'linear-gradient(135deg,#111827,#2563eb)';
    return '';
  }

  function cleanBackgroundValue(type, value){
    value = value || '';
    if(!type)return '';
    if(type === 'color')return /^#[0-9a-f]{6}$/i.test(value) ? value : defaultBackgroundValue(type);
    if(type === 'image')return /^https?:\/\//i.test(value) || value.indexOf('/') === 0 ? value : '';
    if(type === 'gradient')return value.indexOf('gradient(') !== -1 ? value : defaultBackgroundValue(type);
    return '';
  }

  function loadFontPreviewCss(){
    var all = fonts();
    Object.keys(all).forEach(function(k){
      var bunny = all[k].bunny || '';
      if(!bunny)return;
      var link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = 'https://fonts.bunny.net/css?family=' + encodeURIComponent(bunny).replace(/%20/g, '+') + '&display=swap';
      document.head.appendChild(link);
    });
  }

  function renderAppearance(){
    if(!appearance)return;
    var all = fonts();
    var sample = S.title || 'Jane Demo';
    var bio = S.bio || 'Profile links, blocks and multilingual content';
    var bgType = S.bg_type || '';
    var bgValue = cleanBackgroundValue(bgType, S.bg_value);
    var bgField = '';
    if(bgType === 'color') {
      bgField = '<input type="color" class="swatch" id="f-bg-color" value="'+esc(bgValue || '#f4f5f7')+'">';
    } else if(bgType === 'image') {
      bgField = '<div class="row"><input class="inp" id="f-bg-value" value="'+esc(bgValue)+'" placeholder="https://"><button class="btn mini" id="bg-up">'+esc(t('upload','Upload'))+'</button></div>';
    } else if(bgType === 'gradient') {
      bgField = '<textarea class="inp bg-textarea" id="f-bg-value" placeholder="linear-gradient(135deg,#111827,#2563eb)">'+esc(bgValue)+'</textarea>';
    }
    var cards = Object.keys(all).map(function(k){
      var f = all[k];
      var active = (S.font || '') === k ? ' is-active' : '';
      var family = esc(fontCssFamily(f));
      var label = esc(f.label || k || 'System UI');
      var meta = f.bunny ? 'Bunny Fonts' : 'Local';
      return '<button type="button" class="font-card'+active+'" data-font="'+esc(k)+'" style="font-family:'+family+'">'+
        '<span class="font-card-title"><span>'+label+'</span><span>Aa</span></span>'+
        '<span class="font-card-sample">'+esc(sample)+'</span>'+
        '<span class="font-card-meta">'+esc(meta)+' · '+esc(bio.substring(0,42))+'</span>'+
        '</button>';
    }).join('');
    appearance.innerHTML =
      '<div class="appearance-head"><h2>'+esc(t('appearance','Appearance'))+'</h2>'+
      '<p class="muted">'+esc(t('appearanceNote','Theme, accent and open-source font for the public page.'))+'</p></div>'+
      '<div class="appearance-controls">'+
      '<label class="fld">'+esc(t('theme','Theme'))+'</label><select class="inp" id="f-theme">'+themeOptions(S.theme)+'</select>'+
      '<label class="fld">'+esc(t('accent','Accent'))+'</label><input type="color" class="swatch" id="f-accent" value="'+esc(S.accent||'#1e87f0')+'">'+
      '<label class="fld">'+esc(t('font','Font'))+'</label><select class="inp" id="f-font">'+fontOptions(S.font||'')+'</select>'+
      '<label class="fld">'+esc(t('background','Background'))+'</label><select class="inp" id="f-bg-type">'+backgroundOptions(bgType)+'</select>'+
      (bgField ? '<p class="muted bg-note">'+esc(t('backgroundNote','Use a color, uploaded image URL, or CSS gradient for the public page background.'))+'</p>'+bgField : '')+
      '</div><div class="font-list">'+cards+'</div>';
    appearance.querySelectorAll('.font-card').forEach(function(card){
      card.onclick = function(){
        S.font = card.dataset.font || '';
        var sel = appearance.querySelector('#f-font');
        if(sel) sel.value = S.font;
        renderAppearance();
        markDirty();
      };
    });
    ['f-theme','f-accent','f-font'].forEach(function(id){
      var node = appearance.querySelector('#'+id);
      node.addEventListener('input', function(){
        S[{ 'f-theme':'theme', 'f-accent':'accent', 'f-font':'font' }[id]] = node.value;
        if(id === 'f-font')renderAppearance();
        markDirty();
      });
    });
    var bgTypeNode = appearance.querySelector('#f-bg-type');
    if(bgTypeNode) bgTypeNode.addEventListener('input', function(){
      S.bg_type = bgTypeNode.value;
      S.bg_value = defaultBackgroundValue(S.bg_type);
      renderAppearance();
      markDirty();
    });
    var bgValueNode = appearance.querySelector('#f-bg-value');
    if(bgValueNode) bgValueNode.addEventListener('input', function(){
      S.bg_value = bgValueNode.value;
      markDirty();
    });
    var bgColorNode = appearance.querySelector('#f-bg-color');
    if(bgColorNode) bgColorNode.addEventListener('input', function(){
      S.bg_value = bgColorNode.value;
      markDirty();
    });
    var bgUpload = appearance.querySelector('#bg-up');
    if(bgUpload) bgUpload.onclick = function(e){
      e.preventDefault();
      pickFile(function(url){
        S.bg_type = 'image';
        S.bg_value = url;
        renderAppearance();
        markDirty();
      });
    };
  }

  function languageOptions(sel){
    return langs().map(function(k){
      return '<option value="'+esc(k)+'"'+(k===sel?' selected':'')+'>'+esc(k.toUpperCase())+'</option>';
    }).join('');
  }

  function render(){
    form.innerHTML='';
    S.translations = S.translations || {};
    renderAppearance();
    // --- Profile basics ---
    var basics = el('<div class="sect"><h3>'+esc(t('profile','Profile'))+'</h3>'+
      '<p class="sect-note">'+esc(t('profileNote','Core identity, language and visual style for the public page.'))+'</p>'+
      '<label class="fld">'+esc(t('baseLang','Base language'))+'</label><select class="inp" id="f-lang">'+languageOptions(S.lang||'en')+'</select>'+
      '<label class="fld">'+esc(t('profileSlug','Profile slug'))+'</label><div class="slug-field"><span>'+esc(route(''))+'</span><input class="inp" id="f-slug" value="'+esc(S.slug)+'" inputmode="url" autocomplete="off" spellcheck="false" pattern="[a-z0-9]+(?:-[a-z0-9]+)*"></div>'+
      '<p class="muted slug-note">'+esc(t('slugNote','Changing the slug changes the public URL. Use lowercase letters, numbers and hyphens.'))+'</p>'+
      '<label class="fld">'+esc(t('displayName','Display name'))+'</label><input class="inp" id="f-title" value="'+esc(S.title)+'">'+
      '<label class="fld">'+esc(t('shortBio','Short bio'))+'</label><textarea class="inp" id="f-bio">'+esc(S.bio)+'</textarea>'+
      '<label class="fld">'+esc(t('avatar','Avatar'))+'</label><div class="row"><img class="thumb" id="av-prev" src="'+esc(S.avatar)+'">'+
      '<input class="inp" id="f-avatar" value="'+esc(S.avatar)+'" placeholder="'+esc(t('avatarPlaceholder','image URL or upload'))+'">'+
      '<button class="btn mini" id="av-up">'+esc(t('upload','Upload'))+'</button></div>'+
      '</div>');
    form.appendChild(basics);
    bindBasics();

    // --- Links ---
    var lsec = el('<div class="sect"><h3>'+esc(t('links','Links'))+'</h3><div id="links"></div>'+
      '<p class="sect-note">'+esc(t('linksNote','Primary destinations shown before content blocks. Drag to reorder.'))+'</p>'+
      '<div class="addbar"><button class="btn mini" id="addlink">+ '+esc(t('addLink','Add link'))+'</button>'+
      '<button class="btn mini ghost" id="addsocial">+ '+esc(t('socialPresets','Social presets'))+'</button></div></div>');
    form.appendChild(lsec);
    var linksWrap = lsec.querySelector('#links');
    S.links.forEach(function(l,i){ linksWrap.appendChild(linkRow(l,i)); });
    if(window.Sortable) Sortable.create(linksWrap,{handle:'.drag',animation:0,onEnd:reindexLinks});
    lsec.querySelector('#addlink').onclick=function(){S.links.push({label:'',url:'',icon:'',is_social:0,translations:{}});render();};
    lsec.querySelector('#addsocial').onclick=showPresets;

    // --- Portfolio blocks ---
    var bsec = el('<div class="sect"><h3>'+esc(t('portfolio','Portfolio'))+'</h3><p class="sect-note">'+esc(t('portfolioNote','Add richer sections below the link list: images, projects, video or quotes.'))+'</p><div id="blocks"></div>'+
      '<div class="addbar"></div></div>');
    form.appendChild(bsec);
    var addbar = bsec.querySelector('.addbar');
    Object.keys(window.LYNX.blockTypes).forEach(function(ty){
      var b=el('<button class="btn mini ghost">+ '+esc(window.LYNX.blockTypes[ty])+'</button>');
      b.onclick=function(){S.blocks.push(newBlock(ty));render();};
      addbar.appendChild(b);
    });
    var blocksWrap=bsec.querySelector('#blocks');
    S.blocks.forEach(function(b,i){ blocksWrap.appendChild(blockCard(b,i)); });
    if(window.Sortable) Sortable.create(blocksWrap,{handle:'.drag',animation:0,onEnd:reindexBlocks});
    renderTranslations();
  }

  function renderTranslations(){
    var available = transLangs();
    if(!available.length)return;
    var enabled = enabledTransLangs();
    if(enabled.indexOf(activeTransLang) === -1) activeTransLang = enabled[0] || '';
    var addable = available.filter(function(lang){return enabled.indexOf(lang) === -1;});
    var sec = el('<div class="sect"><h3>'+esc(t('translations','Translations'))+'</h3>'+
      '<p class="sect-note">'+esc(t('translationsNote','Add and edit as many profile languages as needed. Empty fields fall back to the base language.'))+'</p>'+
      '<div class="tr-language-bar"><div class="tr-tabs" id="tr-tabs"></div><div class="tr-add" id="tr-add"></div></div>'+
      '<div class="tr-head" id="tr-head"></div>'+
      '<div id="tr-fields"></div></div>');
    form.appendChild(sec);
    var tabs = sec.querySelector('#tr-tabs');
    enabled.forEach(function(lang){
      var ready = hasTranslationContent(lang);
      var tab = el('<button type="button" class="tr-tab'+(lang===activeTransLang?' is-current':'')+(ready?' is-ready':'')+'" aria-pressed="'+(lang===activeTransLang?'true':'false')+'">'+
        '<span>'+esc(lang.toUpperCase())+'</span><span class="tr-state" aria-label="'+esc(ready?t('translated','Translated'):t('draft','Draft'))+'">'+(ready?'&#10003;':'&middot;')+'</span></button>');
      tab.onclick=function(){activeTransLang=lang;render();preview.src=previewPath(lang);};
      tabs.appendChild(tab);
    });
    if(!enabled.length)tabs.appendChild(el('<span class="muted tr-empty">'+esc(t('noTranslations','No translation languages added yet.'))+'</span>'));
    if(addable.length){
      var options = addable.map(function(lang){return '<option value="'+esc(lang)+'">'+esc(lang.toUpperCase())+'</option>';}).join('');
      var add = el('<div class="tr-add-controls"><select class="inp" id="tr-add-lang" aria-label="'+esc(t('language','Language'))+'">'+options+'</select>'+
        '<button type="button" class="btn mini" id="tr-add-button">+ '+esc(t('addLanguage','Add language'))+'</button></div>');
      sec.querySelector('#tr-add').appendChild(add);
      add.querySelector('#tr-add-button').onclick=function(){
        var lang = add.querySelector('#tr-add-lang').value;
        if(!lang)return;
        ensure(S.translations, lang, {}).enabled = '1';
        activeTransLang = lang;
        markDirty();
        render();
        preview.src = previewPath(lang);
      };
    }
    if(!activeTransLang)return;
    var lang = activeTransLang;
    var head = sec.querySelector('#tr-head');
    head.innerHTML = '<strong>'+esc(lang.toUpperCase())+'</strong><span class="muted">'+esc(t('language','Language'))+'</span>'+
      '<span class="tr-head-actions"><a class="btn ghost mini" target="_blank" rel="noopener noreferrer" href="'+profilePath(lang)+'">'+esc(t('open','Open'))+'</a>'+
      '<button type="button" class="del" id="tr-remove">'+esc(t('removeLanguage','Remove language'))+'</button></span>';
    head.querySelector('#tr-remove').onclick=function(){
      if(!window.confirm(t('removeLanguageConfirm','Remove this language and all of its profile, link and block translations?')))return;
      delete S.translations[lang];
      S.links.forEach(function(item){if(item.translations)delete item.translations[lang];});
      S.blocks.forEach(function(item){if(item.translations)delete item.translations[lang];});
      activeTransLang = '';
      markDirty();
      render();
      preview.src = previewPath(activeTransLang);
    };
    var wrap = sec.querySelector('#tr-fields');
    var p = ensure(S.translations, lang, {});
    p.enabled = '1';
    wrap.appendChild(textRow(t('profileTitle','Profile title'), p.title || '', function(v){p.title=v;}));
    wrap.appendChild(areaRow(t('profileBio','Profile bio'), p.bio || '', function(v){p.bio=v;}));
    wrap.appendChild(textRow(t('seoTitle','SEO title'), p.seo_title || '', function(v){p.seo_title=v;}));
    wrap.appendChild(areaRow(t('seoDesc','SEO description'), p.seo_description || '', function(v){p.seo_description=v;}));
    if(S.links.length) wrap.appendChild(el('<label class="fld">'+esc(t('linkLabels','Link labels'))+'</label>'));
    S.links.forEach(function(l){
      l.translations = l.translations || {};
      var tr = ensure(l.translations, lang, {});
      wrap.appendChild(textRow(l.label || t('untitledLink','Untitled link'), tr.label || '', function(v){tr.label=v;}));
    });
    if(S.blocks.length) wrap.appendChild(el('<label class="fld">'+esc(t('blockTitles','Block titles'))+'</label>'));
    S.blocks.forEach(function(b){
      b.translations = b.translations || {};
      var tr = ensure(b.translations, lang, {});
      wrap.appendChild(textRow(b.title || (window.LYNX.blockTypes[b.type]||'Block'), tr.title || '', function(v){tr.title=v;}));
      blockTranslationRows(wrap,b,tr);
    });
  }

  function blockTranslationRows(wrap,b,tr){
    tr.data = tr.data || {};
    var name = b.title || (window.LYNX.blockTypes[b.type]||'Block');
    if(b.type==='gallery'){
      tr.data.images = tr.data.images || [];
      (b.data.images||[]).forEach(function(img,i){
        tr.data.images[i] = tr.data.images[i] || {};
        wrap.appendChild(textRow(name+' image '+(i+1)+' caption', tr.data.images[i].caption || '', function(v){tr.data.images[i].caption=v;}));
      });
    } else if(b.type==='project'){
      tr.data.items = tr.data.items || [];
      (b.data.items||[]).forEach(function(it,i){
        tr.data.items[i] = tr.data.items[i] || {};
        wrap.appendChild(textRow(name+' project '+(i+1)+' heading', tr.data.items[i].heading || '', function(v){tr.data.items[i].heading=v;}));
        wrap.appendChild(areaRow(name+' project '+(i+1)+' body', tr.data.items[i].body || '', function(v){tr.data.items[i].body=v;}));
        wrap.appendChild(textRow(name+' project '+(i+1)+' button', tr.data.items[i].linkLabel || '', function(v){tr.data.items[i].linkLabel=v;}));
      });
    } else if(b.type==='video'){
      tr.data.videos = tr.data.videos || [];
      (b.data.videos||[]).forEach(function(v,i){
        tr.data.videos[i] = tr.data.videos[i] || {};
        wrap.appendChild(textRow(name+' video '+(i+1)+' caption', tr.data.videos[i].caption || '', function(val){tr.data.videos[i].caption=val;}));
      });
    } else if(b.type==='quote'){
      tr.data.quotes = tr.data.quotes || [];
      (b.data.quotes||[]).forEach(function(q,i){
        tr.data.quotes[i] = tr.data.quotes[i] || {};
        wrap.appendChild(areaRow(name+' quote '+(i+1)+' text', tr.data.quotes[i].text || '', function(v){tr.data.quotes[i].text=v;}));
        wrap.appendChild(textRow(name+' quote '+(i+1)+' author', tr.data.quotes[i].author || '', function(v){tr.data.quotes[i].author=v;}));
        wrap.appendChild(textRow(name+' quote '+(i+1)+' role', tr.data.quotes[i].role || '', function(v){tr.data.quotes[i].role=v;}));
      });
    }
  }

  function textRow(label,value,setter){
    var r=el('<div class="tr-row"><div class="tr-label" title="'+esc(label)+'">'+esc(label)+'</div><input class="inp" value="'+esc(value)+'"></div>');
    r.querySelector('input').addEventListener('input',function(e){setter(e.target.value);markDirty();});
    return r;
  }

  function areaRow(label,value,setter){
    var r=el('<div class="tr-row"><div class="tr-label" title="'+esc(label)+'">'+esc(label)+'</div><textarea class="inp">'+esc(value)+'</textarea></div>');
    r.querySelector('textarea').addEventListener('input',function(e){setter(e.target.value);markDirty();});
    return r;
  }

  function bindBasics(){
    var map={'f-lang':'lang','f-title':'title','f-bio':'bio','f-avatar':'avatar'};
    Object.keys(map).forEach(function(id){
      var node=document.getElementById(id);
      node.addEventListener('input',function(){
        S[map[id]]=node.value;
        if(id==='f-avatar')document.getElementById('av-prev').src=node.value;
        if(id==='f-title' || id==='f-bio')renderAppearance();
        if(id==='f-lang'){activeTransLang='';markDirty();render();return;}
        markDirty();
      });
    });
    var slugNode=document.getElementById('f-slug');
    slugNode.addEventListener('change',function(){
      var next=slugNode.value.trim().toLowerCase().replace(/[^a-z0-9-]+/g,'-').replace(/^-+|-+$/g,'').replace(/-+/g,'-');
      if(!next){
        slugNode.value=S.slug;
        saveState.textContent=t('slugRequired','Profile slug cannot be empty.');
        return;
      }
      slugNode.value=next;
      if(next!==S.slug){S.slug=next;markDirty();}
    });
    document.getElementById('av-up').onclick=function(e){e.preventDefault();pickFile(function(url){S.avatar=url;render();markDirty();});};
  }

  function linkRow(l,i){
    var r=el('<div class="row link-row" data-i="'+i+'"><span class="drag">≡</span>'+
      '<input class="inp" placeholder="'+esc(t('label','Label'))+'" value="'+esc(l.label)+'" data-k="label">'+
      '<input class="inp" placeholder="https://" value="'+esc(l.url)+'" data-k="url">'+
      '<input class="inp compact-input" placeholder="'+esc(t('icon','icon'))+'" value="'+esc(l.icon)+'" data-k="icon">'+
      '<button class="del">×</button></div>');
    r.querySelectorAll('input').forEach(function(inp){
      inp.addEventListener('input',function(){l[inp.dataset.k]=inp.value;markDirty();});
    });
    r.querySelector('.del').onclick=function(){S.links.splice(i,1);render();markDirty();};
    return r;
  }
  function reindexLinks(){var ids=[].map.call(document.querySelectorAll('#links .row'),function(r){return +r.dataset.i;});S.links=ids.map(function(i){return S.links[i];});render();markDirty();}
  function reindexBlocks(){var ids=[].map.call(document.querySelectorAll('#blocks .bcard'),function(r){return +r.dataset.i;});S.blocks=ids.map(function(i){return S.blocks[i];});render();markDirty();}

  function newBlock(ty){
    if(ty==='gallery')return{type:ty,title:'',data:{images:[],columns:3},translations:{}};
    if(ty==='project')return{type:ty,title:'',data:{items:[]},translations:{}};
    if(ty==='video')return{type:ty,title:'',data:{videos:[]},translations:{}};
    if(ty==='quote')return{type:ty,title:'',data:{quotes:[]},translations:{}};
    return{type:ty,title:'',data:{},translations:{}};
  }

  function blockCard(b,i){
    var label=window.LYNX.blockTypes[b.type]||b.type;
    var card=el('<div class="bcard sect" data-i="'+i+'" style="background:#fff">'+
      '<div class="row"><span class="drag">≡</span><strong style="flex:1">'+esc(label)+'</strong>'+
      '<button class="del">'+esc(t('remove','Remove'))+'</button></div>'+
      '<input class="inp" placeholder="'+esc(t('sectionTitle','Section title (optional)'))+'" value="'+esc(b.title)+'" data-k="title">'+
      '<div class="binner"></div></div>');
    card.querySelector('[data-k=title]').addEventListener('input',function(e){b.title=e.target.value;markDirty();});
    card.querySelector('.del').onclick=function(){S.blocks.splice(i,1);render();markDirty();};
    var inner=card.querySelector('.binner');
    if(b.type==='gallery')galleryEditor(inner,b);
    else if(b.type==='project')projectEditor(inner,b);
    else if(b.type==='video')videoEditor(inner,b);
    else if(b.type==='quote')quoteEditor(inner,b);
    return card;
  }

  function galleryEditor(wrap,b){
    b.data.images=b.data.images||[];
    var cols=el('<label class="fld">'+esc(t('columns','Columns'))+': <input type="number" min="1" max="4" value="'+(b.data.columns||3)+'" style="width:54px" class="inp"></label>');
    cols.querySelector('input').addEventListener('input',function(e){b.data.columns=+e.target.value;markDirty();});
    wrap.appendChild(cols);
    b.data.images.forEach(function(img,j){
      var r=el('<div class="row"><img class="thumb" src="'+esc(img.src)+'"><input class="inp" placeholder="'+esc(t('caption','caption'))+'" value="'+esc(img.caption||'')+'"><button class="del">×</button></div>');
      r.querySelector('input').addEventListener('input',function(e){img.caption=e.target.value;markDirty();});
      r.querySelector('.del').onclick=function(){b.data.images.splice(j,1);render();markDirty();};
      wrap.appendChild(r);
    });
    var add=el('<button class="btn mini">+ '+esc(t('addImage','Add image'))+'</button>');
    add.onclick=function(e){e.preventDefault();pickFile(function(url){b.data.images.push({src:url,caption:'',link:''});render();markDirty();});};
    wrap.appendChild(add);
  }

  function projectEditor(wrap,b){
    b.data.items=b.data.items||[];
    b.data.items.forEach(function(it,j){
      var r=el('<div class="sect" style="background:#fafbfc">'+
        '<div class="row"><img class="thumb" src="'+esc(it.image||'')+'"><button class="btn mini">'+esc(t('image','Image'))+'</button><button class="del">×</button></div>'+
        '<input class="inp" placeholder="'+esc(t('heading','Heading'))+'" value="'+esc(it.heading||'')+'" data-k="heading">'+
        '<textarea class="inp" placeholder="'+esc(t('description','Description'))+'" data-k="body">'+esc(it.body||'')+'</textarea>'+
        '<div class="row project-link-row"><input class="inp" placeholder="'+esc(t('linkUrl','Link URL'))+'" value="'+esc(it.link||'')+'" data-k="link">'+
        '<input class="inp compact-input" placeholder="'+esc(t('button','Button'))+'" value="'+esc(it.linkLabel||'View')+'" data-k="linkLabel"></div></div>');
      r.querySelectorAll('[data-k]').forEach(function(inp){inp.addEventListener('input',function(){it[inp.dataset.k]=inp.value;markDirty();});});
      r.querySelector('.btn.mini').onclick=function(e){e.preventDefault();pickFile(function(url){it.image=url;render();markDirty();});};
      r.querySelector('.del').onclick=function(){b.data.items.splice(j,1);render();markDirty();};
      wrap.appendChild(r);
    });
    var add=el('<button class="btn mini">+ '+esc(t('addProject','Add project'))+'</button>');
    add.onclick=function(e){e.preventDefault();b.data.items.push({heading:'',body:'',image:'',link:'',linkLabel:'View'});render();markDirty();};
    wrap.appendChild(add);
  }

  function videoEditor(wrap,b){
    b.data.videos=b.data.videos||[];
    b.data.videos.forEach(function(v,j){
      var r=el('<div class="row"><input class="inp" placeholder="'+esc(t('videoUrl','YouTube / Vimeo URL'))+'" value="'+esc(v.url||'')+'"><button class="del">×</button></div>');
      r.querySelector('input').addEventListener('input',function(e){v.url=e.target.value;markDirty();});
      r.querySelector('.del').onclick=function(){b.data.videos.splice(j,1);render();markDirty();};
      wrap.appendChild(r);
    });
    var add=el('<button class="btn mini">+ '+esc(t('addVideo','Add video'))+'</button>');
    add.onclick=function(e){e.preventDefault();b.data.videos.push({url:'',caption:''});render();markDirty();};
    wrap.appendChild(add);
  }

  function quoteEditor(wrap,b){
    b.data.quotes=b.data.quotes||[];
    b.data.quotes.forEach(function(q,j){
      var r=el('<div class="sect" style="background:#fafbfc">'+
        '<textarea class="inp" placeholder="'+esc(t('quoteText','Quote text'))+'" data-k="text">'+esc(q.text||'')+'</textarea>'+
        '<div class="row"><input class="inp" placeholder="'+esc(t('author','Author'))+'" value="'+esc(q.author||'')+'" data-k="author">'+
        '<input class="inp" placeholder="'+esc(t('role','Role'))+'" value="'+esc(q.role||'')+'" data-k="role"></div>'+
        '<button class="del">× '+esc(t('remove','Remove'))+'</button></div>');
      r.querySelectorAll('[data-k]').forEach(function(inp){inp.addEventListener('input',function(){q[inp.dataset.k]=inp.value;markDirty();});});
      r.querySelector('.del').onclick=function(){b.data.quotes.splice(j,1);render();markDirty();};
      wrap.appendChild(r);
    });
    var add=el('<button class="btn mini">+ '+esc(t('addQuote','Add quote'))+'</button>');
    add.onclick=function(e){e.preventDefault();b.data.quotes.push({text:'',author:'',role:''});render();markDirty();};
    wrap.appendChild(add);
  }

  function showPresets(e){
    e.preventDefault();
    var names=Object.keys(window.LYNX.presets).map(function(k){return k;}).join(', ');
    var pick=prompt(t('addSocial','Add which social?')+' ('+names+')');
    if(!pick)return;
    var p=window.LYNX.presets[pick.trim().toLowerCase()];
    if(!p){alert(t('unknown','Unknown'));return;}
    S.links.push({label:p[0],url:p[2],icon:p[1],is_social:1});render();markDirty();
  }

  function pickFile(cb){
    var inp=document.createElement('input');inp.type='file';inp.accept='image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp';
    inp.onchange=function(){
      var file=inp.files[0];
      if(!file)return;
      if(file.size > 8 * 1024 * 1024){alert(t('uploadTooLarge','Image exceeds the 8 MB upload limit'));return;}
      var fd=new FormData();fd.append('file',file);fd.append('profile_id',S.id);
      saveState.textContent=t('uploading','Uploading…');
      fetch(route('upload'),{method:'POST',headers:{'X-XSRF-Token':csrf},body:fd})
        .then(function(r){return r.json().catch(function(){return{error:t('uploadFail','Upload failed')};});}).then(function(d){
          saveState.textContent='';
          if(d.url)cb(d.url);else alert(d.error||t('uploadFail','Upload failed'));
        }).catch(function(){
          saveState.textContent='';
          alert(t('uploadFail','Upload failed'));
        });
    };
    inp.click();
  }

  function markDirty(){dirty=true;saveState.textContent=t('unsaved','Unsaved changes');clearTimeout(saveTimer);saveTimer=setTimeout(save,1200);}

  function initResize(){
    if(!app || !splitter)return;
    var saved = parseInt(localStorage.getItem('lynx-editor-left-width') || '', 10);
    if(saved) app.style.setProperty('--lynx-left-width', saved + 'px');
    splitter.addEventListener('pointerdown', function(e){
      e.preventDefault();
      document.body.classList.add('is-resizing');
      splitter.setPointerCapture(e.pointerId);
      var rect = app.getBoundingClientRect();
      function move(ev){
        var min = 520;
        var max = Math.max(min, rect.width - 320);
        var width = Math.min(max, Math.max(min, ev.clientX - rect.left));
        app.style.setProperty('--lynx-left-width', width + 'px');
        localStorage.setItem('lynx-editor-left-width', String(width));
      }
      function up(){
        document.body.classList.remove('is-resizing');
        splitter.removeEventListener('pointermove', move);
        splitter.removeEventListener('pointerup', up);
        splitter.removeEventListener('pointercancel', up);
      }
      splitter.addEventListener('pointermove', move);
      splitter.addEventListener('pointerup', up);
      splitter.addEventListener('pointercancel', up);
    });
  }

  function save(){
    clearTimeout(saveTimer);
    saveState.textContent=t('saving','Saving…');
    fetch(route('edit/'+S.slug),{method:'POST',headers:{'Content-Type':'application/json','X-XSRF-Token':csrf},body:JSON.stringify(S)})
      .then(function(r){return r.json();}).then(function(d){
        if(d.ok){
          dirty=false;
          saveState.textContent=t('saved','Saved');
          var savedSlug=d.slug||S.slug;
          var slugChanged=savedSlug!==lastSavedSlug;
          S.slug=savedSlug;
          lastSavedSlug=savedSlug;
          if(slugChanged){
            history.replaceState(null,'',route('edit/'+savedSlug));
            var openProfile=document.getElementById('open-profile');
            if(openProfile)openProfile.href=profilePath('');
            render();
          }
          preview.src=previewPath(activeTransLang);
        }
        else saveState.textContent=d.error||t('error','Error');
      }).catch(function(){saveState.textContent=t('netErr','Network error');});
  }

  document.getElementById('savebtn').onclick=save;
  window.addEventListener('beforeunload',function(e){if(dirty){e.preventDefault();e.returnValue='';}});
  loadFontPreviewCss();
  initResize();
  normalizeEnabledTranslations();
  render();
})();
