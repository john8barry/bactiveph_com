import ftplib

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    try:
        print("Themes:", ftp.nlst("staging/wp-content/themes/"))
        print("Blocksy child:", ftp.nlst("staging/wp-content/themes/blocksy-child/"))
    except Exception as e:
        print("Could not list blocksy child", e)
except Exception as e:
    print("FTP Error:", e)
finally:
    ftp.quit()
