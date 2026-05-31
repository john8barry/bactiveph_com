import os
import sys
import ftplib
import requests
from dotenv import load_dotenv

load_dotenv()

command = " ".join(sys.argv[1:])

php_script = f"""<?php
echo shell_exec('{command} 2>&1');
?>"""

with open('remote_exec.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect(os.getenv('FTP_HOST'), int(os.getenv('FTP_PORT', 21)))
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    with open('remote_exec.php', 'rb') as f:
        ftp.storbinary('STOR remote_exec.php', f)
    
    response = requests.get('https://bactiveph.com/remote_exec.php')
    print(response.text)
    
    ftp.delete('remote_exec.php')
except Exception as e:
    print("Error:", e)
finally:
    try:
        ftp.quit()
    except:
        pass
    os.remove('remote_exec.php')
