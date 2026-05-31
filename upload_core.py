import os
import env_loader  # auto-loads .env into os.environ
import ftplib

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login(os.environ.get('FTP_USER','bactive@bactiveph.com'), os.environ['FTP_PASSWORD'])
    
    with open('wp-activate.php', 'rb') as f:
        ftp.storbinary('STOR wp-activate.php', f)
        print("Uploaded wp-activate.php")
        
    with open('wp-comments-post.php', 'rb') as f:
        ftp.storbinary('STOR wp-comments-post.php', f)
        print("Uploaded wp-comments-post.php")
        
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
