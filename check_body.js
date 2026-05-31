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
        const body = window.getComputedStyle(document.body);
        const html = window.getComputedStyle(document.documentElement);
        const container = window.getComputedStyle(document.querySelector('.ct-container') || document.body);
        return {
            body: { margin: body.margin, padding: body.padding, bg: body.backgroundColor, width: body.width },
            html: { margin: html.margin, padding: html.padding, bg: html.backgroundColor, width: html.width },
            container: { margin: container.margin, padding: container.padding, bg: container.backgroundColor, width: container.width, maxWidth: container.maxWidth }
        };
    });

    console.log(info);
    await browser.close();
})();
