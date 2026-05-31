import env_loader  # auto-loads .env into os.environ
import ftplib
import os

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login(os.environ.get('FTP_USER','bactive@bactiveph.com'), os.environ['FTP_PASSWORD'])
    
    # Download .user.ini
    try:
        with open('.user.ini', 'wb') as f:
            ftp.retrbinary('RETR .user.ini', f.write)
        print("Downloaded .user.ini")
    except Exception as e:
        print(".user.ini not found or error:", e)
        
    # Download .htaccess
    try:
        with open('.htaccess', 'wb') as f:
            ftp.retrbinary('RETR .htaccess', f.write)
        print("Downloaded .htaccess")
    except Exception as e:
        print(".htaccess not found or error:", e)
        
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
