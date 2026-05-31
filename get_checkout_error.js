const puppeteer = require('puppeteer');
const delay = ms => new Promise(res => setTimeout(res, ms));
(async () => {
    const browser = await puppeteer.launch({headless: false, args: ['--no-sandbox', '--disable-setuid-sandbox']});
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 1000 });
    await page.authenticate({'username': 'bactive_team', 'password': 'BActive_Stg_2026!'});
    await page.goto('https://staging.bactiveph.com/?add-to-cart=36&variation_id=38', { waitUntil: 'networkidle2' });
    await page.goto('https://staging.bactiveph.com/checkout/', { waitUntil: 'networkidle2' });
    await delay(3000);
    await page.type('#billing_first_name', 'Test');
    await page.type('#billing_last_name', 'User');
    await page.type('#billing_address_1', '123 Test St');
    await page.type('#billing_city', 'Davao City');
    
    try {
        await page.evaluate(() => {
            if (window.jQuery) {
                jQuery('#billing_state').val('PH50').trigger('change');
            } else {
                document.querySelector('#billing_state').value = 'PH50';
                document.querySelector('#billing_state').dispatchEvent(new Event('change'));
            }
        });
    } catch(e){ console.log(e); }
    
    await page.type('#billing_postcode', '8000');
    await page.type('#billing_phone', '09123456789');
    await page.type('#billing_email', 'test@example.com');
    await delay(4000);
    
    try {
        await page.evaluate(() => {
            if (document.querySelector('#terms')) {
                document.querySelector('#terms').click();
            }
            if (document.querySelector('#place_order')) {
                document.querySelector('#place_order').click();
            }
        });
    } catch (e) {}

    await delay(5000); // wait for validation errors
    
    const errors = await page.evaluate(() => {
        let errs = [];
        document.querySelectorAll('.woocommerce-error li').forEach(li => errs.push(li.innerText));
        return errs;
    });
    
    console.log("Checkout errors found:", errors);
    await browser.close();
})();
