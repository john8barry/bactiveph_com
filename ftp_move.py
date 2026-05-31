import env_loader  # auto-loads .env into os.environ
import ftplib
import os

def move_files():
    ftp = ftplib.FTP()
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login(os.environ.get('FTP_USER','bactive@bactiveph.com'), os.environ['FTP_PASSWORD'])

    # Move files from /wordpress to /
    ftp.cwd('/wordpress')
    
    # List all files and directories
    items = []
    ftp.retrlines('NLST', items.append)
    
    for item in items:
        if item not in ['.', '..']:
            print(f'Moving {item} to root...')
            ftp.rename(f'/wordpress/{item}', f'/{item}')
            
    print("Files moved successfully.")
    
    # Delete the zip and empty folder
    ftp.cwd('/')
    try:
        ftp.delete('latest.zip')
        ftp.rmd('wordpress')
    except Exception as e:
        print(f"Cleanup error (ignoring): {e}")

    ftp.quit()

if __name__ == '__main__':
    move_files()
