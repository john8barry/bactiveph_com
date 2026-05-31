import os
import ftplib
from dotenv import load_dotenv

load_dotenv()
ftp = ftplib.FTP()
ftp.connect(os.getenv('FTP_HOST', 'ftp.bactiveph.com'), int(os.getenv('FTP_PORT', 21)))
ftp.login(os.environ['CPANEL_USER'], os.environ['CPANEL_PASSWORD'])
ftp.cwd('staging.bactiveph.com/wp-content/themes/blocksy-child')
with open('wp-content/themes/blocksy-child/functions.php', 'rb') as f:
    ftp.storbinary('STOR functions.php', f)
ftp.quit()
print("functions.php uploaded.")
