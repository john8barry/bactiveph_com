import os
import env_loader  # auto-loads .env into os.environ
import ftplib
import requests

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login(os.environ.get('FTP_USER','bactive@bactiveph.com'), os.environ['FTP_PASSWORD'])
    
    with open('wp_ia_setup.php', 'rb') as f:
        ftp.storbinary('STOR wp_ia_setup.php', f)
        
    response = requests.get('https://bactiveph.com/wp_ia_setup.php')
    print("Response text:", response.text)
    
    ftp.delete('wp_ia_setup.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
