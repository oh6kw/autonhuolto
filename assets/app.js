/* AUTONHUOLTO – selainlogiikka. */
/* Kielitekstit: tt(avain,{muuttujat}); window.I18N tulee palvelimelta (lang/<kieli>/js.php). Paikkamerkit {nimi} korvataan. */
function tt(key,vars){let s=(window.I18N&&window.I18N[key])||key;if(vars)for(const k in vars)s=s.split('{'+k+'}').join(String(vars[k]));return s;}

(()=>{
 const form=document.getElementById('inventoryBulkForm'),body=document.getElementById('inventoryTableBody'),search=document.getElementById('inventorySearch'),carFilter=document.getElementById('inventoryCarFilter'),lowOnly=document.getElementById('inventoryLowOnly'),sortSelect=document.getElementById('inventorySortSelect'),dirtyText=document.getElementById('inventoryDirtyText'),printLink=document.getElementById('inventoryPrintLink'),printInfoLink=document.getElementById('inventoryPrintInfoLink'),excelLink=document.getElementById('inventoryExcelLink');
 if(!form||!body)return;const readOnly=form.dataset.readonly==='1';if(readOnly)dirtyText.textContent=tt('js.inv_readonly');let dirty=false,sortKey='car',sortDir='asc';
 const rows=()=>Array.from(body.querySelectorAll('.inventory-row'));const n=v=>{const x=parseFloat(String(v??'').replace(',','.'));return Number.isFinite(x)?x:0};const txt=v=>String(v??'').toLocaleLowerCase('fi-FI');
 const field=(r,sel)=>r.querySelector(sel);const currentLow=r=>{const rv=field(r,'.inventory-reorder').value.trim();return rv!==''&&n(field(r,'.inventory-stock').value)<=n(rv)};
 const valueFor=(r,key)=>{if(key==='car')return r.dataset.carName||'';if(key==='part')return r.dataset.partName||'';if(key==='shelf')return field(r,'.inventory-shelf').value;if(key==='stock')return n(field(r,'.inventory-stock').value);if(key==='reorder')return field(r,'.inventory-reorder').value.trim()===''?null:n(field(r,'.inventory-reorder').value);if(key==='price')return field(r,'.inventory-price').value.trim()===''?null:n(field(r,'.inventory-price').value);if(key==='value')return n(field(r,'.inventory-stock').value)*n(field(r,'.inventory-price').value);return ''};
 const cmp=(a,b,key)=>{const av=valueFor(a,key),bv=valueFor(b,key);if(av===null&&bv===null)return 0;if(av===null)return sortDir==='desc'?-1:1;if(bv===null)return sortDir==='desc'?1:-1;if(typeof av==='number'&&typeof bv==='number')return av-bv;return String(av).localeCompare(String(bv),'fi',{numeric:true,sensitivity:'base'})};
 function updateRow(r){const low=currentLow(r);r.classList.toggle('inventory-low',low);const hasPrice=field(r,'.inventory-price').value.trim()!=='';const value=n(field(r,'.inventory-stock').value)*n(field(r,'.inventory-price').value);r.querySelector('.inventory-row-value').textContent=hasPrice?value.toLocaleString('fi-FI',{style:'currency',currency:'EUR'}):'–';const ul=r.querySelector('.inventory-unit-label'),ui=field(r,'.inventory-unit');if(ul&&ui)ul.textContent=ui.value||tt('js.unit_default');}
 function updateSummary(){let total=0,low=0,priced=false;rows().forEach(r=>{updateRow(r);total+=n(field(r,'.inventory-stock').value)*n(field(r,'.inventory-price').value);if(field(r,'.inventory-price').value.trim()!=='')priced=true;if(currentLow(r))low++});document.getElementById('inventoryValueBadge').textContent=priced?total.toLocaleString('fi-FI',{style:'currency',currency:'EUR'}):'–';document.getElementById('inventoryLowBadge').textContent=tt('js.inv_low_badge',{n:low})}
 function updateExportLinks(){const q=search.value.trim();const addFilters=p=>{if(q)p.set('q',q);if(carFilter.value)p.set('car_id',carFilter.value);if(lowOnly.checked)p.set('low','1');p.set('sort',sortKey);p.set('dir',sortDir);return p};if(printLink)printLink.href='?'+addFilters(new URLSearchParams({print:'inventory'})).toString();if(printInfoLink)printInfoLink.href='?'+addFilters(new URLSearchParams({print:'inventory',mode:'info'})).toString();if(excelLink)excelLink.href='?'+addFilters(new URLSearchParams({export:'inventory_xlsx'})).toString()}
 function filterRows(){const q=txt(search.value.trim()),cid=carFilter.value,onlyLow=lowOnly.checked;let visible=0;rows().forEach(r=>{const hay=txt((r.dataset.search||'')+' '+field(r,'.inventory-shelf').value);const show=(!q||hay.includes(q))&&(!cid||(r.dataset.carIds||'').includes(','+cid+','))&&(!onlyLow||currentLow(r));r.hidden=!show;if(show)visible++});document.getElementById('inventoryVisibleCount').textContent=tt('js.inv_visible_count',{n:visible});updateExportLinks()}
 function sortRows(key=sortKey,dir=sortDir){sortKey=key;sortDir=dir;const mult=dir==='desc'?-1:1;const sorted=rows().sort((a,b)=>{const c=cmp(a,b,key);return c===0?Number(a.querySelector('.inventory-stock').name.match(/\[(\d+)\]/)?.[1]||0)-Number(b.querySelector('.inventory-stock').name.match(/\[(\d+)\]/)?.[1]||0):c*mult});sorted.forEach(r=>body.appendChild(r));document.querySelectorAll('.inventory-sort').forEach(b=>{b.classList.toggle('active',b.dataset.sort===key);b.dataset.dir=b.dataset.sort===key?dir:''});sortSelect.value=key+':'+dir;updateExportLinks()}
 function changed(){dirty=true;form.dataset.dirty='1';dirtyText.textContent=tt('js.inv_dirty');dirtyText.classList.add('inventory-dirty');updateSummary();filterRows()}
 if(!readOnly)form.querySelectorAll('.inventory-edit').forEach(el=>el.addEventListener('input',changed));search.addEventListener('input',filterRows);carFilter.addEventListener('change',filterRows);lowOnly.addEventListener('change',filterRows);document.getElementById('inventoryLowBadge')?.addEventListener('click',()=>{lowOnly.checked=!lowOnly.checked;filterRows();document.getElementById('inventoryTable')?.scrollIntoView({behavior:'smooth',block:'start'})});body.addEventListener('click',e=>{const bt=e.target.closest('.inventory-more');if(!bt)return;const row=bt.closest('.inventory-row');const open=row.classList.toggle('is-open');bt.setAttribute('aria-expanded',open?'true':'false');bt.textContent=open?tt('js.inv_hide_details'):tt('js.inv_show_details')});
 document.querySelectorAll('.inventory-sort').forEach(btn=>btn.addEventListener('click',()=>{const key=btn.dataset.sort;const dir=sortKey===key&&sortDir==='asc'?'desc':'asc';sortRows(key,dir)}));sortSelect.addEventListener('change',()=>{const [key,dir]=sortSelect.value.split(':');sortRows(key,dir)});
 if(printLink)printLink.addEventListener('click',e=>{if(!dirty)return;e.preventDefault();alert(tt('js.inv_save_before_print'))});
 if(excelLink)excelLink.addEventListener('click',e=>{if(!dirty)return;e.preventDefault();alert(tt('js.inv_save_before_excel'))});
 if(!readOnly)form.addEventListener('submit',()=>{dirty=false;form.dataset.dirty='0'});window.addEventListener('beforeunload',e=>{if(!dirty)return;e.preventDefault();e.returnValue=''});
 updateSummary();sortRows('car','asc');filterRows();
})();

