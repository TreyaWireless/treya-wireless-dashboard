#!/bin/bash
# ============================================================
#  Treya Wireless - RPM Builder for Web Frontend
#  OS: RHEL 9 / RHEL 10
#  Run as: sudo bash build_rpm.sh
# ============================================================
set -e

echo "Installing RPM build dependencies..."
dnf install -y rpm-build rpmdevtools tar 2>/dev/null || true

echo "Setting up RPM build tree..."
mkdir -p ~/rpmbuild/{BUILD,RPMS,SOURCES,SPECS,SRPMS}

VERSION="7.0.28"
RELEASE="release1.el9"
NAME="treya-wireless-web"
TARBALL="${NAME}-${VERSION}.tar.gz"

echo "Creating source tarball..."
TMP_DIR=$(mktemp -d)
mkdir -p "${TMP_DIR}/${NAME}-${VERSION}/ui"
# Copy ui directory contents to tmp
cp -r ui/* "${TMP_DIR}/${NAME}-${VERSION}/ui/"

# Create tar.gz in SOURCES
cd "${TMP_DIR}"
tar -czf ~/rpmbuild/SOURCES/"${TARBALL}" "${NAME}-${VERSION}"
rm -rf "${TMP_DIR}"

echo "Creating RPM spec file..."
cat <<SPEC > ~/rpmbuild/SPECS/${NAME}.spec
Name:           ${NAME}
Version:        ${VERSION}
Release:        ${RELEASE}
Summary:        Treya Wireless Web Frontend
License:        GPLv2+
URL:            https://github.com/TreyaWireless/treya-wireless-dashboard
Source0:        %{name}-%{version}.tar.gz
BuildArch:      noarch
AutoReqProv:    no

%description
Treya Wireless customized Zabbix web frontend.

%prep
%setup -q

%install
rm -rf \$RPM_BUILD_ROOT
mkdir -p \$RPM_BUILD_ROOT/usr/share/treya-wireless
cp -a ui/* \$RPM_BUILD_ROOT/usr/share/treya-wireless/

%files
/usr/share/treya-wireless

%changelog
SPEC

echo "Building RPM package..."
rpmbuild -bb ~/rpmbuild/SPECS/${NAME}.spec

echo "Copying built RPM to current directory..."
cp ~/rpmbuild/RPMS/noarch/${NAME}-${VERSION}-${RELEASE}.noarch.rpm .

echo "======================================================"
echo " RPM Build Complete!"
echo " Package: ${NAME}-${VERSION}-${RELEASE}.noarch.rpm"
echo "======================================================"
