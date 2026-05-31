import os
import env_loader  # loads .env
import ftplib

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    
    with open('error_log.txt', 'wb') as f:
        ftp.retrbinary('RETR staging/error_log', f.write)
        
    with open('error_log.txt', 'r') as f:
        lines = f.readlines()
        print("Last 20 lines of error_log:")
        for line in lines[-20:]:
            print(line.strip())
            
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
