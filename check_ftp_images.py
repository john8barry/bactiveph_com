import ftplib

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    try:
        print("Images in product_images/:")
        print(ftp.nlst("staging/product_images/"))
    except Exception as e:
        print("Could not list product_images", e)
except Exception as e:
    print("FTP Error:", e)
finally:
    ftp.quit()
