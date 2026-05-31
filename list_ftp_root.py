import ftplib

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    print("--- ROOT ---")
    ftp.retrlines('LIST')
    
    print("\\n--- STAGING ---")
    try:
        ftp.retrlines('LIST staging')
    except Exception as e:
        print(f"Error listing staging: {e}")
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
