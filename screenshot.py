import asyncio
from playwright.async_api import async_playwright

async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        page = await browser.new_page()
        # Pass basic auth in URL
        await page.goto('https://bactive_team:BActive_Stg_2026!@staging.bactiveph.com/product/the-court-dress/')
        await page.screenshot(path='Buildout_Resources/staging_pdp.png', full_page=True)
        await browser.close()

asyncio.run(main())
