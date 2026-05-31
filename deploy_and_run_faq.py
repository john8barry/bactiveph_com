import env_loader
import os
import ftplib
import requests

script_name = "update_faq.php"

ftp = ftplib.FTP()
try:
    ftp.connect(os.getenv('FTP_HOST', 'ftp.bactiveph.com'), int(os.getenv('FTP_PORT', 21)))
    ftp.login(os.environ['CPANEL_USER'], os.environ['CPANEL_PASSWORD'])
    ftp.cwd('staging.bactiveph.com')
    with open(script_name, 'rb') as f:
        ftp.storbinary(f'STOR {script_name}', f)
    
    print("Uploaded script.")
    
    response = requests.get(f'https://staging.bactiveph.com/{script_name}?nocache=1', verify=False, auth=('bactive_team', 'BActive_Stg_2026!'))
    print("Response text:", response.text)
    
    ftp.delete(script_name)
    print("Cleaned up script.")
except Exception as e:
    print("Error:", e)
finally:
    try:
        ftp.quit()
    except:
        pass
