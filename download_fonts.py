import requests
import re
import os

def download_font(family, weights, out_dir):
    url = f"https://fonts.googleapis.com/css2?family={family}:wght@{weights}&display=swap"
    headers = {"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"}
    res = requests.get(url, headers=headers)
    css = res.text
    
    os.makedirs(out_dir, exist_ok=True)
    
    # parse url() and font-weight
    for block in css.split('}'):
        if not block.strip(): continue
        weight_m = re.search(r'font-weight:\s*(\d+)', block)
        url_m = re.search(r'url\((.*?)\)', block)
        if weight_m and url_m:
            w = weight_m.group(1)
            f_url = url_m.group(1).strip("'\"")
            fname = f"{family.split(':')[0].replace('+','').lower()}-{w}.woff2"
            fpath = os.path.join(out_dir, fname)
            if not os.path.exists(fpath):
                print(f"Downloading {fname}...")
                with open(fpath, 'wb') as f:
                    f.write(requests.get(f_url).content)
            
            # replace url in css
            css = css.replace(f_url, f"../fonts/{fname}")
            
    with open(os.path.join(out_dir, '..', 'css', f'{family.split(":")[0].replace("+","").lower()}-fonts.css'), 'w') as f:
        f.write(css)

download_font("Inter", "400;500;600", "wordpress/wp-content/themes/blocksy-child/assets/fonts")
download_font("Fraunces", "400;500;600", "wordpress/wp-content/themes/blocksy-child/assets/fonts")
