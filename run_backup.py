import ftplib
import requests

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    
    with open('wp_backup.php', 'rb') as f:
        ftp.storbinary('STOR wp_backup.php', f)
        
    response = requests.get('https://bactiveph.com/wp_backup.php')
    print("Response text:", response.text)
    
    ftp.delete('wp_backup.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
