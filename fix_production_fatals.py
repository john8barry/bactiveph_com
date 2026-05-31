import os
import env_loader  # loads .env
import ftplib
import re
import requests

def fix_production():
    print("Fixing production Wordfence WAF...")
    ftp = ftplib.FTP()
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])

    # Clean .user.ini
    try:
        with open('.user.ini', 'wb') as f:
            ftp.retrbinary('RETR .user.ini', f.write)
        with open('.user.ini', 'r') as f:
            user_ini = f.read()
        
        user_ini_cleaned = re.sub(r"auto_prepend_file\s*=\s*['\"].*?wordfence-waf\.php['\"]", ";auto_prepend_file = ''", user_ini)
        
        with open('.user.ini', 'w') as f:
            f.write(user_ini_cleaned)
        with open('.user.ini', 'rb') as f:
            ftp.storbinary('STOR .user.ini', f)
        print("Cleaned .user.ini")
    except Exception as e:
        print(f"Error processing .user.ini: {e}")

    # Clean .htaccess
    try:
        with open('.htaccess', 'wb') as f:
            ftp.retrbinary('RETR .htaccess', f.write)
        with open('.htaccess', 'r') as f:
            htaccess = f.read()
        
        htaccess_cleaned = re.sub(r"# Wordfence WAF.*?# END Wordfence WAF\n?", "", htaccess, flags=re.DOTALL)
        
        with open('.htaccess', 'w') as f:
            f.write(htaccess_cleaned)
        with open('.htaccess', 'rb') as f:
            ftp.storbinary('STOR .htaccess', f)
        print("Cleaned .htaccess")
    except Exception as e:
        print(f"Error processing .htaccess: {e}")
        
    ftp.quit()

    print("Hitting site to generate logs...")
    requests.get("https://bactiveph.com/", verify=False)
    requests.get("https://bactiveph.com/shop/", verify=False)

    print("Fetching error_log...")
    ftp = ftplib.FTP()
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    try:
        with open('error_log', 'wb') as f:
            ftp.retrbinary('RETR error_log', f.write)
        with open('error_log', 'r') as f:
            lines = f.readlines()
            print("--- LAST 20 LINES OF ERROR LOG ---")
            for line in lines[-20:]:
                print(line.strip())
    except Exception as e:
        print(f"Error fetching error_log: {e}")

    # Also upload a script to run wp core verify-checksums
    php_script = "<?php echo shell_exec('wp core verify-checksums 2>&1'); ?>"
    with open('verify_checksums.php', 'w') as f:
        f.write(php_script)
    with open('verify_checksums.php', 'rb') as f:
        ftp.storbinary('STOR verify_checksums.php', f)
    ftp.quit()

    print("Running verify-checksums...")
    res = requests.get("https://bactiveph.com/verify_checksums.php", verify=False)
    print("--- WP CORE VERIFY CHECKSUMS ---")
    print(res.text)
    
    # cleanup
    ftp = ftplib.FTP()
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    ftp.delete('verify_checksums.php')
    ftp.quit()

if __name__ == "__main__":
    fix_production()
