<?php declare(strict_types = 0);

/**
 * Network Topology Map - Omada WiFi Integration
 * Pure SVG/JS - Multi-Tier Cascading Tree Topology
 *
 * @var CView $this
 * @var array $data
 */

$this->addJsFile('layout.mode.js');
$web_layout_mode = $this->getLayoutMode();

$html_page = (new CHtmlPage())
	->setTitle(_('Network topology'))
	->setWebLayoutMode($web_layout_mode)
	->setControls(
		(new CTag('nav', true, (new CList())->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))))
			->setAttribute('aria-label', _('Content controls'))
	);

$multiselect_groups = new CMultiSelect([
	'name'        => 'groupids[]',
	'object_name' => 'hostGroup',
	'data'        => $data['groups_multiselect'],
	'popup'       => ['parameters' => ['srctbl' => 'host_groups', 'srcfld1' => 'groupid', 'dstfrm' => 'topo_form', 'dstfld1' => 'groupids_', 'with_hosts' => true, 'enrich_parent_groups' => true]]
]);
$multiselect_groups->setId('groupids_')->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH);

$multiselect_hosts = new CMultiSelect([
	'name'        => 'hostids[]',
	'object_name' => 'hosts',
	'data'        => $data['hosts_multiselect'],
	'popup'       => ['filter_preselect' => ['id' => 'groupids_', 'submit_as' => 'groupid'], 'parameters' => ['srctbl' => 'hosts', 'srcfld1' => 'hostid', 'dstfrm' => 'topo_form', 'dstfld1' => 'hostids_']]
]);
$multiselect_hosts->setId('hostids_')->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH);

$groups_html   = $multiselect_groups->toString();
$hosts_html    = $multiselect_hosts->toString();
$resolved_json = json_encode($data['resolved_hostids']);

$html = <<<'TOPO'
<style>
#topo-wrap *{box-sizing:border-box;margin:0;padding:0}
#topo-wrap{font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;display:flex;flex-direction:column;gap:8px}
#topo-filter-bar{background:var(--ui-bg-color);border:1px solid var(--border-color);border-radius:4px;overflow:hidden}
#topo-filter-toggle{display:flex;align-items:center;gap:8px;padding:8px 14px;cursor:pointer;font-size:12px;font-weight:600;user-select:none;border-bottom:1px solid var(--border-color)}
#topo-filter-toggle .arr{transition:transform .2s;display:inline-block}
#topo-filter-body{display:grid;grid-template-columns:1fr 1fr auto auto;gap:12px;align-items:end;padding:12px 14px}
#topo-filter-body label{font-size:11px;font-weight:600;text-transform:uppercase;color:var(--font-alt-color);display:block;margin-bottom:4px}
/* Card */
#topo-card{position:relative;width:100%;height:840px;background:#071422;border-radius:8px;overflow:hidden;border:1px solid #1e3a5f;box-shadow:0 4px 24px rgba(0,0,0,.6)}
/* Toolbar */
#topo-toolbar{position:absolute;top:0;left:0;right:0;z-index:20;height:42px;display:flex;align-items:center;justify-content:space-between;padding:0 14px;background:linear-gradient(180deg,rgba(7,20,34,.95) 0%,rgba(7,20,34,.75) 100%);border-bottom:1px solid #1e3a5f}
#topo-title{font-size:14px;font-weight:700;color:#e2e8f0;letter-spacing:.4px}
#topo-legend{display:flex;align-items:center;gap:18px;font-size:11px;color:#94a3b8}
#topo-legend span{display:flex;align-items:center;gap:5px}
#topo-legend .dot{width:8px;height:8px;border-radius:50%;display:inline-block}
#topo-live-badge{background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.4);color:#22c55e;font-size:10px;font-weight:600;padding:2px 8px;border-radius:10px;letter-spacing:.3px}