// Linkki päivittyy rekisterin mukaan; kenttien sisältöön ei kosketa.
document.querySelectorAll('.vehicle-form').forEach(form=>{
 const input=form.querySelector('[name="reg_plate"]'),link=form.querySelector('.biltema-open'),status=form.querySelector('.biltema-status');
 if(!input||!link)return;
 const update=()=>{const value=input.value.toUpperCase().replace(/\s+/g,'');const match=value.match(/^([A-ZÅÄÖ]{1,3})-?([0-9]{1,3})$/u);const plate=match?match[1]+'-'+match[2]:'';link.href='https://www.biltema.fi/auton-varaosahaku/'+(plate?encodeURIComponent(plate)+'/':'');link.dataset.hasPlate=plate?'1':'0';};
 input.addEventListener('input',update);update();
 link.addEventListener('click',event=>{if(link.dataset.hasPlate!=='1'){event.preventDefault();if(status)status.textContent=tt('js.biltema_enter_plate');input.focus();}else if(status)status.textContent=tt('js.biltema_see_details');});
});


const search=document.getElementById('carSearch');if(search)search.addEventListener('input',()=>{const q=search.value.toLowerCase().trim();document.querySelectorAll('#cars .carcard').forEach(c=>c.style.display=c.dataset.search.includes(q)?'':'none')});const customerSearch=document.getElementById('customerSearch');if(customerSearch)customerSearch.addEventListener('input',()=>{const q=customerSearch.value.toLowerCase().trim();document.querySelectorAll('#customerCards .customer-card').forEach(c=>c.style.display=c.dataset.search.includes(q)?'':'none')});
function syncServiceRows(){let n=0;document.querySelectorAll('.service-form .maint-row[data-item]').forEach(r=>{const cb=r.querySelector('.service-check');r.classList.toggle('is-selected',!!cb?.checked);if(cb?.checked)n++});const sc=document.getElementById('serviceSelectedCount');if(sc)sc.textContent=n?(tt('js.service_selected_count',{n:n})):''}syncServiceRows();document.querySelectorAll('.service-check').forEach(cb=>cb.addEventListener('change',syncServiceRows));document.querySelectorAll('.maint-row input:not([type=checkbox])').forEach(i=>i.addEventListener('focus',()=>{const cb=i.closest('.maint-row')?.querySelector('.service-check');if(cb){cb.checked=true;syncServiceRows()}}));
function stockNum(v){const n=parseFloat(String(v??'').replace(',','.'));return Number.isFinite(n)?n:0}function stockFmt(v){return (Math.round(v*100)/100).toLocaleString('fi-FI',{maximumFractionDigits:2})}
function syncStockBox(box,copyPart=false){const use=box.querySelector('.stock-use-select'),part=box.querySelector('.stock-part-select'),qty=box.querySelector('.stock-qty-input'),balance=box.querySelector('.stock-balance');if(!use||!part)return;const yes=use.value==='1';box.querySelectorAll('.stock-use-details').forEach(el=>el.hidden=!yes);if(!yes)return;const opt=part.selectedOptions[0];if(!opt)return;const stock=stockNum(opt.dataset.stock),unit=opt.dataset.unit||tt('js.unit_default'),shelf=opt.dataset.shelf||'',existingPart=parseInt(box.dataset.existingPart||'0',10)||0,existingQty=stockNum(box.dataset.existingQty),available=stock+((parseInt(opt.value,10)||0)===existingPart?existingQty:0),wanted=stockNum(qty?.value);if(balance){balance.replaceChildren(document.createTextNode(tt('js.stock_balance_now')));const strong=document.createElement('strong');strong.textContent=stockFmt(stock)+' '+unit;balance.append(strong);if(shelf)balance.append(document.createTextNode(' · '+shelf));if((parseInt(opt.value,10)||0)===existingPart&&existingQty>0)balance.append(document.createElement('br'),document.createTextNode(tt('js.stock_linked',{linked:stockFmt(existingQty),unit:unit,available:stockFmt(available)})));if(wanted>available+0.000001){const warning=document.createElement('strong');warning.style.color='var(--bad)';warning.textContent=tt('js.stock_exceeds');balance.append(document.createElement('br'),warning)}};if(copyPart){const row=box.closest('.maint-row');[['service_brand',opt.dataset.brand],['service_supplier_sku',opt.dataset.sku],['service_oem_number',opt.dataset.oem]].forEach(([prefix,val])=>{if(!val)return;const input=row?.querySelector('[name^="'+prefix+'["]');if(input&&!input.value.trim())input.value=val})}}
document.querySelectorAll('.stock-use-field').forEach(box=>{const use=box.querySelector('.stock-use-select'),part=box.querySelector('.stock-part-select'),qty=box.querySelector('.stock-qty-input');use?.addEventListener('change',()=>syncStockBox(box,true));part?.addEventListener('change',()=>syncStockBox(box,true));qty?.addEventListener('input',()=>syncStockBox(box,false));syncStockBox(box,false)});
document.querySelectorAll('.service-action-select').forEach(sel=>sel.addEventListener('change',()=>{const row=sel.closest('.maint-row');const cb=row?.querySelector('.reset-interval-check');if(!row||!cb)return;const schedule=row.dataset.schedule||'replace',a=sel.value,item=row.dataset.item||'';if(['aux_belt','timing_belt'].includes(item))cb.checked=a==='replaced';else if(['brakes_front','brakes_rear'].includes(item))cb.checked=true;else if(schedule==='inspect')cb.checked=['done','inspected','replaced','repaired'].includes(a);else cb.checked=['replaced','repaired'].includes(a)}));
const sel=document.getElementById('selectRecommended');if(sel)sel.addEventListener('click',()=>{document.querySelectorAll('[data-recommend]').forEach(r=>{const k=r.dataset.recommend;const cb=document.querySelector('.maint-row[data-item="'+CSS.escape(k)+'"] .service-check');if(cb)cb.checked=true});syncServiceRows();document.getElementById('huolto')?.scrollIntoView({behavior:'smooth'})});
const serviceImages=document.getElementById('serviceImages'),selectedPhotoPreview=document.getElementById('selectedPhotoPreview');if(serviceImages&&selectedPhotoPreview)serviceImages.addEventListener('change',()=>{selectedPhotoPreview.innerHTML='';[...serviceImages.files].forEach(f=>{if(!f.type.startsWith('image/'))return;const card=document.createElement('div');card.className='photo-card';const img=document.createElement('img');img.alt=f.name;img.src=URL.createObjectURL(f);img.onload=()=>URL.revokeObjectURL(img.src);card.appendChild(img);const tools=document.createElement('div');tools.className='photo-tools';const input=document.createElement('input');input.className='photo-caption-input';input.name='service_image_captions[]';input.maxLength=240;input.placeholder=tt('js.photo_caption_placeholder');tools.appendChild(input);card.appendChild(tools);selectedPhotoPreview.appendChild(card)})});
const priceModeSelect=document.getElementById('priceInputMode');const settingsHourly=document.getElementById('settingHourlyRate');const settingsVat=document.getElementById('settingVatRate');const hourlyRateLabel=document.getElementById('hourlyRateLabel');if(priceModeSelect&&settingsHourly&&settingsVat){let previousPriceMode=priceModeSelect.value;const num=v=>{const n=parseFloat(String(v??'').replace(',','.'));return Number.isFinite(n)?n:null};const fmt=n=>Number.isFinite(n)?String(Math.round(n*100)/100).replace('.',','):'';const updatePricingLabel=()=>{const vat=num(settingsVat.value)??0;hourlyRateLabel.textContent=priceModeSelect.value==='gross'?tt('js.hourly_rate_gross',{vat:String(vat).replace('.',',')}):tt('js.hourly_rate_net');const grossOpt=priceModeSelect.querySelector('option[value="gross"]');if(grossOpt)grossOpt.textContent=tt('js.price_mode_gross',{vat:String(vat).replace('.',',')})};priceModeSelect.addEventListener('change',()=>{const value=num(settingsHourly.value);const vat=num(settingsVat.value)??0;const factor=1+vat/100;if(value!==null&&factor>0){if(previousPriceMode==='net'&&priceModeSelect.value==='gross')settingsHourly.value=fmt(value*factor);else if(previousPriceMode==='gross'&&priceModeSelect.value==='net')settingsHourly.value=fmt(value/factor)}previousPriceMode=priceModeSelect.value;updatePricingLabel()});settingsVat.addEventListener('input',updatePricingLabel);updatePricingLabel()}
const customRows=document.getElementById('customRows');const addCustom=document.getElementById('addCustom');const customInvoicingEnabled=customRows?.dataset.invoicingEnabled==='1';const customPriceLabel=customRows?.dataset.priceLabel||tt('js.custom_price_default');const customVatRate=customRows?.dataset.vatRate||'0';function bindRemove(){document.querySelectorAll('.removeCustom').forEach(b=>b.onclick=()=>{const rows=document.querySelectorAll('#customRows .custom-row');if(rows.length>1)b.closest('.custom-row').remove();else b.closest('.custom-row').querySelectorAll('input').forEach(i=>i.value='')})}bindRemove();if(addCustom)addCustom.onclick=()=>{const d=document.createElement('div');d.className='custom-row';d.innerHTML='<input name="custom_desc[]" placeholder="'+tt('js.custom_desc_placeholder')+'"><input name="custom_notes[]" placeholder="'+tt('js.custom_notes_placeholder')+'">'+(customInvoicingEnabled?'<input name="custom_price_net[]" placeholder="'+escapeHtmlAttr(customPriceLabel)+'">':'<input type="hidden" name="custom_price_net[]" value="">')+'<input type="hidden" name="custom_vat_rate[]" value="'+escapeHtmlAttr(customVatRate)+'"><button class="btn2 removeCustom" type="button">'+tt('js.remove')+'</button>';customRows.appendChild(d);bindRemove()};

