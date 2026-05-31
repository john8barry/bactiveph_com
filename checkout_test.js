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
    await page.screenshot({ path: '/Users/johnbarry/Documents/Antigravity/bactiveph_com/Buildout_Resources/cart_drawer.png', fullPage: true });

    console.log("Navigating to checkout...");
    await page.goto('https://staging.bactiveph.com/checkout/', { waitUntil: 'networkidle2' });
    await delay(3000);

    console.log("Filling checkout form...");
    await page.type('#billing_first_name', 'Test');
    await page.type('#billing_last_name', 'User');
    await page.type('#billing_address_1', '123 Test St');
    await page.type('#billing_city', 'Davao City');
    try {
        await page.evaluate(() => {
            if (window.jQuery) {
                jQuery('#billing_state').val('DAS').trigger('change');
            } else {
                document.querySelector('#billing_state').value = 'DAS';
                document.querySelector('#billing_state').dispatchEvent(new Event('change'));
            }
        });
    } catch(e){ console.log(e); }
    await page.type('#billing_postcode', '8000');
    await page.type('#billing_phone', '09123456789');
    await page.type('#billing_email', 'test@example.com');

    // Wait for AJAX update
    await delay(6000); // 6 seconds for shipping refresh

    // Select COD
    try {
        await page.evaluate(() => {
            if (document.querySelector('#payment_method_cod')) {
                document.querySelector('#payment_method_cod').click();
            }
        });
        await delay(2000);
    } catch(e){}

    console.log("Taking checkout populated screenshot...");
    await page.screenshot({ path: '/Users/johnbarry/Documents/Antigravity/bactiveph_com/Buildout_Resources/checkout_page.png', fullPage: true });

    console.log("Placing COD order...");
    try {
        await page.evaluate(() => {
            if (document.querySelector('#terms')) {
                document.querySelector('#terms').click();
            }
        });
        await delay(1000);

        // Click place order button
        await page.evaluate(() => {
            if (document.querySelector('#place_order')) {
                document.querySelector('#place_order').click();
            }
        });
        
        await page.waitForNavigation({waitUntil: 'networkidle2', timeout: 30000});
        console.log("Taking order received screenshot...");
        await page.screenshot({ path: '/Users/johnbarry/Documents/Antigravity/bactiveph_com/Buildout_Resources/order_received_cod.png', fullPage: true });
    } catch (e) {
        console.log("Order placement failed or timed out: " + e.message);
        // Take a screenshot of the failure
        await page.screenshot({ path: '/Users/johnbarry/Documents/Antigravity/bactiveph_com/Buildout_Resources/order_failed.png', fullPage: true });
    }
    
    await browser.close();
})();
