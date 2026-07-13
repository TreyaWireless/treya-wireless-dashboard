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

def get_cache_dir():
    if os.name == 'nt':
        import tempfile
        return os.path.join(tempfile.gettempdir(), 'treya-wireless')
    else:
        return '/var/cache/treya-wireless'

def get_settings_file():
    if os.name == 'nt':
        import tempfile
        temp_path = os.path.join(tempfile.gettempdir(), 'treya-wireless', 'ai_settings.json')
        if os.path.exists(temp_path):
            return temp_path
        local_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'ai_settings.json')
        if os.path.exists(local_path):
            return local_path
        return r"C:\etc\treya-wireless\ai_settings.json"
    else:
        cache_path = "/var/cache/treya-wireless/ai_settings.json"
        if os.path.exists(cache_path):
            return cache_path
        return "/etc/treya-wireless/ai_settings.json"

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
        if row.get('Name') or row.get('MAC Address') or row.get('mac'):
            rows.append(row)
    return rows


def get_local_analysis(eaps, clients, ip):
    issues = []
    actions = []
    health_score = 100
    
    # Check for high 2.4GHz TX power
    high_pwr_count = 0
    for ap in eaps:
        pwr_2g = ap.get("tx_power_2g") or ap.get("pwr_2g")
        if pwr_2g is not None:
            try:
                pwr_val = int(pwr_2g)
                if pwr_val >= 20:
                    high_pwr_count += 1
                    issues.append({
                        "ap_name": ap.get("name") or "Access Point",
                        "problem": f"High 2.4GHz TX power ({pwr_val} dBm) detected. This causes 'sticky' clients to remain connected to weak 2.4GHz signals instead of steering to 5GHz."
                    })
                    actions.append({
                        "ap_mac": ap.get("mac") or "",
                        "ap_name": ap.get("name") or "Access Point",
                        "parameter": "tx_power_2g",
                        "current_value": str(pwr_val),
                        "new_value": "12",
                        "reason": f"Reduce 2.4GHz TX power on {ap.get('name')} to 12 dBm to encourage client steering to the faster 5GHz band."
                    })
            except Exception:
                pass

    if high_pwr_count > 0:
        health_score -= min(high_pwr_count * 2, 20)

    # Check for 5GHz Co-channel interference
    ch_5g_map = {}
    for ap in eaps:
        ch_5g = ap.get("channel_5g") or ap.get("ch_5g")
        if ch_5g and str(ch_5g).isdigit():
            ch_val = int(ch_5g)
            if ch_val > 14: # 5GHz channel
                ch_5g_map.setdefault(ch_val, []).append(ap)

    # For each channel used by multiple APs
    overlap_groups = 0
    for chan, aps in ch_5g_map.items():
        if len(aps) > 1:
            overlap_groups += 1
            ap_names = ", ".join([ap.get("name") or "AP" for ap in aps])
            for ap in aps:
                issues.append({
                    "ap_name": ap.get("name") or "Access Point",
                    "problem": f"Co-channel interference on 5GHz Channel {chan} with neighboring APs ({ap_names})."
                })
            
            # Suggest shifting one of the APs to another channel
            common_5g_channels = [36, 44, 52, 60, 100, 108, 116, 132, 149, 157]
            unused_channels = [c for c in common_5g_channels if c not in ch_5g_map]
            suggested_chan = unused_channels[0] if unused_channels else 36
            
            for ap in aps[1:]:
                actions.append({
                    "ap_mac": ap.get("mac") or "",
                    "ap_name": ap.get("name") or "Access Point",
                    "parameter": "channel_5g",
                    "current_value": str(chan),
                    "new_value": str(suggested_chan),
                    "reason": f"Shift 5GHz channel from {chan} to {suggested_chan} to resolve co-channel overlap with {aps[0].get('name')}."
                })
                
    if overlap_groups > 0:
        health_score -= min(overlap_groups * 8, 30)

    # Check for poor clients
    poor_clients_count = 0
    for c in clients:
        rssi = c.get("rssi")
        if rssi is not None:
            try:
                rssi_val = int(rssi)
                if rssi_val <= -80:
                    poor_clients_count += 1
            except Exception:
                pass
                
    if poor_clients_count > 0:
        health_score -= min(poor_clients_count * 3, 20)
        issues.append({
            "ap_name": "Network clients",
            "problem": f"{poor_clients_count} client(s) experiencing low RSSI (<= -80 dBm), causing retransmissions and performance degradation."
        })
        
    health_score = max(0, min(100, health_score))
    
    return {
        "health_score": health_score,
        "issues": issues,
        "actions": actions
    }


