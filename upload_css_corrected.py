import os
import env_loader
import ftplib
from dotenv import load_dotenv

load_dotenv()

ftp = ftplib.FTP()
try:
    ftp.connect(os.getenv('FTP_HOST', 'ftp.bactiveph.com'), int(os.getenv('FTP_PORT', 21)))
    # Note: Using FTP_USER and FTP_PASSWORD which we know places us at the docroot of bactiveph.com
    ftp.login(os.environ.get('FTP_USER','bactive@bactiveph.com'), os.environ['FTP_PASSWORD'])
    
    # Upload to staging
    with open('custom.css', 'rb') as f:
        try:
            ftp.storbinary('STOR staging/wp-content/themes/blocksy-child/custom.css', f)
            print("Uploaded custom.css to STAGING")
        except Exception as e:
            print("Staging upload failed:", e)
            
    # Upload to prod
    with open('custom.css', 'rb') as f:
        try:
            ftp.storbinary('STOR wp-content/themes/blocksy-child/custom.css', f)
            print("Uploaded custom.css to PROD")
        except Exception as e:
            print("Prod upload failed:", e)
        
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
