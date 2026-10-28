const ROOT='https://api.mollie.com/v2';
async function request(key,path,opts={}) {
  const res=await fetch(ROOT+path, {
    ...opts,
    headers:{Authorization:`Bearer ${key}`,Accept:'application/json',...(opts.body?{'Content-Type':'application/json'}:{})},
    signal:AbortSignal.timeout(12000)
  });
  const body=await res.json().catch(()=>({}));
  if (!res.ok) throw new Error(`Mollie HTTP ${res.status}: ${body.detail||body.title||'unknown error'}`);
  return body;
}
export function createMolliePayment(key,body) {
  return request(key,'/payments',{method:'POST',body:JSON.stringify(body)});
}
export function getMolliePayment(key,paymentId) {
  if(!/^tr_[a-zA-Z0-9]+$/.test(paymentId)) throw new Error('Ongeldig betaal-ID');
  return request(key,`/payments/${encodeURIComponent(paymentId)}`);
}
