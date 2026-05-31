const puppeteer = require('puppeteer');

const delay = ms => new Promise(res => setTimeout(res, ms));

(async () => {
    const browser = await puppeteer.launch({
        headless: false,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 1000 });
    
    await page.authenticate({'username': 'bactive_team', 'password': 'BActive_Stg_2026!'});

    console.log("Adding variation 38 to cart...");
    await page.goto('https://staging.bactiveph.com/?add-to-cart=36&variation_id=38', { waitUntil: 'networkidle2' });
    await delay(3000);

    console.log("Navigating to cart...");
    await page.goto('https://staging.bactiveph.com/cart/', { waitUntil: 'networkidle2' });
    await delay(2000);
    console.log("Taking cart screenshot...");
    await page.screenshot({ path: '/Users/johnbarry/.gemini/antigravity-ide/brain/292e8698-af8b-4cd4-bf56-2ff95cb5b9d6/cart_drawer.png', fullPage: true });

    console.log("Navigating to checkout...");
    await page.goto('https://staging.bactiveph.com/checkout/', { waitUntil: 'networkidle2' });
    await delay(3000);

    console.log("Taking checkout screenshot...");
    await page.screenshot({ path: '/Users/johnbarry/.gemini/antigravity-ide/brain/292e8698-af8b-4cd4-bf56-2ff95cb5b9d6/checkout_page.png', fullPage: true });

    console.log("Navigating to order receipt...");
    await page.goto('https://staging.bactiveph.com/checkout/order-received/299/?key=wc_order_4br0etZfYgDpW', { waitUntil: 'networkidle2' });
    await delay(3000);

    console.log("Taking order received screenshot...");
    await page.screenshot({ path: '/Users/johnbarry/.gemini/antigravity-ide/brain/292e8698-af8b-4cd4-bf56-2ff95cb5b9d6/order_received_cod.png', fullPage: true });

    await browser.close();
})();