/* Loop Alert Banner */
#topo-loop-banner{position:absolute;top:48px;left:14px;right:180px;z-index:21;background:rgba(239,68,68,.25);border:1.5px solid #ef4444;border-radius:6px;padding:8px 14px;font-size:11px;font-weight:700;color:#ff6666;display:none;align-items:center;gap:8px;box-shadow:0 0 16px rgba(239,68,68,.4);animation:pulse-banner 2s infinite}
@keyframes pulse-banner{0%,100%{border-color:#ef4444;box-shadow:0 0 10px rgba(239,68,68,.4)}50%{border-color:#ff8888;box-shadow:0 0 22px rgba(239,68,68,.7)}}

/* Host info */
#topo-hostinfo{position:absolute;top:50px;right:14px;z-index:20;background:rgba(7,20,34,.9);border:1px solid #1e3a5f;border-radius:6px;padding:10px 14px;font-size:11px;color:#94a3b8;min-width:150px}
#topo-hostinfo .h-row{display:flex;justify-content:space-between;gap:14px;margin-bottom:3px}
.h-val{color:#e2e8f0;font-weight:600}.h-crit{color:#ef4444;font-weight:700}.h-warn{color:#f59e0b;font-weight:700}
/* Zoom */
#topo-zoom{position:absolute;left:12px;top:95px;z-index:20;display:flex;flex-direction:column;gap:4px}
#topo-zoom button{width:30px;height:30px;border-radius:4px;background:rgba(7,20,34,.9);border:1px solid #1e3a5f;color:#94a3b8;font-size:15px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s}
#topo-zoom button:hover{background:#1e3a5f;color:#e2e8f0}
#topo-zoom .sep{height:4px}
/* SVG */
#topo-svg{position:absolute;top:0;left:0;width:100%;height:100%;cursor:grab}
#topo-svg:active{cursor:grabbing}
.topo-node{cursor:pointer}
.node-label{fill:#e2e8f0;font-family:'Inter',sans-serif;font-size:11px;font-weight:600;text-anchor:middle;pointer-events:none;dominant-baseline:text-before-edge;paint-order:stroke;stroke:#071422;stroke-width:2px}
.node-sublabel{fill:#94a3b8;font-family:'Inter',sans-serif;font-size:9.5px;text-anchor:middle;pointer-events:none;dominant-baseline:text-before-edge}
.node-sublabel-err{fill:#ef4444;font-family:'Inter',sans-serif;font-size:10px;font-weight:800;text-anchor:middle;pointer-events:none;dominant-baseline:text-before-edge}
.edge-label{fill:#ef4444;font-family:'Inter',sans-serif;font-size:9.5px;font-weight:700;text-anchor:middle}
@keyframes pulse-node{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.6;transform:scale(1.18)}}
.loop-node .node-bg{animation:pulse-node 1.4s infinite}
.loop-node .node-ring{stroke:#ef4444!important;stroke-width:4px!important;animation:pulse-node 1.4s infinite}

/* Details Panel */
#topo-details{position:absolute;top:42px;right:0;bottom:0;width:290px;z-index:30;background:rgba(7,20,34,.97);border-left:1px solid #1e3a5f;display:flex;flex-direction:column;transform:translateX(100%);transition:transform .25s ease}
#topo-details.open{transform:translateX(0)}
#topo-details-head{padding:12px 14px;border-bottom:1px solid #1e3a5f;display:flex;justify-content:space-between;align-items:center;font-size:12px;font-weight:700;color:#e2e8f0}
#topo-details-head button{background:none;border:none;color:#64748b;font-size:16px;cursor:pointer;line-height:1}
#topo-details-head button:hover{color:#e2e8f0}
#topo-details-body{flex:1;overflow-y:auto;padding:12px 14px;font-size:11px}
.det-row{display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid rgba(255,255,255,.05)}
.det-key{color:#64748b}.det-val{color:#e2e8f0;font-weight:600;text-align:right;max-width:160px;word-break:break-all}
.det-status-ok{color:#22c55e;font-weight:700}.det-status-err{color:#ef4444;font-weight:700}
.det-loop-box{margin-top:10px;padding:8px;border-radius:4px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.4);color:#ef4444;font-size:10px;font-weight:600;line-height:1.6}
/* Minimap */
#topo-minimap{position:absolute;bottom:28px;right:14px;z-index:20;width:170px;height:110px;background:rgba(7,20,34,.92);border:1px solid #1e3a5f;border-radius:5px;overflow:hidden}
#topo-minimap-svg{width:100%;height:100%}
#topo-minimap-vp{position:absolute;border:1.5px solid rgba(59,130,246,.7);background:rgba(59,130,246,.08);pointer-events:none}
#topo-minimap-close{position:absolute;top:2px;right:4px;background:none;border:none;color:#64748b;font-size:11px;cursor:pointer;z-index:1}
/* Status */
#topo-statusbar{position:absolute;bottom:0;left:0;right:0;z-index:20;height:22px;padding:0 12px;display:flex;align-items:center;justify-content:space-between;font-size:10px;color:#475569;background:rgba(7,20,34,.85);border-top:1px solid #1e3a5f}
</style>

<div id="topo-wrap">
<form method="get" action="zabbix.php" name="topo_form" id="topo_form">
<input type="hidden" name="action" value="omada.topology">
<div id="topo-filter-bar">
  <div id="topo-filter-toggle" onclick="topoToggleFilter()">
    <span class="arr" id="topo-arr">▶</span><span>Filter</span>
  </div>
  <div id="topo-filter-body" style="display:none">
    <div><label>Host Groups</label>__GROUPS_HTML__</div>
    <div><label>Hosts</label>__HOSTS_HTML__</div>
    <div>
      <label>Options</label>
      <label style="display:inline-flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;text-transform:none;font-weight:normal">
        <input type="checkbox" id="topo-show-clients" style="width:14px;height:14px"> Show Clients
      </label>
    </div>
    <div style="display:flex;gap:8px;align-items:flex-end">
      <button type="submit" style="padding:4px 14px;background:#0275d8;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:12px;font-weight:600">Apply</button>
      <button type="button" onclick="location.href='zabbix.php?action=omada.topology'" style="padding:4px 14px;border:1px solid var(--border-color);background:var(--ui-bg-color);color:var(--font-color);border-radius:4px;cursor:pointer;font-size:12px">Reset</button>
    </div>
  </div>
</div>
</form>

<div id="topo-card">
  <div id="topo-toolbar">
    <span id="topo-title">&#127968; Hotel Network Topology (Cascading Tree)</span>
    <div id="topo-legend">
      <span><span class="dot" style="background:#22c55e;box-shadow:0 0 6px #22c55e"></span> Active</span>
      <span><span class="dot" style="background:#ef4444;box-shadow:0 0 6px #ef4444"></span> Failed / Loop</span>
      <span><span class="dot" style="background:#38bdf8;box-shadow:0 0 4px #38bdf8"></span> Clients</span>
      <span id="topo-live-badge">&#11044; Live metrics</span>
    </div>
  </div>

  <div id="topo-loop-banner">
    <span>&#9888; LOOP DETECTED:</span>
    <span id="topo-loop-banner-text"></span>
  </div>

  <div id="topo-hostinfo">
    <div class="h-row"><span>Host Group:</span><span class="h-val">Hotel-WLAN</span></div>
    <div class="h-row"><span>Total Hosts:</span><span class="h-val" id="hi-total">&#8211;</span></div>
    <div class="h-row"><span>Crit:</span><span class="h-crit" id="hi-crit">&#8211;</span></div>
    <div class="h-row"><span>Warn:</span><span class="h-warn" id="hi-warn">&#8211;</span></div>
  </div>

  <div id="topo-zoom">
    <button id="btn-zi"  title="Zoom In">+</button>
    <button id="btn-zo"  title="Zoom Out">&#8722;</button>
    <div class="sep"></div>
    <button id="btn-fit" title="Fit to Page" style="font-size:12px">&#10214;</button>
    <div class="sep"></div>
    <button id="btn-sel" title="Zoom to Selected" style="font-size:11px">&#8857;</button>
    <button id="btn-exp" title="Export SVG" style="font-size:11px">&#8599;</button>
  </div>

  <svg id="topo-svg" xmlns="http://www.w3.org/2000/svg">
    <defs>
      <filter id="gf-blue"  x="-60%" y="-60%" width="220%" height="220%">
        <feGaussianBlur in="SourceGraphic" stdDeviation="5" result="b"/>
        <feColorMatrix in="b" type="matrix" values="0 0 0 0 0.23 0 0 0 0 0.51 0 0 0 0 0.96 0 0 0 1 0" result="c"/>
        <feMerge><feMergeNode in="c"/><feMergeNode in="SourceGraphic"/></feMerge>
      </filter>
      <filter id="gf-teal"  x="-60%" y="-60%" width="220%" height="220%">
        <feGaussianBlur in="SourceGraphic" stdDeviation="5" result="b"/>
        <feColorMatrix in="b" type="matrix" values="0 0 0 0 0.08 0 0 0 0 0.72 0 0 0 0 0.65 0 0 0 1 0" result="c"/>
        <feMerge><feMergeNode in="c"/><feMergeNode in="SourceGraphic"/></feMerge>
      </filter>
      <filter id="gf-green" x="-60%" y="-60%" width="220%" height="220%">
        <feGaussianBlur in="SourceGraphic" stdDeviation="4" result="b"/>
        <feColorMatrix in="b" type="matrix" values="0 0 0 0 0.13 0 0 0 0 0.77 0 0 0 0 0.37 0 0 0 1 0" result="c"/>
        <feMerge><feMergeNode in="c"/><feMergeNode in="SourceGraphic"/></feMerge>
      </filter>
      <filter id="gf-red"   x="-60%" y="-60%" width="220%" height="220%">
        <feGaussianBlur in="SourceGraphic" stdDeviation="8" result="b"/>
        <feColorMatrix in="b" type="matrix" values="0 0 0 0 0.94 0 0 0 0 0.27 0 0 0 0 0.27 0 0 0 1 0" result="c"/>
        <feMerge><feMergeNode in="c"/><feMergeNode in="SourceGraphic"/></feMerge>
      </filter>
      <marker id="mk-teal" markerWidth="6" markerHeight="6" refX="3" refY="5" orient="auto">
        <path d="M0,0 L6,0 L3,6 Z" fill="#14b8a6" opacity=".7"/>
      </marker>
      <marker id="mk-red" markerWidth="6" markerHeight="6" refX="3" refY="5" orient="auto">
        <path d="M0,0 L6,0 L3,6 Z" fill="#ef4444"/>
      </marker>
    </defs>
    <g id="topo-vp">
      <g id="topo-edges"></g>
      <g id="topo-nodes"></g>
    </g>
  </svg>

  <div id="topo-details">
    <div id="topo-details-head">
      <span id="det-title">Device Details</span>
      <button onclick="closeDetails()">&#10005;</button>
    </div>
    <div id="topo-details-body">
      <div style="text-align:center;color:#475569;padding:40px 0;font-size:11px">
        Click on a node to view<br>live device parameters.
      </div>
    </div>
  </div>

  <div id="topo-minimap">
    <button id="topo-minimap-close" onclick="document.getElementById('topo-minimap').style.display='none'">&#10005;</button>
    <svg id="topo-mm-svg" xmlns="http://www.w3.org/2000/svg"><g id="topo-mm-g"></g></svg>
    <div id="topo-minimap-vp"></div>
  </div>

  <div id="topo-statusbar">
    <span id="topo-status-msg">Initializing&#8230;</span>
    <span id="topo-status-time"></span>
  </div>
</div>
</div>

<script>
(function(){
'use strict';

/* Restore client checkbox state from localStorage */
var savedShowClients = localStorage.getItem('omada_topo_show_clients');
var clCheckbox = document.getElementById('topo-show-clients');
if (clCheckbox) {
  if (savedShowClients !== null) {
    clCheckbox.checked = (savedShowClients === 'true');
  }
  clCheckbox.addEventListener('change', function() {
    localStorage.setItem('omada_topo_show_clients', clCheckbox.checked);
  });
}

/* ─── Config ─────────────────────────────────── */
var HIDS = __RESOLVED_HOSTIDS__;
var C={gw:'#3b82f6',core:'#14b8a6',sw:'#14b8a6',ap_on:'#22c55e',ap_off:'#ef4444',cl:'#38bdf8',loop:'#ef4444',edge_ok:'#14b8a6',edge_lp:'#ef4444'};
var RADIUS={gw:32,core:30,sw:26,ap:22,cl:7};

var LEVEL_Y_STEP = 170;
var BASE_Y = 80;
var SW_SPACING_X = 180;
var AP_SPACING_X = 120;

/* ─── State ──────────────────────────────────── */
var GN=[], GE=[];
var DEV={switches:[],eaps:[],lldp_neighbors:{},active_loops:0,loop_status_text:'',clients:[]};
var TX={x:30,y:0,s:1};
var panning=false,ps=null,pts=null;
var BB={mnX:0,mnY:0,mxX:1000,mxY:600};
var selId=null, poll=null;

/* ─── Helpers ────────────────────────────────── */
function $(id){return document.getElementById(id);}
function mkSvg(tag,attr){
  var el=document.createElementNS('http://www.w3.org/2000/svg',tag);
  for(var k in attr) el.setAttribute(k,attr[k]);
  return el;
}
function nm(m){return (m||'').toUpperCase().replace(/[^A-F0-9]/g,'');}

/* ─── Fetch ──────────────────────────────────── */
function fetchData(first){
  if(!HIDS||!HIDS.length){
    $('topo-status-msg').textContent='No hosts selected.';
    return;
  }
  var p=new URLSearchParams({action:'omada.devices',hostid:HIDS[0]||0});
  fetch('zabbix.php?'+p.toString(),{headers:{'X-Requested-With':'XMLHttpRequest'}})
  .then(function(r){return r.json();})
  .then(function(j){
    if(!j){$('topo-status-msg').textContent='Error: No response';return;}
    if(j.status==='error'){$('topo-status-msg').textContent='Error: '+(j.error_message||j.error||'Unknown');return;}
    var allDevs=j.devices||[];
    DEV.switches=j.switches||allDevs.filter(function(d){return d.type==='switch';})||[];
    DEV.eaps=j.eaps||allDevs.filter(function(d){return d.type==='ap';})||[];
    DEV.lldp_neighbors=j.lldp_neighbors||{};
    DEV.active_loops=j.active_loops||0;
    DEV.loop_status_text=j.loop_status_text||'';
    DEV.clients=j.clients||[];
    updateInfo();
    buildGraph();
    renderGraph();
    if(first) fitAll();
    $('topo-status-msg').textContent=DEV.switches.length+' switch(es), '+DEV.eaps.length+' AP(s)'+(DEV.active_loops>0?' \u26a0 '+DEV.active_loops+' loop(s)':'') + (DEV.clients.length>0?' ['+DEV.clients.length+' clients]':'');
    $('topo-status-time').textContent='Updated: '+new Date().toLocaleTimeString();
  })
  .catch(function(e){$('topo-status-msg').textContent='Error: '+e.message;});
}

function updateInfo(){
  var total=DEV.switches.length+DEV.eaps.length;
  $('hi-total').textContent=total;
  $('hi-crit').textContent=DEV.active_loops>0?DEV.active_loops:0;
  $('hi-warn').textContent=DEV.eaps.filter(function(a){return a.status!==1;}).length;

  // Loop Banner
  var banner = $('topo-loop-banner');
  var bannerText = $('topo-loop-banner-text');
  if(DEV.active_loops > 0 && DEV.loop_status_text){
    banner.style.display = 'flex';
    bannerText.textContent = DEV.loop_status_text;
  } else {
    banner.style.display = 'none';
  }
}

/* ─── Build Graph (Multi-Tier Cascading Tree + No Overlap Clients) ────────────────────────────── */
function buildGraph(){
  GN=[]; GE=[];
  var showCl=$('topo-show-clients').checked;
  var lldp=DEV.lldp_neighbors;
  var sws=DEV.switches;
  var aps=DEV.eaps;
  var loopTxt=(DEV.loop_status_text||'').toLowerCase();
  var hasLoop=DEV.active_loops>0;

  /* Build switch maps by MAC and Name for robust LLDP matching */
  var adj={};
  var swByMac={};
  var swByName={};
  sws.forEach(function(sw){
    swByMac[nm(sw.mac)]=sw;
    if(sw.name) swByName[sw.name.trim().toLowerCase()]=sw;
  });

  Object.keys(lldp).forEach(function(swMac){
    var fn=nm(swMac);
    if(!adj[fn]) adj[fn]=[];
    var nbrs=lldp[swMac];
    if(!Array.isArray(nbrs)) return;
    nbrs.forEach(function(nb){
      var nbMac=nm(nb.neighborMac||nb.chassisId||'');
      var nbName=(nb.neighborName||nb.systemName||'').trim().toLowerCase();

      var targetSw=swByMac[nbMac] || swByName[nbName];
      if(!targetSw && nbName){
        Object.keys(swByName).forEach(function(sname){
          if(sname.includes(nbName) || nbName.includes(sname)){
            targetSw = swByName[sname];
          }
        });
      }

      if(targetSw){
        var tn=nm(targetSw.mac);
        if(tn !== fn){
          var dup=adj[fn].find(function(x){return x.to===tn;});
          if(!dup) adj[fn].push({to:tn,to_sw:targetSw,lp:nb.localPort||'',rp:nb.remotePort||''});
          if(!adj[tn]) adj[tn]=[];
          var rdup=adj[tn].find(function(x){return x.to===fn;});
          if(!rdup) adj[tn].push({to:fn,to_sw:swByMac[fn],lp:nb.remotePort||'',rp:nb.localPort||''});
        }
      }
    });
  });

  /* Find Core / Root switch */
  var coreMac=null, maxConn=-1;
  sws.forEach(function(sw){
    var mac=nm(sw.mac);
    var conn=(adj[mac]||[]).length;
    var isCore=/core|server.?room|uplink|main/i.test(sw.name);
    if(isCore&&!coreMac){coreMac=mac;}
    else if(!coreMac&&conn>maxConn){maxConn=conn;coreMac=mac;}
  });
  if(!coreMac&&sws.length) coreMac=nm(sws[0].mac);

  /* BFS depth levels for switches */
  var swLv={};
  var swParent={};
  if(coreMac){
    swLv[coreMac]=1;
    var q=[coreMac];
    while(q.length){
      var cur=q.shift();
      (adj[cur]||[]).forEach(function(e){
        if(!(e.to in swLv)){
          swLv[e.to]=swLv[cur]+1;
          swParent[e.to]=cur;
          q.push(e.to);
        }
      });
    }
  }
  sws.forEach(function(sw){
    var mac=nm(sw.mac);
    if(!(mac in swLv)) swLv[mac]=coreMac?2:1;
  });

  /* Map APs to parent switch via LLDP or Name matching */
  var apMap={};
  var apByName={};
  aps.forEach(function(a){
    apMap[nm(a.mac)]=a;
    if(a.name) apByName[a.name.trim().toLowerCase()]=a;
  });
  var apPar={};
  Object.keys(lldp).forEach(function(swMac){
    var fn=nm(swMac);
    if(!swByMac[fn]) return;
    var nbrs=lldp[swMac];
    if(!Array.isArray(nbrs)) return;
    nbrs.forEach(function(nb){
      var nbMac=nm(nb.neighborMac||nb.chassisId||'');
      var nbName=(nb.neighborName||nb.systemName||'').trim().toLowerCase();
      var targetAp=apMap[nbMac] || apByName[nbName];
      if(targetAp){
        var am=nm(targetAp.mac);
        if(!apPar[am]) apPar[am]=fn;
      }
    });
  });

  /* Distribute unassigned APs to non-core switches */
  var nonCoreSws=sws.filter(function(sw){return nm(sw.mac)!==coreMac;});
  var distSws=nonCoreSws.length?nonCoreSws:sws;
  var ai=0;
  aps.forEach(function(ap){
    var mac=nm(ap.mac);
    if(!apPar[mac]&&distSws.length){apPar[mac]=nm(distSws[ai%distSws.length].mac);ai++;}
  });

  function apsOfSw(mac){return aps.filter(function(a){return apPar[nm(a.mac)]===mac;});}

  /* Compute positions for cascading tree */
  var swX={}, swY={};
  var apX={}, apY={};

  var childrenOfSw = {};
  sws.forEach(function(sw){
    var mac = nm(sw.mac);
    var pMac = swParent[mac] || (mac === coreMac ? null : coreMac);
    if(pMac){
      if(!childrenOfSw[pMac]) childrenOfSw[pMac] = [];
      childrenOfSw[pMac].push(mac);
    }
  });

  // Assign Y based on depth level
  sws.forEach(function(sw){
    var mac = nm(sw.mac);
    var lv = swLv[mac] || 2;
    swY[mac] = BASE_Y + lv * LEVEL_Y_STEP;
  });

  // Post-order X placement
  var currX = 100;
  function layoutSubtree(swMac){
    var childSws = childrenOfSw[swMac] || [];
    var childAps = apsOfSw(swMac);
    var childXList = [];

    childSws.forEach(function(cMac){
      layoutSubtree(cMac);
      childXList.push(swX[cMac]);
    });

    // Place APs in a SINGLE clean horizontal row under parent switch to leave space for Clients below APs!
    var apStartY = (swY[swMac] || BASE_Y) + LEVEL_Y_STEP;
    childAps.forEach(function(ap, idx){
      var apMac = nm(ap.mac);
      var xPos = currX + idx * AP_SPACING_X;
      var yPos = apStartY;

      apX[apMac] = xPos;
      apY[apMac] = yPos;
      childXList.push(xPos);
    });

    if(childAps.length > 0){
      currX += childAps.length * AP_SPACING_X + 60;
    } else if(childSws.length === 0){
      swX[swMac] = currX;
      currX += SW_SPACING_X;
      childXList.push(swX[swMac]);
    }

    if(childXList.length > 0){
      var minX = Math.min.apply(null, childXList);
      var maxX = Math.max.apply(null, childXList);
      swX[swMac] = (minX + maxX) / 2;
    }
  }

  if(coreMac){
    layoutSubtree(coreMac);
  } else {
    sws.forEach(function(sw){
      var mac = nm(sw.mac);
      if(!swX[mac]) layoutSubtree(mac);
    });
  }

  var allXValues = Object.keys(swX).map(function(k){return swX[k];});
  var avgX = allXValues.length ? (allXValues.reduce(function(a,b){return a+b;},0)/allXValues.length) : 600;
  var gwX = coreMac ? swX[coreMac] : avgX;
  var gwY = BASE_Y;

  /* 1. Gateway Node (Top Level 0) */
  GN.push({id:'gw',type:'gw',label:'Central Gateway',sublabel:'',color:C.gw,radius:RADIUS.gw,x:gwX,y:gwY,data:null,isLoop:false,gf:'gf-blue'});

  /* 2. Core Switch Node (Level 1) */
  var coreSw=coreMac?swByMac[coreMac]:null;
  if(coreSw){
    var isCoreLoop=hasLoop&&loopTxt.includes(coreSw.name.toLowerCase());
    GN.push({id:'sw_'+coreMac,type:'core',label:coreSw.name,sublabel:isCoreLoop?'⚠ LOOP DETECTED':(coreSw.ip||''),color:isCoreLoop?C.ap_off:C.core,radius:RADIUS.core,x:swX[coreMac],y:swY[coreMac],data:coreSw,isLoop:isCoreLoop,gf:isCoreLoop?'gf-red':'gf-teal'});
    GE.push({from:'gw',to:'sw_'+coreMac,isLoop:isCoreLoop,label:isCoreLoop?'⚠ Loop':'',lp:'',rp:''});
  }

  /* 3. All Cascading Switches (Level 2, Level 3, etc.) */
  sws.forEach(function(sw){
    var mac=nm(sw.mac);
    if(mac===coreMac) return;
    var isSwLoop=hasLoop&&loopTxt.includes(sw.name.toLowerCase());
    var pMac=swParent[mac]||(coreMac?'sw_'+coreMac:'gw');
    if(!pMac.startsWith('sw_')&&pMac!=='gw') pMac='sw_'+pMac;

    GN.push({
      id:'sw_'+mac, type:'sw',
      label:sw.name,
      sublabel:isSwLoop?'⚠ LOOP DETECTED':(sw.ip||''),
      color:isSwLoop?C.ap_off:C.sw,
      radius:RADIUS.sw,
      x:swX[mac]||avgX, y:swY[mac]||(BASE_Y+LEVEL_Y_STEP*2),
      data:sw, isLoop:isSwLoop,
      gf:isSwLoop?'gf-red':'gf-teal'
    });

    var pNode=GN.find(function(n){return n.id===pMac;});
    var isEdgeLoop=isSwLoop || (pNode&&pNode.isLoop);
    var le=(adj[nm(pMac.replace('sw_',''))]||[]).find(function(e){return e.to===mac;})||{};

    GE.push({
      from:pMac, to:'sw_'+mac,
      isLoop:isEdgeLoop,
      label:isEdgeLoop?'⚠ LOOP':'',
      lp:le.lp||'', rp:le.rp||''
    });
  });

  /* 4. Access Points (AP Level) & Clients (Strict Zero Overlap) */
  aps.forEach(function(ap, apIdx){
    var apMac=nm(ap.mac);
    var pMac=apPar[apMac]||coreMac;
    var pNodeId=pMac?('sw_'+pMac):'gw';
    var pNode=GN.find(function(n){return n.id===pNodeId;})||GN.find(function(n){return n.id==='gw';});

    var online=ap.status===1;
    var isApLoop=hasLoop&&loopTxt.includes(ap.name.toLowerCase());
    var isErr=isApLoop||!online;
    var posX = apX[apMac] || (pNode?pNode.x:avgX);
    var posY = apY[apMac] || ((pNode?pNode.y:BASE_Y)+LEVEL_Y_STEP);

    GN.push({
      id:'ap_'+apMac, type:'ap',
      label:ap.name,
      sublabel:isApLoop?'⚠ LOOP ON LINK':(online?'Online':'OFFLINE - Link Down!'),
      color:isErr?C.ap_off:C.ap_on,
      radius:RADIUS.ap,
      x:posX, y:posY,
      data:ap, isLoop:isErr,
      gf:isErr?'gf-red':'gf-green'
    });

    var isEdgeLoop = isErr || (pNode&&pNode.isLoop);
    GE.push({
      from:pNodeId, to:'ap_'+apMac,
      isLoop:isEdgeLoop,
      label:isEdgeLoop?(isApLoop?'⚠ LOOP FAILURE!':''):'',
      lp:'', rp:''
    });

    /* Render Clients below AP with Robust MAC & Name Matching */
    if(showCl){
      var cls = DEV.clients.filter(function(cl){
        var cApMac = nm(cl.apMac || cl.ap_mac || cl.apMacStr || '');
        var cApName = (cl.apName || cl.ap_name || '').trim().toLowerCase();
        return (cApMac && cApMac === apMac) || (cApName && cApName === ap.name.trim().toLowerCase());
      });

      // If no specific AP match, distribute unassigned clients across online APs
      if(cls.length === 0 && DEV.clients.length > 0){
        var apCount = Math.max(1, aps.length);
        var clientsPerAp = Math.ceil(DEV.clients.length / apCount);
        var startIdx = apIdx * clientsPerAp;
        cls = DEV.clients.slice(startIdx, startIdx + clientsPerAp);
      }

      var clCount = Math.min(cls.length, 5);
      cls.slice(0, 5).forEach(function(cl, ci){
        var clMac = nm(cl.mac || cl.clientMac || '');
        var clX = posX + (ci - (clCount-1)/2) * 24;
        var clY = posY + 95; // Placed 95px clearly BELOW AP (No Overlap!)

        GN.push({
          id:'cl_'+apMac+'_'+ci, type:'cl',
          label:cl.name || cl.ip || (clMac ? clMac.slice(-6) : 'Client'),
          sublabel:'', color:C.cl, radius:RADIUS.cl,
          x:clX, y:clY, data:cl, isLoop:false, gf:''
        });
        GE.push({
          from:'ap_'+apMac, to:'cl_'+apMac+'_'+ci,
          isLoop:false, label:'', lp:'', rp:'', thin:true
        });
      });
    }
  });

  /* Bounding box */
  if(GN.length){
    var xs=GN.map(function(n){return n.x;}), ys=GN.map(function(n){return n.y;});
    BB={mnX:Math.min.apply(null,xs)-90,mnY:Math.min.apply(null,ys)-50,mxX:Math.max.apply(null,xs)+90,mxY:Math.max.apply(null,ys)+100};
  }
}

/* ─── Render ─────────────────────────────────── */
function renderGraph(){
  var eG=$('topo-edges'), nG=$('topo-nodes');
  eG.innerHTML=''; nG.innerHTML='';

  /* Draw Tree Edges (Vertical Cascading Branching) */
  GE.forEach(function(e){
    var fn=GN.find(function(n){return n.id===e.from;}), tn=GN.find(function(n){return n.id===e.to;});
    if(!fn||!tn) return;
    var x1=fn.x,y1=fn.y,x2=tn.x,y2=tn.y;
    var color=e.isLoop?C.loop:(e.thin?'rgba(56,189,248,.6)':C.edge_ok);
    var sw=e.thin?1.2:(e.isLoop?3.5:2);
    var dash=e.thin?'3 3':(e.isLoop?'7 4':'none');

    /* Vertical Tree Curve */
    var midY = y1 + (y2 - y1) * 0.5;
    var d = 'M' + x1 + ',' + y1 + ' C' + x1 + ',' + midY + ' ' + x2 + ',' + midY + ' ' + x2 + ',' + y2;

    var g=mkSvg('g');
    if(e.isLoop){
      var tp=mkSvg('path',{d:d,stroke:color,'stroke-width':sw+5,fill:'none',opacity:'.3','stroke-dasharray':dash});
      g.appendChild(tp);
    }
    var path=mkSvg('path',{d:d,stroke:color,'stroke-width':sw,fill:'none','stroke-dasharray':dash,'marker-end':e.thin?'':(e.isLoop?'url(#mk-red)':'url(#mk-teal)')});
    g.appendChild(path);
    if(e.label){
      var mx=(x1+x2)/2, my=(y1+y2)/2;
      var bg=mkSvg('rect',{x:mx-36,y:my-8,width:72,height:14,rx:3,fill:color,opacity:'.22'});
      var lb=mkSvg('text',{x:mx,y:my,class:'edge-label','dominant-baseline':'middle'});
      lb.textContent=e.label;
      g.appendChild(bg); g.appendChild(lb);
    }
    if(e.lp){
      var pl=mkSvg('text',{x:x1+8,y:y1+14,fill:'#475569','font-size':'8','font-family':'Inter,sans-serif'});
      pl.textContent='p'+e.lp;
      g.appendChild(pl);
    }
    eG.appendChild(g);
  });

  /* Draw nodes */
  GN.forEach(function(node){
    var g=mkSvg('g',{class:'topo-node'+(node.isLoop?' loop-node':''),transform:'translate('+node.x+','+node.y+')','data-id':node.id});
    g.addEventListener('click',function(){showDetails(node);});
    var r=node.radius;

    if(node.type==='cl'){
      var c=mkSvg('circle',{r:r,fill:C.cl,opacity:'.9',stroke:'#071422','stroke-width':'1'});
      var lb=mkSvg('text',{x:0,y:r+11,fill:'#93c5fd','font-size':'8.5','font-family':'Inter,sans-serif','text-anchor':'middle'});
      lb.textContent=node.label.slice(0,12);
      g.appendChild(c); g.appendChild(lb);
    } else {
      /* Outer ring */
      var ring=mkSvg('circle',{class:'node-ring',r:r+6,fill:'none',stroke:node.color,'stroke-width':'1.8',opacity:'.4',filter:'url(#'+node.gf+')'});
      /* Main circle */
      var bgFill=node.type==='gw'?'rgba(59,130,246,.25)':(node.isLoop?'rgba(239,68,68,.3)':(node.type==='ap'?'rgba(34,197,94,.2)':'rgba(20,184,166,.2)'));
      var bg=mkSvg('circle',{class:'node-bg',r:r,fill:bgFill,stroke:node.color,'stroke-width':'2.5',filter:'url(#'+node.gf+')'});
      g.appendChild(ring); g.appendChild(bg);
      /* Icon */
      var ig=mkSvg('g',{fill:node.color,'class':'node-bg'});
      if(node.type==='gw')   ig.innerHTML=iconGw();
      else if(node.type==='core'||node.type==='sw') ig.innerHTML=iconSw();
      else if(node.type==='ap') ig.innerHTML=iconAp(node.isLoop);
      g.appendChild(ig);
      /* Label */
      var lblY=r+13;
      var lbl=mkSvg('text',{x:0,y:lblY,class:'node-label'});
      var words=String(node.label).split(' ');
      if(words.length>2&&node.label.length>14){
        var mid=Math.ceil(words.length/2);
        var s1=mkSvg('tspan',{x:0,dy:'0'}); s1.textContent=words.slice(0,mid).join(' ');
        var s2=mkSvg('tspan',{x:0,dy:'11'}); s2.textContent=words.slice(mid).join(' ');
        lbl.appendChild(s1); lbl.appendChild(s2);
        lblY+=11;
      } else {
        lbl.textContent=node.label;
      }
      g.appendChild(lbl);
      if(node.sublabel){
        var sub=mkSvg('text',{x:0,y:lblY+13,class:node.isLoop?'node-sublabel-err':'node-sublabel'});
        sub.textContent=node.sublabel;
        g.appendChild(sub);
      }
    }
    nG.appendChild(g);
  });

  updateMinimap();
}

/* ─── Icons ──────────────────────────────────── */
function iconGw(){
  return '<ellipse rx="14" ry="9" cy="-3" fill="none" stroke="currentColor" stroke-width="2" opacity=".85"/>'+
         '<path d="M-14,-3 C-14,-14 14,-14 14,-3" fill="rgba(59,130,246,.3)" stroke="currentColor" stroke-width="1.5"/>'+
         '<line x1="0" y1="-3" x2="0" y2="12" stroke="currentColor" stroke-width="2"/>'+
         '<ellipse cx="0" cy="12" rx="6" ry="3.5" fill="currentColor" opacity=".8"/>'+
         '<circle cx="0" cy="-9" r="3" fill="currentColor"/>'+
         '<line x1="-8" y1="-14" x2="8" y2="-14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>'+
         '<line x1="-13" y1="-18" x2="13" y2="-18" stroke="currentColor" stroke-width="1" stroke-linecap="round" opacity=".5"/>';
}
function iconSw(){
  return '<rect x="-16" y="-10" width="32" height="11" rx="3" fill="currentColor" opacity=".8"/>'+
         '<rect x="-16" y="2" width="32" height="9" rx="2" fill="currentColor" opacity=".45"/>'+
         '<circle cx="-10" cy="-4.5" r="2" fill="#071422"/>'+
         '<circle cx="-4.5" cy="-4.5" r="2" fill="#071422"/>'+
         '<circle cx="1"  cy="-4.5" r="2" fill="#071422"/>'+
         '<circle cx="6.5" cy="-4.5" r="2" fill="#071422"/>'+
         '<circle cx="12" cy="-4.5" r="2" fill="#071422"/>'+
         '<rect x="10" y="3" width="4.5" height="5.5" rx="1" fill="#071422"/>';
}
function iconAp(isOff){
  var op=isOff?'opacity=".6"':'';
  return '<circle cy="9" r="4" fill="currentColor" '+op+'/>'+
         '<path d="M-8,2 A10,8 0 0,1 8,2" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" '+op+'/>'+
         '<path d="M-14,-4 A17,14 0 0,1 14,-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" '+op+'/>'+
         '<path d="M-20,-10 A24,20 0 0,1 20,-10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" opacity=".5"/>';
}

/* ─── Details Panel ──────────────────────────── */
function showDetails(node){
  selId=node.id;
  var panel=$('topo-details'), body=$('topo-details-body');
  panel.classList.add('open');
  $('det-title').textContent=node.label;
  if(node.type==='gw'){
    body.innerHTML=drow('Name','Aviyaan Gateway')+drow('Status','<span class="det-status-ok">Online</span>')+
      drow('Loops',DEV.active_loops>0?'<span class="det-status-err">'+DEV.active_loops+' Loop(s)</span>':'None');
    return;
  }
  var d=node.data; if(!d) return;
  var html='';
  if(node.type==='core'||node.type==='sw'){
    html+=drow('Type',node.type==='core'?'Core Switch':'Access Switch');
    html+=drow('IP',d.ip||'\u2013');
    html+=drow('MAC',(d.mac||'').toUpperCase());
    html+=drow('Model',d.model||'\u2013');
    html+=drow('Status',d.status===1?'<span class="det-status-ok">Online</span>':'<span class="det-status-err">Offline</span>');
    html+=drow('Uptime',d.uptime||'\u2013');
    html+=drow('CPU',d.cpu!==undefined?d.cpu+'%':'\u2013');
    html+=drow('Memory',d.memory!==undefined?d.memory+'%':'\u2013');
    if(node.isLoop) html+='<div class="det-loop-box">\u26a0 LOOP DETECTED<br>'+DEV.loop_status_text+'</div>';
  } else if(node.type==='ap'){
    html+=drow('IP',d.ip||'\u2013');
    html+=drow('MAC',(d.mac||'').toUpperCase());
    html+=drow('Model',d.model||'\u2013');
    html+=drow('Status',d.status===1?'<span class="det-status-ok">Online</span>':'<span class="det-status-err">OFFLINE \u2013 Link Down!</span>');
    html+=drow('Uptime',d.uptime||'\u2013');
    html+=drow('CPU',d.cpu!==undefined?d.cpu+'%':'\u2013');
    html+=drow('2.4G Ch.Util',d.channel_util_2g>=0?d.channel_util_2g+'%':'N/A');
    html+=drow('5G Ch.Util',d.channel_util_5g>=0?d.channel_util_5g+'%':'N/A');
    html+=drow('2.4G Interf.',d.interference_2g>=0?d.interference_2g+'%':'N/A');
    html+=drow('5G Interf.',d.interference_5g>=0?d.interference_5g+'%':'N/A');
    html+=drow('2.4G TX',d.tx_power_2g>=0?d.tx_power_2g+' dBm':'N/A');
    html+=drow('5G TX',d.tx_power_5g>=0?d.tx_power_5g+' dBm':'N/A');
    if(node.isLoop) html+='<div class="det-loop-box">\u26a0 LOOP ON LINK<br>'+DEV.loop_status_text+'</div>';
  } else if(node.type==='cl'){
    html+=drow('IP',d.ip||'\u2013');
    html+=drow('MAC',(d.mac||'').toUpperCase());
    html+=drow('Wireless',d.wireless?'Yes (Wi-Fi)':'No (Wired)');
    if(d.wireless){html+=drow('SSID',d.ssid||'\u2013');html+=drow('RSSI',d.rssi!=null?d.rssi+' dBm':'\u2013');}
    html+=drow('Parent AP',d.apName||'\u2013');
  }
  body.innerHTML=html;
}
function drow(k,v){return '<div class="det-row"><span class="det-key">'+k+':</span><span class="det-val">'+v+'</span></div>';}
function closeDetails(){$('topo-details').classList.remove('open');selId=null;}

/* ─── Transform (Auto-Fit 1-Page Optimization) ──────────────────────────── */
function applyTx(){
  $('topo-vp').setAttribute('transform','translate('+TX.x+','+TX.y+') scale('+TX.s+')');
  updateMinimap();
}
function fitAll(){
  var card=$('topo-card');
  var cw=card.clientWidth, ch=card.clientHeight-42-22;
  var bw=BB.mxX-BB.mnX||1, bh=BB.mxY-BB.mnY||1;
  var sc=Math.min((cw-40)/bw,(ch-40)/bh,1.25);
  TX.s=sc;
  TX.x=(cw-bw*sc)/2-BB.mnX*sc;
  TX.y=(ch-bh*sc)/2-BB.mnY*sc+15;
  applyTx();
}
$('btn-zi').addEventListener('click',function(){TX.s=Math.min(3,TX.s*1.2);applyTx();});
$('btn-zo').addEventListener('click',function(){TX.s=Math.max(.1,TX.s/1.2);applyTx();});
$('btn-fit').addEventListener('click',fitAll);
$('btn-sel').addEventListener('click',function(){
  if(!selId) return;
  var n=GN.find(function(x){return x.id===selId;});
  if(!n) return;
  var card=$('topo-card');
  TX.s=1.6; TX.x=card.clientWidth/2-n.x*TX.s; TX.y=card.clientHeight/2-n.y*TX.s;
  applyTx();
});
$('btn-exp').addEventListener('click',function(){
  var svg=$('topo-svg');
  var blob=new Blob([svg.outerHTML],{type:'image/svg+xml'});
  var a=document.createElement('a');
  a.href=URL.createObjectURL(blob);
  a.download='network-topology-cascading-tree.svg';
  a.click();
});

/* Pan */
var svg=$('topo-svg');
svg.addEventListener('mousedown',function(e){
  if(e.target.closest('.topo-node')) return;
  panning=true; ps={x:e.clientX,y:e.clientY}; pts={...TX};
});
window.addEventListener('mousemove',function(e){
  if(!panning) return;
  TX.x=pts.x+(e.clientX-ps.x); TX.y=pts.y+(e.clientY-ps.y);
  applyTx();
});
window.addEventListener('mouseup',function(){panning=false;});
svg.addEventListener('wheel',function(e){
  e.preventDefault();
  var rect=svg.getBoundingClientRect();
  var mx=e.clientX-rect.left, my=e.clientY-rect.top;
  var f=e.deltaY<0?1.12:.89;
  var ns=Math.max(.05,Math.min(4,TX.s*f));
  TX.x=mx-(mx-TX.x)*(ns/TX.s); TX.y=my-(my-TX.y)*(ns/TX.s);
  TX.s=ns; applyTx();
},{passive:false});

/* ─── Minimap ────────────────────────────────── */
function updateMinimap(){
  var mmG=$('topo-mm-g');
  var mmW=170,mmH=110;
  var bw=BB.mxX-BB.mnX||1,bh=BB.mxY-BB.mnY||1;
  var sc=Math.min(mmW/bw,mmH/bh)*0.88;
  var ox=(mmW-bw*sc)/2-BB.mnX*sc;
  var oy=(mmH-bh*sc)/2-BB.mnY*sc;
  mmG.innerHTML='';
  GE.forEach(function(e){
    var fn=GN.find(function(n){return n.id===e.from;}),tn=GN.find(function(n){return n.id===e.to;});
    if(!fn||!tn) return;
    var ln=mkSvg('line',{x1:fn.x*sc+ox,y1:fn.y*sc+oy,x2:tn.x*sc+ox,y2:tn.y*sc+oy,stroke:e.isLoop?'#ef4444':'#14b8a6','stroke-width':e.thin?0.4:0.9,opacity:'0.7'});
    mmG.appendChild(ln);
  });
  GN.forEach(function(n){
    var c=mkSvg('circle',{cx:n.x*sc+ox,cy:n.y*sc+oy,r:n.type==='cl'?1:2.5,fill:n.color});
    mmG.appendChild(c);
  });
  var card=$('topo-card');
  var vw=(card.clientWidth/TX.s)*sc, vh=(card.clientHeight/TX.s)*sc;
  var vx=(-TX.x/TX.s)*sc+ox, vy=(-TX.y/TX.s)*sc+oy;
  var vp=$('topo-minimap-vp');
  vp.style.left=vx+'px'; vp.style.top=vy+'px';
  vp.style.width=vw+'px'; vp.style.height=vh+'px';
}

/* ─── Filter toggle ──────────────────────────── */
function topoToggleFilter(){
  var b=$('topo-filter-body'),a=$('topo-arr');
  if(b.style.display==='none'){b.style.display='';a.style.transform='rotate(90deg)';}
  else{b.style.display='none';a.style.transform='rotate(0deg)';}
}
window.topoToggleFilter=topoToggleFilter;
window.closeDetails=closeDetails;

/* ─── Init ───────────────────────────────────── */
$('topo-show-clients').addEventListener('change',function(){
  localStorage.setItem('omada_topo_show_clients', $('topo-show-clients').checked);
  buildGraph();
  renderGraph();
  fitAll();
});

fetchData(true);
poll=setInterval(function(){fetchData(false);},5000);
window.addEventListener('beforeunload',function(){clearInterval(poll);});
window.addEventListener('resize',function(){fitAll();});

})();
</script>
TOPO;

$html = str_replace(
	['__RESOLVED_HOSTIDS__', '__GROUPS_HTML__', '__HOSTS_HTML__'],
	[$resolved_json, $groups_html, $hosts_html],
	$html
);

$html_page->addItem(new CHtmlEntity($html));
$html_page->show();
