import os
import ftplib
from dotenv import load_dotenv

load_dotenv()
ftp = ftplib.FTP()
ftp.connect(os.getenv('FTP_HOST', 'ftp.bactiveph.com'), int(os.getenv('FTP_PORT', 21)))
ftp.login(os.environ['CPANEL_USER'], os.environ['CPANEL_PASSWORD'])
ftp.cwd('staging.bactiveph.com/wp-content/themes/blocksy-child')
with open('functions_remote.php', 'wb') as f:
    ftp.retrbinary('RETR functions.php', f.write)
ftp.quit()
print("functions_remote.php downloaded successfully.")
