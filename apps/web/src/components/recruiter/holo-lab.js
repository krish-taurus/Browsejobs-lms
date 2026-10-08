/* eslint-disable */
/**
 * Canvas lab ported from the Taurus AI command centre (taurus-index.html).
 * Drawing, palette, and motion are the original renderer. Hiring data is
 * pushed in through sync(); the DOM chrome lives in React.
 */
export function createHoloLab(canvas, hooks){
hooks = hooks || {};
var sheetOpen = false;
var onSelect = hooks.onSelect || function(){};
function voiceTick(){}
function drawVoiceFx(){}
var TK={trackW:0};
function tickerStep(){}
var TZ='Asia/Kolkata', P2=Math.PI*2, RS=11.3, HUB_R=3.9;
function $(s){return document.querySelector(s);}
function clamp(v,a,b){return v<a?a:v>b?b:v;}
function lerp(a,b,t){return a+(b-a)*t;}
function rand(a,b){return a+Math.random()*(b-a);}
function pick(a){return a[(Math.random()*a.length)|0];}
function el(tag,cls,text){var e=document.createElement(tag);if(cls)e.className=cls;if(text!=null)e.textContent=text;return e;}
function str(v,max){if(v==null)return '';return String(v).replace(/[\u0000-\u001f\u007f]/g,' ').trim().slice(0,max||200);}

/* ---------- colour helpers ---------- */
var _rgb={};
function toRgb(hex){var c=_rgb[hex];if(c)return c;var h=String(hex).replace('#','');if(h.length===3)h=h.replace(/./g,'$&$&');var n=parseInt(h,16)||0;c=[(n>>16)&255,(n>>8)&255,n&255];_rgb[hex]=c;return c;}
function rgba(hex,a){var c=toRgb(hex);a=a<0?0:a>1?1:a;return 'rgba('+c[0]+','+c[1]+','+c[2]+','+a.toFixed(3)+')';}
var _mix={};
function mix(h1,h2,k){var key=h1+h2+k;var r=_mix[key];if(r)return r;var a=toRgb(h1),b=toRgb(h2);r='#'+[0,1,2].map(function(i){return Math.round(a[i]+(b[i]-a[i])*k).toString(16).padStart(2,'0');}).join('');_mix[key]=r;return r;}
function hslHex(h,s,l){s/=100;l/=100;var a=s*Math.min(l,1-l);function f(n){var k=(n+h/30)%12;return l-a*Math.max(-1,Math.min(k-3,Math.min(9-k,1)));}return '#'+[f(0),f(8),f(4)].map(function(x){return Math.round(x*255).toString(16).padStart(2,'0');}).join('');}
function hashStr(s){var h=2166136261>>>0;for(var i=0;i<s.length;i++){h^=s.charCodeAt(i);h=Math.imul(h,16777619)>>>0;}return h;}
var CYAN='#00d4ff',GOLD='#c9a227',AMBER='#ffb627',RED='#ff3d57',WHITE='#ffffff';

/* ---------- vocab ---------- */
var STATUS={
  working:{label:'Working',short:'WORKING',s3:'WRK',color:'#00d4ff'},
  thinking:{label:'Thinking',short:'THINKING',s3:'THK',color:'#b591ff'},
  waiting:{label:'Awaiting approval',short:'APPROVAL',s3:'APR',color:'#ffb627'},
  error:{label:'Error',short:'ERROR',s3:'ERR',color:'#ff3d57'},
  active:{label:'Active (recent activity)',short:'ACTIVE',s3:'ACT',color:'#34e8a8'},
  idle:{label:'Idle',short:'IDLE',s3:'IDL',color:'#6ec8e8'},
  unknown:{label:'No signal',short:'NO SIGNAL',s3:'N/A',color:'#7d8a96'},
  offline:{label:'Offline',short:'OFFLINE',s3:'OFF',color:'#4a6070'}
};
var ST_ORDER=['working','active','thinking','waiting','error','idle','unknown','offline'];
var ST_ALIAS={'waiting-for-approval':'waiting','awaiting-approval':'waiting','approval':'waiting','needs-approval':'waiting','pending-approval':'waiting','pending':'waiting','blocked':'waiting','busy':'working','running':'working','active':'working','in-progress':'working','executing':'working','processing':'working','planning':'thinking','reasoning':'thinking','analyzing':'thinking','analysing':'thinking','failed':'error','failure':'error','crashed':'error','down':'offline','disconnected':'offline','stopped':'offline','sleeping':'offline','paused':'idle','ready':'idle','done':'idle','complete':'idle','completed':'idle','online':'idle','available':'idle'};
function normStatus(s){s=str(s,40).toLowerCase().replace(/[\s_]+/g,'-');if(STATUS[s])return s;return ST_ALIAS[s]||'idle';}
var PLATFORMS={native:{label:'Native',color:'#00d4ff'},claude:{label:'Claude',color:'#ff9a5c'},chatgpt:{label:'ChatGPT',color:'#34e8a8'},grok:{label:'Grok',color:'#e8eef6'},gemini:{label:'Gemini',color:'#8aa8ff'},custom:{label:'Custom',color:'#ff6ad5'}};
var PLAT_ALIAS={openai:'chatgpt',gpt:'chatgpt','chat-gpt':'chatgpt',anthropic:'claude',xai:'grok','x.ai':'grok',google:'gemini',internal:'native','in-house':'native',jarvis:'native',local:'native'};
var _dynPlat={};
function platKey(p){var k=str(p,32).toLowerCase().replace(/\s+/g,'-');if(!k)return 'custom';return PLAT_ALIAS[k]||k;}
function platInfo(k){if(PLATFORMS[k])return PLATFORMS[k];if(!_dynPlat[k])_dynPlat[k]={label:k.charAt(0).toUpperCase()+k.slice(1),color:hslHex(hashStr(k)%360,85,66)};return _dynPlat[k];}
function agentColor(a){return a.color||platInfo(a.platform).color;}
var ZONES=['Sourcing','AI Calls','AI Interview','L1','L2','Human round','Pre-BGV','Offer','Joining'];
var ZONE_ALIAS={brands:'Brands & Commerce',brand:'Brands & Commerce',commerce:'Brands & Commerce',content:'Content & Marketing',marketing:'Content & Marketing',web:'Web & Growth',growth:'Web & Growth',dev:'Web & Growth',finance:'Finance',trading:'Finance',ops:'Ops',operations:'Ops',general:'Ops'};
function normZone(z){var s=str(z,40);if(!s)return 'Ops';var low=s.toLowerCase();for(var i=0;i<ZONES.length;i++)if(ZONES[i].toLowerCase()===low)return ZONES[i];var k=low.replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');return ZONE_ALIAS[k]||s;}
function callsignOf(a){if(a.callsign)return a.callsign;var w=a.name.replace(/\(.*?\)/g,'').trim().split(/[\s\-_&]+/).filter(Boolean);var cs=w.length>1?w.map(function(x){return x[0];}).join('').slice(0,4):(w[0]||a.id).slice(0,3);return cs.toUpperCase();}

/* ---------- time ---------- */
var fTime=new Intl.DateTimeFormat('en-GB',{timeZone:TZ,hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false});
var fDate=new Intl.DateTimeFormat('en-GB',{timeZone:TZ,weekday:'short',day:'2-digit',month:'short',year:'numeric'});
var fDT=new Intl.DateTimeFormat('en-GB',{timeZone:TZ,day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false});
function tStr(ts){return fTime.format(new Date(ts));}
function ago(ts){if(!ts)return 'no signal';var s=Math.max(0,(Date.now()-ts)/1000);if(s<5)return 'just now';if(s<60)return Math.floor(s)+'s ago';if(s<3600)return Math.floor(s/60)+'m ago';if(s<86400)return Math.floor(s/3600)+'h ago';return Math.floor(s/86400)+'d ago';}
function parseTs(v){if(v==null||v==='')return null;if(typeof v==='number'&&isFinite(v))return v<1e12?v*1000:v;var t=Date.parse(v);return isNaN(t)?null:t;}

/* ---------- state ---------- */
var params=new URLSearchParams(location.search);
var state={agents:new Map(),mode:'demo',liveSource:null,demoReason:'',feedOk:false,feedEverOk:false,feedErr:null,
  filters:{platform:new Set(),zone:new Set()},selected:null,hover:null,zonesLayout:[],dense:false};
var layoutDirty=true,floorDirty=true,uiDirty=true;

function makeAgent(id){
  return {id:id,name:id,platform:'custom',role:'',zone:'Ops',status:'idle',currentTask:'',progress:0,lastUpdated:Date.now(),
    activity:[],callsign:'',color:null,example:false,source:'demo',demo:false,_seen:new Set(),rt:newRT(id)};
}
function newRT(id){
  return {u:0,v:0,path:[],goal:null,goalKind:'',speed:0,speedT:1,walk:0,moving:false,mdx:0,mdy:1,face:0,back:false,
    pause:rand(0,2),vis:0,spawn:0,seed:(hashStr(id)%1000)/1000*P2,glitch:0,hit:null,tagRect:null,scrRect:null,station:null,stand:null,
    placed:false,prog:0,typing:{lines:[],cur:0,acc:0}};
}

/* ---------- ingest (feed / postMessage / API) ---------- */
function actKey(e){return e.ts+'|'+e.message;}
function normAct(e,fallbackTs,fallbackStatus){
  if(typeof e==='string')e={message:e};
  if(!e||typeof e!=='object')return null;
  var msg=str(e.message!=null?e.message:(e.text!=null?e.text:e.event),300);if(!msg)return null;
  return {ts:parseTs(e.ts!=null?e.ts:(e.time!=null?e.time:e.timestamp))||fallbackTs,message:msg,status:e.status?normStatus(e.status):fallbackStatus};
}
function applyRaw(raw,source){
  if(!raw||typeof raw!=='object')return null;
  var id=str(raw.id!=null?raw.id:(raw.agentId!=null?raw.agentId:raw.name),80);
  if(!id)return null;
  var a=state.agents.get(id),isNew=!a;
  if(isNew){a=makeAgent(id);a.source=source;a.name=str(raw.name,60)||id;}
  var prevStatus=a.status,prevZone=a.zone;
  if('name' in raw)a.name=str(raw.name,60)||a.name;
  if('platform' in raw)a.platform=platKey(raw.platform);
  if('role' in raw)a.role=str(raw.role,220);
  if('zone' in raw)a.zone=normZone(raw.zone);
  if('status' in raw)a.status=normStatus(raw.status);
  if('currentTask' in raw||'task' in raw)a.currentTask=str(raw.currentTask!=null?raw.currentTask:raw.task,240);
  if('progress' in raw){var p=Number(raw.progress);if(isFinite(p)){if(p>0&&p<1)p*=100;a.progress=clamp(p,0,100);}}
  if('callsign' in raw)a.callsign=str(raw.callsign,5).toUpperCase();
  if(typeof raw.color==='string'&&/^#[0-9a-f]{3}([0-9a-f]{3})?$/i.test(raw.color))a.color=raw.color;
  if('example' in raw)a.example=!!raw.example;
  var lu=parseTs(raw.lastUpdated);a.lastUpdated=source==='box'?(lu||0):(lu||(isNew||('status' in raw)?Date.now():a.lastUpdated));
  if(source==='box'){a.real=true;a.lastViewed=parseTs(raw.lastViewedByKrish)||0;a.signals=str(raw.signals,80);}
  a.source=source;
  var added=[];
  if(Array.isArray(raw.activity)){
    raw.activity.slice(0,60).forEach(function(e){var n=normAct(e,a.lastUpdated,a.status);if(!n)return;var k=actKey(n);if(a._seen.has(k))return;a._seen.add(k);a.activity.push(n);added.push(n);});
    a.activity.sort(function(x,y){return y.ts-x.ts;});if(a.activity.length>40)a.activity.length=40;
  }
  if(isNew){state.agents.set(id,a);layoutDirty=true;}
  if(prevZone!==a.zone)layoutDirty=true;
  added.sort(function(x,y){return x.ts-y.ts;});
  var toLog=isNew?added.slice(-2):added;
  toLog.forEach(function(n){logEvent(a,n.message,n.status||a.status,n.ts);});
  if(!isNew&&prevStatus!==a.status&&!added.length){
    var m='Status '+STATUS[prevStatus].short+' → '+STATUS[a.status].short+(a.currentTask?' · '+a.currentTask:'');
    pushEvent(a,m,a.status,Date.now());
  }
  uiDirty=true;
  return a;
}
function removeAgent(id){var a=state.agents.get(id);if(!a)return;state.agents.delete(id);if(state.selected===a)closePanel();layoutDirty=true;uiDirty=true;}
function ingest(payload,source){
  if(typeof payload==='string'){try{payload=JSON.parse(payload);}catch(e){return false;}}
  if(!payload||typeof payload!=='object')return false;
  var list=null,replace=false;
  if(Array.isArray(payload)){list=payload;replace=(source==='feed'||source==='box');}
  else if(Array.isArray(payload.agents)){list=payload.agents;replace=payload.replace===true||((source==='feed'||source==='box')&&payload.merge!==true);}
  else if(payload.agent&&typeof payload.agent==='object')list=[payload.agent];
  else if(payload.id!=null||payload.agentId!=null)list=[payload];
  var events=Array.isArray(payload.events)?payload.events:null;
  if(!list&&!events)return false;
  goLive(source);
  var ids=new Set();
  if(list)list.slice(0,300).forEach(function(r){var a=applyRaw(r,source);if(a)ids.add(a.id);});
  if(replace)Array.from(state.agents.keys()).forEach(function(id){if(!ids.has(id))removeAgent(id);});
  if(events)events.slice(0,200).forEach(function(e){
    if(!e||typeof e!=='object')return;
    var a=state.agents.get(str(e.agentId!=null?e.agentId:e.id,80));if(!a)return;
    var n=normAct(e,Date.now(),a.status);if(!n)return;var k=actKey(n);if(a._seen.has(k))return;
    if(e.status){a.status=normStatus(e.status);}
    a.lastUpdated=Math.max(a.lastUpdated,n.ts);pushEvent(a,n.message,n.status,n.ts);
  });
  uiDirty=true;return true;
}

/* ---------- events: activity, mission log, ticker ---------- */
var logEl=$('#mission-log');
function pushEvent(a,message,status,ts){
  ts=ts||Date.now();var n={ts:ts,message:message,status:status};var k=actKey(n);
  if(!a._seen.has(k)){a._seen.add(k);a.activity.unshift(n);if(a.activity.length>40)a.activity.length=40;}
  logEvent(a,message,status,ts);
}
function logEvent(a,message,status,ts){
  if(!logEl)return;
  var sc=STATUS[status]||STATUS.idle;
  var row=el('div','li');
  row.appendChild(el('div','li-time',tStr(ts)));
  var body=el('div');var head=el('div','li-head');
  var nm=el('span','li-agent',a?a.name:'SYSTEM');nm.style.color=a?agentColor(a):GOLD;
  var st=el('span','li-st',a?sc.short:'SYS');st.style.color=a?sc.color:GOLD;
  head.appendChild(nm);head.appendChild(st);body.appendChild(head);body.appendChild(el('div','li-msg',message));
  row.appendChild(body);
  if(a)row.addEventListener('click',function(){if(state.agents.get(a.id)===a)openPanel(a);});
  var empty=logEl.querySelector('.empty-note');if(empty)empty.remove();
  logEl.insertBefore(row,logEl.firstChild);
  while(logEl.children.length>90)logEl.removeChild(logEl.lastChild);
  tickerPush({ts:ts,name:a?a.name:'SYSTEM',color:a?agentColor(a):GOLD,st:a?sc.short:'SYS',sc:a?sc.color:GOLD,msg:message});
}
var TK={x:0,w:0,queue:[],recent:[],idx:0,trackW:0};
var tkInner=$('#tk-inner'),tkTrack=$('#tk-track');
function tickerPush(ev){TK.queue.push(ev);if(TK.queue.length>30)TK.queue.shift();TK.recent.unshift(ev);if(TK.recent.length>14)TK.recent.length=14;}
function tkNode(ev){var d=el('div','tk');d.appendChild(el('time',null,tStr(ev.ts).slice(0,5)));var b=el('b',null,ev.name);b.style.color=ev.color;d.appendChild(b);var em=el('em',null,ev.st);em.style.color=ev.sc;d.appendChild(em);d.appendChild(el('span',null,ev.msg));return d;}
function tickerReset(){TK.queue.length=0;TK.recent.length=0;TK.x=0;TK.w=0;tkInner.textContent='';}
function tickerStep(dt){
  if(!TK.trackW)TK.trackW=tkTrack.clientWidth;
  var guard=0;
  while(TK.w+TK.x<TK.trackW+80&&guard++<6){
    var ev=TK.queue.shift();
    if(!ev){if(!TK.recent.length)break;ev=TK.recent[TK.idx++%TK.recent.length];}
    var n=tkNode(ev);tkInner.appendChild(n);n._w=n.offsetWidth;TK.w+=n._w;
  }
  TK.x-=(TK.trackW<500?42:58)*dt;
  var f=tkInner.firstElementChild;
  while(f&&TK.x+f._w<0){TK.x+=f._w;TK.w-=f._w;tkInner.removeChild(f);f=tkInner.firstElementChild;}
  tkInner.style.transform='translate3d('+TK.x.toFixed(1)+'px,0,0)';
}

/* =====================================================================
   DEMO DATA  (clearly simulated — only used in DEMO MODE)
   ===================================================================== */
var DEMO=[
 {id:'bfm',name:'Bfm',callsign:'BFM',platform:'native',zone:'Brands & Commerce',role:'BALDFATMAN apparel brand — Shopify store operations, product designs and drops',
  tasks:['Generating 6 tee mockups for the "Bald & Bold" drop','Syncing Shopify inventory — 48 SKUs','Writing product copy for the oversized hoodie range','Auditing the abandoned-cart email flow','Preparing collection banner variants'],
  approval:['Preparing "Bald & Bold" collection launch','Publish "Bald & Bold" collection to the storefront'],init:['working',46,0]},
 {id:'the-offer-letter-leads',name:'The Offer Letter Leads',callsign:'TOL',platform:'native',zone:'Brands & Commerce',role:'The Offer Letter — brochure, website and inbound lead management',
  tasks:['Designing The Offer Letter brochure — page 4 of 8','Updating landing-page testimonials','Scoring 37 new inbound leads','Syncing leads to the CRM sheet','Refreshing pricing section copy'],
  approval:['Drafting follow-up sequence for 37 leads','Send follow-up sequence to 37 leads'],init:['working',72,0]},
 {id:'numerology',name:'numerology',callsign:'NUM',platform:'native',zone:'Brands & Commerce',role:'DestinyOS — Instagram DM inbox agent: triage, replies and lead tagging',
  tasks:['Triaging 23 new Instagram DMs','Drafting personalised numerology replies','Tagging hot leads in the DM inbox','Following up on DMs older than 24h'],
  approval:['Drafting 6 DM replies','Send 6 drafted DM replies'],init:['waiting',100,'A']},
 {id:'linkedin',name:'Linkedin',callsign:'LNK',platform:'native',zone:'Content & Marketing',role:'LinkedIn content — posts, carousels and audience growth',
  tasks:['Drafting carousel: 7 lessons from scaling BrowseJobs','Writing Tuesday thought-leadership post','Designing an 8-slide carousel in the brand template','Analysing last week\'s post impressions'],
  approval:['Finalising founder-story carousel','Publish founder-story carousel to LinkedIn'],init:['thinking',12,0]},
 {id:'meta-ads-optimisation',name:'Meta Ads Optimisation',callsign:'META',platform:'native',zone:'Content & Marketing',role:'Meta lead-gen campaign optimisation — budgets, creatives, audiences',
  tasks:['Analysing lead-gen CPL by ad set','Rotating 4 fatigued creatives','Rebalancing budget across 3 ad sets','Building lookalike audience from converters'],
  approval:['Modelling budget scale-up','Increase daily budget ₹2,000 → ₹3,000'],init:['working',31,0]},
 {id:'heygen',name:'Heygen',callsign:'HGN',platform:'native',zone:'Content & Marketing',role:'AI video avatars — scripts, voice-over and avatar renders',
  tasks:['Rendering avatar video "Weekly update" (1080p)','Writing script for a 45s explainer','Generating voice-over with cloned voice','Localising explainer into Hindi'],
  approval:['Assembling final cut of explainer','Approve final cut of explainer video'],init:['working',64,0]},
 {id:'chatgpt-example',name:'ChatGPT (example)',callsign:'GPT',platform:'chatgpt',zone:'Content & Marketing',example:true,role:'EXAMPLE external agent — copywriting & ideation (sample data, not connected)',
  tasks:['Brainstorming 20 hook lines (example)','Rewriting ad copy variants (example)','Summarising a research PDF (example)'],
  approval:['Drafting newsletter (example)','Send newsletter draft (example)'],init:['error',38,0,'Upstream API timeout (example)']},
 {id:'browsejobs-seo',name:'BrowseJobs SEO',callsign:'SEO',platform:'native',zone:'Web & Growth',role:'SEO / AEO for browsejobs.ai — technical SEO, schema and answer-engine content',
  tasks:['Crawling browsejobs.ai — 1,240 URLs','Optimising job-listing schema for AEO answers','Clustering 300 keywords by intent','Fixing 18 broken internal links','Drafting FAQ blocks for answer engines'],
  approval:['Preparing meta-title rewrites','Push meta-title changes to 42 pages'],init:['working',18,0]},
 {id:'browsejobs-web-app-dev',name:'BrowseJobs Web & App Dev',callsign:'DEV',platform:'native',zone:'Web & Growth',role:'Website and app development for browsejobs.ai',
  tasks:['Building job-alert onboarding flow (React)','Fixing Lighthouse performance regressions','Running end-to-end test suite','Refactoring search API pagination'],
  approval:['Preparing release v2.4','Deploy release v2.4 to production'],init:['thinking',8,1]},
 {id:'bot-ui',name:'Bot Ui',callsign:'UI',platform:'native',zone:'Web & Growth',role:'Dashboard builder — builds this Taurus AI command centre',
  tasks:['Building Taurus AI v1 command centre','Rendering holographic agent sprites','Wiring JSON feed + postMessage bridge','Running headless-browser screenshot QA'],
  approval:['Packaging Taurus AI v1','Review Taurus AI v1 screenshots'],init:['working',88,0]},
 {id:'claude-example',name:'Claude (example)',callsign:'CLD',platform:'claude',zone:'Web & Growth',example:true,role:'EXAMPLE external agent — code review & long-form writing (sample data, not connected)',
  tasks:['Reviewing pull request #128 (example)','Writing API documentation (example)','Refactoring a utility module (example)'],
  approval:['Preparing PR summary (example)','Merge PR #128 (example)'],init:['idle',0,null]},
 {id:'trading',name:'Trading',callsign:'TRD',platform:'native',zone:'Finance',role:'Trading & markets — watchlists, research and risk monitoring',
  tasks:['Scanning NSE watchlist for breakouts','Back-testing a moving-average strategy','Summarising pre-market global cues','Reviewing open positions and risk'],
  approval:['Writing up trade idea','Trade idea ready — review before any order'],init:['working',55,0]},
 {id:'emmy',name:'Emmy',callsign:'EMY',platform:'native',zone:'Ops',role:'General assistant — inbox, scheduling and research',
  tasks:['Summarising inbox — 14 unread threads','Drafting meeting agenda','Researching vendor options','Organising Drive folders'],
  approval:['Drafting vendor reply','Send vendor reply email'],init:['idle',0,null]},
 {id:'new-bot',name:'New Bot',callsign:'NEW',platform:'native',zone:'Ops',role:'General assistant — new agent, setting up',
  tasks:['Setting up workspace and tools','Researching competitor pricing','Compiling weekly digest'],
  approval:['Drafting weekly digest','Share weekly digest'],init:['idle',0,null]},
 {id:'grok-bot',name:'Grok Bot',callsign:'GRK',platform:'native',zone:'Ops',role:'General assistant — research, analysis and drafting',
  tasks:['Answering a research question','Comparing 3 CRM options','Drafting project brief','Fact-checking blog draft'],
  approval:['Drafting partner email','Send partner email'],init:['working',40,1]},
 {id:'disk-saver',name:'Disk Saver',callsign:'DSK',platform:'native',zone:'Ops',role:'Computer disk clean-up audits — usage scans, duplicates, safe-to-delete reports',
  tasks:['Scanning disk usage — / (512 GB)','Finding duplicate files in Downloads','Listing caches safe to clear','Compiling clean-up audit report'],
  approval:['Building clean-up plan','Approve clean-up of 18.4 GB caches'],init:['offline',0,null]}
];
var ERRS=['API rate limit hit — backing off','Timeout reaching upstream service','Auth token expired — needs re-login','Unexpected response from upstream API','Tool call failed — retrying shortly'];
var demoTimers=[];

function seedDemo(){
  var now=Date.now();
  DEMO.forEach(function(d,i){
    var a=makeAgent(d.id);a.demo=true;a.source='demo';
    a.name=d.name;a.callsign=d.callsign;a.platform=d.platform;a.zone=d.zone;a.role=d.role;a.example=!!d.example;
    a.sim={def:d,approval:false,phase:'prep',wait:0};
    var st=d.init[0];a.status=st;a.progress=d.init[1];
    if(d.init[2]==='A'){a.sim.approval=true;a.currentTask=d.approval[1];}
    else if(d.init[2]!=null)a.currentTask=d.tasks[d.init[2]];
    var t0=now-rand(40,95)*60000;
    var hist=[{ts:t0,message:'Came online · session started',status:'idle'},
      {ts:t0+rand(5,20)*60000,message:'Completed: '+d.tasks[(i+2)%d.tasks.length],status:'idle'}];
    var tl=now-rand(1,14)*60000;
    if(st==='working')hist.push({ts:tl,message:'Started: '+a.currentTask,status:'working'});
    if(st==='thinking')hist.push({ts:tl,message:'Planning approach: '+a.currentTask,status:'thinking'});
    if(st==='waiting')hist.push({ts:tl,message:'Awaiting approval: '+a.currentTask,status:'waiting'});
    if(st==='error')hist.push({ts:tl,message:d.init[3]||pick(ERRS),status:'error'});
    if(st==='offline')hist.push({ts:tl,message:'Went offline · scheduled pause',status:'offline'});
    if(st==='idle')hist.push({ts:tl,message:'Standing by for next task',status:'idle'});
    hist.sort(function(x,y){return y.ts-x.ts;});
    hist.forEach(function(h){a._seen.add(actKey(h));});
    a.activity=hist;a.lastUpdated=hist[0].ts;
    state.agents.set(a.id,a);
  });
  var all=[];state.agents.forEach(function(a){a.activity.forEach(function(h){all.push([a,h]);});});
  all.sort(function(x,y){return x[1].ts-y[1].ts;});
  all.slice(-22).forEach(function(p){logEvent(p[0],p[1].message,p[1].status,p[1].ts);});
}
function setStatus(a,st,msg){a.status=st;a.lastUpdated=Date.now();pushEvent(a,msg,st,a.lastUpdated);uiDirty=true;}
function startTask(a){
  var d=a.sim.def;a.sim.approval=Math.random()<0.3;a.sim.phase='prep';a.progress=0;
  a.currentTask=a.sim.approval?d.approval[0]:pick(d.tasks.filter(function(t){return t!==a.currentTask;}));
  if(Math.random()<0.45)setStatus(a,'thinking','Planning approach: '+a.currentTask);
  else setStatus(a,'working','Started: '+a.currentTask);
}
function complete(a){
  var d=a.sim.def;
  if(a.sim.approval&&a.sim.phase==='prep'){a.progress=100;a.currentTask=d.approval[1];a.sim.wait=0;setStatus(a,'waiting','Awaiting approval: '+d.approval[1]);return;}
  var done=a.currentTask;a.currentTask='';a.sim.approval=false;a.progress=0;
  setStatus(a,'idle','Completed: '+done);
}
function simStep(){
  var list=Array.from(state.agents.values()).filter(function(a){return a.demo;});if(!list.length)return;
  var n=Math.random()<0.4?2:1;
  for(var i=0;i<n;i++){
    var a=pick(list),r=Math.random(),d=a.sim.def;
    switch(a.status){
      case 'idle':if(r<0.6)startTask(a);else if(r<0.63&&a.id!=='bot-ui')setStatus(a,'offline','Went offline · scheduled pause');break;
      case 'thinking':if(r<0.65){if(!a.currentTask)a.currentTask=pick(d.tasks);setStatus(a,'working','Plan ready — executing: '+a.currentTask);}break;
      case 'working':if(r<0.05)setStatus(a,'error',pick(ERRS));else if(r<0.13&&a.progress<80)setStatus(a,'thinking','Re-evaluating approach for: '+a.currentTask);break;
      case 'waiting':a.sim.wait++;if(a.sim.wait>2&&r<0.45){a.sim.phase='exec';a.progress=0;setStatus(a,'working','Approval received (simulated) — executing: '+a.currentTask);}break;
      case 'error':if(r<0.5){if(!a.currentTask)a.currentTask=pick(d.tasks);setStatus(a,'working','Recovered — retry succeeded, resuming: '+a.currentTask);}break;
      case 'offline':if(r<0.4)setStatus(a,'idle','Back online · ready for tasks');break;
    }
  }
}
function progStep(){
  state.agents.forEach(function(a){
    if(!a.demo||a.status!=='working')return;
    a.progress=Math.min(100,a.progress+rand(1.2,4.2)*(a.sim.phase==='exec'?2:1));
    if(a.progress>=100)complete(a);
  });
  uiDirty=true;
}
function startDemo(reason){
  state.mode='demo';state.demoReason=reason||'';
  if(!Array.from(state.agents.values()).some(function(a){return a.demo;}))seedDemo();
  if(!demoTimers.length)demoTimers.push(setInterval(simStep,3000),setInterval(progStep,1000));
  layoutDirty=true;uiDirty=true;updateModeBadge();
}
function stopDemo(){demoTimers.forEach(clearInterval);demoTimers=[];}
function goLive(source){
  if(state.mode==='live'){state.liveSource=source;updateModeBadge();return;}
  stopDemo();
  Array.from(state.agents.keys()).forEach(function(id){if(state.agents.get(id).demo)removeAgent(id);});
  logEl.textContent='';tickerReset();
  state.mode='live';state.liveSource=source;
  logEvent(null,'Live data connected via '+(source==='box'?'box collector (real Grok Bot agent signals)':source==='feed'?'feed URL':source==='postMessage'?'postMessage bridge':'JS API')+' — demo simulation stopped','idle',Date.now());
  layoutDirty=true;uiDirty=true;updateModeBadge();
}

/* ---------- mode badge ---------- */
var modeBadge=$('#mode-badge'),modeText=$('#mode-text'),modeSub=$('#mode-sub'),tkLabel=$('#tk-label'),logTag=$('#log-tag');
function updateModeBadge(){
  var cls='demo',txt='DEMO MODE',sub='SIMULATED DATA',title='Demo mode — every agent action shown is simulated sample data.';
  if(state.mode==='demo'&&state.demoReason){sub=state.demoReason;title+=' '+state.demoReason+(state.feedErr?' ('+state.feedErr+')':'');}
  if(state.mode==='connecting'){cls='wait';txt='CONNECTING';sub='FEED…';title='Connecting to feed';}
  if(state.mode==='live'){
    if(state.liveSource==='box'){var age=Date.now()-(state.boxSync||0),hm=state.boxSync?fTime.format(new Date(state.boxSync)).slice(0,5)+' IST':'?';
      if(age>10*60000){cls='err';txt='LIVE · STALE';sub='SYNCED '+hm;title='Real agent data, last synced '+hm+' (over 10 min ago). Box sync may be paused.';}
      else{cls='live';txt='LIVE';sub='SYNCED '+hm;title='Real Grok Bot agent signals from the box, synced '+hm+'. Status = file activity (active <15 min).';}
      modeBadge.className='mode '+cls;modeText.textContent=txt;modeSub.textContent=sub;modeBadge.title=title;
      tkLabel.className='tk-label';tkLabel.textContent='LIVE ACTIVITY';logTag.className='tag live';logTag.textContent='LIVE';return;}
    var src=state.liveSource==='feed'?'FEED':state.liveSource==='postMessage'?'BRIDGE':state.liveSource==='api'?'API':'AWAITING DATA';
    if(state.liveSource==='feed'&&state.feedErr){cls='err';txt='LIVE · FEED ERROR';sub='RETRYING';title='Feed error: '+state.feedErr+' — showing last received data';}
    else{cls='live';txt='LIVE';sub=src;title='Live data via '+src;}
  }
  modeBadge.className='mode '+cls;modeText.textContent=txt;modeSub.textContent=sub;modeBadge.title=title;
  var demo=state.mode==='demo';
  tkLabel.className='tk-label'+(demo?' demo':'');tkLabel.textContent=demo?'DEMO · SIMULATED':'LIVE ACTIVITY';
  logTag.className='tag '+(demo?'sim':'live');logTag.textContent=demo?'SIMULATED':'LIVE';
}

/* =====================================================================
   VIEW / LAYOUT
   ===================================================================== */
var cvs=canvas,ctx=cvs.getContext('2d');
var floorCvs=document.createElement('canvas'),fctx=floorCvs.getContext('2d');
var gridCvs=document.createElement('canvas'),gctx=gridCvs.getContext('2d');
var W=0,H=0,DPR=1;
var view={K:20,baseK:20,cx:0,cy:0,T:0.62,sV:1,Z:0.95,zoom:1,panX:0,panY:0,vp:{l:0,t:0,r:0,b:0},mobile:false,figScale:1,topExtra:4};
function P(u,v,z){return [view.cx+u*view.K,view.cy+v*view.K*view.T-(z||0)*view.K*view.Z];}
function toUV(x,y){return {u:(x-view.cx)/view.K,v:(y-view.cy)/(view.K*view.T)};}
function computeVP(){
  var root=cvs.parentElement,box=root.getBoundingClientRect();
  var header=root.querySelector('#hud-top'),ticker=root.querySelector('#ticker');
  var leftP=root.querySelector('#left-panel'),rightP=root.querySelector('#right-panel');
  var mnav=root.querySelector('#mnav'),legend=root.querySelector('#legend');
  var hh=header?header.offsetHeight:58,th=ticker?ticker.offsetHeight:30;
  root.style.setProperty('--hud-h',hh+'px');
  var l=0,r=W,t=hh+th+6,b=H;
  function rel(el,edge){var rect=el.getBoundingClientRect();return (edge==='right'?rect.right:edge==='top'?rect.top:rect.left)-(edge==='top'?box.top:box.left);}
  if(W>=1100){
    if(leftP)l=rel(leftP,'right')+8;
    if(rightP)r=rel(rightP,'left')-8;
    b=H-40;
    if(legend&&getComputedStyle(legend).display!=='none'){var lt=rel(legend,'top');if(lt>t+120)b=Math.min(b,lt-8);}
  }else if(W>760){
    if(rightP)r=rel(rightP,'left')-8;
    b=H-(mnav&&getComputedStyle(mnav).display!=='none'?mnav.offsetHeight:0)-8;
  }else{
    b=H-(mnav?mnav.offsetHeight:56)-8;
    if(sheetOpen)b=Math.min(b,Math.round(H*0.4));
  }
  view.vp={l:Math.max(0,l),r:Math.max(l+80,r),t:t,b:Math.max(t+80,b)};
}
function computeView(){
  var vp=view.vp,aw=Math.max(200,vp.r-vp.l),ah=Math.max(200,vp.b-vp.t);
  view.mobile=W<=760;
  view.figScale=view.mobile?1.35:(W<1100?1.25:1.28);
  var portrait=ah/aw>1.15;
  var pad=view.mobile?14:44;
  var Rx=RS*1.13+1.6,Ry1=RS*1.13;
  var topExtra=1.9*view.Z*view.figScale+(view.mobile?1.6:2.2);
  var K=(aw-pad*2)/(2*Rx);
  var T=portrait?0.84:0.62;
  var sV=((ah-28-topExtra*K)/(2*K*T)-0.6)/Ry1;
  if(sV<1){
    sV=1;T=clamp((ah-28-topExtra*K)/(2*K*(Ry1+0.6)),0.5,T);
    var need=(2*(Ry1+0.6)*T+topExtra)*K+28;if(need>ah)K=(ah-28)/(2*(Ry1+0.6)*T+topExtra);
  }
  sV=clamp(sV,1,portrait?1.9:1.3);
  if(Math.abs(sV-view.sV)>0.001||Math.abs(T-view.T)>0.001)layoutDirty=true;
  view.T=T;view.sV=sV;view.baseK=K;view.K=K*view.zoom;view.topExtra=topExtra;
  var Ry=Ry1*sV+0.6,contentH=(2*Ry*T+topExtra)*K;
  var cy0=vp.t+(ah-contentH)/2+(topExtra+Ry*T)*K;
  var midY=(vp.t+vp.b)/2,midX=(vp.l+vp.r)/2;
  view.cx=midX+view.panX;
  view.cy=midY+(cy0-midY)*view.zoom+view.panY;
}
function resize(){
  var rect=cvs.getBoundingClientRect();
  DPR=Math.min(window.devicePixelRatio||1,2);
  W=Math.max(1,rect.width);H=Math.max(1,rect.height);
  [cvs,floorCvs,gridCvs].forEach(function(c){c.width=Math.round(W*DPR);c.height=Math.round(H*DPR);});
  computeVP();computeView();
  initParticles();layoutDirty=true;floorDirty=true;
}

var ell=null;
function buildEll(){var N=720,T=view.T,cum=[0],L=0,pu=RS,pv=0;for(var i=1;i<=N;i++){var a=i/N*P2,u=Math.cos(a)*RS,v=Math.sin(a)*RS*view.sV;L+=Math.hypot(u-pu,(v-pv)*T);cum.push(L);pu=u;pv=v;}ell={cum:cum,L:L,N:N};}
function phiAt(frac){frac=((frac%1)+1)%1;var c=ell.cum,N=ell.N,tg=frac*ell.L,lo=0,hi=N;while(lo<hi){var m=(lo+hi)>>1;if(c[m]<tg)lo=m+1;else hi=m;}var i=Math.max(1,lo);var f=(tg-c[i-1])/((c[i]-c[i-1])||1);return (i-1+f)/N*P2;}
function fracOfPhi(phi){phi=((phi%P2)+P2)%P2;var x=phi/P2*ell.N,i=Math.floor(x),f=x-i;var c=ell.cum;return (c[i]+(c[Math.min(ell.N,i+1)]-c[i])*f)/ell.L;}
function zonesOrdered(){
  var present=new Set();state.agents.forEach(function(a){present.add(a.zone);});
  var out=ZONES.filter(function(z){return present.has(z);});
  present.forEach(function(z){if(out.indexOf(z)<0)out.push(z);});
  return out;
}
function layout(){
  buildEll();
  var groups=zonesOrdered().map(function(z){var ag=[];state.agents.forEach(function(a){if(a.zone===z&&!a.token)ag.push(a);});return {zone:z,agents:ag};});
  var N=0;groups.forEach(function(g){N+=g.agents.length;});
  var dense=N>24,slots=0,gap=0.9;
  groups.forEach(function(g){var n=g.agents.length;g.per=dense?Math.ceil(n/2):n;g.w=Math.max(g.per,1.5);slots+=g.w;});
  if(groups.length&&slots+gap*groups.length<14)gap=(14-slots)/groups.length;
  var total=slots+gap*groups.length||1;
  var start=fracOfPhi(-Math.PI/2-0.08)+(gap/2)/total;
  var pos=0;
  groups.forEach(function(g){
    g.f0=start+pos/total;g.f1=start+(pos+g.w)/total;var off=(g.w-g.per)/2;
    g.agents.forEach(function(a,i){
      var slot=dense?Math.floor(i/2):i,row=dense?i%2:0;
      var phi=phiAt(start+(pos+off+slot+0.5)/total);
      placeStation(a,phi,row?1.2:1.0);
    });
    pos+=g.w+gap;
  });
  state.zonesLayout=groups;state.dense=dense;placeTokens();floorDirty=true;
}
function placeStation(a,phi,rf){
  var c=Math.cos(phi),s=Math.sin(phi),sV=view.sV;
  var du=c*RS*rf,dv=s*RS*rf*sV,tu=-s,tv=c*sV,tl=Math.hypot(tu,tv)||1;tu/=tl;tv/=tl;
  var il=Math.hypot(du,dv)||1,iu=-du/il,iv=-dv/il;
  var side=tu>=-0.0001?-1:1;
  var su=du+tu*1.35*side+iu*0.55,sv=dv+tv*1.35*side+iv*0.55;
  var hx=tu,hy=tv*view.T,l=Math.hypot(hx,hy)||1;hx/=l;hy/=l;if(hx<0){hx=-hx;hy=-hy;}
  hx=lerp(hx,1,0.55);hy=lerp(hy,0,0.55);l=Math.hypot(hx,hy);
  a.rt.station={phi:phi,du:du,dv:dv,tu:tu,tv:tv,hx:hx/l,hy:hy/l,rf:rf};
  a.rt.stand={u:su,v:sv};
}
function wanderPoint(r){
  var base=r.station?r.station.phi:rand(0,P2);
  var ang=base+rand(-1.1,1.1),rad=rand(5.1,6.9);
  return {u:Math.cos(ang)*rad,v:Math.sin(ang)*rad*(1+(view.sV-1)*0.6)};
}
function planPath(r,goal){
  var path=[],ax=r.u,ay=r.v,bx=goal.u,by=goal.v,dx=bx-ax,dy=by-ay,L2=dx*dx+dy*dy;
  var t=L2?clamp(-(ax*dx+ay*dy)/L2,0,1):0,cx=ax+dx*t,cy=ay+dy*t;
  if(Math.hypot(cx,cy)<HUB_R+0.9){
    var a0=Math.atan2(ay,ax),a1=Math.atan2(by,bx),d=a1-a0;while(d>Math.PI)d-=P2;while(d<-Math.PI)d+=P2;
    var n=Math.max(2,Math.ceil(Math.abs(d)/(Math.PI/3)));
    for(var k=1;k<n;k++){var a=a0+d*k/n;path.push({u:Math.cos(a)*(HUB_R+1.6),v:Math.sin(a)*(HUB_R+1.6)});}
  }
  path.push({u:bx,v:by});return path;
}
function matches(a){var f=state.filters;return (!f.platform.size||f.platform.has(a.platform))&&(!f.zone.size||f.zone.has(a.zone));}

/* =====================================================================
   MOVEMENT
   ===================================================================== */
function updateAgent(a,dt,t){
  var r=a.rt;if(!r.station)return;
  if(!r.placed){
    if(a.status==='idle'){var p=wanderPoint(r);r.u=p.u;r.v=p.v;}else{r.u=r.stand.u;r.v=r.stand.v;r.goalKind='station';r.goal={u:r.u,v:r.v};}
    r.placed=true;r.prog=a.progress;
  }
  r.spawn=Math.min(1,r.spawn+dt*0.7);
  if(a.status!=='idle'){
    var g=r.stand;
    if(r.goalKind!=='station'||!r.goal||Math.abs(r.goal.u-g.u)+Math.abs(r.goal.v-g.v)>0.01){r.goalKind='station';r.goal={u:g.u,v:g.v};r.path=planPath(r,g);}
    r.speedT=a.status==='offline'?1.2:2.6;
  }else{
    if(r.goalKind!=='wander'){r.goalKind='wander';r.path=[];r.pause=rand(0.4,1.6);}
    if(!r.path.length){r.pause-=dt;if(r.pause<=0){var w=wanderPoint(r);r.path=planPath(r,w);r.pause=rand(2.2,6);}}
    r.speedT=0.95;
  }
  if(r.path.length){
    var tg=r.path[0],dx=tg.u-r.u,dy=tg.v-r.v,d=Math.hypot(dx,dy);
    r.speed=lerp(r.speed,r.speedT,Math.min(1,dt*3));var step=r.speed*dt;
    if(d<=step||d<0.001){r.u=tg.u;r.v=tg.v;r.path.shift();}else{r.u+=dx/d*step;r.v+=dy/d*step;}
    if(d>0.001){var sx=dx,sy=dy*view.T,sl=Math.hypot(sx,sy)||1,k=Math.min(1,dt*8);r.mdx=lerp(r.mdx,sx/sl,k);r.mdy=lerp(r.mdy,sy/sl,k);}
    r.walk+=step*4.4;r.moving=true;
  }else{
    r.moving=false;r.speed=0;
    if(r.goalKind==='station'){var st=r.station,fx=st.du-r.u,fy=(st.dv-r.v)*view.T,fl=Math.hypot(fx,fy)||1,k2=Math.min(1,dt*5);r.mdx=lerp(r.mdx,fx/fl,k2);r.mdy=lerp(r.mdy,fy/fl,k2);}
    else{var k3=Math.min(1,dt*0.9);r.mdx=lerp(r.mdx,Math.sin(t*0.3+r.seed)*0.5,k3);r.mdy=lerp(r.mdy,0.75,k3);}
  }
  var ml=Math.hypot(r.mdx,r.mdy)||1;r.face=clamp(r.mdx/ml,-1,1);r.back=r.mdy/ml<-0.3;
  r.vis=lerp(r.vis,matches(a)?1:0.13,Math.min(1,dt*5));
  r.glitch=a.status==='error'?(Math.random()<0.07?rand(-1,1):r.glitch*0.85):0;
  r.prog=lerp(r.prog,a.progress,Math.min(1,dt*2.5));
  if(a.status==='working'){var ty=r.typing;ty.acc+=dt;if(ty.acc>0.07){ty.acc=0;if(!ty.lines.length||ty.cur>=ty.lines[ty.lines.length-1].w){ty.lines.push({w:rand(0.25,0.95),ind:Math.random()<0.3?0.1:0});ty.cur=0;if(ty.lines.length>5)ty.lines.shift();}else ty.cur+=0.06;}}
}

/* =====================================================================
   RENDERING
   ===================================================================== */
var parts=[];
function initParticles(){parts=[];var n=W<760?55:120;for(var i=0;i<n;i++)parts.push({x:Math.random()*W,y:Math.random()*H,vy:rand(3,18),vx:rand(-4,4),r:rand(0.4,2.2),a:rand(0.1,0.55),p:rand(0,P2),kind:i%5===0?'gold':(i%4===0?'spark':'mote')});}
var UI_FONT="'Inter','IBM Plex Sans',system-ui,sans-serif";
var DISP_FONT="'Orbitron','Inter',system-ui,sans-serif";
var MONO_FONT="'IBM Plex Mono',ui-monospace,Consolas,monospace";
function setLS(c,v){if('letterSpacing' in c)c.letterSpacing=v;}

function renderFloor(){
  var c=fctx;c.setTransform(DPR,0,0,DPR,0,0);c.clearRect(0,0,W,H);
  var o=P(0,0,0),K=view.K,T=view.T,i,j;
  var bg=c.createRadialGradient(o[0],o[1],0,o[0],o[1],Math.max(W,H)*0.85);
  bg.addColorStop(0,'#0a2744');bg.addColorStop(0.35,'#041628');bg.addColorStop(0.7,'#020b16');bg.addColorStop(1,'#01040a');c.fillStyle=bg;c.fillRect(0,0,W,H);
  // back wall: translucent holo panels
  var WR=RS*1.62,WH=3.4,segs=26;
  function wq(a0,a1,f,z){var a=lerp(a0,a1,f);return P(Math.cos(a)*WR,Math.sin(a)*WR*view.sV,z);}
  for(i=0;i<segs;i++){
    var a0=Math.PI+i/segs*Math.PI,a1=Math.PI+(i+1)/segs*Math.PI;
    var b0=wq(a0,a1,0,0),b1=wq(a0,a1,1,0),t0=wq(a0,a1,0,WH),t1=wq(a0,a1,1,WH);
    var wg=c.createLinearGradient(0,b0[1],0,t0[1]);wg.addColorStop(0,'rgba(0,212,255,0.075)');wg.addColorStop(1,'rgba(0,212,255,0.008)');
    c.fillStyle=wg;c.beginPath();c.moveTo(b0[0],b0[1]);c.lineTo(b1[0],b1[1]);c.lineTo(t1[0],t1[1]);c.lineTo(t0[0],t0[1]);c.closePath();c.fill();
    c.strokeStyle='rgba(0,212,255,0.10)';c.lineWidth=1;c.beginPath();c.moveTo(b0[0],b0[1]);c.lineTo(t0[0],t0[1]);c.stroke();
    if(i%3===1){
      var m=0.18,p1=wq(a0,a1,m,1.5),p2=wq(a0,a1,1-m,1.5),p3=wq(a0,a1,1-m,2.9),p4=wq(a0,a1,m,2.9);
      c.fillStyle='rgba(0,212,255,0.05)';c.strokeStyle='rgba(0,212,255,0.22)';c.beginPath();c.moveTo(p1[0],p1[1]);c.lineTo(p2[0],p2[1]);c.lineTo(p3[0],p3[1]);c.lineTo(p4[0],p4[1]);c.closePath();c.fill();c.stroke();
      c.strokeStyle='rgba(0,212,255,0.16)';
      for(j=1;j<5;j++){var zz=lerp(2.7,1.7,j/5.5),s1=wq(a0,a1,m+0.05,zz),s2=wq(a0,a1,m+0.05+(1-2*m-0.1)*((hashStr('w'+i+j)%70)/100+0.25),zz);c.beginPath();c.moveTo(s1[0],s1[1]);c.lineTo(s2[0],s2[1]);c.stroke();}
    }
  }
  c.strokeStyle='rgba(201,162,39,0.35)';c.beginPath();
  for(i=0;i<=segs;i++){var aa=Math.PI+i/segs*Math.PI,tp=P(Math.cos(aa)*WR,Math.sin(aa)*WR*view.sV,WH);i?c.lineTo(tp[0],tp[1]):c.moveTo(tp[0],tp[1]);}c.stroke();
  // grid with radial fade
  var g=gctx;g.setTransform(DPR,0,0,DPR,0,0);g.globalCompositeOperation='source-over';g.clearRect(0,0,W,H);g.lineWidth=1;
  for(var n=-24;n<=24;n++){
    g.strokeStyle=n%4===0?'rgba(0,212,255,0.42)':'rgba(0,212,255,0.16)';
    var e1=P(2*n+40,-40),e2=P(2*n-40,40);g.beginPath();g.moveTo(e1[0],e1[1]);g.lineTo(e2[0],e2[1]);g.stroke();
    e1=P(-40-2*n,-40);e2=P(40-2*n,40);g.beginPath();g.moveTo(e1[0],e1[1]);g.lineTo(e2[0],e2[1]);g.stroke();
  }
  g.globalCompositeOperation='destination-in';g.save();g.translate(o[0],o[1]);g.scale(1,T*view.sV);
  var mg=g.createRadialGradient(0,0,0,0,0,RS*1.85*K);mg.addColorStop(0,'rgba(0,0,0,1)');mg.addColorStop(0.45,'rgba(0,0,0,0.92)');mg.addColorStop(0.75,'rgba(0,0,0,0.45)');mg.addColorStop(1,'rgba(0,0,0,0)');
  g.fillStyle=mg;g.fillRect(-W*3,-H*6,W*6,H*12);g.restore();g.globalCompositeOperation='source-over';
  c.save();c.setTransform(1,0,0,1,0,0);c.drawImage(gridCvs,0,0);c.restore();
  // outer floor ring + ticks, walkway ring
  c.save();c.translate(o[0],o[1]);c.scale(K,K*T*view.sV);
  c.lineWidth=0.03;c.strokeStyle='rgba(245,196,81,0.22)';c.beginPath();c.arc(0,0,RS*1.42,0,P2);c.stroke();
  c.strokeStyle='rgba(0,212,255,0.18)';c.beginPath();c.arc(0,0,RS*1.47,0,P2);c.stroke();
  for(i=0;i<120;i++){var ta=i/120*P2,len=i%10===0?0.5:0.2;c.beginPath();c.moveTo(Math.cos(ta)*RS*1.42,Math.sin(ta)*RS*1.42);c.lineTo(Math.cos(ta)*(RS*1.42-len),Math.sin(ta)*(RS*1.42-len));c.stroke();}
  c.strokeStyle='rgba(0,212,255,0.12)';c.setLineDash([0.4,0.3]);c.beginPath();c.arc(0,0,7.3,0,P2);c.stroke();c.setLineDash([]);
  c.restore();
  // zone plates
  var outer=state.dense?1.36:1.17,inner=0.8;
  state.zonesLayout.forEach(function(gz){
    var pts=[],steps=40,f0=gz.f0-0.004,f1=gz.f1+0.004,k,q;
    for(k=0;k<=steps;k++){var ph=phiAt(lerp(f0,f1,k/steps));pts.push([Math.cos(ph),Math.sin(ph)*view.sV]);}
    c.beginPath();
    for(k=0;k<pts.length;k++){q=P(pts[k][0]*RS*outer,pts[k][1]*RS*outer);k?c.lineTo(q[0],q[1]):c.moveTo(q[0],q[1]);}
    for(k=pts.length-1;k>=0;k--){q=P(pts[k][0]*RS*inner,pts[k][1]*RS*inner);c.lineTo(q[0],q[1]);}
    c.closePath();
    var zg=c.createRadialGradient(o[0],o[1],RS*inner*K*0.6,o[0],o[1],RS*outer*K);zg.addColorStop(0,'rgba(201,162,39,0.03)');zg.addColorStop(1,'rgba(201,162,39,0.10)');
    c.fillStyle=zg;c.fill();c.strokeStyle='rgba(201,162,39,0.48)';c.lineWidth=1;c.stroke();
    c.strokeStyle='rgba(0,212,255,0.38)';c.lineWidth=1.5;c.beginPath();
    for(k=0;k<pts.length;k++){q=P(pts[k][0]*RS*inner,pts[k][1]*RS*inner);k?c.lineTo(q[0],q[1]):c.moveTo(q[0],q[1]);}c.stroke();
  });
}

function drawPulse(t){
  var per=5.5,k=(t%per)/per,o=P(0,0,0);
  ctx.save();ctx.translate(o[0],o[1]);ctx.scale(1,view.T);
  ctx.strokeStyle=rgba(CYAN,(1-k)*0.45);ctx.lineWidth=2.4;ctx.beginPath();ctx.arc(0,0,(HUB_R+k*16)*view.K,0,P2);ctx.stroke();
  ctx.strokeStyle=rgba(WHITE,(1-k)*0.12);ctx.lineWidth=1;ctx.beginPath();ctx.arc(0,0,(HUB_R+k*16)*view.K*0.97,0,P2);ctx.stroke();
  ctx.restore();
}
function drawHubFloor(t){
  var o=P(0,0,0),K=view.K,i,pulse=0.85+0.15*Math.sin(t*3.2);
  ctx.save();ctx.translate(o[0],o[1]);ctx.scale(K,K*view.T);ctx.globalCompositeOperation='lighter';
  var g=ctx.createRadialGradient(0,0,0,0,0,6.2);g.addColorStop(0,'rgba(220,250,255,'+(0.55*pulse).toFixed(3)+')');g.addColorStop(0.2,'rgba(0,212,255,0.32)');g.addColorStop(0.5,'rgba(0,180,255,0.12)');g.addColorStop(1,'rgba(0,212,255,0)');
  ctx.fillStyle=g;ctx.beginPath();ctx.arc(0,0,6.2,0,P2);ctx.fill();
  ctx.lineWidth=0.07;ctx.strokeStyle=rgba(GOLD,0.7);ctx.save();ctx.rotate(-t*0.18);ctx.setLineDash([1.1,0.28]);ctx.beginPath();ctx.arc(0,0,HUB_R+0.15,0,P2);ctx.stroke();ctx.restore();
  ctx.setLineDash([]);ctx.strokeStyle=rgba(CYAN,0.75);ctx.lineWidth=0.06;ctx.beginPath();ctx.arc(0,0,HUB_R-0.35,0,P2);ctx.stroke();
  ctx.strokeStyle=rgba(WHITE,0.35);ctx.lineWidth=0.03;ctx.beginPath();ctx.arc(0,0,HUB_R-0.7,0,P2);ctx.stroke();
  ctx.save();ctx.rotate(t*0.08);ctx.strokeStyle=rgba(CYAN,0.55);ctx.lineWidth=0.04;
  for(i=0;i<96;i++){var a=i/96*P2,l=i%8===0?0.42:(i%4===0?0.22:0.1);ctx.beginPath();ctx.moveTo(Math.cos(a)*(HUB_R-0.75),Math.sin(a)*(HUB_R-0.75));ctx.lineTo(Math.cos(a)*(HUB_R-0.75-l),Math.sin(a)*(HUB_R-0.75-l));ctx.stroke();}
  ctx.restore();
  ctx.save();ctx.rotate(t*0.55);ctx.strokeStyle=rgba(CYAN,0.95);ctx.lineWidth=0.13;
  for(i=0;i<3;i++){ctx.beginPath();ctx.arc(0,0,2.65,i*P2/3,i*P2/3+1.35);ctx.stroke();}ctx.restore();
  ctx.save();ctx.rotate(-t*0.9);ctx.strokeStyle=rgba(WHITE,0.55);ctx.lineWidth=0.07;
  for(i=0;i<4;i++){ctx.beginPath();ctx.arc(0,0,2.15,i*P2/4+0.2,i*P2/4+0.95);ctx.stroke();}ctx.restore();
  ctx.save();ctx.rotate(-t*0.75);ctx.strokeStyle=rgba(GOLD,0.75);ctx.lineWidth=0.06;ctx.setLineDash([0.3,0.16]);ctx.beginPath();ctx.arc(0,0,1.95,0,P2);ctx.stroke();ctx.restore();
  ctx.setLineDash([]);
  ctx.save();ctx.rotate(t*0.25);ctx.strokeStyle=rgba(CYAN,0.7);ctx.lineWidth=0.05;ctx.beginPath();
  for(i=0;i<6;i++){var ha=i/6*P2;i?ctx.lineTo(Math.cos(ha)*1.35,Math.sin(ha)*1.35):ctx.moveTo(Math.cos(ha)*1.35,Math.sin(ha)*1.35);}ctx.closePath();ctx.stroke();ctx.restore();
  ctx.save();ctx.rotate(-t*0.4);ctx.strokeStyle=rgba(GOLD,0.55);ctx.lineWidth=0.04;ctx.beginPath();
  for(i=0;i<4;i++){var da=i/4*P2+Math.PI/4;i?ctx.lineTo(Math.cos(da)*0.95,Math.sin(da)*0.95):ctx.moveTo(Math.cos(da)*0.95,Math.sin(da)*0.95);}ctx.closePath();ctx.stroke();ctx.restore();
  var cg=ctx.createRadialGradient(0,0,0,0,0,1.15);cg.addColorStop(0,'rgba(255,255,255,'+(0.55*pulse).toFixed(3)+')');cg.addColorStop(0.4,'rgba(180,245,255,0.35)');cg.addColorStop(1,'rgba(0,212,255,0)');
  ctx.fillStyle=cg;ctx.beginPath();ctx.arc(0,0,1.15,0,P2);ctx.fill();
  ctx.restore();
}
var hubRect=null;
function drawHubTower(t){
  var K=view.K,o=P(0,0,0),rc=P(0,0,2.75),R=Math.max(14,0.95*K),rx=rc[0],ry=rc[1],i,g,pulse=0.88+0.12*Math.sin(t*4.5)*Math.sin(t*11);
  ctx.save();ctx.globalCompositeOperation='lighter';
  // energy column
  g=ctx.createLinearGradient(0,o[1],0,ry);g.addColorStop(0,rgba(CYAN,0.42));g.addColorStop(0.5,rgba(CYAN,0.18));g.addColorStop(1,rgba(WHITE,0.06));
  ctx.fillStyle=g;ctx.beginPath();ctx.moveTo(o[0]-R*0.95,o[1]);ctx.lineTo(rx-R*0.28,ry);ctx.lineTo(rx+R*0.28,ry);ctx.lineTo(o[0]+R*0.95,o[1]);ctx.closePath();ctx.fill();
  // rising energy beads
  for(i=0;i<8;i++){var k=((t*0.42+i/8)%1),py=lerp(o[1],ry,k),px=o[0]+Math.sin(t*2.4+i*1.9)*R*0.4*(1-k);ctx.fillStyle=rgba('#c8fbff',0.85*(1-k));ctx.beginPath();ctx.arc(px,py,1.8+pulse,0,P2);ctx.fill();}
  // gold structural braces
  ctx.strokeStyle=rgba(GOLD,0.45);ctx.lineWidth=1.2;
  for(i=0;i<4;i++){var sa=i*P2/4+0.4+t*0.05,b=P(Math.cos(sa)*2.5,Math.sin(sa)*2.5,0);ctx.beginPath();ctx.moveTo(b[0],b[1]);ctx.lineTo(rx+Math.cos(sa)*R*0.75,ry+R*0.7);ctx.stroke();}
  // outer bloom
  g=ctx.createRadialGradient(rx,ry,0,rx,ry,R*3.1);g.addColorStop(0,'rgba(255,255,255,'+(0.65*pulse).toFixed(3)+')');g.addColorStop(0.18,'rgba(180,245,255,0.45)');g.addColorStop(0.4,rgba(CYAN,0.22));g.addColorStop(1,rgba(CYAN,0));
  ctx.fillStyle=g;ctx.beginPath();ctx.arc(rx,ry,R*3.1,0,P2);ctx.fill();
  // rotating gold ring with node
  ctx.save();ctx.translate(rx,ry);ctx.scale(1,0.32);ctx.rotate(t*0.55);ctx.strokeStyle=rgba(GOLD,0.75);ctx.lineWidth=1.6;ctx.setLineDash([R*0.5,R*0.25]);ctx.beginPath();ctx.arc(0,0,R*2.05,0,P2);ctx.stroke();ctx.setLineDash([]);
  ctx.fillStyle=rgba(GOLD,1);ctx.beginPath();ctx.arc(R*2.05,0,3.4,0,P2);ctx.fill();
  ctx.strokeStyle=rgba(CYAN,0.5);ctx.lineWidth=1;ctx.beginPath();ctx.arc(0,0,R*2.35,0,P2);ctx.stroke();ctx.restore();
  // dual cyan shells
  ctx.strokeStyle=rgba(CYAN,1);ctx.lineWidth=2;ctx.beginPath();ctx.arc(rx,ry,R,0,P2);ctx.stroke();
  ctx.strokeStyle=rgba(WHITE,0.55);ctx.lineWidth=1.1;ctx.beginPath();ctx.arc(rx,ry,R*0.86,0,P2);ctx.stroke();
  ctx.strokeStyle=rgba(GOLD,0.4);ctx.lineWidth=1;ctx.beginPath();ctx.arc(rx,ry,R*1.18,0,P2);ctx.stroke();
  // iris blades
  ctx.save();ctx.translate(rx,ry);ctx.rotate(t*0.18);
  for(i=0;i<12;i++){ctx.rotate(P2/12);ctx.fillStyle=rgba('#9ff6ff',0.45+0.35*Math.sin(t*3.5+i));ctx.fillRect(-R*0.08,-R*0.88,R*0.16,R*0.28);}
  ctx.restore();
  // white-hot core
  g=ctx.createRadialGradient(rx,ry,0,rx,ry,R*0.62);g.addColorStop(0,'rgba(255,255,255,'+pulse.toFixed(3)+')');g.addColorStop(0.25,'rgba(210,250,255,0.95)');g.addColorStop(0.55,'rgba(0,212,255,0.55)');g.addColorStop(1,rgba(CYAN,0));
  ctx.fillStyle=g;ctx.beginPath();ctx.arc(rx,ry,R*0.62,0,P2);ctx.fill();
  // triangle mark
  ctx.strokeStyle='rgba(255,255,255,0.9)';ctx.lineWidth=1.4;ctx.beginPath();
  for(i=0;i<3;i++){var ta=Math.PI/2+i*P2/3,tx=rx+Math.cos(ta)*R*0.38,ty=ry+Math.sin(ta)*R*0.38;i?ctx.lineTo(tx,ty):ctx.moveTo(tx,ty);}ctx.closePath();ctx.stroke();
  ctx.restore();
  // cinematic title
  var fs=clamp(K*0.58,12,24),tyy=ry-R*2.15;
  ctx.save();ctx.font='800 '+fs+'px '+DISP_FONT;ctx.textAlign='center';ctx.textBaseline='alphabetic';setLS(ctx,(fs*0.18).toFixed(1)+'px');
  var title='AI RECRUITER',tw=ctx.measureText(title).width;
  ctx.shadowColor=rgba(CYAN,1);ctx.shadowBlur=22;ctx.fillStyle='#f5fdff';ctx.fillText(title,rx+fs*0.15,tyy);ctx.shadowBlur=0;
  // gold underline accents
  ctx.strokeStyle=rgba(GOLD,0.85);ctx.lineWidth=1.5;ctx.beginPath();ctx.moveTo(rx-tw/2-6,tyy+6);ctx.lineTo(rx+tw/2+6,tyy+6);ctx.stroke();
  var sfs=Math.max(8.5,fs*0.46);ctx.font='700 '+sfs.toFixed(1)+'px '+UI_FONT;setLS(ctx,(sfs*0.28).toFixed(1)+'px');
  var sub='Sample data';
  ctx.fillStyle=state.mode==='demo'?rgba(AMBER,0.95):rgba(GOLD,0.95);ctx.fillText(sub,rx+sfs*0.14,tyy+sfs*1.9);
  var sw=ctx.measureText(sub).width,half=Math.max(tw,sw)/2+12;
  ctx.strokeStyle=rgba(CYAN,0.65);ctx.lineWidth=1.2;
  ctx.beginPath();ctx.moveTo(rx-half-28,tyy-fs*0.4);ctx.lineTo(rx-half,tyy-fs*0.4);ctx.moveTo(rx+half,tyy-fs*0.4);ctx.lineTo(rx+half+28,tyy-fs*0.4);ctx.stroke();
  // corner brackets on title
  ctx.strokeStyle=rgba(GOLD,0.7);ctx.beginPath();
  ctx.moveTo(rx-half-8,tyy-fs*0.85);ctx.lineTo(rx-half-20,tyy-fs*0.85);ctx.lineTo(rx-half-20,tyy-fs*0.4);
  ctx.moveTo(rx+half+8,tyy-fs*0.85);ctx.lineTo(rx+half+20,tyy-fs*0.85);ctx.lineTo(rx+half+20,tyy-fs*0.4);ctx.stroke();
  setLS(ctx,'0px');ctx.restore();
  hubRect={x:rx-half-28,y:tyy-fs-6,w:(half+28)*2,h:fs+sfs*2.4+10};
}

function drawStationFloor(a,t){
  var r=a.rt,st=r.station;if(!st)return;var sc=STATUS[a.status].color,p=P(st.du,st.dv,0),K=view.K;
  var inten={working:0.32,active:0.28,thinking:0.24,waiting:0.3,error:0.32,idle:0.09,unknown:0.04,offline:0.03}[a.status]||0.05;
  if(a.status==='waiting'||a.status==='error')inten*=0.65+0.35*Math.sin(t*(a.status==='error'?10:4));
  ctx.save();ctx.translate(p[0],p[1]);ctx.scale(1,view.T);ctx.globalCompositeOperation='lighter';
  var g=ctx.createRadialGradient(0,0,0,0,0,K*1.6);g.addColorStop(0,rgba(sc,inten*r.vis));g.addColorStop(1,rgba(sc,0));
  ctx.fillStyle=g;ctx.beginPath();ctx.arc(0,0,K*1.6,0,P2);ctx.fill();ctx.restore();
}
function drawDesk(a,t){
  var r=a.rt,st=r.station;if(!st)return;
  var p=P(st.du,st.dv,0),x=p[0],y=p[1],K=view.K,T=view.T,Z=view.Z;
  var pc=agentColor(a),sc=STATUS[a.status].color,off=a.status==='offline';
  var rx=0.62*K,ry=rx*T,h=0.72*K*Z,g;
  ctx.save();ctx.globalAlpha=r.vis*(off?0.6:1);
  g=ctx.createLinearGradient(x-rx,0,x+rx,0);g.addColorStop(0,'#06182a');g.addColorStop(0.45,'#0d2c47');g.addColorStop(1,'#030e1b');
  ctx.fillStyle=g;ctx.beginPath();ctx.moveTo(x+rx,y-h);ctx.lineTo(x+rx,y);ctx.ellipse(x,y,rx,ry,0,0,Math.PI);ctx.lineTo(x-rx,y-h);ctx.closePath();ctx.fill();
  ctx.strokeStyle=rgba(pc,0.35);ctx.lineWidth=1;ctx.beginPath();ctx.moveTo(x-rx,y-h);ctx.lineTo(x-rx,y);ctx.moveTo(x+rx,y-h);ctx.lineTo(x+rx,y);ctx.stroke();
  ctx.beginPath();ctx.ellipse(x,y,rx,ry,0,0,Math.PI);ctx.stroke();
  ctx.strokeStyle=rgba(sc,off?0.15:0.65);ctx.lineWidth=1.5;ctx.beginPath();ctx.ellipse(x,y-h*0.42,rx,ry,0,0.1,Math.PI-0.1);ctx.stroke();
  ctx.fillStyle='#0b2236';ctx.beginPath();ctx.ellipse(x,y-h,rx,ry,0,0,P2);ctx.fill();
  ctx.strokeStyle=rgba(pc,off?0.25:0.9);ctx.lineWidth=1.2;ctx.stroke();
  ctx.globalCompositeOperation='lighter';
  ctx.strokeStyle=rgba(sc,off?0.1:0.5);ctx.beginPath();ctx.ellipse(x,y-h,rx*0.62,ry*0.62,0,0,P2);ctx.stroke();
  var sw=1.7*K,shh=0.95*K*Z,sb=P(st.du,st.dv,1.08),cx=sb[0],cy=sb[1]-shh/2;
  if(!off){g=ctx.createLinearGradient(0,y-h,0,sb[1]);g.addColorStop(0,rgba(sc,0.22));g.addColorStop(1,rgba(sc,0.02));ctx.fillStyle=g;ctx.beginPath();ctx.moveTo(x-rx*0.35,y-h);ctx.lineTo(x+rx*0.35,y-h);ctx.lineTo(sb[0]+st.hx*sw*0.45,sb[1]+st.hy*sw*0.45);ctx.lineTo(sb[0]-st.hx*sw*0.45,sb[1]-st.hy*sw*0.45);ctx.closePath();ctx.fill();}
  ctx.globalCompositeOperation='source-over';
  ctx.translate(cx,cy);ctx.transform(st.hx,st.hy,0,1,0,0);
  var w2=sw/2,h2=shh/2;
  drawScreen(a,t,w2,h2,sc,pc,off);
  ctx.restore();
  r.scrRect={x1:cx-w2,y1:cy-h2-Math.abs(st.hy)*w2,x2:cx+w2,y2:y};
}
function drawScreen(a,t,w2,h2,sc,pc,off){
  var W2=w2*2,H2=h2*2,st=a.status,r=a.rt,i;
  var panelCol=st==='waiting'?AMBER:(st==='error'?RED:CYAN);
  var bg=ctx.createLinearGradient(0,-h2,0,h2);
  if(off){bg.addColorStop(0,'rgba(12,22,34,0.6)');bg.addColorStop(1,'rgba(4,10,18,0.6)');}
  else if(st==='waiting'){bg.addColorStop(0,'rgba(255,182,39,0.28)');bg.addColorStop(1,'rgba(255,140,0,0.08)');}
  else if(st==='error'){bg.addColorStop(0,'rgba(255,61,87,0.30)');bg.addColorStop(1,'rgba(120,10,30,0.12)');}
  else{bg.addColorStop(0,'rgba(0,212,255,0.28)');bg.addColorStop(0.5,'rgba(0,140,200,0.12)');bg.addColorStop(1,'rgba(0,60,100,0.08)');}
  ctx.fillStyle=bg;ctx.fillRect(-w2,-h2,W2,H2);
  // inner glow panel
  if(!off){ctx.globalCompositeOperation='lighter';ctx.fillStyle=rgba(panelCol,0.08);ctx.fillRect(-w2+2,-h2+2,W2-4,H2-4);}
  ctx.strokeStyle=rgba(off?'#4a6070':panelCol,off?0.35:0.95);ctx.lineWidth=1.2;ctx.strokeRect(-w2,-h2,W2,H2);
  // holographic corner brackets
  var cl=Math.min(7,W2*0.18);ctx.strokeStyle=rgba(GOLD,off?0.2:0.9);ctx.lineWidth=1.3;ctx.beginPath();
  ctx.moveTo(-w2,-h2+cl);ctx.lineTo(-w2,-h2);ctx.lineTo(-w2+cl,-h2);
  ctx.moveTo(w2,-h2+cl);ctx.lineTo(w2,-h2);ctx.lineTo(w2-cl,-h2);
  ctx.moveTo(-w2,h2-cl);ctx.lineTo(-w2,h2);ctx.lineTo(-w2+cl,h2);
  ctx.moveTo(w2,h2-cl);ctx.lineTo(w2,h2);ctx.lineTo(w2-cl,h2);ctx.stroke();
  if(off){ctx.globalCompositeOperation='source-over';return;}
  var pad=Math.max(2.5,W2*0.07),fsz=Math.max(6,Math.min(9,H2*0.24));
  ctx.fillStyle=rgba(panelCol,0.95);ctx.fillRect(-w2+pad,-h2+pad,W2*0.32,1.8);
  ctx.fillStyle=rgba(GOLD,0.7);ctx.fillRect(w2-pad-W2*0.14,-h2+pad,W2*0.14,1.8);
  var top=-h2+pad+5,bot=h2-pad;
  ctx.textAlign='center';ctx.textBaseline='middle';
  if(st==='working'){
    // scrolling code lines
    var lines=r.typing.lines,lh=(bot-top-8)/5;
    for(i=0;i<lines.length;i++){var ln=lines[i],last=i===lines.length-1,wv=(last?Math.min(r.typing.cur,ln.w):ln.w)*(W2-pad*2)*(1-ln.ind);
      ctx.fillStyle=rgba(i%3===0?WHITE:(i%2?pc:CYAN),last?0.95:0.55);ctx.fillRect(-w2+pad+ln.ind*W2,top+i*lh,wv,Math.max(1.2,lh*0.38));
      if(last&&Math.sin(t*14)>0){ctx.fillStyle=rgba(WHITE,1);ctx.fillRect(-w2+pad+ln.ind*W2+wv+1,top+i*lh-0.5,1.6,Math.max(1.6,lh*0.55));}}
    // mini bar graph
    var gx=w2-pad-W2*0.28,gw=W2*0.22,gh=H2*0.28,gy=bot-4-gh;
    for(i=0;i<5;i++){var bh=gh*(0.3+0.7*((Math.sin(t*2.5+i*1.1)+1)/2));ctx.fillStyle=rgba(CYAN,0.45+0.4*(bh/gh));ctx.fillRect(gx+i*(gw/5+0.5),gy+gh-bh,gw/5-0.5,bh);}
    ctx.fillStyle=rgba(CYAN,0.2);ctx.fillRect(-w2+pad,bot-3,W2-pad*2,3);
    ctx.fillStyle=rgba(CYAN,1);ctx.fillRect(-w2+pad,bot-3,(W2-pad*2)*r.prog/100,3);
  }else if(st==='thinking'){
    ctx.strokeStyle=rgba(sc,0.95);ctx.lineWidth=1.3;ctx.beginPath();
    for(var xx=0;xx<=28;xx++){var fx=-w2+pad+(W2-pad*2)*xx/28,fy=(top+bot)/2+Math.sin(xx*0.65+t*5.5)*Math.sin(t*1.4+xx*0.25)*(bot-top)*0.34;xx?ctx.lineTo(fx,fy):ctx.moveTo(fx,fy);}ctx.stroke();
    ctx.strokeStyle=rgba(WHITE,0.35);ctx.lineWidth=0.8;ctx.beginPath();
    for(xx=0;xx<=28;xx++){fx=-w2+pad+(W2-pad*2)*xx/28;fy=(top+bot)/2+Math.cos(xx*0.5+t*3)*(bot-top)*0.18;xx?ctx.lineTo(fx,fy):ctx.moveTo(fx,fy);}ctx.stroke();
    for(i=0;i<3;i++){ctx.fillStyle=rgba(sc,0.35+0.65*((Math.sin(t*6-i)+1)/2));ctx.fillRect(-w2+pad+i*4.5,bot-3,3,3);}
  }else if(st==='waiting'){
    var bl=0.5+0.5*Math.sin(t*5);ctx.fillStyle=rgba(AMBER,0.14+0.22*bl);ctx.fillRect(-w2+1,-h2+1,W2-2,H2-2);
    ctx.strokeStyle=rgba(AMBER,0.7);ctx.setLineDash([3,3]);ctx.strokeRect(-w2+3,-h2+3,W2-6,H2-6);ctx.setLineDash([]);
    ctx.fillStyle=rgba(AMBER,1);ctx.font='700 '+fsz+'px '+MONO_FONT;ctx.fillText(W2>40?'APPROVAL':'!',0,(top+bot)/2);
  }else if(st==='error'){
    var fl=Math.sin(t*16)>-0.15;ctx.fillStyle=rgba(RED,fl?0.32:0.08);ctx.fillRect(-w2+1,-h2+1,W2-2,H2-2);
    for(i=0;i<4;i++){ctx.fillStyle=rgba(RED,0.55);ctx.fillRect(-w2+((t*40+i*17)%(W2*0.7)),top+((i*13+t*8)%(bot-top)),rand(6,W2*0.4),1.2);}
    ctx.fillStyle=rgba(WHITE,fl?1:0.45);ctx.font='700 '+fsz+'px '+MONO_FONT;ctx.fillText(W2>40?'ERROR':'!',0,(top+bot)/2);
  }else{
    ctx.strokeStyle=rgba(CYAN,0.55);ctx.lineWidth=1.1;var rr=Math.min(H2,W2)*0.22;ctx.beginPath();ctx.arc(0,(top+bot)/2-2,rr,t,t+4.2);ctx.stroke();
    ctx.strokeStyle=rgba(GOLD,0.4);ctx.beginPath();ctx.arc(0,(top+bot)/2-2,rr*0.7,-t*1.3,-t*1.3+3);ctx.stroke();
    if(W2>40){ctx.fillStyle=rgba(CYAN,0.7);ctx.font='600 '+Math.max(5.5,fsz*0.8).toFixed(1)+'px '+MONO_FONT;ctx.fillText('STANDBY',0,bot-2);}
  }
  ctx.globalCompositeOperation='source-over';
}

function drawFigure(c,x,y,s,o){
  var t=o.t,moving=o.moving,sw=Math.sin(o.walk),cw=Math.cos(o.walk);
  var bob=moving?-Math.abs(cw)*2.0:0,slump=o.pose==='off'?1:0;
  var hipY=-42+bob+slump*2,shY=-78+bob+slump*4,headY=-94+bob+slump*6;
  var f=o.face*1.8,mx=o.mdx,my=o.mdy,legs=[],arms=[];
  for(var si=0;si<2;si++){
    var side=si?1:-1,ph=side<0?sw:-sw,lift=moving?Math.max(0,side<0?cw:-cw)*5:0;
    var hip=[side*5.2+f*0.3,hipY],foot=[side*5.4+(moving?ph*mx*10:0),-lift+(moving?ph*my*4.5:0)];
    var knee=[(hip[0]+foot[0])/2+(moving?mx*2.8:0)+side*1.0,(hipY+foot[1])/2-2+(moving?my*1.4:0)];
    legs.push([hip,knee,foot]);
    var sh=[side*12.5+f,shY+2],hand,elbow,br;
    switch(o.pose){
      case 'walk':hand=[side*13.5-ph*mx*9,shY+30-ph*my*3.5];elbow=[side*14-ph*mx*4.5,shY+15];break;
      case 'type':br=Math.sin(t*16+side*1.7)*1.5;hand=[side*5.5+o.face*12,shY+19+br];elbow=[side*14.5+o.face*3,shY+14];break;
      case 'think':if(side>0){hand=[3+f,headY+10];elbow=[14+f,shY+11];}else{hand=[2+f,shY+18];elbow=[-13+f,shY+14];}break;
      case 'wait':hand=[side*4.5+f,shY+24];elbow=[side*13.5+f,shY+14];break;
      case 'error':br=Math.sin(t*3+side)*2.2;hand=[side*14.5,shY+31+br];elbow=[side*14.5,shY+15];break;
      case 'off':hand=[side*11.5,shY+32];elbow=[side*12.5,shY+16];break;
      default:br=Math.sin(t*1.2+o.seed+side)*0.9;hand=[side*13.5,shY+30+br];elbow=[side*14,shY+15];
    }
    arms.push([sh,elbow,hand]);
  }
  var col=o.color,A=o.alpha,light=mix(col,WHITE,0.6),dark=mix(col,'#001018',0.35);
  c.save();c.translate(x,y);c.scale(s,s);c.lineCap='round';c.lineJoin='round';
  function limbs(){var i,L;for(i=0;i<2;i++){L=legs[i];c.beginPath();c.moveTo(L[0][0],L[0][1]);c.lineTo(L[1][0],L[1][1]);c.lineTo(L[2][0],L[2][1]);c.stroke();}
    for(i=0;i<2;i++){L=arms[i];c.beginPath();c.moveTo(L[0][0],L[0][1]);c.lineTo(L[1][0],L[1][1]);c.lineTo(L[2][0],L[2][1]);c.stroke();}}
  // armored torso silhouette — broader shoulders, tapered waist, chest plate
  function torsoPath(){
    c.moveTo(-14+f,shY+1);c.quadraticCurveTo(f,shY-5,14+f,shY+1);
    c.lineTo(11.5+f*0.4,shY+14);c.lineTo(8.5+f*0.5,hipY-8);
    c.lineTo(9.2+f*0.3,hipY+2);c.lineTo(-9.2+f*0.3,hipY+2);
    c.lineTo(-8.5+f*0.5,hipY-8);c.lineTo(-11.5+f*0.4,shY+14);c.closePath();
  }
  // helmet / head
  function headPath(){c.moveTo(f*1.3+7.2,headY+1);c.ellipse(f*1.3,headY,7.2,8.8,0,0,P2);}
  // projector cone
  if(o.glow>0){
    var cg=c.createLinearGradient(0,6,0,-110);cg.addColorStop(0,rgba(col,0.2*A));cg.addColorStop(1,rgba(col,0));c.fillStyle=cg;c.beginPath();c.moveTo(-15,6);c.lineTo(15,6);c.lineTo(24,-110);c.lineTo(-24,-110);c.closePath();c.fill();
    c.globalCompositeOperation='lighter';c.strokeStyle=rgba(col,0.18*A*o.glow);c.lineWidth=12;limbs();c.beginPath();torsoPath();headPath();c.stroke();
    c.lineWidth=7;c.strokeStyle=rgba(col,0.14*A*o.glow);limbs();c.globalCompositeOperation='source-over';
  }
  // armored limbs — thicker outer, cyan rim
  c.strokeStyle=rgba(dark,0.7*A);c.lineWidth=5.6;limbs();
  c.strokeStyle=rgba(col,0.75*A);c.lineWidth=4.2;limbs();
  c.strokeStyle=rgba(light,0.95*A);c.lineWidth=1.3;limbs();
  // gauntlet nodes
  for(var gi=0;gi<2;gi++){var hnd=arms[gi][2];c.fillStyle=rgba(CYAN,0.7*A);c.beginPath();c.arc(hnd[0],hnd[1],2.2,0,P2);c.fill();}
  // neck
  c.strokeStyle=rgba(col,0.7*A);c.lineWidth=3.4;c.beginPath();c.moveTo(f,shY);c.lineTo(f*1.15,headY+7);c.stroke();
  // torso armor fill
  var tg=c.createLinearGradient(0,shY,0,hipY);tg.addColorStop(0,rgba(col,0.72*A));tg.addColorStop(0.45,rgba(col,0.35*A));tg.addColorStop(1,rgba(col,0.18*A));
  c.fillStyle=tg;c.beginPath();torsoPath();c.fill();
  c.strokeStyle=rgba(light,1*A);c.lineWidth=1.35;c.stroke();
  // chest plate / abs lines
  c.strokeStyle=rgba(light,0.55*A);c.lineWidth=0.9;
  c.beginPath();c.moveTo(-6+f,shY+16);c.lineTo(6+f,shY+16);c.stroke();
  c.beginPath();c.moveTo(f,shY+10);c.lineTo(f,hipY-6);c.stroke();
  // pauldrons
  c.fillStyle=rgba(col,0.65*A);c.beginPath();c.ellipse(-12.5+f,shY+2,4.2,3.0,0.2,0,P2);c.fill();
  c.beginPath();c.ellipse(12.5+f,shY+2,4.2,3.0,-0.2,0,P2);c.fill();
  c.strokeStyle=rgba(light,0.9*A);c.lineWidth=1;c.beginPath();c.ellipse(-12.5+f,shY+2,4.2,3.0,0.2,0,P2);c.stroke();
  c.beginPath();c.ellipse(12.5+f,shY+2,4.2,3.0,-0.2,0,P2);c.stroke();
  // helmet
  var hg=c.createRadialGradient(f*1.3-2,headY-3,1,f*1.3,headY,10);hg.addColorStop(0,rgba(light,0.85*A));hg.addColorStop(1,rgba(col,0.25*A));
  c.fillStyle=hg;c.beginPath();headPath();c.fill();c.strokeStyle=rgba(light,0.95*A);c.lineWidth=1.2;c.stroke();
  // helmet ridge
  c.strokeStyle=rgba(GOLD,0.55*A);c.lineWidth=0.9;c.beginPath();c.moveTo(f*1.3,headY-7.5);c.lineTo(f*1.3,headY+2);c.stroke();
  if(o.scan){
    c.save();c.beginPath();torsoPath();headPath();c.clip();
    c.fillStyle=rgba(WHITE,0.08*A);for(var yy=headY-10;yy<hipY+2;yy+=3)c.fillRect(-16,yy,32,0.85);
    var by=-110+((t*42+o.seed*20)%130),bgr=c.createLinearGradient(0,by-10,0,by+10);bgr.addColorStop(0,rgba(col,0));bgr.addColorStop(0.5,rgba(WHITE,0.55*A));bgr.addColorStop(1,rgba(col,0));
    c.fillStyle=bgr;c.fillRect(-18,by-10,36,20);c.restore();
  }
  c.globalCompositeOperation='lighter';
  if(!o.back){
    // arc reactor chest light
    var cy=shY+12,rg=c.createRadialGradient(f,cy,0,f,cy,7.5);rg.addColorStop(0,rgba(WHITE,1*A));rg.addColorStop(0.25,rgba(o.status,0.95*A));rg.addColorStop(1,rgba(o.status,0));
    c.fillStyle=rg;c.beginPath();c.arc(f,cy,7.5,0,P2);c.fill();
    c.strokeStyle=rgba(WHITE,0.7*A);c.lineWidth=0.8;c.beginPath();c.arc(f,cy,3.2,0,P2);c.stroke();
    // visor slit
    c.fillStyle=rgba(col,0.7*A);c.beginPath();c.ellipse(f*2.6,headY-0.5,7.4,2.6,0,0,P2);c.fill();
    c.fillStyle=rgba('#effeff',1*A);c.beginPath();c.ellipse(f*2.6,headY-0.5,5.2,1.15,0,0,P2);c.fill();
  }else{
    c.strokeStyle=rgba(light,0.65*A);c.lineWidth=1;c.beginPath();c.moveTo(f,shY+2);c.lineTo(f,hipY-2);c.stroke();
    c.fillStyle=rgba(o.status,0.7*A);c.fillRect(f-3.5,shY+8,7,2.5);
  }
  // strong cyan rim glow
  c.strokeStyle=rgba(CYAN,0.35*A*o.glow);c.lineWidth=8;c.beginPath();torsoPath();c.stroke();
  c.globalCompositeOperation='source-over';c.restore();
}
function drawBase(x,y,s,sc,vis,t,st,sel){
  var rx=15*s;ctx.save();ctx.translate(x,y);ctx.scale(1,view.T);ctx.globalCompositeOperation='lighter';
  var pulse=st==='waiting'?0.55+0.45*Math.sin(t*5):st==='error'?0.55+0.45*Math.sin(t*12):st==='offline'?0.25:0.65+0.2*Math.sin(t*2);
  var g=ctx.createRadialGradient(0,0,0,0,0,rx*1.35);g.addColorStop(0,rgba(sc,0.38*vis*pulse));g.addColorStop(1,rgba(sc,0));
  ctx.fillStyle=g;ctx.beginPath();ctx.arc(0,0,rx*1.35,0,P2);ctx.fill();
  ctx.strokeStyle=rgba(sc,(st==='offline'?0.3:0.85)*vis);ctx.lineWidth=1.5;ctx.beginPath();ctx.arc(0,0,rx,0,P2);ctx.stroke();
  ctx.save();ctx.rotate(t*0.9);ctx.setLineDash([3+rx*0.25,4+rx*0.35]);ctx.strokeStyle=rgba(sc,0.4*vis);ctx.lineWidth=1;ctx.beginPath();ctx.arc(0,0,rx*1.28,0,P2);ctx.stroke();ctx.restore();
  if(st==='waiting'||st==='error'){var per=st==='error'?0.8:1.6,k=(t%per)/per;ctx.strokeStyle=rgba(sc,(1-k)*0.85*vis);ctx.lineWidth=2;ctx.beginPath();ctx.arc(0,0,rx*(1+k*1.7),0,P2);ctx.stroke();}
  if(sel){ctx.save();ctx.rotate(-t*1.4);ctx.setLineDash([6,5]);ctx.strokeStyle=rgba(GOLD,0.95);ctx.lineWidth=1.6;ctx.beginPath();ctx.arc(0,0,rx*1.75,0,P2);ctx.stroke();ctx.restore();}
  ctx.restore();
}
function drawStatusIcon(st,x,hy,s,t,vis){
  var sc=STATUS[st].color,h=0,i;
  ctx.save();ctx.globalAlpha=vis;
  if(st==='thinking'){
    ctx.globalCompositeOperation='lighter';
    for(i=0;i<3;i++){var a=t*2.6+i*P2/3,ox=Math.cos(a)*12*s,oy=Math.sin(a)*4*s,fr=Math.sin(a)>0;ctx.fillStyle=rgba(sc,fr?0.95:0.45);ctx.beginPath();ctx.arc(x+ox,hy-4*s+oy,(fr?2.3:1.6)*s+0.7,0,P2);ctx.fill();}
    h=10*s+4;
  }else if(st==='waiting'||st==='error'){
    var R=Math.max(6,7*s+2);
    if(st==='waiting'||Math.sin(t*10)>-0.4){
      var by=hy-R-2+(st==='waiting'?Math.sin(t*3)*2:0);
      ctx.beginPath();if(st==='waiting'){ctx.moveTo(x,by-R);ctx.lineTo(x+R,by);ctx.lineTo(x,by+R);ctx.lineTo(x-R,by);}else{ctx.moveTo(x,by-R);ctx.lineTo(x+R*1.05,by+R*0.8);ctx.lineTo(x-R*1.05,by+R*0.8);}ctx.closePath();
      ctx.fillStyle=rgba(sc,0.22);ctx.fill();ctx.strokeStyle=rgba(sc,1);ctx.lineWidth=1.4;ctx.stroke();
      ctx.fillStyle=rgba(sc,1);ctx.font='800 '+(R*1.15).toFixed(1)+'px '+UI_FONT;ctx.textAlign='center';ctx.textBaseline='middle';ctx.fillText('!',x,by+(st==='error'?R*0.18:0.5));
    }
    h=R*2+4;
  }
  ctx.restore();return h;
}
function wrapName(n){
  if(n.length<=14)return [n];
  var w=n.split(' ');if(w.length<2)return [n.slice(0,17)+(n.length>17?'…':'')];
  var best=null,bm=1e9;
  for(var i=1;i<w.length;i++){var a=w.slice(0,i).join(' '),b=w.slice(i).join(' '),m=Math.max(a.length,b.length);if(m<bm){bm=m;best=[a,b];}}
  return best.map(function(s){return s.length>18?s.slice(0,17)+'…':s;});
}
function statusLine(a){var s=STATUS[a.status].label;if(a.status==='working')return s+' '+Math.round(a.rt.prog)+'%';if(a.status==='waiting')return 'Needs your approval';return s;}
var tags=[];
function makeTag(a,x,y){
  var mob=view.mobile,lines,w,h;
  if(mob||a.token){lines=[callsignOf(a)];ctx.font='600 11px '+UI_FONT;w=ctx.measureText(lines[0]).width+18;h=17;}
  else{lines=wrapName(a.name);ctx.font='600 12px '+UI_FONT;var mw=0;lines.forEach(function(l){mw=Math.max(mw,ctx.measureText(l).width);});
    ctx.font='500 11px '+UI_FONT;mw=Math.max(mw,ctx.measureText('Working 100%').width);w=Math.ceil(mw)+18;h=lines.length*14+18;}
  var ext=!mob&&!a.token&&a.platform!=='native';
  if(ext){ctx.font='800 8px '+UI_FONT;w=Math.max(w,ctx.measureText(platInfo(a.platform).label.toUpperCase()+(a.example?' · EXAMPLE':'')).width+16);}
  return {a:a,ax:x,ay:y,w:w,h:h,lines:lines,ext:ext,pri:state.selected===a?3:state.hover===a?2:0};
}
function overlap(p,q){return p.x<q.x+q.w&&p.x+p.w>q.x&&p.y<q.y+q.h&&p.y+p.h>q.y;}
function ovArea(p,q){var w=Math.min(p.x+p.w,q.x+q.w)-Math.max(p.x,q.x),h=Math.min(p.y+p.h,q.y+q.h)-Math.max(p.y,q.y);return w>0&&h>0?w*h:0;}
function boxToRect(B){return {x:B.x1,y:B.y1,w:B.x2-B.x1,h:B.y2-B.y1};}
function visibleBottom(){return sheetOpen?H*0.42:view.vp.b;}
var zoneLabels=[];
function placeZoneLabels(){
  var obs=[],vp=view.vp,vb=visibleBottom();if(hubRect)obs.push(hubRect);
  state.agents.forEach(function(a){if(a.rt.hit)obs.push(boxToRect(a.rt.hit));if(a.rt.scrRect)obs.push(boxToRect(a.rt.scrRect));});
  var many=state.zonesLayout.length>6;
  var fs=clamp(view.K*(many?0.34:0.4),many?8.5:9,many?11:12);zoneLabels=[];
  ctx.font='700 '+fs+'px '+UI_FONT;setLS(ctx,'0.5px');
  state.zonesLayout.forEach(function(g){
    var pm=phiAt((g.f0+g.f1)/2),c=Math.cos(pm),s=Math.sin(pm)*view.sV,txt=g.zone.toUpperCase();
    var np=P(c*RS,s*RS);if(np[1]<vp.t||np[1]>vb||np[0]<vp.l-40||np[0]>vp.r+40)return;
    var w=ctx.measureText(txt).width+16,h=fs*2.5+6,best=null,bestScore=1e18,n=0;
    var radii=[1.3,1.42,1.2,1.55,0.7,0.62,1.68],dys=[0,-1.3,1.3,-2.6,2.6,-4,4];
    search:for(var j=0;j<dys.length;j++)for(var i=0;i<radii.length;i++){
      var p=P(c*RS*radii[i],s*RS*radii[i]);
      var r={x:clamp(p[0]-w/2,vp.l+4,vp.r-w-4),y:p[1]-h/2+dys[j]*h,w:w,h:h};
      if(r.y<vp.t+2||r.y+r.h>vb-2)continue;
      var ov=0;obs.forEach(function(o){ov+=ovArea(o,r);});
      if(ov===0){best=r;break search;}
      var sc=ov*20+(n++)*40;if(sc<bestScore){bestScore=sc;best=r;}
    }
    if(!best)return;
    obs.push(best);zoneLabels.push({r:best,txt:txt,n:g.agents.length,fs:fs});
  });
  setLS(ctx,'0px');
}
function drawZoneLabels(){
  zoneLabels.forEach(function(z){
    var r=z.r,cx=r.x+r.w/2;
    ctx.save();ctx.textAlign='center';ctx.textBaseline='top';
    ctx.font='700 '+z.fs+'px '+UI_FONT;setLS(ctx,'0.4px');
    ctx.shadowColor='rgba(0,0,0,0.8)';ctx.shadowBlur=4;ctx.fillStyle='#f0e0a0';
    ctx.fillText(z.txt,cx+z.fs*0.1,r.y+3);ctx.shadowBlur=0;
    ctx.strokeStyle='rgba(201,162,39,0.5)';ctx.lineWidth=1;ctx.beginPath();ctx.moveTo(r.x+6,r.y+z.fs+7);ctx.lineTo(r.x+r.w-6,r.y+z.fs+7);ctx.stroke();
    ctx.fillStyle='rgba(201,162,39,0.92)';ctx.beginPath();var dy=r.y+z.fs+7;ctx.moveTo(cx,dy-2.5);ctx.lineTo(cx+2.5,dy);ctx.lineTo(cx,dy+2.5);ctx.lineTo(cx-2.5,dy);ctx.fill();
    ctx.font='600 '+(z.fs*0.82).toFixed(1)+'px '+MONO_FONT;setLS(ctx,'1px');ctx.fillStyle='rgba(0,212,255,0.75)';
    var zn=(state.zoneCounts&&state.zoneCounts[z.txt]!=null)?state.zoneCounts[z.txt]:z.n;ctx.fillText(zn+(zn===1?' CANDIDATE':' CANDIDATES'),cx,r.y+z.fs+11);
    setLS(ctx,'0px');ctx.restore();
  });
}
function placeAndDrawTags(t){
  var vp=view.vp,vb=visibleBottom();
  var fixed=[];if(hubRect)fixed.push(hubRect);zoneLabels.forEach(function(z){fixed.push(z.r);});
  var figs=[];state.agents.forEach(function(a){if(a.rt.hit&&a.rt.vis>0.45)figs.push([a,boxToRect(a.rt.hit)]);});
  state.agents.forEach(function(a){a.rt.tagRect=null;});
  tags=tags.filter(function(tg){return tg.ay>vp.t-6&&tg.ay<vb+30&&tg.ax>vp.l-20&&tg.ax<vp.r+20;});
  tags.sort(function(p,q){return (q.pri-p.pri)||(p.ay-q.ay);});
  var cands=[[0,0],[0,-1],[0.6,-0.5],[-0.6,-0.5],[1,0],[-1,0],[1,-1],[-1,-1],[0,-2],[1.6,0],[-1.6,0],[1.6,-1],[-1.6,-1],[0.8,-2],[-0.8,-2],[2.2,0],[-2.2,0],[0,-3]];
  var placed=[];
  tags.forEach(function(tg){
    var top=tg.ext?7:0,best=null,bestScore=1e18;
    for(var i=0;i<cands.length;i++){
      var rect={x:clamp(tg.ax-tg.w/2+cands[i][0]*(tg.w*0.6+4),vp.l+2,vp.r-tg.w-2),y:tg.ay-tg.h-4+cands[i][1]*(tg.h+top+4),w:tg.w,h:tg.h};
      rect.y=clamp(rect.y,vp.t+top+2,vb-tg.h-2);
      var test={x:rect.x-2,y:rect.y-top-2,w:rect.w+4,h:rect.h+top+4},sc=i*30;
      for(var k=0;k<placed.length;k++)sc+=ovArea(placed[k],test)*25;
      for(k=0;k<fixed.length;k++)sc+=ovArea(fixed[k],test)*25;
      for(k=0;k<figs.length;k++)if(figs[k][0]!==tg.a)sc+=ovArea(figs[k][1],test)*2;
      if(sc<bestScore){bestScore=sc;best=rect;}
      if(sc===i*30)break;
    }
    placed.push({x:best.x-2,y:best.y-top-2,w:best.w+4,h:best.h+top+4});
    tg.rect=best;tg.a.rt.tagRect=best;
  });
  for(var i=tags.length-1;i>=0;i--)drawTag(tags[i],t);
}
function drawTag(tg,t){
  var a=tg.a,r=tg.rect,x=r.x,y=r.y,w=r.w,h=r.h,pc=agentColor(a),sc=STATUS[a.status].color,hi=tg.pri>0;
  ctx.save();ctx.globalAlpha=Math.min(1,a.rt.vis*1.1);
  var bx=x+w/2,displaced=Math.abs(bx-tg.ax)>6||Math.abs(y+h+4-tg.ay)>6;
  ctx.strokeStyle=rgba(pc,0.55);ctx.lineWidth=1;
  if(displaced){var ex=clamp(tg.ax,x+4,x+w-4),ey=tg.ay<y?y:y+h;ctx.beginPath();ctx.moveTo(tg.ax,tg.ay);ctx.lineTo(ex,ey);ctx.stroke();ctx.fillStyle=rgba(pc,0.9);ctx.fillRect(tg.ax-1.5,tg.ay-1.5,3,3);}
  else{ctx.beginPath();ctx.moveTo(bx-4,y+h);ctx.lineTo(bx,y+h+4);ctx.lineTo(bx+4,y+h);ctx.fillStyle=rgba(pc,0.8);ctx.fill();}
  // holographic panel body
  ctx.beginPath();ctx.moveTo(x+6,y);ctx.lineTo(x+w,y);ctx.lineTo(x+w,y+h-6);ctx.lineTo(x+w-6,y+h);ctx.lineTo(x,y+h);ctx.lineTo(x,y+6);ctx.closePath();
  var tgFill=ctx.createLinearGradient(x,y,x,y+h);tgFill.addColorStop(0,hi?'rgba(8,32,52,0.96)':'rgba(2,14,28,0.88)');tgFill.addColorStop(1,hi?'rgba(4,18,34,0.94)':'rgba(1,8,18,0.86)');
  ctx.fillStyle=tgFill;ctx.fill();
  ctx.strokeStyle=rgba(tg.pri===3?GOLD:pc,hi?1:0.7);ctx.lineWidth=hi?1.5:1;ctx.stroke();
  // corner brackets
  var cb=5;ctx.strokeStyle=rgba(GOLD,hi?0.9:0.55);ctx.lineWidth=1.2;ctx.beginPath();
  ctx.moveTo(x,y+cb);ctx.lineTo(x,y);ctx.lineTo(x+cb,y);
  ctx.moveTo(x+w-cb,y);ctx.lineTo(x+w,y);ctx.lineTo(x+w,y+cb);
  ctx.moveTo(x+w,y+h-cb);ctx.lineTo(x+w,y+h);ctx.lineTo(x+w-cb,y+h);
  ctx.moveTo(x+cb,y+h);ctx.lineTo(x,y+h);ctx.lineTo(x,y+h-cb);ctx.stroke();
  // status accent bar
  ctx.fillStyle=sc;ctx.shadowColor=sc;ctx.shadowBlur=6;ctx.fillRect(x+1,y+5,2.8,h-10);ctx.shadowBlur=0;
  // faint scanline across tag
  var sy=y+((t*28+a.rt.seed*10)%(h+8))-4;ctx.fillStyle='rgba(255,255,255,0.06)';ctx.fillRect(x+4,sy,w-8,1.2);
  ctx.textAlign='left';ctx.textBaseline='top';
  if(view.mobile||a.token){ctx.font='600 11px '+UI_FONT;ctx.fillStyle='#fff';ctx.fillText(tg.lines[0],x+8,y+3);ctx.fillStyle=sc;ctx.beginPath();ctx.arc(x+w-7,y+h/2,2.5,0,P2);ctx.fill();}
  else{
    ctx.font='600 12px '+UI_FONT;ctx.fillStyle='#fff';
    tg.lines.forEach(function(l,i){ctx.fillText(l,x+9,y+5+i*14);});
    ctx.font='500 11px '+UI_FONT;ctx.fillStyle=sc;ctx.fillText(statusLine(a),x+9,y+6+tg.lines.length*14);
    if(tg.ext){var lab=platInfo(a.platform).label.toUpperCase()+(a.example?' · EXAMPLE':'');ctx.font='800 8px '+UI_FONT;var pw=ctx.measureText(lab).width+8;
      ctx.fillStyle=pc;ctx.fillRect(x+w-pw-3,y-7,pw,11);ctx.fillStyle='#02101c';ctx.textBaseline='middle';ctx.fillText(lab,x+w-pw+1,y-1);}
  }
  ctx.restore();
}
function drawAgent(a,t){
  var r=a.rt;if(!r.station)return;
  var p=P(r.u,r.v,0),x=p[0],y0=p[1],s=(1.9*view.K*view.Z/100)*view.figScale*(a.token?0.56:1);
  var st=a.status,sc=STATUS[st].color,pc=agentColor(a),off=st==='offline';
  var e=r.spawn,vis=r.vis*(e*(2-e)),sel=state.selected===a,hov=state.hover===a;
  var hover=off?0:(2.5+Math.sin(t*1.8+r.seed)*1.5),y=y0-hover*s;
  // soft floor reflection under agent
  if(vis>0.15&&!off){ctx.save();ctx.translate(x,y0);ctx.scale(1,view.T);ctx.globalCompositeOperation='lighter';
    var rg=ctx.createRadialGradient(0,0,0,0,0,16*s);rg.addColorStop(0,rgba(pc,0.22*vis));rg.addColorStop(0.55,rgba(CYAN,0.08*vis));rg.addColorStop(1,rgba(CYAN,0));
    ctx.fillStyle=rg;ctx.beginPath();ctx.ellipse(0,0,14*s,5.5*s,0,0,P2);ctx.fill();ctx.restore();}
  drawBase(x,y0,s,sc,vis,t,st,sel);
  var pose=r.moving?'walk':(st==='working'&&r.goalKind==='station'?'type':st==='thinking'?'think':st==='waiting'?'wait':st==='error'?'error':off?'off':'idle');
  var body=off?'#5b6c80':(st==='error'&&Math.sin(t*9)>0.3)?mix(pc,RED,0.7):pc;
  if(e<1){ctx.save();ctx.beginPath();ctx.rect(x-40*s,y0+6-(112*s+6)*e,80*s,(112*s+6)*e);ctx.clip();}
  drawFigure(ctx,x+r.glitch*s*4,y,s,{color:body,status:sc,alpha:vis*(off?0.38:1),glow:off?0:(hov||sel?1.6:1),walk:r.walk,moving:r.moving,mdx:r.mdx,mdy:r.mdy,back:r.back,face:r.face,pose:pose,t:t,seed:r.seed,scan:!off});
  if(e<1)ctx.restore();
  if(st==='error'&&Math.abs(r.glitch)>0.2){ctx.save();ctx.globalCompositeOperation='lighter';ctx.fillStyle=rgba(RED,0.35*vis);ctx.fillRect(x-14*s,y-rand(20,90)*s,28*s,2);ctx.restore();}
  var headY=y-100*s,ih=a.token?0:drawStatusIcon(st,x,headY-2,s,t,vis);
  r.hit={x1:x-18*s-6,y1:headY-8-ih,x2:x+18*s+6,y2:y0+6};
  if(r.vis>0.45&&(!a.token||sel||hov))tags.push(makeTag(a,x,headY-4-ih));
}
function drawBeams(t){
  var hub=P(0,0,2.75);
  ctx.save();ctx.globalCompositeOperation='lighter';
  state.agents.forEach(function(a){
    if(a.status!=='working'&&a.status!=='thinking')return;var r=a.rt,st=r.station;if(!st)return;
    var sc=STATUS[a.status].color,s0=P(st.du,st.dv,1.55),mx=(s0[0]+hub[0])/2,my=Math.min(s0[1],hub[1])-1.5*view.K,v=r.vis;
    // soft energy beam
    ctx.strokeStyle=rgba(sc,0.18*v);ctx.lineWidth=2.6;ctx.beginPath();ctx.moveTo(s0[0],s0[1]);ctx.quadraticCurveTo(mx,my,hub[0],hub[1]);ctx.stroke();
    ctx.strokeStyle=rgba(CYAN,0.08*v);ctx.lineWidth=1;ctx.beginPath();ctx.moveTo(s0[0],s0[1]);ctx.quadraticCurveTo(mx,my,hub[0],hub[1]);ctx.stroke();
    var spd=a.status==='working'?0.38:0.18;
    for(var k=0;k<3;k++){var u=(t*spd+r.seed/P2+k*0.33)%1,iu=1-u,px=iu*iu*s0[0]+2*iu*u*mx+u*u*hub[0],py=iu*iu*s0[1]+2*iu*u*my+u*u*hub[1];
      ctx.fillStyle=rgba(sc,0.28*v);ctx.beginPath();ctx.arc(px,py,5,0,P2);ctx.fill();ctx.fillStyle=rgba(WHITE,0.95*v);ctx.beginPath();ctx.arc(px,py,1.5,0,P2);ctx.fill();}
    // occasional energy arc bursts for working agents
    if(a.status==='working'&&((t*2.1+r.seed)%2.4)<0.85){
      var segs=5,pts=[];for(var si=0;si<=segs;si++){var uu=si/segs,ii=1-uu,bx=ii*ii*s0[0]+2*ii*uu*mx+uu*uu*hub[0],by=ii*ii*s0[1]+2*ii*uu*my+uu*uu*hub[1];
        var nx=-(my-s0[1]),ny=(mx-s0[0]),nl=Math.hypot(nx,ny)||1;var jag=(si===0||si===segs)?0:Math.sin(t*28+si*2.1+r.seed)*14;
        pts.push([bx+nx/nl*jag,by+ny/nl*jag]);}
      ctx.strokeStyle=rgba(WHITE,0.55*v);ctx.lineWidth=1.4;ctx.beginPath();
      for(si=0;si<pts.length;si++)si?ctx.lineTo(pts[si][0],pts[si][1]):ctx.moveTo(pts[si][0],pts[si][1]);ctx.stroke();
      ctx.strokeStyle=rgba(CYAN,0.35*v);ctx.lineWidth=3;ctx.beginPath();
      for(si=0;si<pts.length;si++)si?ctx.lineTo(pts[si][0],pts[si][1]):ctx.moveTo(pts[si][0],pts[si][1]);ctx.stroke();
    }
  });
  ctx.restore();
}
function drawParticles(t,dt){
  ctx.save();ctx.globalCompositeOperation='lighter';
  for(var i=0;i<parts.length;i++){
    var p=parts[i];p.y-=p.vy*dt;p.x+=p.vx*dt+Math.sin(t*0.8+p.p)*4*dt;if(p.y<-6){p.y=H+6;p.x=Math.random()*W;}if(p.x<-6)p.x=W+6;if(p.x>W+6)p.x=-6;
    var a=p.a*(0.45+0.55*Math.sin(t*1.6+p.p)),col=p.kind==='gold'?GOLD:(p.kind==='spark'?WHITE:CYAN);
    if(p.kind==='spark'){
      ctx.strokeStyle=rgba(col,a);ctx.lineWidth=0.8;ctx.beginPath();ctx.moveTo(p.x-p.r*2,p.y);ctx.lineTo(p.x+p.r*2,p.y);ctx.moveTo(p.x,p.y-p.r*2);ctx.lineTo(p.x,p.y+p.r*2);ctx.stroke();
    }else{
      var g=ctx.createRadialGradient(p.x,p.y,0,p.x,p.y,p.r*3);g.addColorStop(0,rgba(col,a));g.addColorStop(1,rgba(col,0));
      ctx.fillStyle=g;ctx.beginPath();ctx.arc(p.x,p.y,p.r*3,0,P2);ctx.fill();
      ctx.fillStyle=rgba(col,a*0.9);ctx.fillRect(p.x-p.r*0.5,p.y-p.r*0.5,p.r,p.r);
    }
  }
  ctx.restore();
}
function drawReticle(a,t){
  var h=a.rt.hit;if(!h)return;var pad=5+Math.sin(t*4)*2,x1=h.x1-pad,y1=h.y1-pad,x2=h.x2+pad,y2=h.y2+pad,L=9;
  ctx.save();ctx.strokeStyle=rgba(GOLD,0.95);ctx.lineWidth=1.6;ctx.beginPath();
  ctx.moveTo(x1,y1+L);ctx.lineTo(x1,y1);ctx.lineTo(x1+L,y1);ctx.moveTo(x2-L,y1);ctx.lineTo(x2,y1);ctx.lineTo(x2,y1+L);
  ctx.moveTo(x2,y2-L);ctx.lineTo(x2,y2);ctx.lineTo(x2-L,y2);ctx.moveTo(x1+L,y2);ctx.lineTo(x1,y2);ctx.lineTo(x1,y2-L);ctx.stroke();
  ctx.restore();
}
function drawHudChrome(t){
  /* panels own readability; keep chrome minimal on desktop only */
  if(W<=1099)return;
  var vp=view.vp,mid=(vp.l+vp.r)/2;
  ctx.save();
  ctx.strokeStyle=rgba(GOLD,0.28);ctx.lineWidth=1;
  ctx.beginPath();ctx.moveTo(mid-22,vp.t+5);ctx.lineTo(mid-6,vp.t+5);ctx.moveTo(mid+6,vp.t+5);ctx.lineTo(mid+22,vp.t+5);
  ctx.moveTo(mid,vp.t+2);ctx.lineTo(mid,vp.t+10);ctx.stroke();
  ctx.restore();
}
function drawEmpty(){
  if(state.agents.size)return;var o=P(0,0,0);
  ctx.save();ctx.textAlign='center';ctx.font='700 13px '+DISP_FONT;ctx.fillStyle=rgba(CYAN,0.85);
  ctx.fillText('DEMO DATA',o[0],o[1]+view.K*6.2*view.T+30);
  ctx.font='600 12px '+UI_FONT;ctx.fillStyle=rgba('#9cc6dc',0.8);ctx.fillText('Sample candidates appear as the story runs.',o[0],o[1]+view.K*6.2*view.T+50);ctx.restore();
}
function draw(t,dt){
  ctx.setTransform(DPR,0,0,DPR,0,0);ctx.globalCompositeOperation='source-over';ctx.globalAlpha=1;
  ctx.drawImage(floorCvs,0,0,W,H);
  drawPulse(t);drawHubFloor(t);
  var list=Array.from(state.agents.values());
  list.forEach(function(a){if(!a.token)drawStationFloor(a,t);});
  var items=[];list.forEach(function(a){if(!a.rt.station)return;if(!a.token)items.push([a.rt.station.dv,0,a]);items.push([a.rt.v,1,a]);});
  items.push([0.01,2,null]);items.sort(function(p,q){return p[0]-q[0];});
  tags=[];
  items.forEach(function(it){if(it[1]===0)drawDesk(it[2],t);else if(it[1]===1)drawAgent(it[2],t);else drawHubTower(t);});
  drawBeams(t);drawParticles(t,dt);
  if(state.selected)drawReticle(state.selected,t);
  placeZoneLabels();drawZoneLabels();
  placeAndDrawTags(t);
  drawHudChrome(t);
  drawEmpty();
}

function placeTokens(){
  var by={};
  state.agents.forEach(function(a){if(!a.token)return;(by[a.zone]=by[a.zone]||[]).push(a);});
  state.zonesLayout.forEach(function(g){
    var list=by[g.zone]||[];
    list.forEach(function(a,i){
      var frac=list.length<=1?0.5:(i+0.5)/list.length;
      var phi=phiAt(g.f0+(g.f1-g.f0)*frac);
      var rad=6.55;
      var cu=Math.cos(phi), sv=Math.sin(phi)*view.sV;
      var du=cu*rad, dv=sv*rad;
      var tu=-Math.sin(phi), tv=Math.cos(phi)*view.sV, tl=Math.hypot(tu,tv)||1;
      a.rt.station={phi:phi,du:du,dv:dv,tu:tu/tl,tv:tv/tl,hx:1,hy:0,rf:0.7,token:true};
      a.rt.stand={u:du,v:dv};
    });
  });
}

var pc2=null,pctx=null;
function drawPortrait(t){
  if(!pc2||!pctx||!state.selected)return;
  var a=state.selected,w=72,h=92;
  pctx.setTransform(DPR,0,0,DPR,0,0);pctx.clearRect(0,0,w,h);
  var sc=STATUS[a.status].color,pc=agentColor(a),off=a.status==='offline';
  pctx.save();pctx.translate(w/2,h-16);pctx.scale(1,0.32);pctx.globalCompositeOperation='lighter';
  pctx.strokeStyle=rgba(sc,0.9);pctx.lineWidth=1.5;pctx.beginPath();pctx.arc(0,0,26,0,P2);pctx.stroke();
  pctx.rotate(t);pctx.setLineDash([5,6]);pctx.strokeStyle=rgba(sc,0.5);pctx.beginPath();pctx.arc(0,0,34,0,P2);pctx.stroke();pctx.restore();
  var pose=a.status==='working'?'type':a.status==='thinking'?'think':a.status==='waiting'?'wait':a.status==='error'?'error':off?'off':'idle';
  drawFigure(pctx,w/2,h-14,0.72,{color:off?'#5b6c80':pc,status:sc,alpha:off?0.45:1,glow:off?0:1.3,walk:0,moving:false,mdx:0,mdy:1,back:false,face:Math.sin(t*0.7)*0.5,pose:pose,t:t,seed:a.rt.seed,scan:!off});
}

function localPoint(e){
  var r=cvs.getBoundingClientRect();
  return {x:e.clientX-r.left,y:e.clientY-r.top};
}
function hitTest(x,y){
  var list=Array.from(state.agents.values()).filter(function(a){return a.rt.station&&a.rt.vis>0.4&&!a.token;}).sort(function(a,b){return b.rt.v-a.rt.v;});
  var tokens=Array.from(state.agents.values()).filter(function(a){return a.token&&a.rt.hit;});
  function inR(R){return R&&x>=R.x&&x<=R.x+R.w&&y>=R.y&&y<=R.y+R.h;}
  function inB(B){return B&&x>=B.x1&&x<=B.x2&&y>=B.y1&&y<=B.y2;}
  var i;
  for(i=0;i<list.length;i++)if(inR(list[i].rt.tagRect))return list[i];
  for(i=0;i<tokens.length;i++)if(inB(tokens[i].rt.hit)||inR(tokens[i].rt.tagRect))return tokens[i];
  for(i=0;i<list.length;i++)if(inB(list[i].rt.hit))return list[i];
  for(i=0;i<list.length;i++)if(inB(list[i].rt.scrRect))return list[i];
  return null;
}
function zoomAt(x,y,z){z=clamp(z,0.6,2.6);var before=toUV(x,y);view.zoom=z;computeView();var after=P(before.u,before.v,0);view.panX+=x-after[0];view.panY+=y-after[1];computeView();floorDirty=true;}
var ptrs=new Map(),drag=null,pinch=null;
function onDown(e){
  try{cvs.setPointerCapture(e.pointerId);}catch(_){}
  ptrs.set(e.pointerId,{x:e.clientX,y:e.clientY});
  if(ptrs.size===1)drag={x:e.clientX,y:e.clientY,px:view.panX,py:view.panY,moved:false};
  else if(ptrs.size===2){var v=Array.from(ptrs.values());pinch={d:Math.hypot(v[0].x-v[1].x,v[0].y-v[1].y)||1,z:view.zoom};drag=null;}
}
function onMove(e){
  if(ptrs.has(e.pointerId))ptrs.set(e.pointerId,{x:e.clientX,y:e.clientY});
  if(pinch&&ptrs.size===2){var v=Array.from(ptrs.values()),d=Math.hypot(v[0].x-v[1].x,v[0].y-v[1].y);var mid=localPoint({clientX:(v[0].x+v[1].x)/2,clientY:(v[0].y+v[1].y)/2});zoomAt(mid.x,mid.y,pinch.z*d/pinch.d);return;}
  if(drag){var dx=e.clientX-drag.x,dy=e.clientY-drag.y;if(!drag.moved&&Math.hypot(dx,dy)>6){drag.moved=true;cvs.classList.add('grabbing');}
    if(drag.moved){view.panX=drag.px+dx;view.panY=drag.py+dy;computeView();floorDirty=true;}return;}
  var p=localPoint(e);var h=hitTest(p.x,p.y);state.hover=h;cvs.classList.toggle('hovering',!!h);
}
function onUp(e){
  ptrs.delete(e.pointerId);
  if(drag&&!drag.moved&&e.type==='pointerup'){var p=localPoint(e);var h=hitTest(p.x,p.y);if(h){state.selected=h;onSelect(h);}else if(state.selected){state.selected=null;onSelect(null);}}
  if(ptrs.size<2)pinch=null;if(!ptrs.size){drag=null;cvs.classList.remove('grabbing');}
}
cvs.addEventListener('pointerdown',onDown);
cvs.addEventListener('pointermove',onMove);
cvs.addEventListener('pointerup',onUp);
cvs.addEventListener('pointercancel',onUp);
cvs.addEventListener('pointerleave',function(){state.hover=null;cvs.classList.remove('hovering');});
cvs.addEventListener('wheel',function(e){e.preventDefault();var p=localPoint(e);zoomAt(p.x,p.y,view.zoom*Math.exp(-e.deltaY*0.0015));},{passive:false});

var running=true,lastNow=0,raf=0;
function frame(now){
  if(!running)return;
  var t=now/1000,dt=lastNow?Math.min(0.05,(now-lastNow)/1000):0.016;lastNow=now;
  if(hooks.reduced&&hooks.reduced())dt=0;
  if(!W)resize();
  if(layoutDirty){layout();layoutDirty=false;}
  if(floorDirty){renderFloor();floorDirty=false;}
  state.agents.forEach(function(a){updateAgent(a,dt,t);});
  draw(t,dt);
  if(state.selected)drawPortrait(t);
  raf=requestAnimationFrame(frame);
}
function sync(list, counts){
  var seen={};
  state.zoneCounts=counts||{};
  list.forEach(function(spec){
    seen[spec.id]=1;
    var a=state.agents.get(spec.id);
    if(!a){a=makeAgent(spec.id);state.agents.set(spec.id,a);layoutDirty=true;}
    if(a.zone!==spec.zone||!!a.token!==!!spec.token){if(!a.rt.placed||!a.token)a.rt.placed=false;a.rt.station=null;a.rt.path=[];layoutDirty=true;}
    a.name=spec.name;a.zone=spec.zone;a.status=spec.status;a.currentTask=spec.task||'';
    a.progress=spec.progress||0;a.platform=spec.platform||'native';a.role=spec.role||'';
    a.color=spec.color||null;a.token=!!spec.token;a.callsign=spec.callsign||'';a.example=!!spec.example;
    if(spec.select)state.selected=a;
  });
  Array.from(state.agents.keys()).forEach(function(id){if(!seen[id]){if(state.selected&&state.selected.id===id)state.selected=null;state.agents.delete(id);layoutDirty=true;}});
}
resize();
raf=requestAnimationFrame(frame);
return {
  sync:sync,
  resize:resize,
  recenter:function(){view.zoom=1;view.panX=0;view.panY=0;computeView();floorDirty=true;},
  setSheet:function(open){sheetOpen=!!open;},
  select:function(id){var a=id?state.agents.get(id):null;state.selected=a||null;},
  setPortrait:function(node){pc2=node;pctx=node?node.getContext('2d'):null;if(pc2){pc2.width=72*DPR;pc2.height=92*DPR;}},
  destroy:function(){running=false;cancelAnimationFrame(raf);cvs.removeEventListener('pointerdown',onDown);cvs.removeEventListener('pointermove',onMove);cvs.removeEventListener('pointerup',onUp);cvs.removeEventListener('pointercancel',onUp);}
};
}
