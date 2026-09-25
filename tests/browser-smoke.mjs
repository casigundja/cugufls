import {spawn,execFileSync} from 'node:child_process';
import {mkdirSync,writeFileSync,readFileSync,existsSync,unlinkSync} from 'node:fs';
import path from 'node:path';
import {randomBytes,createHmac} from 'node:crypto';
import {setTimeout as delay} from 'node:timers/promises';

const root=process.cwd();
const artifacts=path.join(root,'storage/app/private/verification');
mkdirSync(artifacts,{recursive:true});
mkdirSync(path.join(root,'storage/framework/testing'),{recursive:true});
const database=path.join(root,'storage/framework/testing/browser-'+Date.now()+'.sqlite');
writeFileSync(database,'');
const password=randomBytes(24).toString('hex');
const env={...process.env,APP_ENV:'testing',APP_DEBUG:'true',APP_URL:'http://127.0.0.1:8011',DB_CONNECTION:'sqlite',DB_DATABASE:database,DB_URL:'',SESSION_DRIVER:'file',CACHE_STORE:'array',QUEUE_CONNECTION:'sync',MAIL_MAILER:'array',BCRYPT_ROUNDS:'4',BROWSER_PASSWORD:password};
const php=process.env.PHP_BINARY||'php';
const chrome=process.env.CHROME_BINARY||'C:/Program Files/Google/Chrome/Application/chrome.exe';
const children=[];
let ws;
const errors=[];
const results=[];
let seq=0;
const pending=new Map();
async function waitUntil(fn,message,timeout=15000){const start=Date.now();while(Date.now()-start<timeout){try{const value=await fn();if(value)return value;}catch{}await delay(150);}throw new Error(message);}
function command(method,params={}){return new Promise((resolve,reject)=>{const id=++seq;const timer=setTimeout(()=>{pending.delete(id);reject(new Error('CDP timeout '+method));},20000);pending.set(id,{resolve:v=>{clearTimeout(timer);resolve(v);},reject});ws.send(JSON.stringify({id,method,params}));});}
async function evaluate(expression){const result=await command('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(result.exceptionDetails)throw new Error(result.exceptionDetails.exception?.description||result.exceptionDetails.text);return result.result.value;}
async function go(route){await command('Page.navigate',{url:'http://127.0.0.1:8011'+route});await waitUntil(()=>evaluate('document.readyState==="complete" && !!document.querySelector("h1")'),'Página não montou: '+route);await delay(150);const state=await evaluate('({title:document.querySelector("h1")?.innerText,body:document.body.innerText,overflow:document.documentElement.scrollWidth>innerWidth+2})');if(/Internal Server Error|Page not found|This page has expired/.test(state.body))throw new Error('Página inválida '+route);return state;}
async function fill(selector,value){await evaluate(`(()=>{const e=document.querySelector(${JSON.stringify(selector)});if(!e)throw new Error('Campo ausente');e.value=${JSON.stringify(value)};e.dispatchEvent(new Event(e.tagName==='SELECT'?'change':'input',{bubbles:true}));})()`);}
async function clickText(text){await evaluate(`(()=>{const e=[...document.querySelectorAll('button')].find(e=>e.innerText.trim()===${JSON.stringify(text)});if(!e)throw new Error('Botão ausente: '+${JSON.stringify(text)});e.click();})()`);}
function totp(secret){const alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';let bits='';for(const c of secret.replace(/=+$/,'')){bits+=alphabet.indexOf(c.toUpperCase()).toString(2).padStart(5,'0');}const bytes=[];for(let i=0;i+8<=bits.length;i+=8)bytes.push(parseInt(bits.slice(i,i+8),2));const counter=Buffer.alloc(8);counter.writeBigUInt64BE(BigInt(Math.floor(Date.now()/30000)));const hash=createHmac('sha1',Buffer.from(bytes)).update(counter).digest();const offset=hash[hash.length-1]&15;return ((hash.readUInt32BE(offset)&0x7fffffff)%1000000).toString().padStart(6,'0');}
try{
 const prepared=execFileSync(php,['tests/prepare-browser.php'],{env,windowsHide:true,stdio:'pipe',encoding:'utf8'});
 if(!prepared.includes('Base de navegador preparada.'))throw new Error('Preparação da base de testes falhou: '+prepared);
 const server=spawn(php,['artisan','serve','--host=127.0.0.1','--port=8011','--no-reload'],{env,windowsHide:true,stdio:'ignore'});children.push(server);
 await waitUntil(async()=>{const r=await fetch('http://127.0.0.1:8011/up');return r.ok;},'Servidor de teste indisponível');
 const profile=path.join(root,'storage/framework/testing/chrome-'+Date.now());
 const browser=spawn(chrome,['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-port=9227','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});children.push(browser);
 const tabs=await waitUntil(async()=>{const r=await fetch('http://127.0.0.1:9227/json');return await r.json();},'Chrome indisponível');
 ws=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
 await new Promise((resolve,reject)=>{ws.addEventListener('open',resolve,{once:true});ws.addEventListener('error',reject,{once:true});});
 ws.addEventListener('message',event=>{const data=JSON.parse(event.data);if(data.id){const p=pending.get(data.id);pending.delete(data.id);if(p){if(data.error)p.reject(new Error(data.error.message));else p.resolve(data.result);}}else if(data.method==='Runtime.exceptionThrown'){errors.push(data.params.exceptionDetails.exception?.description||data.params.exceptionDetails.text);}else if(data.method==='Log.entryAdded'&&data.params.entry.level==='error'){errors.push(data.params.entry.text);}});
 await command('Page.enable');await command('Runtime.enable');await command('Log.enable');
 await command('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
 for(const route of ['/','/farmacia','/comercial','/timbragem','/lubrificantes','/produtos','/contacto']){await go(route);results.push({route,ok:true});}
 await go('/');writeFileSync(path.join(artifacts,'homepage-desktop.png'),Buffer.from((await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false})).data,'base64'));
 await command('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
 for(const route of ['/','/farmacia','/produtos','/contacto']){const state=await go(route);if(state.overflow)throw new Error('Overflow mobile '+route);results.push({route,mobile:true,ok:true});}
 writeFileSync(path.join(artifacts,'contacto-mobile.png'),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
 await command('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
 await go('/login');await fill('#email','browser@example.test');await fill('#password',password);await evaluate('document.querySelector("form button").click()');
 await waitUntil(()=>evaluate('location.pathname==="/admin/seguranca" && document.body.innerText.includes("Perfil e segurança")'),'Login ou montagem Inertia falhou');
 await fill('#confirm-password',password);await clickText('Confirmar acesso');await waitUntil(()=>evaluate('document.body.innerText.includes("Acesso confirmado.")'),'Confirmação de senha falhou');
 await clickText('Configurar autenticador');await waitUntil(()=>evaluate('!!document.querySelector(".qr-code svg")'),'QR de 2FA não apareceu');
 const secret=await evaluate('(async()=>{const r=await fetch("/user/two-factor-secret-key",{headers:{Accept:"application/json"}});return (await r.json()).secretKey;})()');
 await fill('#totp',totp(secret));await clickText('Ativar autenticação');await waitUntil(()=>evaluate('document.body.innerText.includes("Guarde os códigos de recuperação")'),'Ativação 2FA falhou');results.push({flow:'login e configuração 2FA',ok:true});
 for(const route of ['/admin/dashboard','/admin/produtos','/admin/produtos/criar','/admin/categorias/criar','/admin/stock/ajustar','/admin/transferencias/criar','/admin/pedidos/criar','/admin/importacoes','/admin/relatorios','/admin/acessos','/admin/configuracoes','/admin/notificacoes']){await go(route);results.push({route,ok:true});}
 await go('/admin/produtos/criar');await fill('#segment_id','1');await fill('#category_id','1');await fill('#name','Produto navegador');await fill('#slug','produto-navegador');await fill('#sku','BROWSER-1');await fill('#price','12.50');await fill('#unit','unit');await fill('#purchase_mode','whatsapp');await evaluate('document.querySelector("#active").click()');await clickText('Guardar alterações');
 await waitUntil(()=>evaluate('location.pathname==="/admin/produtos" && document.body.innerText.includes("Produto navegador")'),'Criação de produto falhou');results.push({flow:'criação de produto pela interface',ok:true});
 await go('/admin/stock/ajustar');await fill('#stock-product','1');await fill('#stock-branch','1');await fill('#stock-quantity','10');await fill('#stock-reason','Contagem de teste no navegador');await clickText('Registar movimento');await waitUntil(()=>evaluate('document.body.innerText.includes("saldo atual: 10")'),'Movimentação de stock falhou');results.push({flow:'contagem de stock pela interface',ok:true});
 await go('/admin/dashboard');writeFileSync(path.join(artifacts,'dashboard-desktop.png'),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
 await command('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
 for(const route of ['/admin/dashboard','/admin/produtos','/admin/stock/ajustar','/admin/seguranca']){const state=await go(route);if(state.overflow)throw new Error('Overflow administrativo '+route);results.push({route,mobile:true,ok:true});}
 writeFileSync(path.join(artifacts,'dashboard-mobile.png'),Buffer.from((await command('Page.captureScreenshot',{format:'png'})).data,'base64'));
 if(errors.length)throw new Error('Erros de navegador: '+errors.join('\n'));
 writeFileSync(path.join(artifacts,'browser-results.json'),JSON.stringify({passed:true,checks:results,errors},null,2));
 console.log(JSON.stringify({passed:true,checks:results.length,artifacts}));
}catch(error){writeFileSync(path.join(artifacts,'browser-results.json'),JSON.stringify({passed:false,checks:results,errors,error:error.message},null,2));console.error(error.message);process.exitCode=1;}
finally{if(ws?.readyState===1){try{await command('Browser.close');}catch{}ws.close();}for(const child of children){if(child.pid){try{execFileSync('taskkill',['/PID',String(child.pid),'/T','/F'],{windowsHide:true,stdio:'ignore'});}catch{}}}if(existsSync(database)){try{unlinkSync(database);}catch{}}}
