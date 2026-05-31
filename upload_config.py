import ftplib

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    
    with open('.user.ini', 'rb') as f:
        ftp.storbinary('STOR .user.ini', f)
        print("Uploaded .user.ini")
        
    with open('.htaccess', 'rb') as f:
        ftp.storbinary('STOR .htaccess', f)
        print("Uploaded .htaccess")
        
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
