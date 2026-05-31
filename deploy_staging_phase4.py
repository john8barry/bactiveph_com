import ftplib
import os

local_dir = "/Users/johnbarry/Documents/Antigravity/bactiveph_com/wordpress/wp-content/themes/blocksy-child"
remote_dir = "staging/wp-content/themes/blocksy-child"

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    
    # Try creating the root directory
    try:
        ftp.mkd(remote_dir)
    except:
        pass

    for root, dirs, files in os.walk(local_dir):
        # Create directories
        for d in dirs:
            if d == '.git': continue
            local_path = os.path.join(root, d)
            rel_path = os.path.relpath(local_path, local_dir)
            remote_path = f"{remote_dir}/{rel_path}".replace('\\', '/')
            try:
                ftp.mkd(remote_path)
            except:
                pass
                
        # Upload files
        for f in files:
            if f == '.DS_Store' or '.git' in root: continue
            local_path = os.path.join(root, f)
            rel_path = os.path.relpath(local_path, local_dir)
            remote_path = f"{remote_dir}/{rel_path}".replace('\\', '/')
            with open(local_path, 'rb') as file_obj:
                print(f"Uploading {local_path} to {remote_path}")
                ftp.storbinary(f'STOR {remote_path}', file_obj)
                
    print("Deployment successful.")
except Exception as e:
    print("FTP Error:", e)
finally:
    ftp.quit()
