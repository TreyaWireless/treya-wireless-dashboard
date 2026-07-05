#!/usr/bin/env python3
import sys
import json
import urllib.request
import urllib.parse
import ssl
import re
from concurrent.futures import ThreadPoolExecutor


def make_request(url, method="GET", data=None, token=None):
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE

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

    req_data = None
    if data:
        req_data = json.dumps(data).encode("utf-8")

    req = urllib.request.Request(url, method=method, data=req_data, headers=headers)
    try:
        with urllib.request.urlopen(req, context=ctx) as response:
            res_data = response.read().decode("utf-8")
            return json.loads(res_data)
    except Exception as e:
        raise Exception(f"HTTP request to {url} failed: {e}")

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

    for radio in radio_list:
        band = radio.get("band", radio.get("radioId", -1))
        # band 0 = 2.4GHz, band 1 = 5GHz (Omada convention)
        util = radio.get("utilization", radio.get("channelUtilization", None))
        noise = radio.get("noise", radio.get("noiseFloor", None))
        tx_pwr = radio.get("txPower", None)

        if band == 0 or band == "2G":
            if util is not None:
                chan_util_2g = int(util)
            if noise is not None:
                noise_floor_2g = int(noise)
            if tx_pwr is not None:
                tx_power_2g = int(tx_pwr)
        elif band == 1 or band == "5G":
            if util is not None:
                chan_util_5g = int(util)
            if noise is not None:
                noise_floor_5g = int(noise)
            if tx_pwr is not None:
                tx_power_5g = int(tx_pwr)

    return chan_util_2g, chan_util_5g, noise_floor_2g, noise_floor_5g, tx_power_2g, tx_power_5g

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

def main():
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
                    url = f"{base_url}/openapi/v1/{omadac_id}/sites/{site_id}/aps/{mac}/radios"
                    try:
                        res = make_request(url, token=f"AccessToken={token}")
                        if res.get("errorCode") == 0:
                            return mac, res.get("result")
                    except Exception:
                        pass
                    return mac, None

                with ThreadPoolExecutor(max_workers=35) as executor:
                    futures = [executor.submit(fetch_one, m) for m in online_ap_macs]
                    for fut in futures:
                        mac, r_data = fut.result()
                        if r_data:
                            openapi_radios[mac] = r_data

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
                else:
                    cu2g, cu5g, nf2g, nf5g, tp2g, tp5g = get_radio_stats(dev)
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
                "rssi": c.get("rssi", None)
            })

        print(json.dumps({
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
            "error_message": ""
        }, separators=(',', ':')))

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
