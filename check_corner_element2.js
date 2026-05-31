const puppeteer = require('puppeteer');
(async () => {
    const browser = await puppeteer.launch({ 
        headless: "new",
        ignoreHTTPSErrors: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    await page.goto('https://staging.bactiveph.com/', { waitUntil: 'networkidle2' });
    
    const info = await page.evaluate(() => {
        const el = document.elementFromPoint(5, 100);
        if(!el) return null;
        
        let path = [];
        let current = el;
        while(current && current.tagName !== 'HTML') {
            path.push(current.tagName + (current.id ? '#' + current.id : '') + (current.className ? '.' + current.className.replace(/\s+/g, '.') : ''));
            current = current.parentElement;
        }
        
        return {
            path: path.reverse().join(' > '),
            outerHTML: el.outerHTML
        };
    });

    console.log(JSON.stringify(info, null, 2));
    await browser.close();
})();
