import os
import env_loader  # auto-loads .env into os.environ
import ftplib

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login(os.environ.get('FTP_USER','bactive@bactiveph.com'), os.environ['FTP_PASSWORD'])
    
    with open('.user.ini', 'rb') as f:
        ftp.storbinary('STOR .user.ini', f)
        print("Uploaded .user.ini")
        
    with open('.htaccess', 'rb') as f:
        ftp.storbinary('STOR .htaccess', f)
        print("Uploaded .htaccess")
        
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
