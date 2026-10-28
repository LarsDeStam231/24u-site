"""Create a safe, clearly marked, dependency-free interactive visual demo.
This file simulates the public UI; it cannot take donations or store sponsor data.
"""
from pathlib import Path
import base64
folder=Path(__file__).parent
public=folder/'public'
assets={
 '/assets/studio-hero.jpg': ('image/jpeg','studio-hero.jpg'),
 '/assets/studio-live.jpg': ('image/jpeg','studio-live.jpg'),
 '/assets/wensstichting-drenthe-officieel.png': ('image/png','wensstichting-drenthe-officieel.png'),
 '/assets/dcterra-logo.svg': ('image/svg+xml','dcterra-logo.svg'),
}
blobs={name:f'data:{mime};base64,'+base64.b64encode((public/'assets'/file).read_bytes()).decode('ascii') for name,(mime,file) in assets.items()}
css=(public/'styles.css').read_text()
js=(public/'app.js').read_text()
for old,new in blobs.items():
 css=css.replace("url('"+old+"')",'url("'+new+'")')
 js=js.replace(old,new)
js=js.replace("const PREVIEW=new URLSearchParams(location.search).has('preview');",'const PREVIEW=true;')
js=js.replace('const path=location.pathname;document.title=',"const path=(window.__offlineRoute||'/');document.title=")
js=js.replace("function navigate(href){menuOpen=false;history.pushState({},'',href);render();window.scrollTo({top:0,behavior:'instant'});if(location.pathname==='/crew')loadCrew();if(location.pathname==='/bedankt')refreshPaymentStatus();}","function navigate(href){menuOpen=false;window.__offlineRoute=href.split('?')[0];render();window.scrollTo(0,0);}")
# Prevent accidental success messages from simulated form submissions.
safe_fetch='''
window.fetch = async function(url, options={}) {
 const path=String(url);
 if(path.startsWith('/api/state')) return new Response(JSON.stringify({raisedCents:0,goalCents:1000000,donationCount:0,nextMilestoneCents:10000,live:false,youtubeVideoId:'',sponsors:[],paymentEnabled:false}),{status:200,headers:{'Content-Type':'application/json'}});
 return new Response(JSON.stringify({error:'Dit is een offline demonstratie. Deze functie slaat niets op. Gebruik de echte website op een server.'}),{status:503,headers:{'Content-Type':'application/json'}});
};
'''
html=f'''<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>DCTerra 24 Uur Live — Interactieve demo</title><meta name="robots" content="noindex,nofollow"><style>{css}</style></head><body><div id="app"></div><script>{safe_fetch}\n{js.replace('</script>','<\\/script>')}</script></body></html>'''
out=folder/'INTERACTIEVE_DEMO.html'
out.write_text(html,encoding='utf-8')
print('Created',out,'size',out.stat().st_size)
