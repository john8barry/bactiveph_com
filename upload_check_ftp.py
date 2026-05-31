import os
import env_loader  # auto-loads .env into os.environ
import ftplib

ftp = ftplib.FTP()
ftp.connect('ftp.bactiveph.com', 21)
ftp.login(os.environ.get('FTP_USER','bactive@bactiveph.com'), os.environ['FTP_PASSWORD'])

try:
    ftp.cwd('public_html')
except ftplib.error_perm:
    pass

with open('check_theme.php', 'rb') as f:
    ftp.storbinary('STOR check_theme.php', f)

ftp.quit()
