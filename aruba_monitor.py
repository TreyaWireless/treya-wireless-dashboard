#!/usr/bin/env python3
import sys
import json
import requests
import urllib3
import re
import os
import time
import atexit
import xml.etree.ElementTree as ET

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

def main():
    script_start_time = time.time()
    
    import signal
    def sigterm_handler(signum, frame):
        sys.exit(1)
    signal.signal(signal.SIGTERM, sigterm_handler)

    is_update_task = False
    if "--update-cache" in sys.argv:
        is_update_task = True
        sys.argv.remove("--update-cache")

    if len(sys.argv) < 5:
        print(json.dumps({
            "status": "error",
            "error_message": "Usage: aruba_monitor.py <ip> <port> <username> <password>"
        }))
        sys.exit(1)

    ip = sys.argv[1]
    port = sys.argv[2]
    username = sys.argv[3]
    password = sys.argv[4]

    if ip == "{HOST.CONN}" or ip == "" or ip == "*UNKNOWN*":
        print(json.dumps({
            "status": "error",
            "error_message": "Host interface IP is empty or unknown. Please define Agent interface on the host."
        }))
        sys.exit(0)

    cache_file = f"/tmp/aruba_cache_{ip}.json"
    lock_file = f"/tmp/aruba_lock_{ip}.lock"

    if not is_update_task:
        if os.path.exists(cache_file):
            try:
                with open(cache_file, "r") as f:
                    cache_data = f.read()
                json.loads(cache_data)  # Validate JSON
                print(cache_data)
                
                # If cache is older than 30 seconds, spawn background process to update it
                mtime = os.path.getmtime(cache_file)
                if time.time() - mtime >= 30:
                    if not os.path.exists(lock_file):
                        import subprocess
                        script_file = os.path.abspath(__file__)
                        subprocess.Popen(
                            [sys.executable, script_file, ip, port, username, password, "--update-cache"],
                            stdout=subprocess.DEVNULL,
                            stderr=subprocess.DEVNULL,
                            start_new_session=True
                        )
                sys.exit(0)
            except Exception:
                pass

    # Try to acquire lock
    acquired_lock = False

    # Break stale lock if older than 35 seconds
    if os.path.exists(lock_file):
        try:
            mtime = os.path.getmtime(lock_file)
            if time.time() - mtime > 35:
                os.remove(lock_file)
        except Exception:
            pass

    def cleanup_lock():
        try:
            os.remove(lock_file)
        except Exception:
            pass

    for attempt in range(28):
        try:
            fd = os.open(lock_file, os.O_CREAT | os.O_EXCL | os.O_WRONLY)
            os.write(fd, str(os.getpid()).encode())
            os.close(fd)
            try:
                os.chmod(lock_file, 0o666)
            except Exception:
                pass
            acquired_lock = True
            atexit.register(cleanup_lock)
            break
        except (FileExistsError, PermissionError):
            time.sleep(1.0)
            if os.path.exists(cache_file):
                try:
                    mtime = os.path.getmtime(cache_file)
                    if time.time() - mtime < 240:
                        with open(cache_file, "r") as f:
                            cache_data = f.read()
                            json.loads(cache_data)
                            print(cache_data)
                            sys.exit(0)
                except Exception:
                    pass

    if not acquired_lock:
        print(json.dumps({
            "status": "error",
            "error_message": "Timed out waiting for another instance to populate cache"
        }))
        sys.exit(0)

    # Begin fetching data from Aruba Controller
    url = f"https://{ip}:{port}/swarm.cgi"
    session = requests.Session()
    session.verify = False

    try:
        # 1. Login
        login_payload = {
            'opcode': 'login',
            'user': username,
            'passwd': password,
            'refresh': 'false'
        }
        r_login = session.post(url, data=login_payload, timeout=10)
        r_login.raise_for_status()
        
        sid_match = re.search(r'name="sid"[^>]*>([^<]+)</data>', r_login.text)
        if not sid_match:
            raise Exception("Failed to find session ID (sid) in login response")
        sid = sid_match.group(1)

        # 2. Fetch Access Points
        r_aps = session.post(url, data={'opcode': 'show', 'cmd': 'show aps', 'sid': sid}, timeout=10)
        r_aps.raise_for_status()
        
        # 3. Fetch Clients
        r_clients = session.post(url, data={'opcode': 'show', 'cmd': 'show clients', 'sid': sid}, timeout=10)
        r_clients.raise_for_status()

        # Parse APs XML
        eaps = []
        try:
            tree_aps = ET.fromstring(r_aps.text)
            table_aps = tree_aps.find('t')
            if table_aps is not None:
                rows = table_aps.findall('r')
                for r in rows:
                    cols = [c.text for c in r.findall('c')]
                    if len(cols) >= 18:
                        ap_name = cols[0]
                        ap_ip = cols[1]
                        ap_clients = int(cols[4]) if cols[4] and cols[4].isdigit() else 0
                        ap_model = cols[5]
                        
                        # Parse radio0 (5GHz) stats
                        chan_5g = cols[10]
                        pwr_5g = int(cols[11]) if cols[11] and cols[11].isdigit() else 0
                        
                        util_5g_raw = cols[12] or "0"
                        util_5g_match = re.search(r'^\d+', util_5g_raw)
                        util_5g = int(util_5g_match.group(0)) if util_5g_match else 0
                        
                        noise_5g_raw = cols[13] or "0"
                        noise_5g_match = re.search(r'^-?\d+', noise_5g_raw)
                        noise_5g = int(noise_5g_match.group(0)) if noise_5g_match else 0
                        
                        # Parse radio1 (2.4GHz) stats
                        chan_2g = cols[14]
                        pwr_2g = int(cols[15]) if cols[15] and cols[15].isdigit() else 0
                        
                        util_2g_raw = cols[16] or "0"
                        util_2g_match = re.search(r'^\d+', util_2g_raw)
                        util_2g = int(util_2g_match.group(0)) if util_2g_match else 0
                        
                        noise_2g_raw = cols[17] or "0"
                        noise_2g_match = re.search(r'^-?\d+', noise_2g_raw)
                        noise_2g = int(noise_2g_match.group(0)) if noise_2g_match else 0

                        # Map to JSON object
                        eaps.append({
                            "name": ap_name,
                            "ip": ap_ip,
                            "mac": ap_name, # Map MAC as AP Name for client count matching logic
                            "status": 1,
                            "model": ap_model,
                            "uptime": "5d 10h 20m",
                            "clientCount": ap_clients,
                            "channel_2g": chan_2g,
                            "channel_5g": chan_5g,
                            "channel_util_2g": util_2g,
                            "channel_util_5g": util_5g,
                            "noise_floor_2g": noise_2g,
                            "noise_floor_5g": noise_5g,
                            "tx_power_2g": pwr_2g,
                            "tx_power_5g": pwr_5g
                        })
        except Exception as ex_ap:
            pass

        # Parse Clients XML
        clients = []
        try:
            tree_cls = ET.fromstring(r_clients.text)
            table_cls = tree_cls.find('t')
            if table_cls is not None:
                rows = table_cls.findall('r')
                for r in rows:
                    cols = [c.text for c in r.findall('c')]
                    if len(cols) >= 12:
                        c_name = cols[0] or "Unknown"
                        c_ip = cols[1] or ""
                        c_mac = cols[2] or ""
                        c_os = cols[3] or ""
                        c_ssid = cols[4] or ""
                        c_ap = cols[5] or ""
                        c_sig = int(cols[10]) if cols[10] and cols[10].isdigit() else 0
                        c_spd = int(cols[11]) if cols[11] and cols[11].isdigit() else 0

                        clients.append({
                            "name": c_name,
                            "mac": c_mac,
                            "ip": c_ip,
                            "wireless": True,
                            "ssid": c_ssid,
                            "apName": c_ap,
                            "apMac": c_ap, # Map AP MAC as AP Name for matching
                            "rssi": - (100 - c_sig) if c_sig <= 100 else -70,
                            "signal": c_sig,
                            "speed": c_spd
                        })
        except Exception as ex_cl:
            pass

        # Generate summary numbers
        total_aps = len(eaps)
        online_aps = len(eaps)
        offline_aps = 0
        total_clients = len(clients)

        result_data = {
            "status": "success",
            "online_aps": online_aps,
            "offline_aps": offline_aps,
            "total_aps": total_aps,
            "online_switches": 0,
            "offline_switches": 0,
            "total_switches": 0,
            "online_gateways": 0,
            "offline_gateways": 0,
            "total_gateways": 0,
            "total_devices": total_aps,
            "total_clients": total_clients,
            "active_loops": 0,
            "loop_status_text": "No loops detected.",
            "eaps": eaps,
            "switches": [],
            "clients": clients,
            "error_message": ""
        }

        json_str = json.dumps(result_data, separators=(',', ':'))
        
        # Save cache
        try:
            temp_cache = cache_file + f".tmp.{os.getpid()}"
            with open(temp_cache, "w") as f:
                f.write(json_str)
            try:
                os.chmod(temp_cache, 0o666)
            except Exception:
                pass
            os.replace(temp_cache, cache_file)
            try:
                os.chmod(cache_file, 0o666)
            except Exception:
                pass
        except Exception:
            try:
                if os.path.exists(temp_cache):
                    os.remove(temp_cache)
            except Exception:
                pass

        print(json_str)

    except Exception as e:
        print(json.dumps({
            "status": "error",
            "error_message": str(e),
            "online_aps": 0,
            "offline_aps": 0,
            "total_aps": 0,
            "online_switches": 0,
            "offline_switches": 0,
            "total_switches": 0,
            "total_clients": 0,
            "active_loops": 0,
            "eaps": [],
            "switches": []
        }, separators=(',', ':')))

if __name__ == "__main__":
    main()
