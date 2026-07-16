# -*- coding: utf-8 -*-
"""Arnês de regressão do Vertex: um caso de cada situação real já enviada.
   Espelha os regex/lógica do index.php para validar cobertura sem PHP."""
import re, math

def norm(t):
    return (t.replace('\u00ba','°').replace('\u00b0','°')
             .replace('\u2019',"'").replace('\u2018',"'").replace('\u00b4',"'").replace('\u2032',"'")
             .replace('\u201c','"').replace('\u201d','"').replace('\u2033','"').replace("''",'"'))

def dms(deg,mi,se):
    neg='-' in str(deg); d=abs(float(re.sub(r'[^\d.,-]','',str(deg)).replace(',','.')))
    m=float(str(mi).replace(',','.') or 0); s=float(str(se).replace(',','.') or 0)
    v=d+m/60+s/3600; return -v if neg else v
def brnum(s):
    s=str(s); pc=s.rfind(','); pd=s.rfind('.'); p=max(pc,pd)
    if p>=0: return float((re.sub(r'\D','',s[:p]) or '0')+'.'+(re.sub(r'\D','',s[p+1:]) or '0'))
    return float(re.sub(r'\D','',s) or 0)
def numUTM(raw):
    if ',' in raw:
        i,_,d=raw.rpartition(','); return float((re.sub(r'\D','',i) or '0')+'.'+(re.sub(r'\D','',d) or '0'))
    if re.fullmatch(r'\d{1,3}(\.\d{3})+',raw): return float(re.sub(r'\D','',raw))
    if raw.count('.')==1:
        i,_,d=raw.rpartition('.'); return float((re.sub(r'\D','',i) or '0')+'.'+(re.sub(r'\D','',d) or '0'))
    return float(re.sub(r'\D','',raw) or 0)

# --- GMS rotulado (extractByLabel) — hemisfério [NSLOW]e?(?![A-Za-z]) ---
def bylabel(t,lab):
    t=norm(t); out=[]
    rx=re.compile(lab+r"(?:itude)?\s*[:.]?\s*(-?\s*\d+)\s*°\s*(\d+)\s*'\s*([\d.,]+)\s*\"(?:\s*([NSLOW])e?(?![A-Za-z]))?",re.I)
    for x in rx.finditer(t):
        v=dms(x.group(1),x.group(2),x.group(3)); hem=(x.group(4) or '').upper()
        if hem in('S','O','W'): v=-abs(v)
        elif hem in('N','L'): v=abs(v)
        mn=float(x.group(2)); sc=float(x.group(3).replace(',','.'))
        out.append((v, mn<60 and sc<60))
    return out
def gms_labeled(t):
    lo=bylabel(t,'long'); la=bylabel(t,'lat'); n=min(len(lo),len(la))
    return [(la[i][0],lo[i][0]) for i in range(n) if -90<=la[i][0]<=90 and -180<=lo[i][0]<=180]

# --- GMS tabela s/ rótulo (extractGeoCoordinatesTabela) ---
def gms_tabela(t):
    t=norm(t); lo=[];la=[]
    rx=re.compile(r"(-?\s*\d+)\s*°\s*(\d+)\s*'\s*([\d.,]+)\s*\"(?:\s*([NSLOW])e?(?![A-Za-z]))?",re.I)
    for x in rx.finditer(t):
        minus='-' in x.group(1); hem=(x.group(4) or '').upper()
        if not minus and hem=='': continue
        v=dms(x.group(1),x.group(2),x.group(3))
        if hem in('S','O','W'): v=-abs(v)
        elif hem in('N','L'): v=abs(v)
        deg=abs(float(re.sub(r'[^\d]','',x.group(1))))
        ehlon = True if hem in('L','O','W') else (False if hem in('N','S') else deg>=20)
        (lo if ehlon else la).append(v)
    n=min(len(lo),len(la)); return [(la[i],lo[i]) for i in range(n)]

# --- UTM E/N rotulado (extractUTMCoordinates) ---
def utm_en(t):
    t=norm(t); toks=[]
    rx=re.compile(r'(?<![^\W\d_])([NE])\s*=?\s*(\d{1,3}(?:\.\d{3})+(?:,\d+)?|\d+(?:[.,]\d+)?)\s*m?(?!\d)')
    for x in rx.finditer(t):
        v=brnum(x.group(2)); ip=int(abs(v))
        if x.group(1)=='N' and 1_000_000<=ip<=99_999_999: toks.append(('N',v))
        elif x.group(1)=='E' and 100_000<=ip<=999_999: toks.append(('E',v))
    return pares(toks)

