#!/usr/bin/env python3
import sys
import json
import requests
import urllib3
import re
import os
import time
import atexit
from concurrent.futures import ThreadPoolExecutor


urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

def get_cache_dir():
    if os.name == 'nt':
        import tempfile
        return os.path.join(tempfile.gettempdir(), 'treya-wireless')
    else:
        return '/var/cache/treya-wireless'

def get_settings_file():
    if os.name == 'nt':
        local_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'ai_settings.json')
        if os.path.exists(local_path):
            return local_path
        return r"C:\etc\treya-wireless\ai_settings.json"
    else:
        cache_path = "/var/cache/treya-wireless/ai_settings.json"
        if os.path.exists(cache_path):
            return cache_path
        return "/etc/treya-wireless/ai_settings.json"

session = requests.Session()
session.verify = False
adapter = requests.adapters.HTTPAdapter(pool_connections=100, pool_maxsize=120)
session.mount('http://', adapter)
session.mount('https://', adapter)

def make_request(url, method="GET", data=None, token=None):
    headers = {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko)",
        "ngrok-skip-browser-warning": "true"
    }
    if token:
        if token.startswith("Bearer ") or token.startswith("AccessToken="):
            headers["Authorization"] = token
        else:
            headers["Token"] = token

    try:
        if method == "POST":
            response = session.post(url, json=data, headers=headers, timeout=10)
        else:
            response = session.get(url, headers=headers, timeout=10)
        response.raise_for_status()
        return response.json()
    except Exception as e:
        raise Exception(f"HTTP request to {url} failed: {e}")

def parse_actual_channel(channel_val):
    if not channel_val:
        return None
    channel_str = str(channel_val).strip()
    if "/" in channel_str:
        channel_str = channel_str.split("/")[0].strip()
    try:
        match = re.match(r'^\s*(\d+)', channel_str)
        if match:
            return int(match.group(1))
    except Exception:
        pass
    return None

def get_radio_stats(device):
    """Extract channel utilization and noise floor from a device's radio list."""
    radio_setting = device.get("radioSetting", device.get("radioConfig", {}))
    radio_list = device.get("radioList", [])

    chan_util_2g = None
    chan_util_5g = None
    noise_floor_2g = None
    noise_floor_5g = None
    tx_power_2g = None
    tx_power_5g = None
    ch_2g = None
    ch_5g = None

    for radio in radio_list:
        band = radio.get("band", radio.get("radioId", -1))
        # band 0 = 2.4GHz, band 1 = 5GHz (Omada convention)
        util = radio.get("utilization", radio.get("channelUtilization", None))
        noise = radio.get("noise", radio.get("noiseFloor", None))
        tx_pwr = radio.get("txPower", None)
        chan = radio.get("channel", radio.get("actualChannel", None))

        if band == 0 or band == "2G":
            if util is not None:
                chan_util_2g = int(util)
            if noise is not None:
                noise_floor_2g = int(noise)
            if tx_pwr is not None:
                tx_power_2g = int(tx_pwr)
            if chan is not None:
                ch_2g = parse_actual_channel(chan)
        elif band == 1 or band == "5G":
            if util is not None:
                chan_util_5g = int(util)
            if noise is not None:
                noise_floor_5g = int(noise)
            if tx_pwr is not None:
                tx_power_5g = int(tx_pwr)
            if chan is not None:
                ch_5g = parse_actual_channel(chan)

    return chan_util_2g, chan_util_5g, noise_floor_2g, noise_floor_5g, tx_power_2g, tx_power_5g, ch_2g, ch_5g

def normalize_port(port_str):
    if not port_str:
        return ""
    port_str = str(port_str).strip()
    if '/' in port_str:
        return port_str.split('/')[-1]
    match = re.search(r'(\d+)$', port_str)
    if match:
        return match.group(1)
    return port_str

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


