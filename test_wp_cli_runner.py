import env_loader  # auto-loads .env into os.environ
import ftplib
import os
import requests

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login(os.environ.get('FTP_USER','bactive@bactiveph.com'), os.environ['FTP_PASSWORD'])
    
    with open('test_wp_cli.php', 'rb') as f:
        ftp.storbinary('STOR test_wp_cli.php', f)
        
    response = requests.get('https://bactiveph.com/test_wp_cli.php')
    print("Response text:", response.text)
    
    ftp.delete('test_wp_cli.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
