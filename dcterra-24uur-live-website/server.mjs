import http from 'node:http';
import { existsSync, createReadStream, statSync } from 'node:fs';
import { resolve, join, extname, sep } from 'node:path';
import { createHash, randomBytes, randomUUID, timingSafeEqual } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { dirname } from 'node:path';
import { openDatabase, readState, readAdmin, applyPaymentStatus } from './lib-database.mjs';
import { createMolliePayment, getMolliePayment } from './lib-mollie.mjs';

const root=dirname(fileURLToPath(import.meta.url));
if (existsSync(join(root,'.env'))) process.loadEnvFile(join(root,'.env'));
const port=Number(process.env.PORT||3000);
const APP_URL=(process.env.APP_URL||`http://localhost:${port}`).replace(/\/$/,'');
const base=new URL(APP_URL);
const secret=process.env.ADMIN_PASSWORD||'';
const key=process.env.MOLLIE_API_KEY||'';
const paymentsEnabled=process.env.PAYMENTS_ENABLED==='true' && /^((test|live)_)/.test(key);
const db=openDatabase(resolve(root,process.env.DB_PATH||'./data/site.sqlite'));
const publicDir=resolve(root,'public');
const mime={'.html':'text/html; charset=utf-8','.css':'text/css; charset=utf-8','.js':'text/javascript; charset=utf-8','.jpg':'image/jpeg','.jpeg':'image/jpeg','.png':'image/png','.svg':'image/svg+xml','.ico':'image/x-icon'};
const rates=new Map();
const euroText=(n)=>(n/100).toFixed(2);
const hash=(str)=>createHash('sha256').update(str).digest('hex');
function sameString(a,b) {
  const x=Buffer.from(hash(a),'hex'),y=Buffer.from(hash(b),'hex');
  return timingSafeEqual(x,y);
}
function deny(res,code,msg) { sendJson(res,code,{error:msg}); }
function sendJson(res,status,data,headers={}) {
  res.writeHead(status,{'Content-Type':'application/json; charset=utf-8','Cache-Control':'no-store',...headers});
  res.end(JSON.stringify(data));
}
function checkRate(req,label,max,ms) {
  const ip=req.socket.remoteAddress||'unknown';
  const key=`${ip}:${label}`;const now=Date.now();
  const old=rates.get(key)||[];const recent=old.filter(t=>now-t<ms);
  recent.push(now);rates.set(key,recent);
  if(rates.size>3000) for(const [k,v] of rates) if(!v.some(t=>now-t<ms)) rates.delete(k);
  return recent.length<=max;
}
function cookies(req) {
  return Object.fromEntries((req.headers.cookie||'').split(';').filter(Boolean).map(x=>{const i=x.indexOf('=');return [x.slice(0,i).trim(),decodeURIComponent(x.slice(i+1))];}));
}
function authenticated(req) {
  const token=cookies(req).dc_admin||'';
  if(!/^[a-f0-9]{64}$/.test(token)) return false;
  const entry=db.prepare('SELECT expires FROM admin_sessions WHERE token_hash=?').get(hash(token));
  return Boolean(entry && entry.expires>Date.now());
}
function secureCookie(value,maxAge) {
  return `dc_admin=${value}; HttpOnly; SameSite=Strict; Path=/; Max-Age=${maxAge}${base.protocol==='https:'?'; Secure':''}`;
}
async function readRaw(req,max=15000) {
  let size=0,chunks=[];
  for await (const chunk of req) {
    size+=chunk.length;
    if(size>max) throw new Error('Het formulier is te groot.');
    chunks.push(chunk);
  }
  return Buffer.concat(chunks).toString('utf8');
}
async function readJson(req) {
  const raw=await readRaw(req);
  try{return JSON.parse(raw||'{}')}catch{throw new Error('Ongeldige invoer.');}
}
function validOrigin(req) {
  const origin=req.headers.origin;
  if (!origin) return true;
  try {return new URL(origin).origin===base.origin;}catch{return false;}
}
function sanitizedString(value,max=120) {return String(value||'').trim().slice(0,max);}
function safeUrl(s) {
  try {const u=new URL(s);return ['https:','http:'].includes(u.protocol)?u.href:'';}catch{return '';}
}
function validAmount(value) {
  const n=Number(value);
  if(!Number.isFinite(n)||n<1||n>10000) return null;
  const cents=Math.round(n*100);
  return Number.isSafeInteger(cents)&&Math.abs(n*100-cents)<0.0000001?cents:null;
}
function errorMessage(err) {return err instanceof Error?err.message:'Onbekende serverfout';}
function settingsUpdate(key,value) {db.prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value').run(key,value);}
async function routeApi(req,res,path,url) {
  if(req.method==='GET'&&path==='/api/state') {
    return sendJson(res,200,{...readState(db),paymentEnabled:paymentsEnabled,preview:false,streamChannelUrl:'https://www.youtube.com/@DCterra24uur'});
  }
  if(req.method==='POST'&&!validOrigin(req)) return deny(res,403,'Deze aanvraag is niet toegestaan.');
  if(req.method==='POST'&&path==='/api/donations/create') {
    if(!checkRate(req,'donate',20,60*60*1000)) return deny(res,429,'Te veel aanvragen. Probeer het later opnieuw.');
    if(!paymentsEnabled) return deny(res,503,'Online doneren via Mollie is nog niet actief. Gebruik voorlopig de website van Wensstichting Drenthe.');
    const body=await readJson(req),amount=validAmount(body.amount);
    if(!amount) return deny(res,400,'Kies een bedrag van € 1 t/m € 10.000.');
    if(base.protocol!=='https:' && key.startsWith('live_')) return deny(res,503,'Live betalingen vereisen HTTPS.');
    const orderId=randomUUID();
    db.prepare('INSERT INTO payments(order_id,amount_cents) VALUES(?,?)').run(orderId,amount);
    try {
      const payment=await createMolliePayment(key,{
        amount:{currency:'EUR',value:euroText(amount)},
        description:'Donatie DCTerra 24 Uur Live 2026 - Wensstichting Drenthe',
        redirectUrl:`${APP_URL}/bedankt?order=${encodeURIComponent(orderId)}`,
        webhookUrl:`${APP_URL}/api/mollie/webhook`,
        metadata:{campaign:'dcterra24uur2026',orderId}
      });
      const checkout=payment?._links?.checkout?.href;
      if(!checkout||!checkout.startsWith('https://')) throw new Error('Mollie heeft geen geldige betaallink teruggegeven.');
      db.prepare('UPDATE payments SET payment_id=?,status=? WHERE order_id=?').run(payment.id,payment.status||'open',orderId);
      return sendJson(res,201,{checkoutUrl:checkout,orderId});
    } catch(e) {
      db.prepare('DELETE FROM payments WHERE order_id=?').run(orderId);
      console.error('Mollie payment creation:',errorMessage(e));
      return deny(res,502,'Betaling kon niet worden gestart. Probeer het later opnieuw.');
    }
  }
  if(req.method==='POST'&&path==='/api/mollie/webhook') {
    if(!key) return deny(res,503,'Mollie niet geconfigureerd.');
    const raw=await readRaw(req,2000),id=new URLSearchParams(raw).get('id')||'';
    if(!/^tr_[A-Za-z0-9]+$/.test(id)) return deny(res,400,'Ongeldig betalings-ID.');
    const paymentRow=db.prepare('SELECT order_id FROM payments WHERE payment_id=?').get(id);
    if(!paymentRow) return sendJson(res,200,{received:true});
    // Never trust webhook payload: retrieve authoritative status from Mollie.
    const payment=await getMolliePayment(key,id);
    const verified=applyPaymentStatus(db,paymentRow.order_id,payment);
    return sendJson(res,verified?200:422,{received:verified});
  }
  if(req.method==='GET'&&path==='/api/donations/status') {
    const order=url.searchParams.get('order')||'';
    if(!/^[a-f0-9-]{36}$/.test(order)) return deny(res,400,'Ongeldige betaalsessie.');
    const row=db.prepare('SELECT order_id,payment_id,amount_cents,status FROM payments WHERE order_id=?').get(order);
    if(!row) return deny(res,404,'Betaling niet gevonden.');
    if(key&&row.payment_id&&['open','pending','authorized'].includes(row.status)) {
      try {applyPaymentStatus(db,order,await getMolliePayment(key,row.payment_id));} catch(e){console.error('Status check:',errorMessage(e));}
    }
    const latest=db.prepare('SELECT status,amount_cents FROM payments WHERE order_id=?').get(order);
    return sendJson(res,200,{status:latest.status,amountCents:latest.amount_cents});
  }
  if(req.method==='POST'&&path==='/api/sponsor-lead') {
    if(!checkRate(req,'sponsor',6,60*60*1000)) return deny(res,429,'Te veel aanvragen. Probeer het later opnieuw.');
    const v=await readJson(req);
    if(v.website) return sendJson(res,201,{accepted:true}); // Honeypot
    const company=sanitizedString(v.company,100),contact=sanitizedString(v.contact,100),email=sanitizedString(v.email,140),tier=sanitizedString(v.tier,20),message=sanitizedString(v.message,1600);
    if(company.length<2||contact.length<2||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)||!['brons','zilver','goud'].includes(tier)||v.consent!==true) return deny(res,400,'Vul alle verplichte velden correct in en geef toestemming voor contact.');
    db.prepare('INSERT INTO leads (company,contact,email,tier,message) VALUES (?,?,?,?,?)').run(company,contact,email,tier,message);
    return sendJson(res,201,{accepted:true});
  }
  if(req.method==='POST'&&path==='/api/admin/login') {
    if(!secret) return deny(res,503,'Beheer is nog niet ingesteld. Configureer ADMIN_PASSWORD.');
    if(!checkRate(req,'login',8,15*60*1000)) return deny(res,429,'Te veel pogingen. Probeer later opnieuw.');
    const v=await readJson(req);
    if(!sameString(String(v.password||''),secret)) return deny(res,401,'Onjuist wachtwoord.');
    const token=randomBytes(32).toString('hex');
    db.prepare('DELETE FROM admin_sessions WHERE expires<?').run(Date.now());
    db.prepare('INSERT INTO admin_sessions(token_hash,expires) VALUES(?,?)').run(hash(token),Date.now()+8*3600000);
    return sendJson(res,200,{ok:true},{'Set-Cookie':secureCookie(token,8*3600)});
  }
  if(path.startsWith('/api/admin/')) {
    if(!authenticated(req)) return deny(res,401,'Log eerst in bij Crewbeheer.');
    if(req.method==='GET'&&path==='/api/admin/data') return sendJson(res,200,{...readState(db),...readAdmin(db),paymentsEnabled});
    if(req.method==='POST'&&path==='/api/admin/logout') {
      const token=cookies(req).dc_admin||'';
      db.prepare('DELETE FROM admin_sessions WHERE token_hash=?').run(hash(token));
      return sendJson(res,200,{ok:true},{'Set-Cookie':secureCookie('',0)});
    }
    if(req.method==='POST'&&path==='/api/admin/settings') {
      const v=await readJson(req);
      const rawGoal=Number(v.goal),goal=Number.isFinite(rawGoal)&&rawGoal>=100&&rawGoal<=1000000?Math.round(rawGoal*100):null,youtube=sanitizedString(v.youtubeVideoId,40);
      if(!goal||goal<10000||!(youtube===''||/^[A-Za-z0-9_-]{11}$/.test(youtube))||typeof v.live!=='boolean') return deny(res,400,'Ongeldige instellingen.');
      settingsUpdate('goal_cents',String(goal));settingsUpdate('youtube_id',youtube);settingsUpdate('live',String(v.live));
      return sendJson(res,200,{ok:true});
    }
    if(req.method==='POST'&&path==='/api/admin/adjustment') {
      const v=await readJson(req),n=Number(String(v.amount||'').replace(',','.'));
      const cents=Math.round(n*100),reason=sanitizedString(v.reason,160);
      if(!Number.isFinite(n)||!Number.isSafeInteger(cents)||cents===0||Math.abs(cents)>1000000||reason.length<5) return deny(res,400,'Vul een niet-nul bedrag en een duidelijke toelichting in.');
      const row=readState(db);
      if(row.raisedCents+cents<0) return deny(res,400,'De teller mag niet negatief worden.');
      db.prepare('INSERT INTO adjustments(cents,reason) VALUES(?,?)').run(cents,reason);
      return sendJson(res,201,{ok:true});
    }
    if(req.method==='POST'&&path==='/api/admin/sponsor') {
      const v=await readJson(req),name=sanitizedString(v.name,80),tier=sanitizedString(v.tier,20),urlValue=sanitizedString(v.url,400);
      if(name.length<2||!['brons','zilver','goud'].includes(tier)|| (urlValue&&!safeUrl(urlValue))) return deny(res,400,'Ongeldige sponsorgegevens.');
      db.prepare('INSERT INTO sponsors(name,tier,url) VALUES (?,?,?)').run(name,tier,urlValue?safeUrl(urlValue):'');
      return sendJson(res,201,{ok:true});
    }
    if(req.method==='POST'&&path==='/api/admin/delete-lead') {
      const v=await readJson(req),id=Number(v.id);
      if(!Number.isSafeInteger(id)||id<1) return deny(res,400,'Ongeldige aanvraag.');
      db.prepare('DELETE FROM leads WHERE id=?').run(id);
      return sendJson(res,200,{ok:true});
    }
    if(req.method==='POST'&&path==='/api/admin/delete-sponsor') {
      const v=await readJson(req),id=Number(v.id);
      if(!Number.isSafeInteger(id)||id<1) return deny(res,400,'Ongeldige aanvraag.');
      db.prepare('DELETE FROM sponsors WHERE id=?').run(id);
      return sendJson(res,200,{ok:true});
    }
  }
  return deny(res,404,'API-endpoint niet gevonden.');
}
function serveStatic(req,res,path) {
  let candidate=path==='/'?'/index.html':path==='/overlay'?'/overlay.html':path;
  if (!['/index.html','/overlay.html','/styles.css','/app.js','/overlay.css','/overlay.js','/favicon.svg'].includes(candidate) && !candidate.startsWith('/assets/')) candidate='/index.html';
  const file=resolve(publicDir,'.'+candidate);
  if(!file.startsWith(publicDir+sep)||!existsSync(file)||!statSync(file).isFile()) {res.writeHead(404);res.end('Niet gevonden');return;}
  const contentType=mime[extname(file)]||'application/octet-stream';
  res.writeHead(200,{'Content-Type':contentType,'Cache-Control':candidate.endsWith('.html')?'no-cache':'public, max-age=3600'});
  createReadStream(file).pipe(res);
}
const server=http.createServer(async(req,res)=>{
  res.setHeader('X-Content-Type-Options','nosniff');
  res.setHeader('Referrer-Policy','strict-origin-when-cross-origin');
  res.setHeader('X-Frame-Options','SAMEORIGIN');
  res.setHeader('Content-Security-Policy',"default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https://i.ytimg.com; frame-src https://www.youtube-nocookie.com; connect-src 'self'; object-src 'none'; base-uri 'self'");
  try {
    const url=new URL(req.url,APP_URL),path=url.pathname;
    if(path==='/health') return sendJson(res,200,{ok:true});
    if(path.startsWith('/api/')) return await routeApi(req,res,path,url);
    if(req.method==='GET'||req.method==='HEAD') return serveStatic(req,res,path);
    return deny(res,405,'Methode niet toegestaan.');
  } catch(e) {
    console.error('Request error:',errorMessage(e));
    return deny(res,500,'Er ging iets mis. Probeer het later opnieuw.');
  }
});
server.listen(port,()=>{
  console.log(`\nDCTerra 24 Uur Live: http://localhost:${port}`);
  console.log(`Crewbeheer: http://localhost:${port}/crew`);
  console.log(`OBS-overlay: http://localhost:${port}/overlay`);
  console.log(`Betalingen: ${paymentsEnabled?'geconfigureerd':'uitgeschakeld (veilig prototype)'}`);
  console.log(`Beheer: ${secret?'ingeschakeld':'uitgeschakeld; vul ADMIN_PASSWORD in .env'}\n`);
});
