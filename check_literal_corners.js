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
        const getElInfo = (x, y) => {
            const el = document.elementFromPoint(x, y);
            if(!el) return null;
            return {
                tag: el.tagName,
                class: el.className,
                id: el.id,
                bg: window.getComputedStyle(el).backgroundColor,
                radius: window.getComputedStyle(el).borderRadius,
                boxShadow: window.getComputedStyle(el).boxShadow,
                rect: el.getBoundingClientRect()
            };
        };
        
        return {
            topLeft: getElInfo(0, 0),
            topRight: getElInfo(1439, 0),
            bottomLeft: getElInfo(0, 899),
            bottomRight: getElInfo(1439, 899)
        };
    });

    console.log(JSON.stringify(info, null, 2));
    await browser.close();
})();
