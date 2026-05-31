import os
import env_loader  # loads .env
import ftplib
import re

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    
    ftp.cwd('staging')
    content = []
    ftp.retrlines('RETR wp-config.php', content.append)
    
    text = "\\n".join(content)
    match = re.search(r"define\(\s*'DB_NAME',\s*'([^']+)'\s*\);", text)
    if match:
        print(f"Staging DB Name: {match.group(1)}")
    else:
        print("DB Name not found in wp-config.php")
        
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
