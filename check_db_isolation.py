import ftplib
import re

def get_db_name(ftp, filename):
    content = []
    try:
        ftp.retrlines(f'RETR {filename}', content.append)
    except Exception as e:
        return f"Error reading {filename}: {e}"
        
    text = "\\n".join(content)
    match = re.search(r"define\(\s*'DB_NAME',\s*'([^']+)'\s*\);", text)
    if match:
        return match.group(1)
    return "Not found"

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    
    prod_db = get_db_name(ftp, 'wp-config.php')
    print(f"Production DB Name: {prod_db}")
    
    stg_db = get_db_name(ftp, 'staging/wp-config.php')
    print(f"Staging DB Name: {stg_db}")
    
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
