const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch({ 
        headless: "new",
        ignoreHTTPSErrors: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    await page.goto('https://bactiveph.com/', { waitUntil: 'networkidle2' });
    
    // Find the element at the top-left corner (e.g., 5px, 5px)
    const topLeftEl = await page.evaluate(() => {
        const el = document.elementFromPoint(5, 5);
        if (!el) return null;
        const compStyle = window.getComputedStyle(el);
        return {
            tagName: el.tagName,
            className: el.className,
            id: el.id,
            backgroundColor: compStyle.backgroundColor,
            borderRadius: compStyle.borderRadius,
            position: compStyle.position,
            zIndex: compStyle.zIndex
        };
    });
    
    // Also find the element at the top-left just under the header (say, 5px, 80px)
    const underHeaderEl = await page.evaluate(() => {
        const el = document.elementFromPoint(5, 120);
        if (!el) return null;
        const compStyle = window.getComputedStyle(el);
        return {
            tagName: el.tagName,
            className: el.className,
            id: el.id,
            backgroundColor: compStyle.backgroundColor,
            borderRadius: compStyle.borderRadius
        };
    });

    console.log("Top-Left (5, 5):", topLeftEl);
    console.log("Under Header (5, 120):", underHeaderEl);
    
    await browser.close();
})();
