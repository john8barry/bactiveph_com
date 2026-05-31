import os
import env_loader  # auto-loads .env into os.environ
import ftplib
import requests

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login(os.environ.get('FTP_USER','bactive@bactiveph.com'), os.environ['FTP_PASSWORD'])
    with open('step4.php', 'rb') as f:
        ftp.storbinary('STOR staging/step4.php', f)
    
    # Bypass staging domain DNS issue by accessing via main domain subfolder
    response = requests.get('https://bactiveph.com/staging/step4.php', auth=('bactive_team', 'BActive_Stg_2026!'))
    print("PHP Output:\n", response.text)
    
    ftp.delete('staging/step4.php')
except Exception as e:
    print("FTP Error:", e)
finally:
    ftp.quit()
