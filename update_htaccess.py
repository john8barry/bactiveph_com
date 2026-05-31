import os
import env_loader  # loads .env
import ftplib

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    
    # Download existing .htaccess
    with open('.htaccess_main', 'wb') as f:
        ftp.retrbinary('RETR .htaccess', f.write)
    
    with open('.htaccess_main', 'r') as f:
        content = f.read()
    
    # Modify it to ignore /staging/
    new_content = content.replace("RewriteRule ^index\.php$ - [L]", "RewriteRule ^index\.php$ - [L]\\nRewriteCond %{REQUEST_URI} !^/staging/")
    
    with open('.htaccess_main_new', 'w') as f:
        f.write(new_content)
        
    # Upload it back
    with open('.htaccess_main_new', 'rb') as f:
        ftp.storbinary('STOR .htaccess', f)
        
    print(".htaccess updated successfully!")
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
