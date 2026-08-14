'use strict';
const fs=require('fs');const vm=require('vm');const path=require('path');
const root=path.resolve(__dirname,'..');
const page=fs.readFileSync(path.join(root,'admin/updates.php'),'utf8');
const source=fs.readFileSync(path.join(root,'admin/assets/js/licora-updater.js'),'utf8');
const ids=[...page.matchAll(/\bid="([A-Za-z0-9_-]+)"/g)].map(m=>m[1]);
function fail(message){console.error('FAIL: '+message);process.exit(1);}
class FakeClassList{constructor(owner){this.owner=owner;}add(...names){const set=new Set(String(this.owner.className||'').split(/\s+/).filter(Boolean));names.forEach(n=>set.add(n));this.owner.className=[...set].join(' ');}}
class FakeElement{
  constructor(id=''){this.id=id;this.hidden=false;this.disabled=false;this.textContent='';this.className='';this.dataset={};this.style={};this.value='';this.checked=true;this.href='';this.children=[];this.listeners={};this.scrollTop=0;this.scrollHeight=100;this.classList=new FakeClassList(this);this.parentNode=null;this._innerHTML='';}
  addEventListener(type,fn){(this.listeners[type]||(this.listeners[type]=[])).push(fn);}
  trigger(type,event={}){for(const fn of this.listeners[type]||[]){fn(Object.assign({target:this},event));}}
  setAttribute(name,value){if(name.startsWith('data-'))this.dataset[name.slice(5).replace(/-([a-z])/g,(_,c)=>c.toUpperCase())]=String(value);else this[name]=String(value);}
  append(...nodes){nodes.forEach(n=>this.appendChild(n));}
  appendChild(node){node.parentNode=this;this.children.push(node);return node;}
  querySelector(selector){if(selector==='[data-log-close]')return this._logClose||null;if(selector==='[data-confirm-close]')return this._confirmClose||null;return null;}
  querySelectorAll(selector){if(selector==='.vib-log-row')return this.children.filter(c=>String(c.className).split(/\s+/).includes('vib-log-row'));return [];}
  closest(selector){if(selector==='[data-update-rollback]'&&this.dataset.updateRollback)return this;return null;}
  remove(){if(this.parentNode)this.parentNode.children=this.parentNode.children.filter(c=>c!==this);}
  focus(){}
  get innerHTML(){return this._innerHTML;}
  set innerHTML(value){this._innerHTML=String(value);this.children=[];}
}
const elements={};for(const id of ids)elements[id]=new FakeElement(id);
const logClose=new FakeElement();const confirmClose=new FakeElement();
if(elements['licora-update-log-modal'])elements['licora-update-log-modal']._logClose=logClose;
if(elements['licora-update-confirm-modal'])elements['licora-update-confirm-modal']._confirmClose=confirmClose;
const documentListeners={};
const document={
  body:new FakeElement('body'),
  getElementById:id=>elements[id]||null,
  querySelector:selector=>selector==='meta[name="csrf-token"]'?{content:'fixture-csrf'}:null,
  createElement:tag=>new FakeElement(tag),
  addEventListener:(type,fn)=>{(documentListeners[type]||(documentListeners[type]=[])).push(fn);}
};
let checkCalls=0;
async function fetchStub(url,options={}){
  let payload={success:true};
  if(String(url).includes('update-events.php'))payload={success:true,events:[],last_id:0};
  else if(String(url).includes('update-check.php')){checkCalls++;payload={success:true,data:{current_version:'5.4.0',latest:{version:'5.4.1',published_at:'2026-08-14T00:00:00Z',body:'fixture'},update_available:true,last_checked_at:1},active_job:null};}
  return {ok:true,status:200,json:async()=>payload};
}
const context={window:{LicoraUpdaterInitial:{update:{current_version:'5.4.0',latest:{version:'5.4.1',published_at:'',body:''},update_available:true,last_checked_at:1},active_job:{job_uuid:'11111111-1111-4111-8111-111111111111',from_version:'5.3.0',target_version:'5.4.0',status:'success',stage:'complete',progress:100,error_code:null,error_message:null},history:[]}},document,navigator:{clipboard:{writeText:async()=>{}}},fetch:fetchStub,console,setTimeout,clearTimeout,Promise,Date,Math,JSON,Number,String,Array,Object,Error,encodeURIComponent};
context.window.console=console;
try{vm.createContext(context);vm.runInContext(source,context,{filename:'licora-updater.js'});}catch(e){fail('runtime initialization threw: '+e.stack);}
setTimeout(()=>{
  try{
    if(elements['licora-update-log-title'].textContent!=='Licora Update — 5.3.0 → 5.4.0')fail('renderJob did not update the real live-log title DOM ID');
    if(elements['update-preflight-button'].disabled!==false)fail('terminal updater job incorrectly blocks a new preflight');
    elements['update-check-button'].trigger('click');
    setTimeout(()=>{
      if(checkCalls!==1)fail('Check for Updates handler did not execute');
      if(elements['update-preflight-button'].disabled!==false)fail('terminal job truthiness blocked preflight after release refresh');
      console.log('Updater browser runtime checks passed.');
    },25);
  }catch(e){fail(e.stack||String(e));}
},25);
