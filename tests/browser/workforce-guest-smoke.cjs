'use strict';
// Safe guest-only QAS smoke; no credentials or DB mutations.
const fs=require('node:fs'), path=require('node:path');
const {chromium}=require(process.env.FLOWRA_PLAYWRIGHT_MODULE||'playwright-core');
const base=(process.env.FLOWRA_QAS_URL||'https://v42.tag-marketplace.com').replace(/\/$/,'');
const shots=path.resolve(__dirname,'../../storage/logs'); fs.mkdirSync(shots,{recursive:true});
(async()=>{
  const opts={headless:true,args:['--no-first-run','--no-default-browser-check']};
  if(process.env.FLOWRA_CHROME_PATH)opts.executablePath=process.env.FLOWRA_CHROME_PATH;
  else opts.channel='chrome';
  const browser=await chromium.launch(opts), cases=[];
  try{
    for(const v of [{name:'desktop',width:1366,height:800},{name:'mobile',width:390,height:844}]){
      const context=await browser.newContext({viewport:{width:v.width,height:v.height}});
      try{
        const page=await context.newPage();
        await page.goto(base+'/admin/workforce/employees',{waitUntil:'domcontentloaded',timeout:30000});
        const redirected=new URL(page.url()).pathname==='/login';
        const hasForm=await page.locator('input[name=email]').count()>0;
        cases.push({id:'GUEST-'+v.name,pass:redirected&&hasForm,actual:{redirected,hasForm}});
        await page.screenshot({path:path.join(shots,'worg01b_guest_'+v.name+'.png'),fullPage:true});
        await page.goto(base+'/locale/ar?redirect=%2Flogin',{waitUntil:'domcontentloaded',timeout:30000});
        const dir=await page.locator('html').getAttribute('dir');
        const password=await page.locator('input[name=password]').count()>0;
        cases.push({id:'RTL-'+v.name,pass:dir==='rtl'&&password,actual:{dir,password}});
        await page.screenshot({path:path.join(shots,'worg01b_guest_ar_'+v.name+'.png'),fullPage:true});
      }finally{await context.close();}
    }
  }finally{await browser.close();}
  console.log(JSON.stringify({target:base,scope:'guest-only',cases},null,2));
  if(!cases.every(v=>v.pass))process.exitCode=1;
})().catch(e=>{console.error('GUEST_BROWSER_BLOCKED '+e.message);process.exitCode=2});
