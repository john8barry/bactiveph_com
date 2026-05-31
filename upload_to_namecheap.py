import os
import env_loader  # auto-loads .env into os.environ
import ftplib

ftp = ftplib.FTP()
ftp.connect('ftp.bactiveph.com', 21)
ftp.login(os.environ.get('FTP_USER','bactive@bactiveph.com'), os.environ['FTP_PASSWORD'])

ftp.cwd('/') # assuming root is public_html equivalent

with open('deploy.zip', 'rb') as f:
    ftp.storbinary('STOR deploy.zip', f)

with open('wp-config.php', 'rb') as f:
    ftp.storbinary('STOR wp-config.php', f)

with open('extract.php', 'rb') as f:
    ftp.storbinary('STOR extract.php', f)

ftp.quit()
print("Upload complete.")
