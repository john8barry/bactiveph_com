import env_loader  # auto-loads .env into os.environ
import ftplib
import os
import requests

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login(os.environ.get('FTP_USER','bactive@bactiveph.com'), os.environ['FTP_PASSWORD'])
    
    with open('wp_setup.php', 'rb') as f:
        ftp.storbinary('STOR wp_setup.php', f)
        
    print("Uploaded wp_setup.php")
    
    response = requests.get('https://bactiveph.com/wp_setup.php')
    print("Response status:", response.status_code)
    print("Response text:", response.text)
    
    ftp.delete('wp_setup.php')
    print("Deleted wp_setup.php")
    
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
