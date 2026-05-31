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
        return els.map(el => {
            const style = window.getComputedStyle(el);
            const rect = el.getBoundingClientRect();
            return {
                tag: el.tagName,
                class: el.className,
                id: el.id,
                bg: style.backgroundColor,
                rect: { top: rect.top, left: rect.left, width: rect.width, height: rect.height },
                position: style.position,
                zIndex: style.zIndex
            };
        }).filter(el => {
            // Looking for something that might be a rectangle on the side or corner
            // Dark background, not transparent
            return el.bg.indexOf('rgba(0, 0, 0, 0)') === -1 && 
                   el.bg !== 'transparent' &&
                   (el.rect.width > 50 && el.rect.width < 300) && 
                   el.rect.height > 50 && 
                   (el.rect.left <= 0 || el.rect.left > 1000);
        });
    });

    console.log(JSON.stringify(info, null, 2));
    await browser.close();
})();