document.querySelectorAll('.clear-stock-history-form').forEach(form=>form.addEventListener('submit',e=>{if(document.getElementById('inventoryBulkForm')?.dataset.dirty==='1'){e.preventDefault();alert(tt('js.save_before_clear_history'));return}if(!confirm(tt('js.confirm_clear_stock_history')))e.preventDefault()}));
document.querySelectorAll('.clear-auth-audit-form').forEach(form=>form.addEventListener('submit',e=>{const all=form.querySelector('[name="audit_scope"]').value==='all';if(!confirm(all?tt('js.confirm_clear_audit_all'):tt('js.confirm_clear_audit_logins')))e.preventDefault()}));
document.querySelectorAll('.delete-car-form').forEach(form=>form.addEventListener('submit',e=>{const label=form.dataset.deleteLabel||tt('js.this_car');if(!confirm(tt('js.confirm_delete_car_1',{label:label}))){e.preventDefault();return}if(!confirm(tt('js.confirm_delete_car_2',{label:label})))e.preventDefault()}));
document.querySelectorAll('.delete-service-form').forEach(form=>form.addEventListener('submit',e=>{const label=form.dataset.deleteLabel||tt('js.this_service');const draft=form.dataset.draftInvoice||'';const msg=draft?tt('js.confirm_delete_service_draft',{label:label,draft:draft}):tt('js.confirm_delete_service',{label:label});if(!confirm(msg))e.preventDefault()}));
document.querySelectorAll('.delete-invoice-form').forEach(form=>form.addEventListener('submit',e=>{const label=form.dataset.deleteLabel||tt('js.this_invoice_draft');if(!confirm(tt('js.confirm_delete_invoice',{label:label})))e.preventDefault()}));
document.querySelectorAll('.delete-photo-form').forEach(form=>form.addEventListener('submit',e=>{if(!confirm(tt('js.confirm_delete_photo')))e.preventDefault()}));
document.querySelectorAll('.delete-odometer-form').forEach(form=>form.addEventListener('submit',e=>{if(!confirm(tt('js.confirm_delete_odometer')))e.preventDefault()}));
const serviceOrigin=document.getElementById('serviceOrigin'),workshopField=document.getElementById('workshopField'),mechanicSelect=document.getElementById('mechanicSelect');if(serviceOrigin){const defaultWorkshop=serviceOrigin.dataset.defaultWorkshop||'';serviceOrigin.addEventListener('change',()=>{if(serviceOrigin.value==='external'){if(mechanicSelect)mechanicSelect.value='0';if(workshopField&&workshopField.value===defaultWorkshop)workshopField.value='';}else if(serviceOrigin.value==='own'){if(workshopField&&workshopField.value.trim()==='')workshopField.value=defaultWorkshop;if(mechanicSelect&&mechanicSelect.value==='0'&&serviceOrigin.dataset.defaultMechanic)mechanicSelect.value=serviceOrigin.dataset.defaultMechanic;}});}
const serviceType=document.getElementById('serviceType'),serviceTitle=document.getElementById('serviceTitle');if(serviceType&&serviceTitle){let lastAuto=serviceTitle.value;serviceType.addEventListener('change',()=>{if(serviceTitle.value.trim()===''||serviceTitle.value===lastAuto){serviceTitle.value=(serviceType.selectedOptions[0]?.textContent||serviceType.value).trim();lastAuto=serviceTitle.value}})}
const serviceForm=document.getElementById('serviceForm');const serviceHistory=(()=>{const raw=serviceForm?.dataset.serviceHistory||'[]';try{const parsed=JSON.parse(raw);return Array.isArray(parsed)?parsed:[]}catch(e){return[]}})();
function odometerHistoryIssues(form){const date=form.querySelector('[name="service_date"]')?.value||'';const odo=parseInt((form.querySelector('[name="odometer"]')?.value||'').replace(/\D/g,''),10)||0;const currentId=parseInt(form.querySelector('[name="service_id"]')?.value||'0',10)||0;if(!date||!odo)return[];const list=serviceHistory.filter(x=>x.id!==currentId&&x.date);let older=null,newer=null;for(const x of list){if(x.date<date&&(!older||x.date>older.date||(x.date===older.date&&x.id>older.id)))older=x;if(x.date>date&&(!newer||x.date<newer.date||(x.date===newer.date&&x.id<newer.id)))newer=x}const issues=[];if(older&&older.odometer>0&&odo<older.odometer)issues.push(tt('js.odo_older',{date:older.date.split('-').reverse().join('.'),odo:older.odometer.toLocaleString('fi-FI'),diff:(older.odometer-odo).toLocaleString('fi-FI')}));if(newer&&newer.odometer>0&&odo>newer.odometer)issues.push(tt('js.odo_newer',{date:newer.date.split('-').reverse().join('.'),odo:newer.odometer.toLocaleString('fi-FI'),diff:(odo-newer.odometer).toLocaleString('fi-FI')}));return issues}
function refreshOdoWarning(){const box=document.getElementById('odoHistoryWarning'),form=document.getElementById('serviceForm');if(!box||!form)return;const issues=odometerHistoryIssues(form);if(!issues.length){box.style.display='none';box.innerHTML='';return}box.style.display='block';box.innerHTML='<strong>'+tt('js.odo_warn_title')+'</strong><br>'+issues.map(x=>x.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')).join('<br>')+'<br><span class="tiny">'+tt('js.odo_warn_note')+'</span>'}
if(serviceForm){serviceForm.querySelector('[name="service_date"]')?.addEventListener('change',refreshOdoWarning);serviceForm.querySelector('[name="odometer"]')?.addEventListener('input',refreshOdoWarning);refreshOdoWarning();serviceForm.addEventListener('submit',e=>{const marked=[...serviceForm.querySelectorAll('input[name="delete_photo[]"]:checked')];if(marked.length&&!confirm(tt(marked.length===1?'js.confirm_delete_photos_one':'js.confirm_delete_photos_many',{n:marked.length}))){e.preventDefault();return}for(const box of serviceForm.querySelectorAll('.maint-row.is-selected .stock-use-field')){const use=box.querySelector('.stock-use-select');if(use?.value!=='1')continue;const part=box.querySelector('.stock-part-select'),qty=box.querySelector('.stock-qty-input'),opt=part?.selectedOptions?.[0];const wanted=stockNum(qty?.value);if(!opt||wanted<=0){alert(tt('js.stock_qty_required'));e.preventDefault();return}const stock=stockNum(opt.dataset.stock),existingPart=parseInt(box.dataset.existingPart||'0',10)||0,existingQty=stockNum(box.dataset.existingQty),available=stock+(((parseInt(opt.value,10)||0)===existingPart)?existingQty:0);if(wanted>available+0.000001){alert(tt('js.stock_insufficient',{available:stockFmt(available),unit:(opt.dataset.unit||tt('js.unit_default'))}));e.preventDefault();return}}const issues=odometerHistoryIssues(serviceForm);if(issues.length&&!confirm(tt('js.confirm_odo_mismatch',{issues:issues.join('\n')})))e.preventDefault()})}


/* Työaikalaskuri: tila säilyy localStoragessa myös sivun vaihtuessa tai puhelimen näytön sammuessa. */
const workTimer=document.getElementById('workTimer');
if(workTimer){
 const display=document.getElementById('workTimerDisplay'),stateEl=document.getElementById('workTimerState'),hidden=document.getElementById('actualWorkSeconds');
 const startBtn=document.getElementById('timerStart'),pauseBtn=document.getElementById('timerPause'),stopBtn=document.getElementById('timerStop'),resetBtn=document.getElementById('timerReset');
 const key=workTimer.dataset.storageKey||'';let elapsed=parseInt(workTimer.dataset.initialSeconds||hidden?.value||'0',10)||0;let running=false;let startedAt=0;let ticker=null;
 try{const saved=JSON.parse(localStorage.getItem(key)||'null');if(saved&&Number.isFinite(saved.elapsed)){elapsed=Math.max(elapsed,parseInt(saved.elapsed||0,10)||0);running=!!saved.running;startedAt=parseInt(saved.startedAt||0,10)||0;if(running&&startedAt<=0){running=false;startedAt=0}}}catch(e){}
 const totalSeconds=()=>Math.max(0,elapsed+(running&&startedAt?Math.floor((Date.now()-startedAt)/1000):0));
 const format=s=>{s=Math.max(0,Math.floor(s));const h=Math.floor(s/3600),m=Math.floor((s%3600)/60),ss=s%60;return String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(ss).padStart(2,'0')};
 const persist=()=>{const total=totalSeconds();if(hidden)hidden.value=String(total);try{localStorage.setItem(key,JSON.stringify({elapsed:running?elapsed:total,running,startedAt}))}catch(e){}};
 const render=()=>{const total=totalSeconds();if(display)display.textContent=format(total);if(hidden)hidden.value=String(total);if(stateEl)stateEl.textContent=running?tt('js.timer_running'):tt('js.timer_idle');startBtn&&(startBtn.disabled=running);pauseBtn&&(pauseBtn.disabled=!running);stopBtn&&(stopBtn.disabled=!running)};
 const ensureTicker=()=>{clearInterval(ticker);if(running)ticker=setInterval(render,1000);render()};
 startBtn?.addEventListener('click',()=>{if(running)return;running=true;startedAt=Date.now();persist();ensureTicker()});
 const pause=label=>{if(!running)return;elapsed=totalSeconds();running=false;startedAt=0;persist();ensureTicker();if(stateEl)stateEl.textContent=label};
 pauseBtn?.addEventListener('click',()=>pause(tt('js.timer_paused')));
 stopBtn?.addEventListener('click',()=>pause(tt('js.timer_stopped')));
 resetBtn?.addEventListener('click',()=>{if(!confirm(tt('js.timer_confirm_reset')))return;elapsed=0;running=false;startedAt=0;try{localStorage.removeItem(key)}catch(e){};persist();ensureTicker()});
 serviceForm?.addEventListener('submit',e=>{if(e.defaultPrevented)return;if(running){elapsed=totalSeconds();running=false;startedAt=0}persist();try{localStorage.removeItem(key)}catch(e){}});
 ensureTicker();
}

/* Huoltotilanteen taulukon lajittelu: otsikkoa klikkaamalla nouseva/laskeva järjestys. */
const maintenanceStatusTable=document.querySelector('.service-status-table');
if(maintenanceStatusTable){
 const sortButtons=[...maintenanceStatusTable.querySelectorAll('.service-status-sort')];
 const sortRows=(key,dir)=>{
  const tbody=maintenanceStatusTable.tBodies[0];if(!tbody)return;
  const rows=[...tbody.rows];
  rows.sort((a,b)=>{
   const av=a.dataset['sort'+key.charAt(0).toUpperCase()+key.slice(1)]||'';
   const bv=b.dataset['sort'+key.charAt(0).toUpperCase()+key.slice(1)]||'';
   const c=key==='kohde'||key==='seuraava'?av.localeCompare(bv,'fi-FI',{numeric:true,sensitivity:'base'}):av.localeCompare(bv,'fi-FI',{numeric:true,sensitivity:'base'});
   if(c!==0)return dir==='desc'?-c:c;
   const ai=rows.indexOf(a),bi=rows.indexOf(b);return ai-bi;
  });
  rows.forEach(r=>tbody.appendChild(r));
  sortButtons.forEach(btn=>{const active=btn.dataset.sort===key;btn.classList.toggle('active',active);btn.dataset.dir=active?dir:'';const th=btn.closest('th');if(th)th.setAttribute('aria-sort',active?(dir==='asc'?'ascending':'descending'):'none')});
 };
 sortButtons.forEach(btn=>btn.addEventListener('click',()=>{const key=btn.dataset.sort||'';if(!key)return;const dir=btn.classList.contains('active')&&btn.dataset.dir==='asc'?'desc':'asc';sortRows(key,dir)}));
}

/* Huoltohistorian haku ja suodatus */
const historySearch=document.getElementById('historySearch'),historyType=document.getElementById('historyType'),historyYear=document.getElementById('historyYear'),historyResultCount=document.getElementById('historyResultCount');
const historyList=document.getElementById('historyList'),historyMore=document.getElementById('historyMore');let historyExpanded=false;if(historyMore)historyMore.addEventListener('click',()=>{historyExpanded=true;filterHistory()});
function filterHistory(){const q=(historySearch?.value||'').toLocaleLowerCase('fi-FI').trim(),t=historyType?.value||'',y=historyYear?.value||'';let visible=0,total=0;document.querySelectorAll('#historia .history-entry').forEach(el=>{total++;const okQ=!q||(el.dataset.search||'').includes(q),okT=!t||el.dataset.type===t,okY=!y||el.dataset.year===y;const ok=okQ&&okT&&okY;el.classList.toggle('is-filtered-out',!ok);if(ok)visible++});if(historyResultCount)historyResultCount.textContent=(q||t||y)?(tt('js.history_showing',{visible:visible,total:total})):'';const filtering=!!(q||t||y);if(historyList)historyList.classList.toggle('is-collapsed',!filtering&&!historyExpanded);if(historyMore)historyMore.hidden=filtering||historyExpanded}
[historySearch,historyType,historyYear].forEach(el=>{if(el){el.addEventListener('input',filterHistory);el.addEventListener('change',filterHistory)}});filterHistory();

/* Mobiilin huoltolomakkeen luonnostallennus. Tiedostoja ei voi selaimen turvarajoitusten vuoksi palauttaa. */
const serviceSavedOk=serviceForm?.dataset.serviceSavedOk==='1';
function draftSnapshot(form){
 const controls=[];form.querySelectorAll('input,select,textarea').forEach(el=>{if(!el.name||['csrf','action','car_id','service_id','edit_version','delete_photo[]'].includes(el.name)||el.disabled||el.readOnly||el.type==='file'||el.name.startsWith('custom_'))return;controls.push({name:el.name,type:el.type,value:el.value,checked:!!el.checked})});
 const custom=[...form.querySelectorAll('#customRows .custom-row')].map(r=>({desc:r.querySelector('[name="custom_desc[]"]')?.value||'',notes:r.querySelector('[name="custom_notes[]"]')?.value||'',price:r.querySelector('[name="custom_price_net[]"]')?.value||''}));
 return {savedAt:Date.now(),controls,custom};
}
function escapeHtmlAttr(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;')}
function addCustomRowValues(v={}){if(!customRows)return;const d=document.createElement('div');d.className='custom-row';d.innerHTML='<input name="custom_desc[]" placeholder="'+tt('js.custom_desc_placeholder')+'"><input name="custom_notes[]" placeholder="'+tt('js.custom_notes_placeholder')+'">'+(customInvoicingEnabled?'<input name="custom_price_net[]" placeholder="'+escapeHtmlAttr(customPriceLabel)+'">':'<input type="hidden" name="custom_price_net[]" value="">')+'<input type="hidden" name="custom_vat_rate[]" value="'+escapeHtmlAttr(customVatRate)+'"><button class="btn2 removeCustom" type="button">'+tt('js.remove')+'</button>';d.querySelector('[name="custom_desc[]"]').value=v.desc||'';d.querySelector('[name="custom_notes[]"]').value=v.notes||'';const p=d.querySelector('[name="custom_price_net[]"]');if(p)p.value=v.price||'';customRows.appendChild(d);bindRemove()}
function restoreDraft(form,draft){for(const c of draft.controls||[]){if(['csrf','action','car_id','service_id','edit_version','delete_photo[]'].includes(c.name))continue;const els=[...form.querySelectorAll('[name="'+CSS.escape(c.name)+'"]')];let el=els.find(x=>(x.type==='checkbox'||x.type==='radio')?x.value===c.value:true);if(!el)continue;if(el.type==='checkbox'||el.type==='radio')el.checked=!!c.checked;else el.value=c.value}if(customRows&&Array.isArray(draft.custom)){customRows.innerHTML='';(draft.custom.length?draft.custom:[{}]).forEach(addCustomRowValues)}syncServiceRows();refreshOdoWarning()}
if(serviceForm&&!(serviceForm.querySelector('[name="service_id"]'))){
 const draftKey=serviceForm.dataset.draftKey||'',legacyKey=serviceForm.dataset.legacyDraftKey||'';
 if(draftKey){
  const content=d=>JSON.stringify({controls:d.controls||[],custom:d.custom||[]});
  const baseline=content(draftSnapshot(serviceForm));let notice=null,draftTimer=null;
  const readDraft=key=>{if(!key)return null;try{const raw=localStorage.getItem(key);if(!raw)return null;const d=JSON.parse(raw);if(d&&Array.isArray(d.controls)&&Number.isFinite(d.savedAt)&&Date.now()-d.savedAt>=0&&Date.now()-d.savedAt<30*86400000)return d;localStorage.removeItem(key);}catch(e){}return null;};
  const remove=key=>{if(key)try{localStorage.removeItem(key)}catch(e){}};
  const showNotice=(draft,key)=>{
   notice=document.createElement('div');notice.className='flash warn service-draft-notice';notice.setAttribute('role','status');
   const title=document.createElement('strong');title.textContent=key===legacyKey?tt('js.draft_found_legacy'):tt('js.draft_found');
   const info=document.createElement('p');info.textContent=tt('js.draft_info');
   const buttons=document.createElement('div');buttons.className='form-actions';
   const restore=document.createElement('button');restore.type='button';restore.className='btn2';restore.textContent=tt('js.draft_restore');
   const discard=document.createElement('button');discard.type='button';discard.className='btn2';discard.textContent=tt('js.draft_discard');
   restore.addEventListener('click',()=>{restoreDraft(serviceForm,draft);remove(key);notice?.remove();notice=null;saveDraft();});
   discard.addEventListener('click',()=>{remove(key);notice?.remove();notice=null;});
   buttons.append(restore,discard);notice.append(title,info,buttons);serviceForm.before(notice);
  };
  const persistDraft=()=>{
   const draft=draftSnapshot(serviceForm);
   if(content(draft)===baseline){remove(draftKey);return;}
   draft.format=2;try{localStorage.setItem(draftKey,JSON.stringify(draft));notice?.remove();notice=null;}catch(e){}
  };
  const saveDraft=()=>{clearTimeout(draftTimer);draftTimer=setTimeout(persistDraft,350);};
  if(serviceSavedOk){remove(draftKey);remove(legacyKey);}
  else{const draft=readDraft(draftKey);if(draft)showNotice(draft,draftKey);else{const old=readDraft(legacyKey);if(old)showNotice(old,legacyKey);}}
  serviceForm.addEventListener('input',saveDraft);serviceForm.addEventListener('change',saveDraft);
  // Navigointi ei luo oletusarvoista luonnosta; viimeinen oikea muutos tallennetaan heti.
  addEventListener('pagehide',()=>{if(draftTimer!==null){clearTimeout(draftTimer);persistDraft();}});
 }
}

/* varastotapahtuma (+/−) ja varaosan lisäys varastosivulta */
(()=>{
 const bulk=document.getElementById('inventoryBulkForm');
 const closeBtns=document.querySelectorAll('[data-close-dialog]');closeBtns.forEach(b=>b.addEventListener('click',()=>b.closest('dialog')?.close()));
 document.querySelectorAll('dialog.app-dialog').forEach(d=>d.addEventListener('click',e=>{if(e.target===d)d.close()}));
 const show=d=>{if(typeof d.showModal==='function')d.showModal();else d.setAttribute('open','')};
 const mv=document.getElementById('stockMoveDialog');
 if(mv){
  const qty=document.getElementById('smQty'),inR=document.getElementById('smIn'),outR=document.getElementById('smOut');let stock=0;
  const syncMax=()=>{if(outR.checked){qty.max=String(stock);qty.title=tt('js.stock_in_stock',{n:stock})}else{qty.removeAttribute('max');qty.title=''}};
  [inR,outR].forEach(r=>r.addEventListener('change',syncMax));
  document.querySelectorAll('.stock-move-btn').forEach(b=>b.addEventListener('click',()=>{
   if(bulk&&bulk.dataset.dirty==='1'){alert(tt('js.stock_move_save_first'));return}
   stock=parseFloat(b.dataset.stock)||0;
   document.getElementById('smPart').value=b.dataset.part;document.getElementById('smCar').value=b.dataset.car;
   document.getElementById('smTitle').textContent=b.dataset.name;
   document.getElementById('smInfo').textContent=tt('js.stock_current_balance',{stock:String(stock).replace('.',','),unit:b.dataset.unit});
   document.getElementById('smUnit').textContent='('+b.dataset.unit+')';
   (b.dataset.dir==='out'?outR:inR).checked=true;qty.value='';document.getElementById('smNote').value='';syncMax();show(mv);setTimeout(()=>qty.focus(),30);
  }));
 }
 const ap=document.getElementById('addPartDialog'),open=document.getElementById('addPartOpen');
 if(ap&&open){
  const car=document.getElementById('apCar');
  const syncCompat=()=>ap.querySelectorAll('.compat-choice[data-car]').forEach(l=>{const same=l.dataset.car===car.value;l.hidden=same;if(same)l.querySelector('input').checked=false});
  car.addEventListener('change',syncCompat);
  open.addEventListener('click',()=>{if(bulk&&bulk.dataset.dirty==='1'){alert(tt('js.stock_add_part_save_first'));return}const f=document.getElementById('inventoryCarFilter');if(f&&f.value)car.value=f.value;syncCompat();show(ap)});
  syncCompat();
 }
})();

/* asetusten välilehdet, kelluvan tallennuspalkin muutosmerkki, salasanan näyttö */
(function(){
  var nav=document.getElementById('settingsTabs'),wrap=document.getElementById('settingsPanels');
  if(nav&&wrap){
    var secs=[].slice.call(wrap.querySelectorAll('[data-tab]')),links=[].slice.call(nav.querySelectorAll('[data-tab-link]'));
    var keys=links.map(function(a){return a.getAttribute('data-tab-link');});
    var show=function(k,store){
      if(keys.indexOf(k)<0)k=keys[0];
      secs.forEach(function(s){s.hidden=s.getAttribute('data-tab')!==k;});
      links.forEach(function(a){var on=a.getAttribute('data-tab-link')===k;a.classList.toggle('is-active',on);a.setAttribute('aria-current',on?'page':'false');});
      if(store){try{sessionStorage.setItem('settingsTab',k);}catch(e){}}
    };
    var target=function(){
      var id='';try{id=decodeURIComponent((location.hash||'').slice(1));}catch(e){}
      var el=id?document.getElementById(id):null,s=el?el.closest('[data-tab]'):null;
      return s?{key:s.getAttribute('data-tab'),el:el}:null;
    };
    var openParents=function(el){for(var p=el;p&&p!==wrap;p=p.parentElement){if(p.tagName==='DETAILS')p.open=true;}};
    var init=function(){
      var t=target();
      if(t){show(t.key,true);openParents(t.el);setTimeout(function(){t.el.scrollIntoView({block:'start'});},30);return;}
      var k=null;try{k=sessionStorage.getItem('settingsTab');}catch(e){}
      show(k||keys[0],false);
    };
    links.forEach(function(a){a.addEventListener('click',function(ev){
      ev.preventDefault();show(a.getAttribute('data-tab-link'),true);
      try{history.replaceState(null,'',location.pathname+location.search);}catch(e){}
      if(nav.getBoundingClientRect().top<0)nav.scrollIntoView({block:'start'});
    });});
    window.addEventListener('hashchange',init);
    init();
  }
  /* Tallentamattomien muutosten merkki kelluvassa palkissa */
  [].forEach.call(document.querySelectorAll('form[data-savebar]'),function(f){
    var note=f.querySelector('.savebar-note');if(!note)return;
    var mark=function(){note.hidden=false;f.setAttribute('data-dirty','1');};
    f.addEventListener('input',mark);f.addEventListener('change',mark);
    f.addEventListener('submit',function(){f.removeAttribute('data-dirty');});
  });
  /* Näytä salasanat -valinta */
  [].forEach.call(document.querySelectorAll('[data-show-passwords]'),function(cb){
    cb.addEventListener('change',function(){
      var form=cb.closest('form');if(!form)return;
      [].forEach.call(form.querySelectorAll('input[type=password],input[data-was-password]'),function(i){
        i.type=cb.checked?'text':'password';i.setAttribute('data-was-password','1');
      });
    });
  });
})();

/* varaosakate. Varastosta otetulle osalle ehdotetaan hankintahinta × (1 + kate); käsin hinnoitellulle osalle ＋kate -painike. */
(function(){
  var form=document.getElementById('serviceForm');if(!form)return;
  var pct=parseFloat(form.dataset.markup||'');if(!isFinite(pct)||pct<=0)return;
  var num=function(v){var n=parseFloat(String(v||'').replace(/\s/g,'').replace(',','.'));return isFinite(n)?n:NaN;};
  var fmt=function(n){return (Math.round(n*100)/100).toFixed(2).replace('.',',');};
  var rowOf=function(el){return el.closest('.maint-row');};
  var priceOf=function(row){return row?row.querySelector('input[name^="service_price_net["]'):null;};
  var setNote=function(row,text){var n=row?row.querySelector('.markup-note'):null;if(!n)return;n.textContent=text||'';n.hidden=!text;};
  var markPrice=function(input,value,note){input.value=value;input.dataset.autoPrice=value;setNote(rowOf(input),note);input.dispatchEvent(new Event('change',{bubbles:true}));};
  /* Kun käyttäjä kirjoittaa hinnan käsin, kate-merkintä poistuu */
  form.addEventListener('input',function(ev){var t=ev.target;if(ev.isTrusted&&t&&t.matches&&t.matches('input[name^="service_price_net["]')){delete t.dataset.autoPrice;setNote(rowOf(t),'');}});
  /* ＋kate -painike */
  form.addEventListener('click',function(ev){
    var btn=ev.target.closest&&ev.target.closest('[data-markup-btn]');if(!btn)return;ev.preventDefault();
    var row=rowOf(btn),input=priceOf(row);if(!input)return;
    var v=num(input.value);if(!(v>0)){input.focus();setNote(row,tt('js.markup_enter_price'));return;}
    markPrice(input,fmt(v*(1+pct/100)),tt('js.markup_note',{pct:String(pct).replace('.',',')}));
  });
  /* Varastosta otettu osa: ehdota hinta vain, kun kenttä on tyhjä tai sisältää aiemman automaattisen ehdotuksen */
  var onStock=function(box){
    var row=rowOf(box),input=priceOf(row),use=box.querySelector('.stock-use-select'),part=box.querySelector('.stock-part-select');
    if(!input||!use||!part)return;
    var pristine=input.value.trim()===''||input.dataset.autoPrice===input.value;
    if(use.value!=='1'){if(input.dataset.autoPrice&&input.dataset.autoPrice===input.value){input.value='';delete input.dataset.autoPrice;setNote(row,'');}return;}
    var opt=part.selectedOptions[0],buy=opt?num(opt.dataset.price):NaN;
    if(!(buy>=0)||!pristine)return;
    if(buy===0)return;
    markPrice(input,fmt(buy*(1+pct/100)),tt('js.markup_note_stock',{buy:fmt(buy),pct:String(pct).replace('.',',')}));
  };
  form.querySelectorAll('.stock-use-field').forEach(function(box){
    var use=box.querySelector('.stock-use-select'),part=box.querySelector('.stock-part-select');
    if(use)use.addEventListener('change',function(){onStock(box);});
    if(part)part.addEventListener('change',function(){onStock(box);});
  });
})();

/* Saavutettavuus: nimiöt kentille, joilla ei ole omaa nimiötä (taulukkokentät, pikasyötteet). Nimi johdetaan paikkamerkistä, sarakeotsikosta tai rivin ensimmäisestä solusta. */
(()=>{
 function nameFor(el){
  var ph=el.getAttribute('placeholder');if(ph)return ph;
  var td=el.closest('td,th');
  if(td){
   var tr=td.parentElement,table=tr&&tr.closest('table');
   if(table){
    var idx=Array.prototype.indexOf.call(tr.children,td),head=table.querySelector('thead tr');
    var col=head&&head.children[idx]?head.children[idx].textContent.trim():'';
    var first=tr.children[0]&&tr.children[0]!==td?tr.children[0].textContent.trim().replace(/\s+/g,' ').slice(0,60):'';
    if(col&&first)return col+' – '+first;if(col)return col;
   }
  }
  var wrap=el.closest('.cat-row,.mech-card,.user-row,div');
  var lab=wrap&&wrap.querySelector('label,.row-lbl');if(lab&&lab.textContent.trim())return lab.textContent.trim().slice(0,80);
  return '';
 }
 function run(){
  var sel='input:not([type=hidden]):not([type=submit]):not([type=button]):not([type=checkbox]):not([type=radio]),select,textarea';
  document.querySelectorAll(sel).forEach(function(el){
   if(el.id&&document.querySelector('label[for="'+el.id.replace(/"/g,'\\"')+'"]'))return;
   if(el.closest('label')||el.getAttribute('aria-label')||el.getAttribute('aria-labelledby')||el.title)return;
   var n=nameFor(el);if(n)el.setAttribute('aria-label',n);
  });
  document.querySelectorAll('input[type=checkbox],input[type=radio]').forEach(function(el){
   if(el.closest('label')||el.getAttribute('aria-label')||(el.id&&document.querySelector('label[for="'+el.id+'"]')))return;
   var n=nameFor(el);if(n)el.setAttribute('aria-label',n);
  });
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',run);else run();
})();

/* Autosivun kiinteä pikavalikko: näkyy, kun yläosan pikavalikko on vierittynyt pois näkyvistä. */
(()=>{
 var src=document.querySelector('.car-quicknav');if(!src||!('IntersectionObserver' in window))return;
 var bar=document.createElement('nav');bar.className='car-sticky-nav';bar.setAttribute('aria-label',src.getAttribute('aria-label')||'');bar.hidden=true;
 [].forEach.call(src.querySelectorAll('a'),function(a){var c=document.createElement('a');c.href=a.getAttribute('href');c.textContent=a.textContent;bar.appendChild(c);});
 document.body.appendChild(bar);
 new IntersectionObserver(function(es){var e=es[0];bar.hidden=e.isIntersecting||e.boundingClientRect.top>0;},{threshold:0}).observe(src);
})();
