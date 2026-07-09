#!/usr/bin/env python3
import subprocess
import re
import sys
import os

def get_zabbix_aruba_ips():
    try:
        # Run mysql query to get host IPs linked to template 10686 (Aruba Controller Custom API)
        query = "SELECT DISTINCT i.ip FROM interface i JOIN hosts_templates ht ON i.hostid = ht.hostid WHERE ht.templateid = 10686;"
        cmd = ["mysql", "-utreya", "-pTreyaPass@123", "treya", "-N", "-e", query]
        result = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, check=True)
        ips = [line.strip() for line in result.stdout.splitlines() if line.strip()]
        return ips
    except Exception as e:
        print(f"Error fetching IPs from database: {e}", file=sys.stderr)
        return []

def get_current_routes():
    try:
        result = subprocess.run(["ip", "route", "show"], stdout=subprocess.PIPE, text=True, check=True)
        return result.stdout
    except Exception as e:
        print(f"Error reading routing table: {e}", file=sys.stderr)
        return ""

def add_route_runtime(ip):
    try:
        cmd = ["ip", "route", "add", ip, "via", "10.8.0.1", "dev", "tun0"]
        subprocess.run(cmd, check=True)
        print(f"Successfully added runtime route for {ip} via tun0")
    except Exception as e:
        print(f"Error adding route for {ip}: {e}", file=sys.stderr)

def remove_route_runtime(ip):
    try:
        cmd = ["ip", "route", "del", ip, "via", "10.8.0.1", "dev", "tun0"]
        subprocess.run(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        print(f"Successfully removed runtime route for {ip}")
    except Exception as e:
        pass

def add_route_config(ip):
    config_path = "/etc/openvpn/client/Treya_Wireless.conf"
    if not os.path.exists(config_path):
        return
    try:
        with open(config_path, "r") as f:
            content = f.read()
        
        route_line = f"route {ip} 255.255.255.255"
        if route_line not in content:
            with open(config_path, "a") as f:
                f.write(f"\n{route_line}\n")
            print(f"Successfully added route {ip} to Treya_Wireless.conf")
    except Exception as e:
        print(f"Error updating config for {ip}: {e}", file=sys.stderr)

def remove_route_config(ip):
    config_path = "/etc/openvpn/client/Treya_Wireless.conf"
    if not os.path.exists(config_path):
        return
    try:
        with open(config_path, "r") as f:
            content = f.read()
        
        route_line = f"route {ip} 255.255.255.255"
        if route_line in content:
            new_content = content.replace(route_line, "")
            with open(config_path, "w") as f:
                f.write(new_content)
            print(f"Successfully removed route {ip} from Treya_Wireless.conf")
    except Exception as e:
        pass

def main():
    ips = get_zabbix_aruba_ips()
    if not ips:
        print("No Aruba hosts found in database.")
        return

    routes = get_current_routes()
    for ip in ips:
        # Validate it's an IP address (exclude macros / strings)
        if not re.match(r'^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$', ip):
            continue
            
        # We access Bhiwandi (43.241.129.220) directly over WAN, not VPN:
        if ip == '43.241.129.220':
            remove_route_config(ip)
            if ip in routes:
                remove_route_runtime(ip)
            continue

        # Check if route already exists
        if ip in routes:
            # Still make sure it is in the config file
            add_route_config(ip)
            continue

        print(f"Route for {ip} is missing.")
        add_route_runtime(ip)
        add_route_config(ip)

if __name__ == '__main__':
    main()
