import ftplib

ftp = ftplib.FTP()
ftp.connect('ftp.bactiveph.com', 21)
ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')

ftp.cwd('/') # assuming root is public_html equivalent

with open('deploy.zip', 'rb') as f:
    ftp.storbinary('STOR deploy.zip', f)

with open('wp-config.php', 'rb') as f:
    ftp.storbinary('STOR wp-config.php', f)

with open('extract.php', 'rb') as f:
    ftp.storbinary('STOR extract.php', f)

ftp.quit()
print("Upload complete.")