def get_ai_analysis_cached(eaps, clients, ip, cache_file):
    settings_file = get_settings_file()
    
    # Check if cache already contains fresh AI analysis (less than 30 mins old)
    if os.path.exists(cache_file):
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

    # Build telemetry payload
    telemetry = {
        "site_ip": ip,
        "eaps": [],
        "poor_clients": []
    }
    
    for ap in eaps:
        telemetry["eaps"].append({
            "name": ap.get("name"),
            "mac": ap.get("mac"),
            "ch_2g": ap.get("channel_2g"),
            "pwr_2g": ap.get("tx_power_2g"),
            "util_2g": ap.get("channel_util_2g"),
            "ch_5g": ap.get("channel_5g"),
            "pwr_5g": ap.get("tx_power_5g"),
            "util_5g": ap.get("channel_util_5g"),
            "clientCount": ap.get("clientCount")
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

    prompt = f"""
You are an automated RF Optimization Agent. Review this wireless network telemetry data:
{json.dumps(telemetry)}

Tasks:
1. Identify APs experiencing Co-Channel Interference on 5GHz.
2. Identify APs with extremely high 2.4GHz TX power causing sticky clients.
3. Suggest remediation actions (reducing 2.4GHz power or shifting 5GHz channels to non-overlapping ones).

You must respond ONLY with a valid JSON object matching the schema below. Do not include any markdown styling, conversational text, or explanation outside the JSON.

Required JSON Schema:
{{
    "health_score": 85,
    "issues": [
        {{"ap_name": "AP Name", "problem": "Detailed description of the issue"}}
    ],
    "actions": [
        {{"ap_mac": "00:00:00:00:00:00", "ap_name": "AP Name", "parameter": "channel_5g|tx_power_2g", "current_value": "161", "new_value": "36", "reason": "Why this action is recommended"}}
    ]
}}
"""

    # 4. Try Groq
    if groq_key:
        try:
            url = "https://api.groq.com/openai/v1/chat/completions"
            headers = {"Authorization": f"Bearer {groq_key}", "Content-Type": "application/json"}
            payload = {
                "model": "llama-3.3-70b-versatile",
                "messages": [{"role": "user", "content": prompt}],
                "temperature": 0.0,
                "response_format": {"type": "json_object"}
            }
            r = requests.post(url, headers=headers, json=payload, timeout=20)
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
            url = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={gemini_key}"
            headers = {"Content-Type": "application/json"}
            payload = {
                "contents": [{"parts": [{"text": prompt + " Respond in strict JSON."}]}],
                "generationConfig": {"responseMimeType": "application/json"}
            }
            r = requests.post(url, headers=headers, json=payload, timeout=20)
            if r.status_code == 200:
                res_txt = r.json()["candidates"][0]["content"]["parts"][0]["text"].strip()
                res_dict = json.loads(res_txt)
                res_dict["engine"] = "Gemini 1.5 Flash"
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
    if "--update-cache" in sys.argv:
        is_update_task = True
        sys.argv.remove("--update-cache")

    if len(sys.argv) < 5:
        print(json.dumps({
            "status": "error",
            "error_message": "Usage: omada_monitor.py <ip> <port> <username/client_id> <password/client_secret> [omadac_id]"
        }))
        sys.exit(1)

    ip = sys.argv[1]
    port = sys.argv[2]
    arg3 = sys.argv[3]
    arg4 = sys.argv[4]
    omadac_id = sys.argv[5] if len(sys.argv) > 5 else ""

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

    cache_file = os.path.join(cache_dir, f"omada_cache_{ip}.json")
    lock_file = os.path.join(lock_dir, f"omada_lock_{ip}.lock")

    if not is_update_task:
        cache_valid = False
        if os.path.exists(cache_file):
            try:
                with open(cache_file, "r") as f:
                    cache_data = f.read()
                json.loads(cache_data)  # Validate JSON
                print(cache_data)
                cache_valid = True
                
                # If cache is older than 30 seconds, spawn background process to update it
                mtime = os.path.getmtime(cache_file)
                if time.time() - mtime >= 30:
                    if not os.path.exists(lock_file):
                        import subprocess
                        script_file = os.path.abspath(__file__)
                        
                        popen_kwargs = {
                            'stdout': subprocess.DEVNULL,
                            'stderr': subprocess.DEVNULL
                        }
                        if os.name == 'nt':
                            popen_kwargs['creationflags'] = 0x00000008  # DETACHED_PROCESS
                        else:
                            popen_kwargs['start_new_session'] = True
                        
                        subprocess.Popen(
                            [sys.executable, script_file, ip, port, arg3, arg4, omadac_id, "--update-cache"],
                            **popen_kwargs
                        )
                sys.exit(0)
            except Exception:
                pass

        if not cache_valid:
            # Write a placeholder and spawn background task immediately!
            placeholder = {
                "status": "error",
                "error_message": "Cache is initializing. Please wait for next polling cycle.",
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
                "loop_status_text": "Cache is initializing.",
                "lldp_count": 0,
                "lldp_neighbors": {},
                "eaps": [],
                "switches": [],
                "clients": [],
                "ai_analysis": None,
                "ai_analysis_timestamp": 0
            }
            placeholder_str = json.dumps(placeholder, separators=(',', ':'))
            try:
                with open(cache_file, "w") as f:
                    f.write(placeholder_str)
                try:
                    os.chmod(cache_file, 0o666)
                except Exception:
                    pass
            except Exception:
                pass
            
            if not os.path.exists(lock_file):
                import subprocess
                script_file = os.path.abspath(__file__)
                
                popen_kwargs = {
                    'stdout': subprocess.DEVNULL,
                    'stderr': subprocess.DEVNULL
                }
                if os.name == 'nt':
                    popen_kwargs['creationflags'] = 0x00000008  # DETACHED_PROCESS
                else:
                    popen_kwargs['start_new_session'] = True
                
                subprocess.Popen(
                    [sys.executable, script_file, ip, port, arg3, arg4, omadac_id, "--update-cache"],
                    **popen_kwargs
                )
            print(placeholder_str)
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

    base_url = f"https://{ip}:{port}"

    try:
        if omadac_id:
            # OpenAPI mode
            # 1. Get token
            token_url = f"{base_url}/openapi/authorize/token?grant_type=client_credentials"
            payload = {
                "omadacId": omadac_id,
                "client_id": arg3,
                "client_secret": arg4
            }
            token_res = make_request(token_url, method="POST", data=payload)
            token = token_res.get("result", {}).get("accessToken")
            if not token:
                msg = token_res.get("msg", "Unknown error")
                raise Exception(f"OpenAPI login failed, token not found in response. Message: {msg}")

            # 2. Get Site ID
            sites_url = f"{base_url}/openapi/v1/{omadac_id}/sites?page=1&pageSize=100"
            sites_res = make_request(sites_url, token=f"AccessToken={token}")
            sites = sites_res.get("result", {}).get("data", [])
            if not sites:
                raise Exception("No sites found on this Omada Controller via OpenAPI.")
            site_id = sites[0].get("siteId", "default")

            # 3. Get Devices (paginated)
            devices = []
            page = 1
            while True:
                devices_url = f"{base_url}/openapi/v1/{omadac_id}/sites/{site_id}/devices?page={page}&pageSize=100"
                devices_res = make_request(devices_url, token=f"AccessToken={token}")
                data_list = devices_res.get("result", {}).get("data", [])
                if not data_list:
                    break
                devices.extend(data_list)
                if len(data_list) < 100:
                    break
                page += 1

            # 4. Get Clients (paginated)
            clients = []
            page = 1
            while True:
                clients_url = f"{base_url}/openapi/v1/{omadac_id}/sites/{site_id}/clients?page={page}&pageSize=100"
                clients_res = make_request(clients_url, token=f"AccessToken={token}")
                data_list = clients_res.get("result", {}).get("data", [])
                if not data_list:
                    break
                clients.extend(data_list)
                if len(data_list) < 100:
                    break
                page += 1

        else:
            # Local Credentials mode
            login_url = f"{base_url}/api/v2/login"
            login_payload = {"username": arg3, "password": arg4}

            login_res = make_request(login_url, method="POST", data=login_payload)
            token = login_res.get("result", {}).get("token")
            omadac_id_local = login_res.get("result", {}).get("omadacId")
            if not token or not omadac_id_local:
                raise Exception("Login failed, token or omadacId not found in response.")

            site_id = "default"
            try:
                sites_res = make_request(f"{base_url}/api/v2/users/current/sites", token=token)
                sites = sites_res.get("result", {}).get("data", [])
                if not sites:
                    sites_res = make_request(f"{base_url}/{omadac_id_local}/api/v2/users/current/sites", token=token)
                    sites = sites_res.get("result", {}).get("data", [])
                if sites:
                    site_id = sites[0].get("id", "default")
            except Exception:
                pass

            devices_url = f"{base_url}/{omadac_id_local}/api/v2/sites/{site_id}/devices"
            devices_res = make_request(devices_url, token=token)
            devices = devices_res.get("result", {}).get("data", [])

            clients_url = f"{base_url}/{omadac_id_local}/api/v2/sites/{site_id}/clients"
            clients_res = make_request(clients_url, token=token)
            clients = clients_res.get("result", {}).get("data", [])

        # Process Devices
        openapi_radios = {}
        if omadac_id:
            # OpenAPI mode: Concurrently fetch radios for all online APs
            online_ap_macs = []
            for dev in devices:
                dev_type = dev.get("type")
                status = dev.get("status")
                is_online = (status == 1)
                dev_type_str = str(dev_type).lower()
                if dev_type == 2 or "ap" in dev_type_str or "eap" in dev_type_str:
                    if is_online:
                        online_ap_macs.append(dev.get("mac"))
            
            if online_ap_macs:
                def fetch_one(mac):
                    if not is_update_task and time.time() - script_start_time > 25.0:
                        return mac, None
                    url = f"{base_url}/openapi/v1/{omadac_id}/sites/{site_id}/aps/{mac}/radios"
                    try:
                        res = make_request(url, token=f"AccessToken={token}")
                        if res.get("errorCode") == 0:
                            return mac, res.get("result")
                    except Exception:
                        pass
                    return mac, None

                with ThreadPoolExecutor(max_workers=60) as executor:
                    futures = [executor.submit(fetch_one, m) for m in online_ap_macs]
                    for fut in futures:
                        mac, r_data = fut.result()
                        if r_data:
                            openapi_radios[mac] = r_data

        ap_client_counts = {}
        for c in clients:
            ap_mac = c.get("apMac", c.get("ap_mac", ""))
            if ap_mac:
                ap_mac_up = str(ap_mac).upper()
                ap_client_counts[ap_mac_up] = ap_client_counts.get(ap_mac_up, 0) + 1

        online_aps = 0
        offline_aps = 0
        online_switches = 0
        offline_switches = 0
        online_gateways = 0
        offline_gateways = 0
        eaps = []
        switches = []

        for dev in devices:
            dev_type = dev.get("type")
            status = dev.get("status")
            is_online = (status == 1)
            dev_type_str = str(dev_type).lower()

            mac = dev.get("mac", "")
            name = dev.get("name", mac)
            ip_addr = dev.get("ip", "")
            uptime = dev.get("uptime", 0)
            cpu = dev.get("cpuUtil", dev.get("cpu", 0))
            mem = dev.get("memUtil", dev.get("memory", 0))
            model = dev.get("model", dev.get("productName", ""))
            sn = dev.get("sn", dev.get("serialNumber", ""))
            fw = dev.get("firmwareVersion", dev.get("version", ""))

            if dev_type == 2 or "ap" in dev_type_str or "eap" in dev_type_str:
                if is_online:
                    online_aps += 1
                else:
                    offline_aps += 1

                # Extract radio statistics (channel utilization & noise floor)
                if mac in openapi_radios:
                    r_data = openapi_radios[mac]
                    wp2g = r_data.get("wp2g", {})
                    wp5g = r_data.get("wp5g", {})
                    
                    cu2g = wp2g.get("txUtil", 0) + wp2g.get("rxUtil", 0) + wp2g.get("interUtil", 0)
                    cu5g = wp5g.get("txUtil", 0) + wp5g.get("rxUtil", 0) + wp5g.get("interUtil", 0)
                    
                    intf2g = wp2g.get("interUtil", -1)
                    intf5g = wp5g.get("interUtil", -1)
                    
                    tp2g = wp2g.get("txPower", -1)
                    tp5g = wp5g.get("txPower", -1)
                    
                    nf2g = 0
                    nf5g = 0

                    ch_2g = parse_actual_channel(wp2g.get("actualChannel", ""))
                    ch_5g = parse_actual_channel(wp5g.get("actualChannel", ""))
                else:
                    cu2g, cu5g, nf2g, nf5g, tp2g, tp5g, ch_2g, ch_5g = get_radio_stats(dev)
                    intf2g = -1
                    intf5g = -1

                eaps.append({
                    "mac": mac,
                    "name": name,
                    "ip": ip_addr,
                    "status": 1 if is_online else 0,
                    "uptime": uptime,
                    "cpu": cpu,
                    "model": model,
                    "sn": sn,
                    "firmwareVersion": fw,
                    # Channel Utilization (%)
                    "channel_util_2g": cu2g if cu2g is not None else -1,
                    "channel_util_5g": cu5g if cu5g is not None else -1,
                    # Interference (%)
                    "interference_2g": intf2g if intf2g is not None else -1,
                    "interference_5g": intf5g if intf5g is not None else -1,
                    # Noise Floor (dBm) — negative value, higher = better
                    "noise_floor_2g": nf2g if nf2g is not None else 0,
                    "noise_floor_5g": nf5g if nf5g is not None else 0,
                    # TX Power (dBm)
                    "tx_power_2g": tp2g if tp2g is not None else -1,
                    "tx_power_5g": tp5g if tp5g is not None else -1,
                    # Added telemetry fields
                    "channel_2g": ch_2g,
                    "channel_5g": ch_5g,
                    "clientCount": ap_client_counts.get(mac.upper(), 0)
                })

            elif dev_type == 1 or "switch" in dev_type_str:
                if is_online:
                    online_switches += 1
                else:
                    offline_switches += 1
                switches.append({
                    "mac": mac,
                    "name": name,
                    "ip": ip_addr,
                    "status": 1 if is_online else 0,
                    "uptime": uptime,
                    "cpu": cpu,
                    "memory": mem,
                    "model": model,
                    "sn": sn,
                    "firmwareVersion": fw,
                })

            elif dev_type == 0 or "gateway" in dev_type_str or "router" in dev_type_str:
                if is_online:
                    online_gateways += 1
                else:
                    offline_gateways += 1

        total_aps = online_aps + offline_aps
        total_switches = online_switches + offline_switches
        total_gateways = online_gateways + offline_gateways
        total_devices = len(devices)
        total_clients = len(clients)

        # --- Loop Detection via LLDP (unchanged) ---
        lldp_neighbors = {}
        lldp_count = 0
        active_loops = 0
        loop_status_text = "No loops detected."
        loop_descriptions = []

        try:
            if omadac_id:
                for sw in switches:
                    if not is_update_task and time.time() - script_start_time > 25.0:
                        break
                    sw_mac = sw.get("mac", "")
                    lldp_url = f"{base_url}/openapi/v1/{omadac_id}/sites/{site_id}/switches/{sw_mac}/lldp-neighbors?page=1&pageSize=100"
                    try:
                        lldp_res = make_request(lldp_url, token=f"AccessToken={token}")
                        lldp_data = lldp_res.get("result", {}).get("data", [])
                        if lldp_data:
                            lldp_neighbors[sw_mac] = lldp_data
                            lldp_count += len(lldp_data)
                    except Exception:
                        pass

            unique_links = set()
            self_loops_set = set()

            # Map MAC addresses to human-readable names
            device_names = {dev.get("mac", "").upper(): dev.get("name", dev.get("mac", "")) for dev in devices}
            
            def get_name(mac):
                mac_up = str(mac).upper()
                name = device_names.get(mac_up, mac)
                if '\\\\' in name:
                    name = name.split('\\\\')[-1]
                elif '\\' in name:
                    name = name.split('\\')[-1]
                return name

            for sw_mac, neighbors in lldp_neighbors.items():
                sw_mac = sw_mac.upper()
                for nb in neighbors:
                    nb_mac = nb.get("neighborMac", nb.get("chassisId", nb.get("deviceId", ""))).upper()
                    if not nb_mac:
                        continue
                    
                    local_port = nb.get("localPort", nb.get("localPortId", ""))
                    if not local_port:
                        if "standardPortIndex" in nb and isinstance(nb["standardPortIndex"], dict) and "port" in nb["standardPortIndex"]:
                            local_port = str(nb["standardPortIndex"]["port"])
                        else:
                            local_port = str(nb.get("portId", ""))
                    
                    remote_port = nb.get("remotePort", nb.get("neighborPortId", ""))
                    if not remote_port:
                        remote_port = str(nb.get("portId", ""))

                    local_port_norm = normalize_port(local_port)
                    remote_port_norm = normalize_port(remote_port)

                    # Case 1: Self loop
                    if sw_mac == nb_mac:
                        loop_ports = tuple(sorted([local_port_norm, remote_port_norm]))
                        self_loops_set.add((sw_mac, loop_ports))
                        continue

                    # Case 2: External links
                    link = tuple(sorted([
                        (sw_mac, local_port_norm),
                        (nb_mac, remote_port_norm)
                    ]))
                    unique_links.add(link)

            # Build self loops list
            self_loops = []
            for sw_mac, loop_ports in self_loops_set:
                self_loops.append(
                    f"{get_name(sw_mac)} port {loop_ports[0]} to {loop_ports[1]} (same switch)"
                )

            # Build combined graph of switch ports to detect cycles
            graph = {}
            def add_edge(u, v):
                if u not in graph: graph[u] = set()
                if v not in graph: graph[v] = set()
                graph[u].add(v)
                graph[v].add(u)

            # Add external connections to graph
            for (node1, node2) in unique_links:
                add_edge(node1, node2)

            # Add internal switch connections (bridging different ports on the same switch)
            sw_ports = {}
            for (sw, port) in graph.keys():
                if sw not in sw_ports:
                    sw_ports[sw] = []
                sw_ports[sw].append(port)

            for sw, ports in sw_ports.items():
                if len(ports) >= 2:
                    for i in range(len(ports)):
                        for j in range(i + 1, len(ports)):
                            add_edge((sw, ports[i]), (sw, ports[j]))

            # DFS cycle finder
            cycles = []
            visited = set()
            found_cycle_ids = set()

            def dfs(node, parent, path):
                visited.add(node)
                path.append(node)
                for neighbor in graph.get(node, []):
                    if neighbor == parent:
                        continue
                    if neighbor in path:
                        idx = path.index(neighbor)
                        cycle = path[idx:]
                        if len(cycle) >= 3:
                            # Verify if cycle contains at least one external edge
                            has_external = False
                            for k in range(len(cycle)):
                                n1 = cycle[k]
                                n2 = cycle[(k + 1) % len(cycle)]
                                if n1[0] != n2[0]:
                                    has_external = True
                                    break
                            if has_external:
                                cycle_id = tuple(sorted(cycle))
                                if cycle_id not in found_cycle_ids:
                                    found_cycle_ids.add(cycle_id)
                                    cycles.append(cycle)
                    elif neighbor not in visited:
                        dfs(neighbor, node, path)
                path.pop()

            for node in list(graph.keys()):
                if node not in visited:
                    dfs(node, None, [])

            # Format multi-switch loops
            for cycle in cycles:
                node_strs = []
                for node in cycle:
                    node_strs.append(f"{get_name(node[0])} port {node[1]}")
                # Append first node at the end to show the loop closes
                first_node = cycle[0]
                node_strs.append(f"{get_name(first_node[0])} port {first_node[1]}")
                loop_descriptions.append(" = ".join(node_strs))

            # Add self loops to descriptions
            for sl in self_loops:
                loop_descriptions.append(sl)

            active_loops = len(cycles) + len(self_loops)

            if active_loops > 0:
                loop_status_text = f"{active_loops} loop(s) detected! " + " | ".join(loop_descriptions)

        except Exception:
            pass

        formatted_clients = []
        for c in clients:
            formatted_clients.append({
                "name": c.get("name") or c.get("hostname") or c.get("ip") or c.get("mac", ""),
                "mac": c.get("mac", ""),
                "ip": c.get("ip", ""),
                "wireless": c.get("wireless", True),
                "ssid": c.get("ssid", ""),
                "apMac": c.get("apMac", c.get("ap_mac", "")),
                "apName": c.get("apName", c.get("ap_name", "")),
                "rssi": c.get("rssi", None),
                "trafficDown": c.get("trafficDown", c.get("download", None)),
                "trafficUp": c.get("trafficUp", c.get("upload", None)),
                "uptime": c.get("uptime", None),
                "radioId": c.get("radioId", None),
                "channel": c.get("channel", None),
                "currentSpeedMbps": None
            })

        ai_analysis, ai_time = get_ai_analysis_cached(eaps, formatted_clients, ip, cache_file)

        result_data = {
            "status": "success",
            "online_aps": online_aps,
            "offline_aps": offline_aps,
            "total_aps": total_aps,
            "online_switches": online_switches,
            "offline_switches": offline_switches,
            "total_switches": total_switches,
            "online_gateways": online_gateways,
            "offline_gateways": offline_gateways,
            "total_gateways": total_gateways,
            "total_devices": total_devices,
            "total_clients": total_clients,
            "active_loops": active_loops,
            "loop_status_text": loop_status_text,
            "lldp_count": lldp_count,
            "lldp_neighbors": lldp_neighbors,
            "eaps": eaps,
            "switches": switches,
            "clients": formatted_clients,
            "error_message": "",
            "ai_analysis": ai_analysis,
            "ai_analysis_timestamp": ai_time
        }
        json_str = json.dumps(result_data, separators=(',', ':'))
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
            "loop_status_text": "No loops detected.",
            "eaps": [],
            "switches": []
        }, separators=(',', ':')))

if __name__ == "__main__":
    main()
