import os
import env_loader  # loads .env
import ftplib
import requests

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    try:
        with open('staging_htaccess', 'wb') as f:
            ftp.retrbinary('RETR staging/.htaccess', f.write)
        with open('staging_htaccess', 'r') as f:
            print("--- staging/.htaccess ---")
            print(f.read())
    except Exception as e:
        print("No staging/.htaccess found or error:", e)
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
