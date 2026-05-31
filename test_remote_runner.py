import ftplib
import os
import requests

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    
    with open('test_remote.php', 'rb') as f:
        ftp.storbinary('STOR test_remote.php', f)
        
    print("Uploaded test script.")
    
    response = requests.get('https://bactiveph.com/test_remote.php')
    print("Response status:", response.status_code)
    print("Response text:", response.text)
    
    ftp.delete('test_remote.php')
    print("Deleted test script.")
    
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
