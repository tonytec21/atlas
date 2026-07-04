<?php
require_once __DIR__ . '/session_check.php';
checkSession();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Diagnóstico Assinador SERPRO · Atlas</title>
<style>
    body{ font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif; background:#0f172a; color:#e2e8f0;
        margin:0; padding:24px; }
    .wrap{ max-width:1000px; margin:0 auto; }
    h1{ font-size:1.25rem; margin:0 0 4px; }
    .sub{ color:#94a3b8; font-size:.9rem; margin-bottom:18px; }
    .status{ display:inline-flex; align-items:center; gap:8px; padding:6px 12px; border-radius:999px;
        font-weight:600; font-size:.85rem; background:#334155; }
    .status .dot{ width:10px;height:10px;border-radius:50%; background:#eab308; }
    .status.on .dot{ background:#22c55e; } .status.off .dot{ background:#ef4444; }
    .btns{ display:flex; flex-wrap:wrap; gap:10px; margin:18px 0; }
    button{ border:0; border-radius:10px; padding:11px 16px; font-weight:700; cursor:pointer;
        background:#4f46e5; color:#fff; font-size:.9rem; }
    button:hover{ background:#4338ca; } button:disabled{ opacity:.5; cursor:not-allowed; }
    button.alt{ background:#334155; } button.alt:hover{ background:#475569; }
    button.copy{ background:#0891b2; }
    pre{ background:#020617; border:1px solid #1e293b; border-radius:12px; padding:16px;
        white-space:pre-wrap; word-break:break-word; font-size:.82rem; line-height:1.5; max-height:60vh; overflow:auto; }
    .hint{ color:#94a3b8; font-size:.82rem; margin-top:6px; }
    a{ color:#818cf8; }
</style>
</head>
<body>
<div class="wrap">
    <h1>Diagnóstico do Assinador SERPRO</h1>
    <div class="sub">
        Rode esta página numa máquina com o <b>token/cartão A3 conectado</b> e o Assinador aberto/autorizado.
        Clique nos botões na ordem e, ao final, use <b>Copiar log</b> e me envie o resultado.
    </div>

    <div id="status" class="status"><span class="dot"></span><span id="statusText">Verificando…</span></div>

    <div class="btns">
        <button id="btnList" disabled>1) Listar comandos (list)</button>
        <button id="btnCert" class="alt" disabled>2) Buscar certificado</button>
        <button id="btnHash" class="alt" disabled>3) Testar sign('hash')</button>
        <button id="btnText" class="alt" disabled>4) Testar sign('text')</button>
        <button id="btnVerdict" disabled>5) Veredito do modo hash</button>
        <button id="btnCopy" class="copy">Copiar log</button>
        <button id="btnClear" class="alt">Limpar</button>
    </div>
    <div class="hint">
        O teste 3/4 vai pedir o PIN do certificado — é esperado. Nenhum documento real é assinado aqui;
        são apenas dados de teste para descobrirmos o formato de resposta do seu Assinador.
    </div>

    <pre id="log">— aguardando —</pre>
</div>

<script src="serpro/serpro-signer-promise.js"></script>
<script src="serpro/serpro-signer-client.js"></script>
<script>
(function(){
    "use strict";
    var C = window.SerproSignerClient;
    var online = false;
    var logEl = document.getElementById('log');
    var buf = [];

    function ts(){ var d=new Date(); return d.toLocaleTimeString(); }
    function log(title, obj){
        var line = '[' + ts() + '] ' + title;
        if (obj !== undefined) {
            var body;
            try { body = JSON.stringify(obj, null, 2); }
            catch(e){ body = String(obj); }
            line += '\n' + body;
        }
        buf.push(line);
        logEl.textContent = buf.join('\n\n');
        logEl.scrollTop = logEl.scrollHeight;
    }
    function setStatus(state, txt){
        var s = document.getElementById('status');
        s.className = 'status ' + (state||'');
        document.getElementById('statusText').textContent = txt;
        var dis = state !== 'on';
        ['btnList','btnCert','btnHash','btnText','btnVerdict'].forEach(function(id){ document.getElementById(id).disabled = dis; });
    }

    // ---- Conexão ----
    function verifyAndConnect(){
        C.verifyIsInstalledAndRunning()
         .success(function(){ connect(); })
         .error(function(){ setStatus('off','Assinador não está em execução'); setTimeout(verifyAndConnect, 3000); });
    }
    function connect(){
        try { C.connect(
            function(){ online=true; setStatus('on','Assinador conectado'); log('CONNECT ok'); },
            function(){ online=false; setStatus('off','Conexão encerrada'); setTimeout(verifyAndConnect,3000); },
            function(e){ online=false; setStatus('','Autorização pendente (aceite o certificado em http://127.0.0.1:65056/)'); log('CONNECT error', e); }
        ); } catch(e){ log('CONNECT exception', String(e)); }
    }

    // ---- Utils ----
    function b64FromBytes(bytes){
        var bin=''; for (var i=0;i<bytes.length;i++) bin+=String.fromCharCode(bytes[i]);
        return btoa(bin);
    }
    async function sha256b64(text){
        var enc = new TextEncoder().encode(text);
        var digest = await crypto.subtle.digest('SHA-256', enc);
        return b64FromBytes(new Uint8Array(digest));
    }

    // ---- 1) list ----
    document.getElementById('btnList').addEventListener('click', function(){
        log('LIST → enviando {command:"list"}');
        try {
            C.list().success(function(r){ log('LIST success', r); })
                    .error(function(e){ log('LIST error', e); });
        } catch(e){ log('LIST exception', String(e)); }
    });

    // ---- 2) certificado (tenta comandos comuns via execute) ----
    document.getElementById('btnCert').addEventListener('click', function(){
        var tries = ['certificates','certificate','certificateList','listCertificates','getCertificate','info','status'];
        tries.forEach(function(cmd){
            try {
                C.execute({ command: cmd })
                 .success(function(r){ log('execute {command:"'+cmd+'"} SUCCESS', r); })
                 .error(function(e){ log('execute {command:"'+cmd+'"} error', e); });
            } catch(e){ log('execute {command:"'+cmd+'"} exception', String(e)); }
        });
    });

    // ---- 3) sign hash ----
    document.getElementById('btnHash').addEventListener('click', async function(){
        var hashB64 = await sha256b64('atlas-diagnostico-' + Date.now());
        log('SIGN hash → sign("hash", <SHA-256 base64>)', { inputData: hashB64 });
        try {
            C.sign('hash', hashB64).success(function(r){
                log('SIGN hash SUCCESS (chaves: ' + Object.keys(r||{}).join(', ') + ')', r);
            }).error(function(e){ log('SIGN hash error', e); });
        } catch(e){ log('SIGN hash exception', String(e)); }
    });

    // ---- 4) sign text (referência) ----
    document.getElementById('btnText').addEventListener('click', function(){
        log('SIGN text → sign("text", "atlas-teste")');
        try {
            C.sign('text', 'atlas-teste').success(function(r){
                log('SIGN text SUCCESS (chaves: ' + Object.keys(r||{}).join(', ') + ')', r);
            }).error(function(e){ log('SIGN text error', e); });
        } catch(e){ log('SIGN text exception', String(e)); }
    });

    // ---- 5) VEREDITO do modo hash ----
    function toHex(bytes){ var s=''; for (var i=0;i<bytes.length;i++){ s += ('0'+bytes[i].toString(16)).slice(-2); } return s; }
    function b64ToBytes(b64){ var bin=atob(b64); var a=new Uint8Array(bin.length); for(var i=0;i<bin.length;i++)a[i]=bin.charCodeAt(i); return a; }
    // procura o atributo messageDigest (OID 1.2.840.113549.1.9.4) e devolve os 32 bytes
    function findMessageDigest(der){
        var pat=[0x06,0x09,0x2A,0x86,0x48,0x86,0xF7,0x0D,0x01,0x09,0x04];
        for (var i=0;i+pat.length+36<=der.length;i++){
            var ok=true; for (var j=0;j<pat.length;j++){ if(der[i+j]!==pat[j]){ok=false;break;} }
            if(!ok) continue;
            for (var m=i+pat.length; m<i+pat.length+8 && m+2+32<=der.length; m++){
                if(der[m]===0x04 && der[m+1]===0x20){ return der.slice(m+2, m+2+32); }
            }
        }
        return null;
    }
    document.getElementById('btnVerdict').addEventListener('click', async function(){
        var H = new Uint8Array(32); crypto.getRandomValues(H);
        var Hb64 = b64FromBytes(H);
        var shaH = new Uint8Array(await crypto.subtle.digest('SHA-256', H));
        log('VEREDITO → sign("hash", base64(H))', { H: toHex(H), 'SHA256(H)': toHex(shaH) });
        try {
            C.sign('hash', Hb64).success(function(r){
                try {
                    if (!r || !r.signature){ log('VEREDITO: resposta sem signature', r); return; }
                    var der = b64ToBytes(r.signature);
                    var md = findMessageDigest(der);
                    if (!md){ log('VEREDITO: messageDigest não localizado no CMS (envie o log do sign hash bruto).'); return; }
                    var mdHex = toHex(md), Hhex = toHex(H), sHex = toHex(shaH);
                    var verdict;
                    if (mdHex === Hhex) verdict = 'DIGESTO  → o Assinador usa o hash como está. No fluxo, ENVIAR o SHA-256 do ByteRange.';
                    else if (mdHex === sHex) verdict = 'CONTEUDO → o Assinador re-hasheia a entrada. No fluxo, ENVIAR o ByteRange inteiro.';
                    else verdict = 'INDETERMINADO (nenhum bate)';
                    log('>>> VEREDITO DO MODO HASH: ' + verdict, {
                        messageDigest_no_CMS: mdHex,
                        input_H: Hhex,
                        SHA256_input: sHex,
                        cert_subject: (r.by && r.by.subject) || null,
                        cms_bytes: der.length
                    });
                } catch(e){ log('VEREDITO erro ao processar', String(e)); }
            }).error(function(e){ log('VEREDITO error', e); });
        } catch(e){ log('VEREDITO exception', String(e)); }
    });

    // ---- copiar / limpar ----
    document.getElementById('btnCopy').addEventListener('click', function(){
        var t = buf.join('\n\n');
        navigator.clipboard.writeText(t).then(function(){ alert('Log copiado!'); },
            function(){ // fallback
                var ta=document.createElement('textarea'); ta.value=t; document.body.appendChild(ta);
                ta.select(); document.execCommand('copy'); document.body.removeChild(ta); alert('Log copiado!');
            });
    });
    document.getElementById('btnClear').addEventListener('click', function(){ buf=[]; logEl.textContent='— limpo —'; });

    setStatus('','Verificando o Assinador…');
    verifyAndConnect();
})();
</script>
</body>
</html>
