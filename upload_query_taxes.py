import os
import ftplib
from dotenv import load_dotenv

load_dotenv()
ftp = ftplib.FTP()
ftp.connect(os.getenv('FTP_HOST', 'ftp.bactiveph.com'), int(os.getenv('FTP_PORT', 21)))
ftp.login(os.environ['CPANEL_USER'], os.environ['CPANEL_PASSWORD'])
ftp.cwd('staging.bactiveph.com')
with open('query_taxes.php', 'rb') as f:
    ftp.storbinary('STOR query_taxes.php', f)
ftp.quit()
print("query_taxes.php uploaded.")
