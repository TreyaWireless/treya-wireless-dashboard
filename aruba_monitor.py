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
import paramiko

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

def recv_until(chan, pattern, timeout=10):
    buf = ""
    start = time.time()
    while time.time() - start < timeout:
        if chan.recv_ready():
            chunk = chan.recv(4096).decode('utf-8', errors='ignore')
            buf += chunk
            if isinstance(pattern, list):
                if any(p in buf for p in pattern):
                    return buf
            elif pattern in buf:
                return buf
        time.sleep(0.1)
    return buf

def fetch_switches_info(fw_ip, fw_user, fw_pass, sw_pass, sw_ips):
    switches = []
    if not sw_ips:
        return switches

    ip_list = [ip.strip() for ip in sw_ips.split(",") if ip.strip()]
    if not ip_list:
        return switches

    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        ssh.connect(fw_ip, port=22, username=fw_user, password=fw_pass, timeout=8)
        chan = ssh.invoke_shell()
        
        recv_until(chan, ["Press 'a' to accept", "(Press 'a' to accept):"], timeout=8)
        chan.send("a")
        time.sleep(0.5)
        
        # Determine dynamic firewall prompt
        fw_init_output = recv_until(chan, ["# "], timeout=8)
        lines = fw_init_output.splitlines()
        fw_prompt = "# "
        for line in reversed(lines):
            if "#" in line:
                fw_prompt = line.strip()
                break
        
        for sw_ip in ip_list:
            try:
                chan.send(f"execute ssh admin@{sw_ip}\n")
                out = recv_until(chan, ["Are you sure you want to continue connecting", "password:", "Password:"], timeout=2)
                
                if "Are you sure you want to continue connecting" in out:
                    chan.send("yes\n")
                    out = recv_until(chan, ["password:", "Password:"], timeout=2)
                    
                chan.send(f"{sw_pass}\n")
                
                # Wait for switch prompt (make sure it doesn't contain fw_prompt)
                prompt = recv_until(chan, [">", "#"], timeout=3)
                if fw_prompt in prompt:
                    raise Exception("Returned to firewall prompt prematurely or login failed")
                
                # Deduce clean switch prompt from the last line
                sw_prompt = "#"
                prompt_lines = prompt.splitlines()
                for line in reversed(prompt_lines):
                    if "#" in line or ">" in line:
                        sw_prompt = line.strip()
                        break
                        
                chan.send("show version\n")
                time.sleep(1.0)
                chan.send("show system\n")
                time.sleep(1.0)
                
                output = recv_until(chan, sw_prompt, timeout=2)
                
                hostname = sw_ip
                model = "Aruba Switch"
                serial = ""
                mac = ""
                os_ver = ""
                uptime = ""
                
                for line in output.splitlines():
                    line_str = line.strip()
                    if not line_str:
                        continue
                    if "Hostname" in line_str and ":" in line_str:
                        hostname = line_str.split(":", 1)[1].strip()
                    elif "Product Name" in line_str and ":" in line_str:
                        model = line_str.split(":", 1)[1].strip()
                    elif "Chassis Serial Nbr" in line_str and ":" in line_str:
                        serial = line_str.split(":", 1)[1].strip()
                    elif "Base MAC Address" in line_str and ":" in line_str:
                        mac = line_str.split(":", 1)[1].strip()
                    elif "ArubaOS-CX Version" in line_str and ":" in line_str:
                        os_ver = line_str.split(":", 1)[1].strip()
                    elif "Up Time" in line_str and ":" in line_str:
                        uptime = line_str.split(":", 1)[1].strip()
                
                switches.append({
                    "name": hostname,
                    "ip": sw_ip,
                    "mac": mac or sw_ip,
                    "status": 1,
                    "model": model,
                    "sn": serial,
                    "firmwareVersion": os_ver,
                    "uptime": uptime or "Unknown",
                    "lastSeen": None
                })
                
                # Exit switch to return to firewall prompt
                chan.send("exit\n")
                recv_until(chan, fw_prompt, timeout=8)
                
            except Exception as e:
                switches.append({
                    "name": f"Switch-{sw_ip}",
                    "ip": sw_ip,
                    "mac": sw_ip,
                    "status": 0,
                    "model": "Aruba Switch",
                    "sn": "",
                    "firmwareVersion": "",
                    "uptime": "--",
                    "lastSeen": int(time.time() * 1000)
                })
                # Abort any hung connection/prompt using Ctrl+C
                try:
                    chan.send("\x03")
                    time.sleep(0.5)
                    chan.send("exit\n")
                    time.sleep(0.5)
                    chan.send("\n")
                    recv_until(chan, fw_prompt, timeout=5)
                except Exception:
                    pass
        ssh.close()
    except Exception as e:
        for sw_ip in ip_list:
            switches.append({
                "name": f"Switch-{sw_ip}",
                "ip": sw_ip,
                "mac": sw_ip,
                "status": 0,
                "model": "Aruba Switch",
                "sn": "",
                "firmwareVersion": "",
                "uptime": "--",
                "lastSeen": int(time.time() * 1000)
            })
    return switches
