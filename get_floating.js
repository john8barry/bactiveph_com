const puppeteer = require('puppeteer');
(async () => {
    const browser = await puppeteer.launch({ 
        headless: "new",
        ignoreHTTPSErrors: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    const page = await browser.newPage();
    await page.goto('https://staging.bactiveph.com/', { waitUntil: 'networkidle2' });
    
    const info = await page.evaluate(() => {
        const els = Array.from(document.querySelectorAll('*'));
        const floating = els.filter(el => {
            const style = window.getComputedStyle(el);
            return (style.position === 'absolute' || style.position === 'fixed') 
                   && parseInt(style.width) > 50 
                   && style.backgroundColor !== 'rgba(0, 0, 0, 0)'
                   && style.backgroundColor !== 'transparent';
        }).map(el => {
            const rect = el.getBoundingClientRect();
            return {
                tag: el.tagName,
                class: el.className,
                id: el.id,
                rect: {top: rect.top, left: rect.left, width: rect.width, height: rect.height},
                bg: window.getComputedStyle(el).backgroundColor
            };
        });
        return floating;
    });

    console.log(JSON.stringify(info, null, 2));
    await browser.close();
})();
