import os
import env_loader  # loads .env
import ftplib

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    
    print("\\n--- PARENT DIRECTORY (cwd) ---")
    print(ftp.pwd())
    ftp.cwd('..')
    print(ftp.pwd())
    ftp.retrlines('LIST')

except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
