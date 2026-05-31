import ftplib
import os
import requests

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    
    with open('wp_setup.php', 'rb') as f:
        ftp.storbinary('STOR wp_setup.php', f)
        
    print("Uploaded wp_setup.php")
    
    response = requests.get('https://bactiveph.com/wp_setup.php')
    print("Response status:", response.status_code)
    print("Response text:", response.text)
    
    ftp.delete('wp_setup.php')
    print("Deleted wp_setup.php")
    
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
