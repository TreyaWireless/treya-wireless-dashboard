#!/usr/bin/env python3
# ============================================================
#  Treya Wireless - Zabbix Custom Template Auto-Importer
#  Run as: python3 import_template.py
# ============================================================
import os
import json
import sys
import urllib.request

url = 'http://localhost/api_jsonrpc.php'
headers = {'Content-Type': 'application/json-rpc'}

# 1. Log in to Zabbix API
print("Logging in to Zabbix API...")
login_payload = {
    "jsonrpc": "2.0",
    "method": "user.login",
    "params": {
        "username": "treya",
        "password": "redhat"
    },
    "id": 1
}

try:
    req = urllib.request.Request(url, data=json.dumps(login_payload).encode('utf-8'), headers=headers)
    with urllib.request.urlopen(req) as response:
        res = json.loads(response.read().decode('utf-8'))
        if "error" in res:
            print(f"Login failed: {res['error']['data']}")
            sys.exit(1)
        auth_token = res["result"]
        print("Login successful. Token obtained.")
except Exception as e:
    print(f"Connection failed: {e}")
    sys.exit(1)

# 2. Read template YAML content
yaml_path = 'tplink_omada_custom.yaml'
if not os.path.exists(yaml_path):
    # Fallback to absolute path in home directory
    yaml_path = '/home/ec2-user/treya-wireless-dashboard/tplink_omada_custom.yaml'

if not os.path.exists(yaml_path):
    print(f"Error: Template file '{yaml_path}' not found.")
    sys.exit(1)

with open(yaml_path, 'r', encoding='utf-8') as f:
    yaml_content = f.read()

# 3. Import template configuration
print("Importing custom TP-Link Omada template...")
import_payload = {
    "jsonrpc": "2.0",
    "method": "configuration.import",
    "params": {
        "format": "yaml",
        "rules": {
            "template_groups": {
                "createMissing": True,
                "updateExisting": True
            },
            "templates": {
                "createMissing": True,
                "updateExisting": True
            },
            "items": {
                "createMissing": True,
                "updateExisting": True
            },
            "triggers": {
                "createMissing": True,
                "updateExisting": True
            },
            "discoveryRules": {
                "createMissing": True,
                "updateExisting": True
            }
        },
        "source": yaml_content
    },
    "auth": auth_token,
    "id": 2
}

try:
    req = urllib.request.Request(url, data=json.dumps(import_payload).encode('utf-8'), headers=headers)
    with urllib.request.urlopen(req) as response:
        res = json.loads(response.read().decode('utf-8'))
        if "error" in res:
            print(f"Import failed: {res['error']['message']} - {res['error']['data']}")
        else:
            print("======================================================")
            print(" Template 'TP-Link Omada Controller Custom API'")
            print(" has been successfully imported and is now built-in!")
            print("======================================================")
except Exception as e:
    print(f"Import failed with exception: {e}")
