import os
import ftplib
import shutil
from dotenv import load_dotenv

load_dotenv()

local_theme_dir = 'wp-content/themes/blocksy-child'
os.makedirs(local_theme_dir, exist_ok=True)

ftp = ftplib.FTP()
ftp.connect(os.getenv('FTP_HOST', 'ftp.bactiveph.com'), int(os.getenv('FTP_PORT', 21)))
ftp.login(os.environ['CPANEL_USER'], os.environ['CPANEL_PASSWORD'])
ftp.cwd('staging.bactiveph.com/wp-content/themes/blocksy-child')

with open(f'{local_theme_dir}/functions.php', 'wb') as f:
    ftp.retrbinary('RETR functions.php', f.write)

try:
    with open(f'{local_theme_dir}/footer.php', 'wb') as f:
        ftp.retrbinary('RETR footer.php', f.write)
except Exception as e:
    print(f"No footer.php found or error: {e}")

try:
    os.makedirs(f'{local_theme_dir}/template-parts', exist_ok=True)
    ftp.cwd('template-parts')
    with open(f'{local_theme_dir}/template-parts/footer.php', 'wb') as f:
        ftp.retrbinary('RETR footer.php', f.write)
except Exception as e:
    print(f"No template-parts/footer.php found or error: {e}")

ftp.quit()
print("Files synced to local.")
