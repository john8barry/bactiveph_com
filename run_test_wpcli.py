import os
import env_loader  # auto-loads .env into os.environ
import ftplib
import requests

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login(os.environ.get('FTP_USER','bactive@bactiveph.com'), os.environ['FTP_PASSWORD'])
    
    with open('test_wpcli.php', 'rb') as f:
        ftp.storbinary('STOR test_wpcli.php', f)
        
    response = requests.get('https://bactiveph.com/test_wpcli.php')
    print("Response text:", response.text)
    
    ftp.delete('test_wpcli.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
