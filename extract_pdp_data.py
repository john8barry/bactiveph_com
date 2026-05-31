import json
from bs4 import BeautifulSoup
import glob
import os

files = glob.glob("Buildout_Resources/pdp_*.html")

for f in files:
    with open(f, 'r') as file:
        soup = BeautifulSoup(file.read(), 'html.parser')
        
    print(f"--- Data for {os.path.basename(f)} ---")
    
    # Title
    title_el = soup.select_one('h1.product_title')
    title = title_el.get_text(strip=True) if title_el else "Not found"
    
    # Price
    price_el = soup.select_one('.price')
    price = price_el.get_text(strip=True) if price_el else "Not found"
    
    # Colors
    color_options = []
    # Woo Variation Swatches puts colors in a div with data-value or aria-label, etc.
    # Alternatively, look for standard select option for pa_colour
    color_select = soup.select_one('select#pa_colour')
    if color_select:
        opts = color_select.select('option')
        for opt in opts:
            if opt.get('value'):
                color_options.append(opt.get_text(strip=True))
    
    print(f"Name: {title}")
    print(f"Price: {price}")
    print(f"Colors: {', '.join(color_options)}")
    
    # Tabs
    # WooCommerce tabs are usually ul.tabs and div.panel
    tabs_content = {}
    tab_panels = soup.select('.woocommerce-Tabs-panel')
    for panel in tab_panels:
        tab_id = panel.get('id', '')
        if tab_id.startswith('tab-'):
            tab_name = tab_id.replace('tab-', '').replace('-', ' ').title()
            text = panel.get_text(separator=' ', strip=True)
            tabs_content[tab_name] = text

    print("Tabs:")
    for name, content in tabs_content.items():
        print(f"  [{name}]: {content}")
    print("\n")
