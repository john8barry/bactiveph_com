const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch({ 
        headless: "new",
        ignoreHTTPSErrors: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    const page = await browser.newPage();
    
    // Auth for staging
    // await page.authenticate({ username: 'bactive_team', password: 'BActive_Stg_2026!' });
    
    await page.setViewport({ width: 1440, height: 900 });
    await page.goto('https://staging.bactiveph.com/', { waitUntil: 'networkidle2' });
    
    const info = await page.evaluate(() => {
        const results = {};
        const corners = [
            {x: 5, y: 5},
            {x: 1435, y: 5},
            {x: 5, y: 895},
            {x: 1435, y: 895}
        ];
        corners.forEach(p => {
            const el = document.elementFromPoint(p.x, p.y);
            if(el) {
                const rect = el.getBoundingClientRect();
                results[`${p.x},${p.y}`] = {
                    tagName: el.tagName,
                    className: el.className,
                    bg: window.getComputedStyle(el).backgroundColor,
                    borderRadius: window.getComputedStyle(el).borderRadius
                };
            }
        });
        return results;
    });

    console.log(info);
    await browser.close();
})();
