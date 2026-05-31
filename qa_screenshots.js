const puppeteer = require('puppeteer');
const fs = require('fs');

const products = [
    { slug: 'the-court-dress', name: 'court_dress' },
    { slug: 'the-pleated-skort', name: 'pleated_skort' },
    { slug: 'the-halter-set', name: 'halter_set' }
];

(async () => {
    const browser = await puppeteer.launch({ 
        headless: "new",
        ignoreHTTPSErrors: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox'
        ]
    });
    const page = await browser.newPage();
    
    // Authenticate
    await page.authenticate({ username: 'bactive_team', password: 'BActive_Stg_2026!' });
    
    for (let p of products) {
        const url = `https://bactiveph.com/staging/product/${p.slug}/`;
        console.log(`Processing ${url}`);
        
        try {
            await page.goto(url, { waitUntil: 'networkidle2' });
            
            // Wait for WooCommerce scripts to load
            await new Promise(r => setTimeout(r, 2000));
            
            // Save HTML
            const html = await page.content();
            fs.writeFileSync(`Buildout_Resources/pdp_${p.name}.html`, html);
            
            // Desktop screenshot
            await page.setViewport({ width: 1440, height: 900 });
            await page.screenshot({ path: `Buildout_Resources/${p.name}_desktop.png`, fullPage: true });
            
            // Mobile screenshot
            await page.setViewport({ width: 380, height: 800 });
            await page.screenshot({ path: `Buildout_Resources/${p.name}_mobile.png`, fullPage: true });
            
            console.log(`Saved ${p.name} screenshots and HTML.`);
        } catch (e) {
            console.error(`Error on ${p.name}:`, e.message);
        }
    }
    
    await browser.close();
})();
