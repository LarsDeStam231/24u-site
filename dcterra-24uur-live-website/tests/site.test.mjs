import test, { before, after } from 'node:test';
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { openDatabase, readState, applyPaymentStatus } from '../lib-database.mjs';

const projectRoot=resolve(dirname(fileURLToPath(import.meta.url)),'..');
const dir=mkdtempSync(join(tmpdir(),'dcsite-tests-'));
const PORT=33291;
const BASE=`http://localhost:${PORT}`;
let child=null;
before(async()=>{
  child=spawn(process.execPath,['server.mjs'],{cwd:projectRoot,env:{...process.env,PORT:String(PORT),APP_URL:BASE,DB_PATH:join(dir,'site.sqlite'),ADMIN_PASSWORD:'een-sterk-testwachtwoord',PAYMENTS_ENABLED:'false',MOLLIE_API_KEY:''},stdio:['ignore','pipe','pipe']});
  for(let i=0;i<70;i++){
    try {const r=await fetch(BASE+'/health');if(r.ok)return;} catch{}
    await new Promise(r=>setTimeout(r,85));
  }
  throw new Error('Server startte niet');
});
after(()=>{child?.kill('SIGTERM');rmSync(dir,{recursive:true,force:true});});
async function json(path,method='GET',value,headers={}){
  const r=await fetch(BASE+path,{method,headers:{...headers,...(value!==undefined?{'Content-Type':'application/json'}:{})},body:value!==undefined?JSON.stringify(value):undefined});
  return {status:r.status,body:await r.json(),headers:r.headers};
}
test('website & transparante overlay bereikbaar',async()=>{
  const [home,overlay]=await Promise.all([fetch(BASE+'/'),fetch(BASE+'/overlay')]);
  assert.equal(home.status,200);assert.match(await home.text(),/DCTerra 24 Uur Live/);
  assert.equal(overlay.status,200);assert.match(await overlay.text(),/OBS Donatieteller/);
});
test('teller begint op nul; betaling kan niet starten zonder goedkeuring',async()=>{
  const a=await json('/api/state');assert.equal(a.body.raisedCents,0);assert.equal(a.body.goalCents,1000000);assert.equal(a.body.paymentEnabled,false);
  const b=await json('/api/donations/create','POST',{amount:25});assert.equal(b.status,503);
});
test('ongeldige sponsorinvoer geweigerd en legitieme aanvraag opgeslagen',async()=>{
  const invalid=await json('/api/sponsor-lead','POST',{company:'X',contact:'Y',email:'geen-email',tier:'goud',consent:true});assert.equal(invalid.status,400);
  const valid=await json('/api/sponsor-lead','POST',{company:'ICT Emmen BV',contact:'Test Persoon',email:'test@example.org',tier:'goud',message:'Sponsorprijzen voor de stream',consent:true});assert.equal(valid.status,201);
});
test('beveiliging: login, sponsor publiceren, teller handmatig aanpassen, auditlog',async()=>{
  const wrong=await json('/api/admin/login','POST',{password:'verkeerd'});assert.equal(wrong.status,401);
  const login=await json('/api/admin/login','POST',{password:'een-sterk-testwachtwoord'});assert.equal(login.status,200);
  const cookie=login.headers.get('set-cookie').split(';')[0];
  const auth={Cookie:cookie};
  const data=await json('/api/admin/data','GET',undefined,auth);assert.equal(data.body.leads.length,1);
  const sponsor=await json('/api/admin/sponsor','POST',{name:'Test Sponsor',tier:'goud',url:'https://example.org'},auth);assert.equal(sponsor.status,201);
  const malicious=await json('/api/admin/sponsor','POST',{name:'Nope',tier:'goud',url:'javascript:alert(1)'},auth);assert.equal(malicious.status,400);
  const adjustment=await json('/api/admin/adjustment','POST',{amount:50,reason:'Bevestigde contante gift'},auth);assert.equal(adjustment.status,201);
  const state=await json('/api/state');assert.equal(state.body.raisedCents,5000);assert.equal(state.body.nextMilestoneCents,10000);assert.equal(state.body.sponsors.length,1);
  const negative=await json('/api/admin/adjustment','POST',{amount:-51,reason:'Ongeldige correctie'},auth);assert.equal(negative.status,400);
  const settings=await json('/api/admin/settings','POST',{goal:12500,youtubeVideoId:'abcdefghijk',live:true},auth);assert.equal(settings.status,200);
  const newer=await json('/api/state');assert.equal(newer.body.goalCents,1250000);assert.equal(newer.body.live,true);
  const audit=await json('/api/admin/data','GET',undefined,auth);assert.equal(audit.body.adjustments.length,1);
  const csrf=await json('/api/admin/adjustment','POST',{amount:10,reason:'csrf attempt'},{...auth,Origin:'https://evil.example'});assert.equal(csrf.status,403);
  const logout=await json('/api/admin/logout','POST',{},auth);assert.equal(logout.status,200);
  const after=await json('/api/admin/data','GET',undefined,auth);assert.equal(after.status,401);
});
test('Mollie statusverwerking telt een transactie slechts één keer',()=>{
  const db=openDatabase(join(dir,'unit.sqlite'));
  db.prepare('INSERT INTO payments(order_id,payment_id,amount_cents) VALUES(?,?,?)').run('order001','tr_AA123',2500);
  const payment={id:'tr_AA123',status:'paid',amount:{currency:'EUR',value:'25.00'},metadata:{campaign:'dcterra24uur2026',orderId:'order001'}};
  assert.equal(applyPaymentStatus(db,'order001',payment),true);
  assert.equal(applyPaymentStatus(db,'order001',payment),true);
  assert.equal(readState(db).raisedCents,2500);
  assert.equal(applyPaymentStatus(db,'order001',{...payment,amountRefunded:{currency:'EUR',value:'10.00'}}),true);
  assert.equal(readState(db).raisedCents,1500);
  assert.equal(applyPaymentStatus(db,'order001',{...payment,status:'refunded'}),true);
  assert.equal(readState(db).raisedCents,0);
  assert.equal(applyPaymentStatus(db,'order001',{...payment,amount:{currency:'EUR',value:'99.99'}}),false);
  db.close();
});
