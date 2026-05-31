import env_loader  # auto-loads .env into os.environ
import ftplib
import os
import requests

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login(os.environ.get('FTP_USER','bactive@bactiveph.com'), os.environ['FTP_PASSWORD'])
    
    with open('wp_check_plugins.php', 'rb') as f:
        ftp.storbinary('STOR wp_check_plugins.php', f)
        
    response = requests.get('https://bactiveph.com/wp_check_plugins.php')
    print("Response:", response.text)
    
    ftp.delete('wp_check_plugins.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