# --- UTM tabela topográfica (extractUTMTabelaSimples) ---
def utm_tabela(t):
    t=norm(t); toks=[]
    sh=r'\d{1,2}\.\d{3}\.\d{3}(?:,\d+)?|\d{7,8}(?:[.,]\d+)?|\d{3}\.\d{3}(?:,\d+)?|\d{6}(?:[.,]\d+)?'
    rx=re.compile(r'(?<![\d.,])('+sh+r')(?![\d.,]*\d)')
    for x in rx.finditer(t):
        raw=x.group(1)
        if raw[0]=='0': continue
        v=numUTM(raw); ip=int(abs(v))
        if 1_000_000<=ip<=10_000_000: toks.append(('N',v))
        elif 100_000<=ip<=999_999: toks.append(('E',v))
    return pares(toks)

def pares(toks):
    out=[];pN=None;pE=None
    for k,v in toks:
        if k=='N':pN=v
        else:pE=v
        if pN is not None and pE is not None: out.append((pN,pE));pN=None;pE=None
    return out
def utm2geo(E,N,zone=23,south=True):
    a=6378137.0;f=1/298.257223563;e2=f*(2-f);k0=0.9996
    x=E-500000.0;y=N-(10000000.0 if south else 0)
    e1=(1-math.sqrt(1-e2))/(1+math.sqrt(1-e2));M=y/k0
    mu=M/(a*(1-e2/4-3*e2**2/64-5*e2**3/256))
    phi1=mu+(3*e1/2-27*e1**3/32)*math.sin(2*mu)+(21*e1**2/16-55*e1**4/32)*math.sin(4*mu)+(151*e1**3/96)*math.sin(6*mu)
    N1=a/math.sqrt(1-e2*math.sin(phi1)**2);T1=math.tan(phi1)**2;ep2=e2/(1-e2)
    C1=ep2*math.cos(phi1)**2;R1=a*(1-e2)/((1-e2*math.sin(phi1)**2)**1.5);D=x/(N1*k0)
    lat=phi1-(N1*math.tan(phi1)/R1)*(D*D/2-(5+3*T1+10*C1-4*C1*C1-9*ep2)*D**4/24)
    lon=(D-(1+2*T1+C1)*D**3/6+(5-2*C1+28*T1)*D**5/120)/math.cos(phi1)
    return math.degrees(lat), math.degrees(math.radians((zone-1)*6-180+3)+lon)

def no_brasil(pts): return bool(pts) and all(-34<=la<=6 and -74<=lo<=-34 for la,lo in pts)
def em(pts, la0,la1,lo0,lo1): return bool(pts) and all(la0<=la<=la1 and lo0<=lo<=lo1 for la,lo in pts)

# ============ CASOS ============
casos=[]
def caso(nome, ok, detalhe=''): casos.append((nome, ok, detalhe)); 

# 1) GMS rotulado com sinal '-' + "e Altitude" (Fazenda Matança)
p=gms_labeled('(Longitude: -45°37\'17,183", Latitude: -07°08\'36,589" e Altitude: 262,52 m) (Longitude: -45°36\'47,148", Latitude: -07°09\'06,951" e Altitude: 246,95 m) (Longitude: -45°37\'26,084", Latitude: -07°09\'31,857" e Altitude: 257,20 m)')
caso('GMS rotulado + "e Altitude" (S.R.Mangabeiras)', em(p,-8,-6,-46,-45), f'{len(p)} vért, {p[0] if p else "-"}')

# 2) GMS S/W sem sinal + "Se" grudado (825 ha S.Mateus)
p=gms_labeled('Latitude: 3°52\'39,7892" S e Longitude: 44°35\'00,6783" W  Latitude: 3°52\'41,2131" Se Longitude: 44°34\'35,0669" W  Latitude: 3°53\'03,6254" S e Longitude: 44°33\'32,4953" W')
caso('GMS S/W + "Se" grudado (S.Mateus)', em(p,-4,-3,-45,-44), f'{len(p)} vért')

# 3) GMS "LONG" colado + segundos inválidos 80->08 (planta urbana)
p=gms_labeled('P-1 LAT. -04°02\'29,07" LONG. -44°28\'08,80" P-2 LAT. -04°02\'28,82" LONG. -44°28\'08,72" P-3 LAT. -04°02\'28,22" LONG. -44°28\'10,34"')
caso('GMS "LONG" colado (planta)', em(p,-5,-4,-45,-44), f'{len(p)} vért')
# detecção de segundos inválidos
inval=gms_labeled('LONG. -44°28\'80,80"'); 
bad=[v for v,okc in bylabel('LONG. -44°28\'80,80"','long') if not okc]
caso('Detecta segundo inválido (>=60)', len(bad)==1, f'{len(bad)} inválido')

