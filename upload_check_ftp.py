import ftplib

ftp = ftplib.FTP()
ftp.connect('ftp.bactiveph.com', 21)
ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')

try:
    ftp.cwd('public_html')
except ftplib.error_perm:
    pass

with open('check_theme.php', 'rb') as f:
    ftp.storbinary('STOR check_theme.php', f)

ftp.quit()
