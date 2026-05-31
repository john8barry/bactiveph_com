import ftplib
import requests

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    
    with open('wp_install_plugins.php', 'rb') as f:
        ftp.storbinary('STOR wp_install_plugins.php', f)
        
    response = requests.get('https://bactiveph.com/wp_install_plugins.php')
    print("Response text:", response.text)
    
    ftp.delete('wp_install_plugins.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
