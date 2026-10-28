import { DatabaseSync } from 'node:sqlite';
import { mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

export function openDatabase(filename) {
  mkdirSync(dirname(resolve(filename)), { recursive: true });
  const db = new DatabaseSync(filename);
  db.exec(`
    PRAGMA journal_mode=WAL;
    PRAGMA busy_timeout=5000;
    CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS payments (
      order_id TEXT PRIMARY KEY,
      payment_id TEXT UNIQUE,
      amount_cents INTEGER NOT NULL CHECK(amount_cents > 0),
      net_cents INTEGER NOT NULL DEFAULT 0,
      status TEXT NOT NULL DEFAULT 'open',
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS adjustments (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      cents INTEGER NOT NULL,
      reason TEXT NOT NULL,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS leads (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      company TEXT NOT NULL,
      contact TEXT NOT NULL,
      email TEXT NOT NULL,
      tier TEXT NOT NULL,
      message TEXT NOT NULL,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS sponsors (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      tier TEXT NOT NULL,
      url TEXT NOT NULL,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS admin_sessions (
      token_hash TEXT PRIMARY KEY,
      expires INTEGER NOT NULL
    );
  `);
  for (const [k,v] of Object.entries({ goal_cents:'1000000', live:'false', youtube_id:'' })) {
    db.prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)').run(k,v);
  }
  return db;
}
export function readState(db) {
  const settings=Object.fromEntries(db.prepare('SELECT key,value FROM settings').all().map(x=>[x.key,x.value]));
  const paid=db.prepare(`SELECT COALESCE(SUM(net_cents),0) AS cents,COUNT(*) AS count FROM payments WHERE net_cents > 0`).get();
  const adjusted=db.prepare('SELECT COALESCE(SUM(cents),0) AS cents FROM adjustments').get();
  const total=Math.max(0,paid.cents+adjusted.cents);
  const milestones=[100,250,500,750,1000,2500,5000,7500,10000].map(e=>e*100);
  const next=milestones.find(m=>m>total) ?? null;
  return {
    raisedCents:total, goalCents:Number(settings.goal_cents)||1000000,
    donationCount:paid.count, nextMilestoneCents:next,
    live:settings.live==='true', youtubeVideoId:settings.youtube_id||'',
    sponsors:db.prepare('SELECT id,name,tier,url FROM sponsors ORDER BY CASE tier WHEN \'goud\' THEN 0 WHEN \'zilver\' THEN 1 ELSE 2 END, id DESC').all(),
  };
}
export function readAdmin(db) {
  return {
    payments:db.prepare('SELECT order_id,amount_cents,net_cents,status,created_at FROM payments ORDER BY created_at DESC LIMIT 40').all(),
    adjustments:db.prepare('SELECT id,cents,reason,created_at FROM adjustments ORDER BY id DESC LIMIT 40').all(),
    leads:db.prepare('SELECT id,company,contact,email,tier,message,created_at FROM leads ORDER BY id DESC LIMIT 100').all()
  };
}
export function applyPaymentStatus(db, orderId, payment) {
  const existing=db.prepare('SELECT * FROM payments WHERE order_id=?').get(orderId);
  if (!existing || existing.payment_id!==payment.id || payment.metadata?.campaign!=='dcterra24uur2026' || payment.metadata?.orderId!==orderId) return false;
  const amount=Math.round(Number(payment.amount?.value)*100);
  if (!Number.isSafeInteger(amount)||amount!==existing.amount_cents||payment.amount?.currency!=='EUR') return false;
  const status=String(payment.status||'unknown');
  const refunded=Math.max(0,Math.round(Number(payment.amountRefunded?.value||0)*100));
  let net=0;
  if (status==='paid') net=Math.max(0,amount-refunded);
  if (status==='refunded'||status==='charged_back') net=0;
  // Idempotent reconciliation: every payment contributes its current net value once.
  db.prepare('UPDATE payments SET status=?,net_cents=?,updated_at=CURRENT_TIMESTAMP WHERE order_id=?').run(status,net,orderId);
  return true;
}
