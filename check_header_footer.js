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
        const header = document.querySelector('header');
        const footer = document.querySelector('footer');
        return {
            header: header ? {
                bg: window.getComputedStyle(header).backgroundColor,
                radius: window.getComputedStyle(header).borderRadius,
                className: header.className
            } : null,
            footer: footer ? {
                bg: window.getComputedStyle(footer).backgroundColor,
                radius: window.getComputedStyle(footer).borderRadius,
                className: footer.className
            } : null
        };
    });

    console.log(JSON.stringify(info, null, 2));
    await browser.close();
})();
