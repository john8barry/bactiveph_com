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
    
    const html = await page.evaluate(() => {
        const frame = document.querySelector('.ct-site-frame');
        const container = document.querySelector('.ct-container');
        const bodyClass = document.body.className;
        
        return {
            frame_html: frame ? frame.outerHTML : 'null',
            container_class: container ? container.className : 'null',
            body_class: bodyClass
        };
    });

    console.log(JSON.stringify(html, null, 2));
    await browser.close();
})();