# 4) GMS tabela s/ rótulo com S/W (MAPA_IMOVEL)
p=gms_tabela('P1 4°02\'42.1"S 44°28\'19.7"W P2 285°,27\' 21,60" 4,50  P2 4°02\'42.1"S 44°28\'19.9"W P3 197°,10\' 40,83" 52,00  P3 4°02\'43.6"S 44°28\'20.4"W')
caso('GMS tabela S/W, azimute ignorado (MAPA)', em(p,-5,-4,-45,-44) and len(p)==3, f'{len(p)} vért')

# 5) UTM E/N rotulado "N=.. E=.."
p=[utm2geo(e,n) for n,e in utm_en('P1 N=9.222.799,638 E=445.517,024 P2 N=9.222.785,736 E=445.525,444 P3 N=9.222.771,093 E=445.502,080')]
caso('UTM N=/E= rotulado', em(p,-8,-6,-46,-45), f'{len(p)} vért')

# 6) UTM tabela topográfica (cabeçalho N(Y)/E(X)) + ruído (CPF/CEP/processo)
raw=utm_tabela('CPF:662.695.803-82 CEP;65470-00 Processo 2024.0001234-7 CFT 0249078333-5 1 2 9.222.799,638 445.517,024 16,25 m 2 3 9.222.785,736 445.525,444 27,57 m 3 4 9.222.771,093 445.502,080 13,60 m 4 1 9.222.782,663 445.494,939 27,86 m')
p=[utm2geo(e,n) for n,e in raw]
caso('UTM tabela topográfica + ruído CPF/CEP', em(p,-8,-6,-46,-45) and len(p)==4, f'{len(p)} vért (ruído rejeitado)')

# 7) Azimute+distância SIGEF "135º20' e X m até" + reconstrução por âncora
def sigef_legs(t):
    t=norm(t);legs=[]
    for x in re.finditer(r"(\d{1,3})\s*°\s*(\d{1,2})?\s*'?\s*e\s+([\d.]+(?:,\d+)?)\s*m\s+at[ée]",t,re.I):
        legs.append((dms(x.group(1),x.group(2) or '',''),brnum(x.group(3))))
    return legs
legs=sigef_legs("135º20' e 1.311,26 m até o vértice X, 212º10' e 54,69 m até o vértice Y, 238º20' e 1.369,43 m até o vértice Z")
caso('Extrai lados SIGEF "AAAº MM e D m até"', len(legs)==3, f'{len(legs)} lados')

# 8) Zona UTM automática (imóvel zona 24 c/ referência)
def geo2utm(lat,lon,zone):
    a=6378137.0;f=1/298.257223563;e2=f*(2-f);k0=0.9996
    lon0=math.radians((zone-1)*6-180+3);latr=math.radians(lat);lonr=math.radians(lon)
    ep2=e2/(1-e2);N=a/math.sqrt(1-e2*math.sin(latr)**2);T=math.tan(latr)**2
    C=ep2*math.cos(latr)**2;A=math.cos(latr)*(lonr-lon0)
    M=a*((1-e2/4-3*e2**2/64-5*e2**3/256)*latr-(3*e2/8+3*e2**2/32+45*e2**3/1024)*math.sin(2*latr)+(15*e2**2/256+45*e2**3/1024)*math.sin(4*latr)-(35*e2**3/3072)*math.sin(6*latr))
    E=k0*N*(A+(1-T+C)*A**3/6)+500000
    Nn=k0*(M+N*math.tan(latr)*(A*A/2+(5-T+9*C)*A**4/24))+10000000
    return Nn,E
def resolver(pares,rlat,rlon):
    def cen(z):
        pts=[utm2geo(e,n,z) for n,e in pares];return (sum(p[0] for p in pts)/len(pts),sum(p[1] for p in pts)/len(pts)),pts
    (la,lo),base=cen(23)
    if rlat is None: return 23,base
    dist=lambda c:math.hypot((c[0]-rlat)*111000,(c[1]-rlon)*111000*math.cos(math.radians(rlat)))
    if dist((la,lo))<=150000: return 23,base
    best=(23,base,dist((la,lo)))
    for z in [24,22,25]:
        c,pts=cen(z);d=dist(c)
        if d<best[2]: best=(z,pts,d)
    return best[0],best[1]
