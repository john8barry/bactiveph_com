import requests
import json
import sys
import os

API_TOKEN = os.environ.get("CF_API_TOKEN", "")
headers = {
    "Authorization": f"Bearer {API_TOKEN}",
    "Content-Type": "application/json"
}

def update_setting(zone_id, setting_name, value):
    url = f"https://api.cloudflare.com/client/v4/zones/{zone_id}/settings/{setting_name}"
    payload = {"value": value}
    res = requests.patch(url, headers=headers, json=payload)
    if res.status_code == 200:
        print(f"✅ Successfully updated {setting_name} to {value}")
    else:
        print(f"❌ Failed to update {setting_name}: {res.text}")

def main():
    # 1. Get Zone ID
    res = requests.get("https://api.cloudflare.com/client/v4/zones?name=bactiveph.com", headers=headers)
    data = res.json()
    if "result" in data and len(data["result"]) > 0:
        zone_id = data["result"][0]["id"]
        print(f"Zone ID: {zone_id}")
    else:
        print("Zone not found:", data)
        sys.exit(1)

    # 2. Update Settings
    print("\n--- Updating Zone Settings ---")
    update_setting(zone_id, "ssl", "strict")
    update_setting(zone_id, "brotli", "on")
    update_setting(zone_id, "http3", "on")
    update_setting(zone_id, "early_hints", "on")
    update_setting(zone_id, "always_use_https", "on")

    # 3. Create WAF Custom Rule for sensitive files
    print("\n--- Updating WAF Rules ---")
    
    # Get the http_request_firewall_custom ruleset
    res = requests.get(f"https://api.cloudflare.com/client/v4/zones/{zone_id}/rulesets", headers=headers)
    rulesets = res.json().get("result", [])
    
    waf_ruleset_id = None
    for rs in rulesets:
        if rs.get("phase") == "http_request_firewall_custom":
            waf_ruleset_id = rs.get("id")
            break

    block_rule = {
        "action": "block",
        "expression": '(http.request.uri.path contains "/xmlrpc.php") or (http.request.uri.path contains "wp-config.php") or (http.request.uri.path contains "/.env") or (http.request.uri.path.extension eq "log")',
        "description": "Block sensitive WordPress and config files",
        "enabled": True
    }

    if waf_ruleset_id:
        # Ruleset exists, append the rule
        print(f"Found WAF Custom Ruleset: {waf_ruleset_id}")
        # Fetch existing rules
        r = requests.get(f"https://api.cloudflare.com/client/v4/zones/{zone_id}/rulesets/{waf_ruleset_id}", headers=headers)
        existing_rules = r.json().get("result", {}).get("rules", [])
        
        # Check if rule already exists
        rule_exists = False
        for rule in existing_rules:
            if rule.get("description") == block_rule["description"]:
                rule_exists = True
                print("Rule already exists. Skipping.")
                break
                
        if not rule_exists:
            existing_rules.append(block_rule)
            payload = {"rules": existing_rules}
            r_update = requests.put(f"https://api.cloudflare.com/client/v4/zones/{zone_id}/rulesets/{waf_ruleset_id}", headers=headers, json=payload)
            if r_update.status_code == 200:
                print("✅ Successfully appended WAF rule to block sensitive files.")
            else:
                print(f"❌ Failed to update WAF ruleset: {r_update.text}")
    else:
        # Ruleset doesn't exist, create it
        print("WAF Custom Ruleset not found, creating a new one.")
        payload = {
            "name": "default",
            "kind": "zone",
            "phase": "http_request_firewall_custom",
            "rules": [block_rule]
        }
        r_create = requests.post(f"https://api.cloudflare.com/client/v4/zones/{zone_id}/rulesets", headers=headers, json=payload)
        if r_create.status_code == 200:
            print("✅ Successfully created WAF ruleset and added rule to block sensitive files.")
        else:
            print(f"❌ Failed to create WAF ruleset: {r_create.text}")

if __name__ == "__main__":
    main()
