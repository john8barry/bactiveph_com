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
        const els = Array.from(document.querySelectorAll('*'));
        return els.filter(el => {
            const style = window.getComputedStyle(el);
            const r = parseInt(style.borderRadius);
            return r > 0 && el.getBoundingClientRect().width > 1000;
        }).map(el => {
            return {
                tag: el.tagName,
                class: el.className,
                borderRadius: window.getComputedStyle(el).borderRadius
            };
        });
    });

    console.log(JSON.stringify(info, null, 2));
    await browser.close();
})();
