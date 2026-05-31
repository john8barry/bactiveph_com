import ftplib
import os
import requests

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    
    with open('wp_check_plugins.php', 'rb') as f:
        ftp.storbinary('STOR wp_check_plugins.php', f)
        
    response = requests.get('https://bactiveph.com/wp_check_plugins.php')
    print("Response:", response.text)
    
    ftp.delete('wp_check_plugins.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