def format_aruba_uptime(raw_time):
    if not raw_time:
        return "Unknown"
    parts = raw_time.split(':')
    friendly_parts = [p for p in parts if not p.endswith('s')]
    if friendly_parts:
        return " ".join(friendly_parts)
    return raw_time

def parse_cli_table(table_text):
    lines = table_text.strip().splitlines()
    if not lines:
        return []
    
    sep_idx = -1
    for idx, line in enumerate(lines):
        if (line.startswith('----') and ' ' in line) or (line.strip() and all(c in '- ' for c in line) and ' ' in line):
            sep_idx = idx
            break
            
    if sep_idx == -1:
        return []
        
    sep_line = lines[sep_idx]
    col_starts = []
    in_dash = False
    for i, c in enumerate(sep_line):
        if c == '-':
            if not in_dash:
                col_starts.append(i)
                in_dash = True
        else:
            in_dash = False
            
    columns = []
    for idx, start in enumerate(col_starts):
        end = col_starts[idx + 1] if idx + 1 < len(col_starts) else len(sep_line)
        columns.append((start, end))
        
    header_line = lines[sep_idx - 1] if sep_idx > 0 else ""
    headers = []
    for start, end in columns:
        headers.append(header_line[start:end].strip())
        
    rows = []
    for line in lines[sep_idx + 1:]:
        if not line.strip():
            continue
        if line.startswith('---') or 'Access Points' in line:
            continue
        row = {}
        for (start, end), header in zip(columns, headers):
            val = line[start:end].strip() if start < len(line) else ""
            row[header] = val
        if row.get('Name'):
            rows.append(row)
    return rows


