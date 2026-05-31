import ftplib

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    with open('index_check.php', 'wb') as f:
        ftp.retrbinary('RETR staging/index.php', f.write)
    
    with open('index_check.php', 'r') as f:
        print("--- staging/index.php ---")
        print(f.read())
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
