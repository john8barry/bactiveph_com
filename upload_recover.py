import env_loader  # auto-loads .env into os.environ
import ftplib
import os
import requests

ftp = ftplib.FTP('ftp.bactiveph.com')
ftp.login(os.environ.get('FTP_USER','bactive@bactiveph.com'), os.environ['FTP_PASSWORD'])

# Check if we are in the root where wp-config.php is
files = ftp.nlst()
print("Root files:", files)

# Find public_html or whatever the root is
if 'public_html' in files:
    ftp.cwd('public_html')
elif 'www' in files:
    ftp.cwd('www')
elif 'bactiveph.com' in files:
    ftp.cwd('bactiveph.com')

print("Uploading recover.php...")
with open('/Users/johnbarry/Documents/Antigravity/bactiveph_com/recover.php', 'rb') as f:
    ftp.storbinary('STOR recover.php', f)

print("Triggering recover.php...")
r = requests.get('https://bactiveph.com/recover.php', verify=False)
print("Response code:", r.status_code)
print("Response text:", r.text)

print("Deleting recover.php...")
try:
    ftp.delete('recover.php')
except Exception as e:
    print("Delete failed:", e)

ftp.quit()