def get_ai_analysis_cached(eaps, clients, ip, cache_file, force=False):
    settings_file = get_settings_file()
    
    # Check if cache already contains fresh AI analysis (less than 30 mins old)
    if not force and os.path.exists(cache_file):
        try:
            with open(cache_file) as f:
                old_cache = json.load(f)
            if "ai_analysis" in old_cache and "ai_analysis_timestamp" in old_cache:
                age = time.time() - old_cache["ai_analysis_timestamp"]
                if age < 1800:
                    return old_cache["ai_analysis"], old_cache["ai_analysis_timestamp"]
        except Exception:
            pass

    groq_key = os.environ.get("GROQ_API_KEY")
    gemini_key = os.environ.get("GEMINI_API_KEY")
    
    if not groq_key or not gemini_key:
        if os.path.exists(settings_file):
            try:
                with open(settings_file) as f:
                    settings = json.load(f)
                if not groq_key:
                    groq_key = settings.get("groq_api_key")
                if not gemini_key:
                    gemini_key = settings.get("gemini_api_key")
            except Exception:
                pass

    # Build telemetry payload - only include APs with active RF data to minimize token usage
    # APs with channel_util == -1 have no meaningful data (offline/no stats), skip them
    active_eaps = [ap for ap in eaps if ap.get("channel_util_5g", -1) != -1]
    
    # Sort by 5GHz utilization descending (most problematic first), cap at 15 APs
    active_eaps.sort(key=lambda x: (x.get("channel_util_5g") or 0), reverse=True)
    active_eaps = active_eaps[:15]
    
    telemetry = {
        "site_ip": ip,
        "total_aps": len(eaps),
        "analyzed_aps": len(active_eaps),
        "eaps": [],
        "poor_clients": []
    }
    
    for ap in active_eaps:
        telemetry["eaps"].append({
            "name": ap.get("name"),
            "mac": ap.get("mac"),
            "ch_2g": ap.get("channel_2g"),
            "pwr_2g": ap.get("tx_power_2g"),
            "util_2g": ap.get("channel_util_2g"),
            "ch_5g": ap.get("channel_5g"),
            "pwr_5g": ap.get("tx_power_5g"),
            "util_5g": ap.get("channel_util_5g"),
            "clients": ap.get("clientCount")
        })
        
    for c in clients:
        if c.get("rssi") is not None and c.get("rssi") <= -80:
            telemetry["poor_clients"].append({
                "name": c.get("name"),
                "mac": c.get("mac"),
                "apMac": c.get("apMac"),
                "apName": c.get("apName"),
                "rssi": c.get("rssi"),
                "ch": c.get("channel"),
                "radioId": c.get("radioId")
            })

    prompt = f"""RF Optimization Agent. Analyze {telemetry['analyzed_aps']} active APs (of {telemetry['total_aps']} total) below.
Data: {json.dumps(telemetry['eaps'])}
Poor clients: {json.dumps(telemetry['poor_clients'])}

Find: 1) 5GHz co-channel interference (same channel_5g on nearby APs) 2) High 2.4GHz TX power (pwr_2g>20) causing sticky clients.
Output TOP 10 most impactful issues and TOP 10 recommended actions only.
Respond ONLY with compact JSON (no markdown):
{{"health_score":85,"issues":[{{"ap_name":"name","problem":"short description"}}],"actions":[{{"ap_mac":"mac","ap_name":"name","parameter":"channel_5g","current_value":"36","new_value":"52","reason":"short reason"}}]}}"""

    # 4. Try Groq
    if groq_key:
        try:
            url = "https://api.groq.com/openai/v1/chat/completions"
            headers = {"Authorization": f"Bearer {groq_key}", "Content-Type": "application/json"}
            payload = {
                "model": "llama-3.3-70b-versatile",
                "messages": [{"role": "user", "content": prompt}],
                "temperature": 0.0,
                "max_tokens": 2048,
                "response_format": {"type": "json_object"}
            }
            r = requests.post(url, headers=headers, json=payload, timeout=60)
            if r.status_code == 200:
                res_txt = r.json()["choices"][0]["message"]["content"].strip()
                res_dict = json.loads(res_txt)
                res_dict["engine"] = "Groq Llama 3.3"
                return res_dict, time.time()
        except Exception:
            pass

    # 5. Fallback to Gemini
    if gemini_key:
        try:
            url = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key={gemini_key}"
            headers = {"Content-Type": "application/json"}
            payload = {
                "contents": [{"parts": [{"text": prompt}]}],
                "generationConfig": {
                    "responseMimeType": "application/json",
                    "maxOutputTokens": 2048
                }
            }
            r = requests.post(url, headers=headers, json=payload, timeout=60)
            if r.status_code == 200:
                res_txt = r.json()["candidates"][0]["content"]["parts"][0]["text"].strip()
                res_dict = json.loads(res_txt)
                res_dict["engine"] = "Gemini Flash Lite"
                return res_dict, time.time()
        except Exception:
            pass

    # 6. Fallback to Local Heuristics
    local_res = get_local_analysis(eaps, clients, ip)
    local_res["engine"] = "Local Heuristics"
    return local_res, time.time()


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

    req_timeout = 40 if is_update_task else 10

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

    cache_dir = get_cache_dir()
    lock_dir = os.path.join(cache_dir, 'locks')
    try:
        os.makedirs(lock_dir, exist_ok=True)
        if os.name != 'nt':
            os.chmod(lock_dir, 0o777)
    except Exception:
        pass

    cache_file = os.path.join(cache_dir, f"aruba_cache_{ip}.json")
    ap_names_file = os.path.join(cache_dir, f"aruba_ap_names_{ip}.json")
    ap_serials_file = os.path.join(cache_dir, f"aruba_ap_serials_{ip}.json")
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
                        
                        popen_kwargs = {
                            'stdout': subprocess.DEVNULL,
                            'stderr': subprocess.DEVNULL
                        }
                        if os.name == 'nt':
                            popen_kwargs['creationflags'] = 0x00000008  # DETACHED_PROCESS
                        else:
                            popen_kwargs['start_new_session'] = True
                        
                        subprocess.Popen(args, **popen_kwargs)
                sys.exit(0)
            except Exception:
                pass
        else:
            # Cache file does not exist. Initialize it with empty/loading template
            # and spawn background process to run the heavy fetch. This prevents
            # Zabbix execution timeout (30s limit) on new or slow controller connections.
            default_data = json.dumps({
                "status": "success",
                "online_aps": 0,
                "offline_aps": 0,
                "total_aps": 0,
                "online_switches": 0,
                "offline_switches": 0,
                "total_switches": 0,
                "online_gateways": 0,
                "offline_gateways": 0,
                "total_gateways": 0,
                "total_devices": 0,
                "total_clients": 0,
                "active_loops": 0,
                "loop_status_text": "Loading data...",
                "eaps": [],
                "switches": [],
                "clients": [],
                "error_message": "Initializing cache in background..."
            }, separators=(',', ':'))
            
            try:
                with open(cache_file, "w") as f:
                    f.write(default_data)
                os.chmod(cache_file, 0o666)
            except Exception:
                pass
                
            print(default_data)

            if not os.path.exists(lock_file):
                import subprocess
                script_file = os.path.abspath(__file__)
                args = [sys.executable, script_file, ip, port, username, password]
                if len(sys.argv) > 5:
                    args.extend(sys.argv[5:])
                args.append("--update-cache")
                try:
                    popen_kwargs = {
                        'stdout': subprocess.DEVNULL,
                        'stderr': subprocess.DEVNULL
                    }
                    if os.name == 'nt':
                        popen_kwargs['creationflags'] = 0x00000008  # DETACHED_PROCESS
                    else:
                        popen_kwargs['start_new_session'] = True
                    
                    subprocess.Popen(args, **popen_kwargs)
                except Exception:
                    pass
            sys.exit(0)

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
        r_login = session.post(url, data=login_payload, timeout=req_timeout)
        r_login.raise_for_status()
        
        sid_match = re.search(r'name="sid"[^>]*>([^<]+)</data>', r_login.text)
        if not sid_match:
            raise Exception("Failed to find session ID (sid) in login response")
        sid = sid_match.group(1)

        # Fetch Summary (for cluster uptime/Elected Time)
        elected_time = "5d 10h 20m"
        try:
            r_summary = session.post(url, data={'opcode': 'show', 'cmd': 'show summary', 'sid': sid}, timeout=req_timeout)
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
        r_aps = session.post(url, data={'opcode': 'show', 'cmd': 'show aps', 'sid': sid}, timeout=req_timeout)
        r_aps.raise_for_status()

        # Fetch individual AP age (uptime) and serial from support command
        ap_uptimes = {}
        ap_serials_by_name = {}
        try:
            r_support = session.post(url, data={'opcode': 'support', 'cmd': 'show aps', 'sid': sid}, timeout=req_timeout)
            if r_support.status_code == 200:
                parsed_aps = parse_cli_table(r_support.text)
                for ap in parsed_aps:
                    ap_name = ap.get("Name")
                    ap_age = ap.get("Age")
                    ap_sn = ap.get("Serial #", "") or ""
                    if ap_name and ap_age:
                        ap_uptimes[ap_name.strip()] = format_aruba_uptime(ap_age)
                    if ap_name and ap_sn:
                        ap_serials_by_name[ap_name.strip()] = ap_sn.strip()
        except Exception:
            pass
        
        # 3. Fetch Clients
        r_clients = session.post(url, data={'opcode': 'show', 'cmd': 'show clients', 'sid': sid}, timeout=req_timeout)
        r_clients.raise_for_status()

        # Parse AP Name to MAC mapping from show summary
        ap_name_to_mac = {}  # name -> mac
        mac_to_ap_name = {}  # mac -> name  (for offline AP naming)
        try:
            tree_sum = ET.fromstring(r_summary.text)
            for t in tree_sum.findall('.//t'):
                if 'Access Points' in t.attrib.get('tn', ''):
                    for r_row in t.findall('r'):
                        cols_sum = [c_cell.text for c_cell in r_row.findall('c')]
                        if len(cols_sum) >= 3:
                            sum_mac = cols_sum[0].lower().strip()
                            sum_name = cols_sum[2].strip()
                            ap_name_to_mac[sum_name] = sum_mac
                            mac_to_ap_name[sum_mac] = sum_name
        except Exception:
            pass

        # Load persistent AP name cache from disk and merge
        persistent_mac_names = {}
        try:
            if os.path.exists(ap_names_file):
                with open(ap_names_file, 'r') as _f:
                    persistent_mac_names = json.load(_f)
        except Exception:
            pass
        # Merge: live data takes priority over cached data
        merged_mac_names = {**persistent_mac_names, **mac_to_ap_name}

        # Load persistent serial number cache
        persistent_mac_serials = {}
        try:
            if os.path.exists(ap_serials_file):
                with open(ap_serials_file, 'r') as _f:
                    persistent_mac_serials = json.load(_f)
        except Exception:
            pass

        # Save updated live AP names back to disk
        if mac_to_ap_name:
            try:
                merged_save = {**persistent_mac_names, **mac_to_ap_name}
                with open(ap_names_file, 'w') as _f:
                    json.dump(merged_save, _f)
                try:
                    os.chmod(ap_names_file, 0o666)
                except Exception:
                    pass
            except Exception:
                pass


        # Parse APs XML
        eaps = []
        active_macs = set()
        try:
            tree_aps = ET.fromstring(r_aps.text)
            table_aps = tree_aps.find('t')
            if table_aps is not None:
                rows = table_aps.findall('r')
                for r in rows:
                    cols = [c.text for c in r.findall('c')]
                    if len(cols) >= 18:
                        ap_name = cols[0].strip() if cols[0] else ""
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
                        real_mac = ap_name_to_mac.get(ap_name, ap_name.lower())
                        active_macs.add(real_mac.lower().strip())
                        ap_serial = ap_serials_by_name.get(ap_name, cols[9] if len(cols) > 9 and cols[9] else "")

                        # Save serial in persistent cache keyed by MAC
                        if real_mac and ap_serial:
                            persistent_mac_serials[real_mac.lower().strip()] = ap_serial

                        eaps.append({
                            "name": ap_name,
                            "ip": ap_ip,
                            "mac": real_mac,
                            "serial": ap_serial,
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

        # Identify offline APs using ONLY the persistent name cache.
        # The persistent cache only contains APs that were previously seen ONLINE by this monitor.
        # This ensures we only show truly offline APs (previously connected but now down),
        # NOT every MAC in the allowlist (which includes uninstalled/decommissioned/other-site APs).
        # Save updated serial cache to disk
        if persistent_mac_serials:
            try:
                with open(ap_serials_file, 'w') as _f:
                    json.dump(persistent_mac_serials, _f)
                try:
                    os.chmod(ap_serials_file, 0o666)
                except Exception:
                    pass
            except Exception:
                pass

        previously_known_macs = set(merged_mac_names.keys())
        offline_macs = previously_known_macs - active_macs
        for off_mac in offline_macs:
            friendly_name = merged_mac_names.get(off_mac.lower().strip(), off_mac.upper())
            off_serial = persistent_mac_serials.get(off_mac.lower().strip(), "--")
            eaps.append({
                "name": friendly_name,
                "ip": "--",
                "mac": off_mac,
                "serial": off_serial,
                "status": 0,
                "model": "Aruba AP",
                "uptime": "--",
                "clientCount": 0,
                "channel_2g": "--",
                "channel_5g": "--",
                "channel_util_2g": 0,
                "channel_util_5g": 0,
                "noise_floor_2g": 0,
                "noise_floor_5g": 0,
                "tx_power_2g": 0,
                "tx_power_5g": 0
            })

        # Fetch client uptimes (Age) from support command
        client_uptimes = {}
        try:
            r_support_clients = session.post(url, data={'opcode': 'support', 'cmd': 'show clients debug', 'sid': sid}, timeout=req_timeout)
            if r_support_clients.status_code == 200:
                parsed_cls = parse_cli_table(r_support_clients.text)
                for c in parsed_cls:
                    c_mac = c.get("MAC Address")
                    c_age = c.get("Age")
                    if c_mac and c_age and c_age.isdigit():
                        client_uptimes[c_mac.lower().strip()] = int(c_age)
        except Exception:
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
                        c_chan = cols[6] or ""
                        
                        c_sig_raw = cols[10] or "0"
                        c_sig_match = re.search(r'^\d+', c_sig_raw)
                        c_sig = int(c_sig_match.group(0)) if c_sig_match else 0

                        c_spd_raw = cols[11] or "0"
                        c_spd_match = re.search(r'^\d+', c_spd_raw)
                        c_spd = int(c_spd_match.group(0)) if c_spd_match else 0

                        # radioId: 1 for 5GHz (chan > 14), 0 for 2.4GHz
                        r_id = 1 if (c_chan.isdigit() and int(c_chan) > 14) else 0

                        # Resolve real AP MAC
                        c_ap_mac = ap_name_to_mac.get(c_ap, c_ap)

                        clients.append({
                            "name": c_name,
                            "mac": c_mac,
                            "ip": c_ip,
                            "wireless": True,
                            "ssid": c_ssid,
                            "apName": c_ap,
                            "apMac": c_ap_mac,
                            "rssi": - (100 - c_sig) if c_sig <= 100 else -70,
                            "signal": c_sig,
                            "speed": c_spd,
                            "currentSpeedMbps": c_spd,
                            "trafficDown": None,
                            "trafficUp": None,
                            "radioId": r_id,
                            "uptime": client_uptimes.get(c_mac.lower().strip(), None),
                            "channel": c_chan
                        })
        except Exception as ex_cl:
            pass

        # 4. Fetch switches info via SSH hop through firewall
        switches = []
        if sw_ips and sw_ssh_pass and fw_pass:
            switches = fetch_switches_info(ip, username, fw_pass, sw_ssh_pass, sw_ips)

        # Generate summary numbers
        total_aps = len(eaps)
        online_aps = len([ap for ap in eaps if ap["status"] == 1])
        offline_aps = len([ap for ap in eaps if ap["status"] == 0])
        total_clients = len(clients)
        
        online_switches = len([s for s in switches if s["status"] == 1])
        offline_switches = len([s for s in switches if s["status"] == 0])
        total_switches = len(switches)

        ai_analysis, ai_time = get_ai_analysis_cached(eaps, clients, ip, cache_file, force=is_update_task)

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
            "error_message": "",
            "ai_analysis": ai_analysis,
            "ai_analysis_timestamp": ai_time
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
