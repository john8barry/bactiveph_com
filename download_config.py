import ftplib
import os

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    
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
