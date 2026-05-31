import env_loader  # loads .env
import os
import sys
import ftplib
import requests
import uuid
from dotenv import load_dotenv

load_dotenv()

command = " ".join(sys.argv[1:])

php_script = f"""<?php
echo shell_exec('{command} 2>&1');
?>"""

script_name = f"remote_exec_{uuid.uuid4().hex[:8]}.php"

with open(script_name, 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect(os.getenv('FTP_HOST', 'ftp.bactiveph.com'), int(os.getenv('FTP_PORT', 21)))
    ftp.login(os.environ['CPANEL_USER'], os.environ['CPANEL_PASSWORD'])
    ftp.cwd('staging.bactiveph.com')
    with open(script_name, 'rb') as f:
        ftp.storbinary(f'STOR {script_name}', f)
    
    response = requests.get(f'https://staging.bactiveph.com/{script_name}?nocache=1', verify=False, auth=('bactive_team', 'BActive_Stg_2026!'))
    print(response.text)
    
    ftp.delete(script_name)
except Exception as e:
    print("Error:", e)
finally:
    try:
        ftp.quit()
    except:
        pass
    os.remove(script_name)