pares24=[geo2utm(-6.5,-42.5,24),geo2utm(-6.5005,-42.4995,24),geo2utm(-6.501,-42.5,24)]
z,pts=resolver(pares24,-6.5,-42.5)
caso('Zona UTM auto (24) c/ referência', z==24 and em(pts,-7,-6,-43,-42), f'zona {z}')
z2,pts2=resolver([geo2utm(-3.9,-45.5,23),geo2utm(-3.9005,-45.4995,23),geo2utm(-3.901,-45.5,23)],-3.9,-45.5)
caso('Zona 23 real NÃO é alterada', z2==23, f'zona {z2}')

# 9) Reconciliação: 5 vértices corrompidos entre bons (matrícula 1563) — âncora pela mediana
import statistics as st
def reconcilia(escritos, legs, tol=5.0):
    v=len(escritos)
    U=[geo2utm(la,lo,23) for la,lo in escritos]  # (N,E)
    rel=[(0.0,0.0)]
    for i in range(v-1):
        az=math.radians(legs[i][0]); d=legs[i][1]
        rel.append((rel[i][0]+d*math.cos(az), rel[i][1]+d*math.sin(az)))
    offN=st.median([U[i][0]-rel[i][0] for i in range(v)])
    offE=st.median([U[i][1]-rel[i][1] for i in range(v)])
    novo=[];corr=0;inl=0
    for i in range(v):
        rN=rel[i][0]+offN; rE=rel[i][1]+offE
        dev=math.hypot(U[i][0]-rN, U[i][1]-rE)
        if dev<=tol: novo.append(U[i]); inl+=1
        else: novo.append((rN,rE)); corr+=1
    usou = corr>0 and inl>=max(2,math.ceil(v*0.6))
    return usou, corr, inl
# polígono real (bom) + legs corretas; corrompe 3 de 8 vértices
import math as _m
base=[(-3.90,-45.50)]
legs8=[(90,60),(0,60),(270,60),(180,20),(270,40),(0,40),(90,100),(180,80)]
E,N=geo2utm(*base[0],23)[1],geo2utm(*base[0],23)[0]
bom=[base[0]]
for az,d in legs8[:-1]:
    a=_m.radians(az); E+=d*_m.sin(a); N+=d*_m.cos(a); bom.append(utm2geo(E,N))
escritos=list(bom)
# corrompe vértices 2,4,6 (desloca ~150 m)
for idx in (2,4,6):
    la,lo=escritos[idx]; escritos[idx]=(la+0.0014, lo+0.0014)
usou,corr,inl=reconcilia(escritos, legs8)
caso('Reconcilia vértices corrompidos (3 de 8)', usou and corr==3 and inl==5, f'{corr} corrigidos, {inl} inliers')

# 10) Âncora UTM + lados OCR sujo (São Mateus, acervo antigo) + fechamento Bowditch
_t=norm("coordenadas UTM 557341-20 e 9553121-35N ... 166°46'07 e 1.394.38m até o M-13, 105º17'30; 1.055,81m até o M-14, 97°67'52 e 531,77m até o M-15, 78°08'04 e 1.133,33m até o M-16")
_ma=re.search(r'coordenadas?\s+UTM\s+([0-9][0-9.\- ]*?)\s+e\s+([0-9][0-9.\- ]*?)\s*N',_t,re.I)
_anc = None
if _ma:
    def _n(r): return brnum(re.sub(r'\s','',r).replace('-',','))
    _anc=(_n(_ma.group(2)),_n(_ma.group(1)))  # (N,E)
_rl=re.compile(r"(\d{1,3})\s*[°.]\s*(\d{1,3})\s*'\s*(\d{0,2})\s*\"?\s*[^0-9]{0,28}?([\d][\d.,\s]*?)\s*m?\s*,?\s*at[ée]\b",re.I)
_legs=[(dms(x.group(1),x.group(2),x.group(3)),brnum(re.sub(r'\s','',x.group(4)))) for x in _rl.finditer(_t)]
caso('Âncora UTM + lados OCR "até o M" (S.Mateus)', _anc is not None and len(_legs)>=3 and 100000<=_anc[1]<1000000 and 1000000<=_anc[0]<=10000000, f'âncora ok, {len(_legs)} lados')

# RELATÓRIO
print("="*64)
ok=sum(1 for _,o,_ in casos if o)
for nome,o,det in casos:
    print(f"  [{'OK ' if o else 'FALHA'}] {nome}"+(f"  — {det}" if det else ""))
print("="*64)
print(f"  {ok}/{len(casos)} casos cobertos")
