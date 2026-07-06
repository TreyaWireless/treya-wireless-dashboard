# WireGuard Site-to-Site VPN Setup (EC2 to MikroTik Gateway)

This document details the configuration used to set up the WireGuard VPN tunnel connecting the AWS EC2 Zabbix/Treya Server with the local MikroTik Gateway Router in Lucknow.

---

## 1. Network Architecture
* **EC2 Server VPN IP (wg0):** `10.0.0.1/24`
* **MikroTik Router VPN IP (wg-ec2):** `10.0.0.2/24`
* **Local Subnet (Lucknow Hotel LAN):** `10.5.58.0/24`
* **EC2 Public Endpoint IP:** `65.1.105.95`
* **WireGuard Port:** `51820` (UDP)

---

## 2. AWS Security Group Configuration
An inbound rule must be configured in the AWS EC2 Instance Security Group to allow incoming VPN traffic:
* **Protocol:** UDP
* **Port Range:** `51820`
* **Source:** `0.0.0.0/0` (or the public WAN IP of the MikroTik router for extra security)

---

## 3. EC2 Server Configuration (RHEL 9 / 10)

### Config File: `/etc/wireguard/wg0.conf`
```ini
[Interface]
Address = 10.0.0.1/24
SaveConfig = true
ListenPort = 51820
PrivateKey = <Server_Private_Key>

[Peer]
PublicKey = emszFF54e9CRzDu+nhe5Y2RkbRWGbgX7z86C5sSeEEE=
AllowedIPs = 10.0.0.2/32, 10.5.58.0/24
```

### Server Management Commands
To start, stop, and enable the service on boot:
```bash
# Start WireGuard
sudo systemctl start wg-quick@wg0

# Enable on Boot
sudo systemctl enable wg-quick@wg0

# Check VPN Status
sudo wg show
```

---

## 4. MikroTik Router Configuration (RouterOS v7+)
Paste the following commands in the MikroTik **New Terminal** (via WinBox) to configure the client interface and route traffic to the EC2 server:

```routeros
# 1. Create the WireGuard interface on MikroTik
/interface wireguard add name=wg-ec2 listen-port=51820 private-key="qEHuIeIXIqd2Lc0kgiwcWcwxKpEVGf11RvAQ/o1AHnI="

# 2. Add the EC2 Server as a Peer
/interface wireguard peers add interface=wg-ec2 public-key="6qyGBURPJQanLzRL8HZLRXX68asJ5Sf0DQaIBI/EKlU=" endpoint-address=65.1.105.95 endpoint-port=51820 allowed-address=10.0.0.1/32,10.0.0.0/24,10.5.58.0/24 persistent-keepalive=25s

# 3. Assign IP Address to the interface (this automatically adds the route to 10.0.0.0/24)
/ip address add address=10.0.0.2/24 interface=wg-ec2
```

---

## 5. Zabbix Monitoring Integration
Since the VPN connects the EC2 server to the Lucknow local network directly, you must configure the Zabbix Host to use the private IP instead of public NAT/port forwarding:
* **Host Interface IP:** `10.5.58.123` (Private IP of the Omada Controller)
* **Host Interface Port:** `443` (Standard SSL Port)
* **Macro `{$OMADA_PORT}`:** `443`
* **Macro `{$OMADA_URL}`:** `https://10.5.58.123`
