import ftplib

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    
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
