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

    console.log("Navigating to simple product add-to-cart...");
    // 36 is the variable product, let's just go to the shop page and click the first add to cart button using JS!
    await page.goto('https://staging.bactiveph.com/shop/', { waitUntil: 'networkidle2' });
    
    await page.evaluate(() => {
        // Find any product link
        const link = document.querySelector('a.woocommerce-LoopProduct-link');
        if (link) window.location.href = link.href;
    });
    
    await delay(3000);
    
    // Now on product page, select all selects
    await page.evaluate(() => {
        const selects = document.querySelectorAll('select');
        selects.forEach(s => {
            if (s.options.length > 1) s.selectedIndex = 1;
        });
        const swatches = document.querySelectorAll('.vi-wpvs-option');
        swatches.forEach(s => s.click());
    });
    
    await delay(1000);
    
    // Click add to cart
    await page.evaluate(() => {
        const btn = document.querySelector('.single_add_to_cart_button');
        if (btn) btn.click();
    });
    
    await delay(3000);
    
    // Check cart
    await page.goto('https://staging.bactiveph.com/cart/', { waitUntil: 'networkidle2' });
    const cartHtml = await page.evaluate(() => document.body.innerHTML);
    if (cartHtml.includes('cart-empty')) {
        console.log("Cart is still empty!");
    } else {
        console.log("Item added to cart!");
    }
    
    await browser.close();
})();
