const puppeteer = require('puppeteer');

const delay = ms => new Promise(res => setTimeout(res, ms));

(async () => {
    const browser = await puppeteer.launch({
        headless: "new",
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 1000 });
    
    await page.authenticate({'username': 'bactive_team', 'password': 'BActive_Stg_2026!'});

    console.log("Adding variation 38 to cart...");
    await page.goto('https://staging.bactiveph.com/?add-to-cart=38', { waitUntil: 'networkidle2' });
    await delay(3000);

    console.log("Navigating to cart to take drawer screenshot (if cart is a drawer, otherwise just cart page)...");
    await page.goto('https://staging.bactiveph.com/', { waitUntil: 'networkidle2' });
    try {
        await page.click('.ct-cart-item');
        await delay(2000);
    } catch(e){}
    await page.screenshot({ path: '/Users/johnbarry/.gemini/antigravity-ide/brain/292e8698-af8b-4cd4-bf56-2ff95cb5b9d6/cart_drawer.png' });

    console.log("Navigating to checkout...");
    await page.goto('https://staging.bactiveph.com/checkout/', { waitUntil: 'networkidle2' });
    await delay(3000);

    console.log("Taking checkout page screenshot...");
    await page.screenshot({ path: '/Users/johnbarry/.gemini/antigravity-ide/brain/292e8698-af8b-4cd4-bf56-2ff95cb5b9d6/checkout_page.png', fullPage: true });

    console.log("Filling checkout form...");
    await page.type('#billing_first_name', 'Test');
    await page.type('#billing_last_name', 'User');
    await page.type('#billing_address_1', '123 Test St');
    await page.type('#billing_city', 'Davao City');
    try {
        await page.select('#billing_state', 'PH50'); 
    } catch(e){}
    await page.type('#billing_postcode', '8000');
    await page.type('#billing_phone', '09123456789');
    await page.type('#billing_email', 'test@example.com');
    
    await page.evaluate(() => {
        if (window.jQuery) jQuery(document.body).trigger('update_checkout');
    });
    await delay(4000);

    console.log("Taking checkout populated screenshot...");
    await page.screenshot({ path: '/Users/johnbarry/.gemini/antigravity-ide/brain/292e8698-af8b-4cd4-bf56-2ff95cb5b9d6/checkout_cod.png', fullPage: true });

    try {
        await page.click('label[for="payment_method_cod"]');
        await delay(1000);
    } catch(e){}

    console.log("Placing COD order...");
    try {
        await page.click('#place_order');
        await page.waitForNavigation({waitUntil: 'networkidle2', timeout: 15000});
        console.log("Taking order received screenshot...");
        await page.screenshot({ path: '/Users/johnbarry/.gemini/antigravity-ide/brain/292e8698-af8b-4cd4-bf56-2ff95cb5b9d6/order_received_cod.png', fullPage: true });
    } catch (e) {
        console.log("Order placement failed or timed out.");
    }
    
    await browser.close();
})();