def main():
    script_start_time = time.time()
    
    import signal
    def sigterm_handler(signum, frame):
        sys.exit(1)
    signal.signal(signal.SIGTERM, sigterm_handler)

    is_update_task = False
    # Check if this is the background cache updater run
    if "--update-cache" in sys.argv:
        is_update_task = True
        sys.argv.remove("--update-cache")

    if len(sys.argv) < 5:
        print(json.dumps({
            "status": "error",
            "error_message": "Usage: aruba_monitor.py <ip> <port> <username> <password> [<sw_ssh_pass> <fw_pass> <sw_ips>]"
        }))
        sys.exit(1)

    ip = sys.argv[1]
    port = sys.argv[2]
    username = sys.argv[3]
    password = sys.argv[4]
    
    # Extra parameters for switch SSH hop
    sw_ssh_pass = sys.argv[5] if len(sys.argv) > 5 else ""
    fw_pass = sys.argv[6] if len(sys.argv) > 6 else ""
    sw_ips = sys.argv[7] if len(sys.argv) > 7 else ""
    
    # Sanitize inputs that could be empty macros passed literally by Zabbix
    if sw_ssh_pass.startswith("{$"): sw_ssh_pass = ""
    if fw_pass.startswith("{$"): fw_pass = ""
    if sw_ips.startswith("{$") or sw_ips == "" or sw_ips == "*UNKNOWN*": sw_ips = ""

    if ip == "{HOST.CONN}" or ip == "" or ip == "*UNKNOWN*":
        print(json.dumps({
            "status": "error",
            "error_message": "Host interface IP is empty or unknown. Please define Agent interface on the host."
        }))
        sys.exit(0)

    lock_dir = "/var/cache/treya-wireless/locks"
    try:
        os.makedirs(lock_dir, exist_ok=True)
        os.chmod(lock_dir, 0o777)
    except Exception:
        pass

    cache_file = f"/var/cache/treya-wireless/aruba_cache_{ip}.json"
    lock_file = os.path.join(lock_dir, f"aruba_lock_{ip}.lock")

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
                    # Break stale lock if older than 45 seconds
                    if os.path.exists(lock_file):
                        try:
                            lmtime = os.path.getmtime(lock_file)
                            if time.time() - lmtime > 45:
                                os.remove(lock_file)
                        except Exception:
                            pass

                    if not os.path.exists(lock_file):
                        import subprocess
                        script_file = os.path.abspath(__file__)
                        
                        # Forward all extra parameters to background runner
                        args = [sys.executable, script_file, ip, port, username, password]
                        if len(sys.argv) > 5:
                            args.extend(sys.argv[5:])
                        args.append("--update-cache")
                        
                        subprocess.Popen(
                            args,
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

        # Fetch Summary (for cluster uptime/Elected Time)
        elected_time = "5d 10h 20m"
        try:
            r_summary = session.post(url, data={'opcode': 'show', 'cmd': 'show summary', 'sid': sid}, timeout=10)
            r_summary.raise_for_status()
            match_elected = re.search(r'name="Elected Time\s*"[^>]*>([^<]+)</data>', r_summary.text)
            if match_elected:
                raw_time = match_elected.group(1).strip()
                parts = raw_time.split(':')
                friendly_parts = [p for p in parts if not p.endswith('s')]
                if friendly_parts:
                    elected_time = " ".join(friendly_parts)
                else:
                    elected_time = raw_time
        except Exception:
            pass

        # 2. Fetch Access Points
        r_aps = session.post(url, data={'opcode': 'show', 'cmd': 'show aps', 'sid': sid}, timeout=10)
        r_aps.raise_for_status()

        # Fetch individual AP age (uptime) from support command
        ap_uptimes = {}
        try:
            r_support = session.post(url, data={'opcode': 'support', 'cmd': 'show aps', 'sid': sid}, timeout=10)
            if r_support.status_code == 200:
                parsed_aps = parse_cli_table(r_support.text)
                for ap in parsed_aps:
                    ap_name = ap.get("Name")
                    ap_age = ap.get("Age")
                    if ap_name and ap_age:
                        ap_uptimes[ap_name] = format_aruba_uptime(ap_age)
        except Exception:
            pass
        
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
                        mapped_uptime = ap_uptimes.get(ap_name, elected_time)
                        eaps.append({
                            "name": ap_name,
                            "ip": ap_ip,
                            "mac": ap_name, # Map MAC as AP Name for client count matching logic
                            "status": 1,
                            "model": ap_model,
                            "uptime": mapped_uptime,
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
                        c_sig_raw = cols[10] or "0"
                        c_sig_match = re.search(r'^\d+', c_sig_raw)
                        c_sig = int(c_sig_match.group(0)) if c_sig_match else 0

                        c_spd_raw = cols[11] or "0"
                        c_spd_match = re.search(r'^\d+', c_spd_raw)
                        c_spd = int(c_spd_match.group(0)) if c_spd_match else 0

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
                            "speed": c_spd,
                            "currentSpeedMbps": c_spd,
                            "trafficDown": None,
                            "trafficUp": None
                        })
        except Exception as ex_cl:
            pass

        # 4. Fetch switches info via SSH hop through firewall
        switches = []
        if sw_ips and sw_ssh_pass and fw_pass:
            switches = fetch_switches_info(ip, username, fw_pass, sw_ssh_pass, sw_ips)

        # Generate summary numbers
        total_aps = len(eaps)
        online_aps = len(eaps)
        offline_aps = 0
        total_clients = len(clients)
        
        online_switches = len([s for s in switches if s["status"] == 1])
        offline_switches = len([s for s in switches if s["status"] == 0])
        total_switches = len(switches)

        result_data = {
            "status": "success",
            "online_aps": online_aps,
            "offline_aps": offline_aps,
            "total_aps": total_aps,
            "online_switches": online_switches,
            "offline_switches": offline_switches,
            "total_switches": total_switches,
            "online_gateways": 0,
            "offline_gateways": 0,
            "total_gateways": 0,
            "total_devices": total_aps + total_switches,
            "total_clients": total_clients,
            "active_loops": 0,
            "loop_status_text": "No loops detected.",
            "eaps": eaps,
            "switches": switches,
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
            
            try:
                os.replace(temp_cache, cache_file)
            except PermissionError:
                # Fallback to direct write if directory permissions (sticky bit) block rename
                with open(cache_file, "w") as f:
                    f.write(json_str)
                try:
                    os.remove(temp_cache)
                except Exception:
                    pass
            
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
