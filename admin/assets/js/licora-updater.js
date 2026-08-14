(function(){
'use strict';
var initial=window.LicoraUpdaterInitial||{};
var state={update:initial.update||{},job:initial.active_job||null,preflightVersion:null,lastEventId:0,driving:false,totalEvents:0,retryMs:700};
var csrf=(document.querySelector('meta[name="csrf-token"]')||{}).content||'';
var confirmResolver=null;
function el(id){return document.getElementById(id);}
function sleep(ms){return new Promise(function(r){setTimeout(r,ms);});}
function jobIsActive(job){return !!job&&['running','rollback_running'].indexOf(job.status)>=0;}
function requiredDomIds(){return [
'updater-page-error','current-version','last-checked','latest-version','latest-published','release-notes','update-availability-badge',
'update-check-button','update-preflight-button','update-install-button','preflight-summary','preflight-results','active-job-panel','update-open-logs',
'active-job-id','active-job-progress','active-job-stage','active-job-percent','licora-update-log-title','update-log-description','update-log-stage',
'update-download-diagnostics','update-log-search','update-log-filter','update-log-pin','update-log-viewport','update-log-count','update-copy-logs',
'licora-update-log-modal','update-history-body','licora-update-confirm-modal','update-confirm-message','update-confirm-proceed','update-confirm-cancel'
];}
function validateDom(){
    var missing=requiredDomIds().filter(function(id){return !el(id);});
    var logModal=el('licora-update-log-modal');
    if(logModal&&!logModal.querySelector('[data-log-close]'))missing.push('licora-update-log-modal[data-log-close]');
    var confirmModal=el('licora-update-confirm-modal');
    if(confirmModal&&!confirmModal.querySelector('[data-confirm-close]'))missing.push('licora-update-confirm-modal[data-confirm-close]');
    if(!missing.length)return true;
    var message='Updater UI contract is incomplete. Missing required element(s): '+missing.join(', ')+'. Reload the verified Licora source before continuing.';
    var box=el('updater-page-error');
    if(box){box.textContent=message;box.hidden=false;}
    ['update-check-button','update-preflight-button','update-install-button'].forEach(function(id){var button=el(id);if(button)button.disabled=true;});
    if(window.console&&console.error)console.error(message);
    return false;
}
async function api(url,options){
    options=options||{};
    var headers=Object.assign({'Accept':'application/json'},options.headers||{});
    if(options.method==='POST'){headers['Content-Type']='application/json';headers['X-CSRF-Token']=csrf;}
    var response=await fetch(url,{method:options.method||'GET',headers:headers,body:options.body?JSON.stringify(options.body):undefined,credentials:'same-origin',cache:'no-store'});
    var data;
    try{data=await response.json();}catch(e){throw new Error('Updater returned a non-JSON response (HTTP '+response.status+').');}
    if(!response.ok||data.success===false){var err=new Error(data.message||('Updater request failed (HTTP '+response.status+').'));err.code=data.code||'UPDATE_REQUEST_FAILED';throw err;}
    return data;
}
function showPageError(message){var box=el('updater-page-error');if(!box)return;box.textContent=message||'';box.hidden=!message;}
function fmtTime(epoch){if(!epoch)return'Not yet';try{return new Date(epoch*1000).toLocaleString();}catch(e){return String(epoch);}}
function updateReleaseUI(data){
    state.update=data||{};
    var latest=data.latest||null;
    el('current-version').textContent=data.current_version||'';
    el('last-checked').textContent=fmtTime(data.last_checked_at);
    el('latest-version').textContent=latest?latest.version:'Unavailable';
    el('latest-published').textContent=latest?(latest.published_at||'—'):'—';
    el('release-notes').textContent=latest?(latest.body||'No release notes provided.'):'No release metadata available.';
    var badge=el('update-availability-badge');
    badge.textContent=data.update_available?'Update available':'Up to date';
    badge.className='vib-badge'+(data.update_available?' vib-badge-success':'');
    var active=jobIsActive(state.job);
    var pre=el('update-preflight-button');pre.disabled=!data.update_available||active;
    state.preflightVersion=null;
    el('update-install-button').disabled=true;
    el('preflight-summary').textContent='Not run';
    el('preflight-summary').className='vib-badge';
    el('preflight-results').innerHTML='<div class="licora-inline-info">Run Preflight to verify signature, PHP/extensions, storage, source permissions, disk space, and updater compatibility.</div>';
    if(data.warning)showPageError(data.warning);
}
function renderPreflight(payload){
    var result=payload.preflight||{ok:false,checks:[]};
    var box=el('preflight-results');box.innerHTML='';
    (result.checks||[]).forEach(function(c){
        var row=document.createElement('div');row.className='licora-preflight-row';row.setAttribute('data-ok',c.ok?'1':'0');
        var icon=document.createElement('span');icon.className='licora-preflight-icon';icon.textContent=c.ok?'✓':'×';
        var label=document.createElement('span');label.className='licora-preflight-label';label.textContent=c.label;
        var detail=document.createElement('span');detail.className='licora-preflight-detail';detail.textContent=c.detail||'';
        row.append(icon,label,detail);box.appendChild(row);
    });
    var badge=el('preflight-summary');badge.textContent=result.ok?'Ready to install':'Blocked';badge.className='vib-badge '+(result.ok?'vib-badge-success':'vib-badge-danger');
    state.preflightVersion=result.ok?payload.version:null;
    el('update-install-button').disabled=!result.ok||jobIsActive(state.job);
}
function renderJob(job){
    state.job=job||null;
    var panel=el('active-job-panel'),open=el('update-open-logs');
    if(!job){panel.hidden=true;open.hidden=true;return;}
    panel.hidden=false;open.hidden=false;
    el('active-job-id').textContent=job.job_uuid;
    el('active-job-progress').style.width=Math.max(0,Math.min(100,job.progress||0))+'%';
    el('active-job-stage').textContent=(job.status||'')+' · '+(job.stage||'');
    el('active-job-percent').textContent=(job.progress||0)+'%';
    el('licora-update-log-title').textContent='Licora Update — '+job.from_version+' → '+job.target_version;
    el('update-log-description').textContent=(job.status||'')+' · '+(job.stage||'');
    el('update-log-stage').textContent='Stage: '+(job.stage||'unknown')+' · '+(job.progress||0)+'%';
    el('update-download-diagnostics').href='ajax/update-diagnostics.php?job='+encodeURIComponent(job.job_uuid);
    var terminal=['success','failed','rolled_back'].indexOf(job.status)>=0;
    if(terminal){
        state.driving=false;
        el('update-preflight-button').disabled=!(state.update&&state.update.update_available);
        el('update-install-button').disabled=true;
        if(job.status==='success'){el('current-version').textContent=job.target_version;}
        if(job.error_message)showPageError((job.error_code?job.error_code+': ':'')+job.error_message);
    }else{
        el('update-preflight-button').disabled=true;
        el('update-install-button').disabled=true;
    }
}
function openModal(){el('licora-update-log-modal').hidden=false;document.body.style.overflow='hidden';if(el('update-log-pin').checked)scrollLogs();}
function closeModal(){el('licora-update-log-modal').hidden=true;if(el('licora-update-confirm-modal').hidden)document.body.style.overflow='';}
function scrollLogs(){var v=el('update-log-viewport');v.scrollTop=v.scrollHeight;}
function applyLogFilter(){
    var q=(el('update-log-search').value||'').toLowerCase();var level=el('update-log-filter').value;
    var rows=[].slice.call(el('update-log-viewport').querySelectorAll('.vib-log-row'));var visible=0;
    rows.forEach(function(row){var ok=(!level||row.dataset.level===level)&&(!q||row.textContent.toLowerCase().indexOf(q)>=0);row.hidden=!ok;if(ok)visible++;});
    el('update-log-count').textContent=visible+' / '+rows.length+' lines';
}
function appendEvents(events){
    var viewport=el('update-log-viewport');var empty=el('update-log-empty');if(events.length&&empty)empty.remove();
    events.forEach(function(event){
        var row=document.createElement('div');row.className='vib-log-row';row.dataset.level=event.level||'info';row.dataset.eventId=event.id;
        var badge=document.createElement('span');badge.className='vib-log-badge';badge.textContent=event.level||'info';
        var time=document.createElement('span');time.className='vib-log-time';time.textContent='#'+event.id+' '+(event.created_at||'');
        var msg=document.createElement('span');msg.className='vib-log-message';msg.textContent='['+(event.stage||'update')+'] '+event.message;
        row.append(badge,time,msg);viewport.appendChild(row);state.totalEvents++;state.lastEventId=Math.max(state.lastEventId,Number(event.id)||0);
    });
    applyLogFilter();if(events.length&&el('update-log-pin').checked)scrollLogs();
}
async function fetchEvents(){if(!state.job)return;var data=await api('ajax/update-events.php?job='+encodeURIComponent(state.job.job_uuid)+'&after='+state.lastEventId);appendEvents(data.events||[]);state.lastEventId=Math.max(state.lastEventId,data.last_id||0);}
function resetLogs(){state.lastEventId=0;state.totalEvents=0;var v=el('update-log-viewport');v.innerHTML='<div class="vib-log-empty" id="update-log-empty">Waiting for updater events…</div>';el('update-log-count').textContent='0 / 0 lines';}
async function driveJob(){
    if(state.driving||!jobIsActive(state.job))return;
    state.driving=true;state.retryMs=700;
    while(state.driving&&jobIsActive(state.job)){
        try{
            await fetchEvents();
            var response=await api('ajax/update-step.php',{method:'POST',body:{job_uuid:state.job.job_uuid}});
            renderJob(response.job);await fetchEvents();state.retryMs=700;await sleep(500);
        }catch(err){
            showPageError((err.code?err.code+': ':'')+err.message);state.retryMs=Math.min(10000,state.retryMs*1.7);await sleep(state.retryMs);
            try{var status=await api('ajax/update-status.php?job='+encodeURIComponent(state.job.job_uuid));renderJob(status.job);}catch(ignore){}
        }
    }
    state.driving=false;
    try{await fetchEvents();await refreshHistory();}catch(ignore){}
}
async function refreshHistory(){
    var data=await api('ajax/update-status.php');
    if(data.update)updateReleaseUI(data.update);
    if(data.active_job){renderJob(data.active_job);}else if(state.job&&['success','failed','rolled_back'].indexOf(state.job.status)>=0){/* preserve terminal job panel until reload */}
    renderHistory(data.history||[]);
}
function statusClass(s){return s==='success'?'vib-badge-success':(s==='rolled_back'?'vib-badge-warning':(s==='failed'?'vib-badge-danger':''));}
function renderHistory(rows){
    var body=el('update-history-body');body.innerHTML='';
    if(!rows.length){body.innerHTML='<tr><td colspan="5">No updater jobs yet.</td></tr>';return;}
    rows.forEach(function(r){
        var tr=document.createElement('tr');tr.dataset.historyJob=r.job_uuid;
        var v=document.createElement('td');v.textContent=r.from_version+' → '+r.target_version;
        var st=document.createElement('td');var b=document.createElement('span');b.className='vib-badge '+statusClass(r.status);b.textContent=r.status;st.appendChild(b);
        var started=document.createElement('td');started.textContent=r.created_at||'—';var finished=document.createElement('td');finished.textContent=r.finished_at||'—';
        var actions=document.createElement('td');var d=document.createElement('a');d.className='vib-button';d.href='ajax/update-diagnostics.php?job='+encodeURIComponent(r.job_uuid);d.textContent='Diagnostics';actions.appendChild(d);
        if(r.status==='success'&&el('current-version').textContent===r.target_version){var rb=document.createElement('button');rb.type='button';rb.className='vib-button vib-button-danger';rb.classList.add('ui-ms-1');rb.dataset.updateRollback=r.job_uuid;rb.textContent='Rollback';actions.appendChild(rb);}
        tr.append(v,st,started,finished,actions);body.appendChild(tr);
    });
}
function closeConfirmation(result){
    var modal=el('licora-update-confirm-modal');modal.hidden=true;
    if(el('licora-update-log-modal').hidden)document.body.style.overflow='';
    if(confirmResolver){var resolve=confirmResolver;confirmResolver=null;resolve(!!result);}
}
function askConfirmation(message,proceedLabel,danger){
    if(confirmResolver)closeConfirmation(false);
    el('update-confirm-message').textContent=message;
    var proceed=el('update-confirm-proceed');proceed.textContent=proceedLabel||'Continue';proceed.className='vib-button '+(danger?'vib-button-danger':'vib-button-primary');
    el('licora-update-confirm-modal').hidden=false;document.body.style.overflow='hidden';
    return new Promise(function(resolve){confirmResolver=resolve;setTimeout(function(){try{proceed.focus();}catch(ignore){}},0);});
}
async function checkUpdates(){showPageError('');el('update-check-button').disabled=true;try{var res=await api('ajax/update-check.php',{method:'POST',body:{force:true}});updateReleaseUI(res.data);if(res.active_job)renderJob(res.active_job);}catch(err){showPageError((err.code?err.code+': ':'')+err.message);}finally{el('update-check-button').disabled=false;}}
async function runPreflight(){
    var version=state.update&&state.update.latest&&state.update.latest.version;if(!version)return;
    showPageError('');var btn=el('update-preflight-button');btn.disabled=true;el('preflight-summary').textContent='Running…';
    try{var res=await api('ajax/update-preflight.php',{method:'POST',body:{target_version:version}});renderPreflight(res.data);}catch(err){showPageError((err.code?err.code+': ':'')+err.message);el('preflight-summary').textContent='Blocked';el('preflight-summary').className='vib-badge vib-badge-danger';el('update-install-button').disabled=true;}finally{if(!jobIsActive(state.job))btn.disabled=!(state.update&&state.update.update_available);}
}
async function installUpdate(){
    var version=state.update&&state.update.latest&&state.update.latest.version;if(!version||state.preflightVersion!==version)return;
    var accepted=await askConfirmation('Install Licora '+version+' now? The signed manifest will be re-verified and a rollback backup will be created before the critical update phase.','Install Update',false);if(!accepted)return;
    showPageError('');el('update-install-button').disabled=true;
    try{var res=await api('ajax/update-start.php',{method:'POST',body:{target_version:version}});resetLogs();renderJob(res.job);openModal();driveJob();}catch(err){showPageError((err.code?err.code+': ':'')+err.message);}
}
async function requestRollback(uuid){
    var accepted=await askConfirmation('Rollback this completed Licora update using its retained verified source backup?','Rollback',true);if(!accepted)return;
    showPageError('');
    try{var res=await api('ajax/update-rollback.php',{method:'POST',body:{job_uuid:uuid}});resetLogs();renderJob(res.job);openModal();driveJob();}catch(err){showPageError((err.code?err.code+': ':'')+err.message);}
}
function copyLogs(){
    var rows=[].slice.call(el('update-log-viewport').querySelectorAll('.vib-log-row'));var text=rows.map(function(r){return r.textContent.trim();}).join('\n');if(!text)return;
    if(!navigator.clipboard||!navigator.clipboard.writeText){showPageError('Copy is unavailable in this browser. Select the log text manually.');return;}
    navigator.clipboard.writeText(text).then(function(){var b=el('update-copy-logs'),old=b.textContent;b.textContent='Copied';setTimeout(function(){b.textContent=old;},1200);}).catch(function(){showPageError('Copy failed. Select the log text manually.');});
}
if(!validateDom())return;
document.addEventListener('click',function(e){var rb=e.target.closest('[data-update-rollback]');if(rb){requestRollback(rb.dataset.updateRollback);}});
el('update-check-button').addEventListener('click',checkUpdates);
el('update-preflight-button').addEventListener('click',runPreflight);
el('update-install-button').addEventListener('click',installUpdate);
el('update-open-logs').addEventListener('click',openModal);
el('update-copy-logs').addEventListener('click',copyLogs);
el('update-log-search').addEventListener('input',applyLogFilter);
el('update-log-filter').addEventListener('change',applyLogFilter);
el('update-log-pin').addEventListener('change',function(){if(this.checked)scrollLogs();});
el('licora-update-log-modal').querySelector('[data-log-close]').addEventListener('click',closeModal);
el('update-confirm-proceed').addEventListener('click',function(){closeConfirmation(true);});
el('update-confirm-cancel').addEventListener('click',function(){closeConfirmation(false);});
el('licora-update-confirm-modal').querySelector('[data-confirm-close]').addEventListener('click',function(){closeConfirmation(false);});
el('licora-update-confirm-modal').addEventListener('click',function(e){if(e.target===this)closeConfirmation(false);});
document.addEventListener('keydown',function(e){
    if(e.key!=='Escape')return;
    if(!el('licora-update-confirm-modal').hidden){closeConfirmation(false);return;}
    if(!el('licora-update-log-modal').hidden)closeModal();
});
if(state.job){resetLogs();renderJob(state.job);openModal();fetchEvents().then(driveJob).catch(function(err){showPageError((err&&err.message)||'Updater event stream could not be loaded. The job remains resumable.');driveJob();});}
else{updateReleaseUI(state.update||{});}
})();
