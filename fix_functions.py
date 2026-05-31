import os
import ftplib
from dotenv import load_dotenv

# Fix the syntax error locally
with open('functions_remote.php', 'r') as f:
    content = f.read()

content = content.replace('"input[name=\'payment_method\']"', '"input[name=\\"payment_method\\"]"')

with open('functions_remote_fixed.php', 'w') as f:
    f.write(content)

# Upload the fixed file
load_dotenv()
ftp = ftplib.FTP()
ftp.connect(os.getenv('FTP_HOST', 'ftp.bactiveph.com'), int(os.getenv('FTP_PORT', 21)))
ftp.login(os.environ['CPANEL_USER'], os.environ['CPANEL_PASSWORD'])
ftp.cwd('staging.bactiveph.com/wp-content/themes/blocksy-child')
with open('functions_remote_fixed.php', 'rb') as f:
    ftp.storbinary('STOR functions.php', f)
ftp.quit()
print("functions.php fixed and uploaded successfully.")
