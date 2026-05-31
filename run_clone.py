import ftplib
import requests

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    
    with open('wp_clone.php', 'rb') as f:
        ftp.storbinary('STOR wp_clone.php', f)
        
    print("Running clone script...")
    response = requests.get('https://bactiveph.com/wp_clone.php')
    print("Response text:", response.text)
    
    ftp.delete('wp_clone.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
